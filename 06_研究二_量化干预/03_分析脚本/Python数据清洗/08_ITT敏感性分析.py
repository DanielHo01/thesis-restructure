"""Exploratory ITT sensitivity analysis for outcomes with complete randomized-sample baseline.

This script does not alter the PP analysis. It uses all 36 randomized participants for
absolute and relative 1RM only. Missing T1 values are handled by:
1) baseline carried forward (no change),
2) group-specific observed PP mean-change imputation, and
3) Bayesian-style posterior predictive multiple imputation under MAR.

CMJ, SJ and self-efficacy are not included because baseline values are unavailable for
some randomized participants; imputing both T0 and T1 would require an additional,
more assumption-heavy model.

Confirmed timeline (2026-09-09): stratified block randomization took place AFTER the
formal 1RM pre-test (used for strength stratification) and BEFORE the remaining T0
baseline tests. Hence all 36 randomized participants have a 1RM baseline, while the
7 participants who withdrew during the post-randomization baseline-testing phase
(6 personal reasons + P028 lumbar injury) never took CMJ/SJ/self-efficacy baselines.
"""
from pathlib import Path
import numpy as np
import pandas as pd
from scipy import stats
import statsmodels.formula.api as smf

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
RAW = ROOT / "10_最终交付包" / "01_原始数据"
OUT = ROOT / "05_分析说明" / "Python重算报告"
OUT.mkdir(parents=True, exist_ok=True)

rng = np.random.default_rng(20260909)

cons = pd.read_csv(ROOT / "05_分析说明/CONSORT核对/01_CONSORT_36人底表.csv", encoding="utf-8-sig")
pp = pd.read_csv(HERE / "清洗分析数据/01_main_PP_24.csv", encoding="utf-8-sig")
keep = ["ID", "Group", "Stratum", "BW_kg", "Pre1RM", "Post1RM"]
d = cons[["ID", "随机组别", "相对力量分层", "体重_kg", "正式T0基线1RM_kg"]].rename(columns={
    "随机组别":"Group", "相对力量分层":"Stratum", "体重_kg":"BW_kg", "正式T0基线1RM_kg":"Pre1RM"
})
d = d.merge(pp[["ID", "Pre1RM", "Post1RM"]].rename(columns={"Pre1RM":"Pre1RM_pp"}), on="ID", how="left")
d["Pre1RM"] = d["Pre1RM_pp"].combine_first(d["Pre1RM"])
d = d.drop(columns="Pre1RM_pp")
d["Post1RM"] = d["Post1RM"]
d["PreRel"] = d["Pre1RM"] / d["BW_kg"]
d["PostRel"] = d["Post1RM"] / d["BW_kg"]
d["Group"] = pd.Categorical(d["Group"], categories=["Self组", "AI组"])
d["Stratum"] = pd.Categorical(d["Stratum"], categories=["低力量层", "高力量层"])

# Confirm complete baseline in randomized sample.
assert len(d) == 36 and d.ID.nunique() == 36
assert d.Pre1RM.notna().all() and d.BW_kg.notna().all()

specs = [("绝对1RM", "Pre1RM", "Post1RM", "kg"), ("相对1RM", "PreRel", "PostRel", "kg/kg")]

def fit_effect(x, pre, post):
    fit = smf.ols(f"{post} ~ C(Group) + {pre} + C(Stratum)", data=x).fit()
    term = "C(Group)[T.AI组]"
    ci = fit.conf_int().loc[term]
    return {"estimate": fit.params[term], "se": fit.bse[term], "p": fit.pvalues[term], "low": ci[0], "high": ci[1]}

def scenario_results():
    rows = []
    for label, pre, post, unit in specs:
        miss = d[post].isna()
        # Scenario 1: baseline carried forward.
        x = d.copy(); x.loc[miss, post] = x.loc[miss, pre]
        r = fit_effect(x, pre, post)
        rows.append({"结局":label,"方法":"LOCF（T1缺失按T0）","n":36,**r,"单位":unit})
        # Scenario 2: group-specific observed PP mean change.
        x = d.copy()
        pp_obs = x.loc[~miss].copy(); pp_obs["change"] = pp_obs[post] - pp_obs[pre]
        changes = pp_obs.groupby("Group", observed=False).change.mean()
        x.loc[miss, post] = x.loc[miss, pre].astype(float) + x.loc[miss, "Group"].map(changes).astype(float)
        r = fit_effect(x, pre, post)
        rows.append({"结局":label,"方法":"按随机组别的PP平均变化插补","n":36,**r,"单位":unit})
    return pd.DataFrame(rows)


def posterior_mi(label, pre, post, unit, m=1000):
    obs = d[d[post].notna()].copy()
    formula = f"{post} ~ C(Group) + {pre} + C(Stratum)"
    base_fit = smf.ols(formula, data=obs).fit()
    X = base_fit.model.exog
    y = obs[post].to_numpy(float)
    beta = base_fit.params.to_numpy(float)
    cov = base_fit.cov_params().to_numpy(float)
    resid_ss = float(np.sum(base_fit.resid**2)); df_resid = int(base_fit.df_resid)
    missing = d[post].isna()
    estimates = []
    for j in range(m):
        # Draw regression coefficients and residual variance to include parameter uncertainty.
        sigma2 = resid_ss / rng.chisquare(df_resid)
        b = rng.multivariate_normal(beta, cov * sigma2 / base_fit.scale)
        ximp = d.copy()
        # Build missing design matrix through the fitted model's design_info.
        from patsy import dmatrix
        Xmiss = dmatrix(formula.split('~', 1)[1], ximp.loc[missing], return_type='dataframe')
        Xmiss = Xmiss[base_fit.model.exog_names]
        ximp.loc[missing, post] = np.asarray(Xmiss) @ b + rng.normal(0, np.sqrt(sigma2), missing.sum())
        r = fit_effect(ximp, pre, post)
        estimates.append(r)
    q = np.array([z["estimate"] for z in estimates]); u = np.array([z["se"]**2 for z in estimates])
    qbar = q.mean(); ubar = u.mean(); between = q.var(ddof=1); total = ubar + (1 + 1/m) * between
    se = np.sqrt(total)
    df_old = (m-1)*(1 + ubar/((1+1/m)*between))**2 if between > 0 else 1e9
    tcrit = stats.t.ppf(.975, df_old)
    tstat = qbar/se
    p = 2*stats.t.sf(abs(tstat), df_old)
    return {"结局":label,"方法":"多重插补（MAR，1000次）","n":36,"estimate":qbar,"se":se,"p":p,"low":qbar-tcrit*se,"high":qbar+tcrit*se,"单位":unit,"MI_df":df_old,"missing_T1":int(missing.sum()),"PP_observed_n":int((~missing).sum())}

scenario = scenario_results()
mi = pd.DataFrame([posterior_mi(*s) for s in specs])
allout = pd.concat([scenario, mi], ignore_index=True)
allout.to_csv(OUT / "08_ITT敏感性分析_36人_1RM.csv", index=False, encoding="utf-8-sig")

missing = d.groupby("Group", observed=False).agg(n=("ID","size"), T1_missing=("Post1RM",lambda x: int(x.isna().sum())), T1_observed=("Post1RM",lambda x: int(x.notna().sum())))
missing.to_csv(OUT / "08_ITT敏感性分析_缺失结构.csv", encoding="utf-8-sig")

report = f"""# ITT敏感性分析初步报告

日期：2026年9月9日

## 1. 分析对象和限制

- 随机化样本：36人，AI组18人，Self组18人。
- 仅绝对1RM和相对1RM具备36名随机化受试者的完整T0基线及体重资料，因此本轮ITT敏感性分析仅针对1RM。
- CMJ、SJ和训练自我效能在部分退出者中缺少T0基线，当前不在没有额外假设的情况下强行进行ITT插补。
- T1后测缺失：AI组7人，Self组5人，共12人；可观察T1：24人。

## 2. 分析方法

对全部36名随机化受试者，以T1为结局、T0为协变量、Group为固定因素、力量分层为协变量进行ANCOVA。缺失T1分别采用：

1. LOCF：以T0替代缺失T1，作为保守的无变化情景；
2. 按随机组别的PP平均变化插补；
3. 基于观察到的24名受试者拟合 `T1 ~ Group + T0 + Stratum`，在MAR假设下进行1000次后验预测多重插补，并使用Rubin规则合并结果。

这些结果是ITT敏感性分析，不替代当前PP主分析，也不意味着缺失数据机制已经被验证。

## 3. 结果

详细结果见 `08_ITT敏感性分析_36人_1RM.csv`。

{allout.to_markdown(index=False)}

## 4. 初步解释

本轮首先用于判断：把随机化样本从24名PP受试者扩展至36人，并对12名T1缺失者进行合理敏感性处理后，主要1RM结论是否发生方向性改变。由于CMJ、SJ和训练自我效能的退出者缺少部分T0基线，不能把这些结局与1RM用同样的低假设方式直接外推到36人；如需进行这些结局的ITT分析，需要另行预设联合多重插补模型并进行敏感性分析。
"""
(OUT / "08_ITT敏感性分析_初步报告.md").write_text(report, encoding="utf-8")
print(allout.to_string(index=False))
print(missing)
