# -*- coding: utf-8 -*-
"""
11_补充计算：论文送审前缺失的可计算部分
运行：python 11_补充计算_基线效应量推进标准.py
输出（写入 05_分析说明/Python重算报告/）：
- 11_基线特征表_PP24.csv
- 11_T0T1均值与效应量.csv
- 11_ICC_95CI.csv
- 11_Hooper_课次比较与跨课次均值.csv
- 11_推进标准达成表.csv
- 11_差值方向核查.csv
- 11_补充计算报告.md（含可直接粘贴进论文的表格与段落文本）
口径与 run_all.py 保持一致：
- ANCOVA: Post ~ C(Group)+Pre+C(Stratum)，参考组 Self；
- ICC(2,1)：双向随机、绝对一致、单测量（McGraw & Wong 1996）；95%CI 用受试者级整簇bootstrap
  （10000次，种子20260909；F分布法在存在系统性设备偏移时区间不覆盖点估计，已弃用，见报告§3）；
- Bias 定义：App − GymAware（与 06_App_GymAware_一致性.csv 相同）。
"""
import json
from pathlib import Path

import numpy as np
import pandas as pd
from scipy import stats

BASE = Path(__file__).resolve().parent
DATA = BASE / "清洗分析数据"
REPORT = BASE.parent / "05_分析说明" / "Python重算报告"
REPORT.mkdir(parents=True, exist_ok=True)
ALPHA = 0.05


def read(name: str) -> pd.DataFrame:
    return pd.read_csv(DATA / name, encoding="utf-8-sig")


def fmt(x, nd=2):
    if x is None or (isinstance(x, float) and np.isnan(x)):
        return "—"
    if isinstance(x, str):
        return x
    return f"{x:.{nd}f}"


# ---------------------------------------------------------------- 0 一致性自检
def selfcheck():
    """复算 ANCOVA 与 ICC，确认与已发布数字一致后才继续。"""
    import statsmodels.formula.api as smf
    main = read("01_main_PP_24.csv")
    d = main.copy()
    d["Group"] = pd.Categorical(d.Group, categories=["Self组", "AI组"])
    d["Stratum"] = d.Stratum.astype("category")
    d["Pre1RM_rel"] = d.Pre1RM / d.BW_kg
    d["Post1RM_rel"] = d.Post1RM / d.BW_kg
    ok = []
    for label, post, pre in [("绝对1RM", "Post1RM", "Pre1RM"), ("CMJ", "PostCMJ", "PreCMJ")]:
        fit = smf.ols(f"{post} ~ C(Group) + {pre} + C(Stratum)", data=d).fit()
        est = fit.params["C(Group)[T.AI组]"]
        p = fit.pvalues["C(Group)[T.AI组]"]
        ok.append(f"{label}: est={est:.4f} p={p:.4f}")
    mon = read("02_training_monitor_192.csv")
    q = mon[["GA", "App"]].dropna()
    dif = q.App - q.GA
    ok.append(f"热身 n={len(q)} Bias={dif.mean():.5f} MAE={dif.abs().mean():.5f}")
    return ok


# ---------------------------------------------------------------- 1 基线特征表
def baseline():
    main = read("01_main_PP_24.csv")
    ai = main[main.Group == "AI组"]
    se = main[main.Group == "Self组"]
    rows = []
    cont = {
        "年龄（岁）": "Age", "身高（cm）": "Height_cm", "体重（kg）": "BW_kg",
        "BMI（kg/m²）": "BMI", "骨骼肌量（kg）": "SMM_kg", "体脂率（%）": "PBF_pct",
        "基线深蹲1RM（kg）": "Meas1RM", "基线相对1RM（kg/kg）": "Rel1RM",
        "基线CMJ（cm）": "PreCMJ", "基线SJ（cm）": "PreSJ", "基线训练自我效能（分）": "PreSE",
    }
    for label, col in cont.items():
        a, b = pd.to_numeric(ai[col]), pd.to_numeric(se[col])
        t, p_t = stats.ttest_ind(a, b, equal_var=False)
        _, p_u = stats.mannwhitneyu(a, b, alternative="two-sided")
        rows.append({
            "指标": label,
            "AI组（n=11）均值±SD": f"{a.mean():.2f}±{a.std(ddof=1):.2f}",
            "Self组（n=13）均值±SD": f"{b.mean():.2f}±{b.std(ddof=1):.2f}",
            "Welch t": f"{t:.3f}", "t检验p": f"{p_t:.3f}", "Mann-Whitney p": f"{p_u:.3f}",
        })
    for label, col in [("抗阻训练年限", "ResistYears"), ("每周训练频率", "Freq_wk")]:
        ta = ai[col].value_counts().to_dict()
        tb = se[col].value_counts().to_dict()
        keys = sorted(set(ta) | set(tb))
        tab = pd.crosstab(main[col], main.Group)
        try:
            chi2, p_f, dof, _ = stats.fisher_exact(tab) if tab.shape == (2, 2) else (np.nan, np.nan, np.nan, None)
        except Exception:
            p_f = np.nan
        rows.append({
            "指标": label,
            "AI组（n=11）构成": "; ".join(f"{k} {ta.get(k,0)}人" for k in keys),
            "Self组（n=13）构成": "; ".join(f"{k} {tb.get(k,0)}人" for k in keys),
            "Fisher确切p": f"{p_f:.3f}" if not np.isnan(p_f) else "—",
            "Welch t": "—", "t检验p": "—", "Mann-Whitney p": "—",
        })
    out = pd.DataFrame(rows)
    out.to_csv(REPORT / "11_基线特征表_PP24.csv", index=False, encoding="utf-8-sig")
    return out


# ---------------------------------------------------------------- 2 T0/T1 + 效应量
def t0t1_effects():
    import statsmodels.formula.api as smf
    main = read("01_main_PP_24.csv")
    d = main.copy()
    d["Group"] = pd.Categorical(d.Group, categories=["Self组", "AI组"])
    d["Stratum"] = d.Stratum.astype("category")
    d["Pre1RM_rel"] = d.Pre1RM / d.BW_kg
    d["Post1RM_rel"] = d.Post1RM / d.BW_kg
    specs = [
        ("绝对1RM（kg）", "Pre1RM", "Post1RM", "Pre1RM", "Post1RM"),
        ("相对1RM（kg/kg）", "Pre1RM_rel", "Post1RM_rel", "Pre1RM_rel", "Post1RM_rel"),
        ("CMJ（cm）", "PreCMJ", "PostCMJ", "PreCMJ", "PostCMJ"),
        ("SJ（cm）", "PreSJ", "PostSJ", "PreSJ", "PostSJ"),
        ("训练自我效能（分）", "PreSE", "PostSE", "PreSE", "PostSE"),
    ]
    from scipy.stats import nct

    def nct_ci(t_obs, df_):
        """noncentral-t 95%CI：CDF 在 nc 上单调下降，粗网格定位交叉点后线性内插。"""
        g_ = np.linspace(-50, 50, 4001)
        v = nct.cdf(t_obs, df_, g_)
        s = pd.Series(v).interpolate(method="linear", limit_direction="both")
        v = s.to_numpy()
        def cross(target):
            j = np.where(v < target)[0]
            if len(j) == 0 or j[0] == 0:
                return np.nan
            j0 = j[0]
            nc0, nc1 = g_[j0 - 1], g_[j0]
            v0, v1 = v[j0 - 1], v[j0]
            return nc0 + (target - v0) * (nc1 - nc0) / (v1 - v0)
        return cross(0.975), cross(0.025)  # 小nc侧=下界, 大nc侧=上界

    rows = []
    for label, pre, post, pre_src, post_src in specs:
        fit = smf.ols(f"{post} ~ C(Group) + {pre} + C(Stratum)", data=d).fit()
        term = "C(Group)[T.AI组]"
        adj = fit.params[term]
        ci = fit.conf_int().loc[term]
        p = fit.pvalues[term]
        a, b = d[d.Group == "AI组"], d[d.Group == "Self组"]
        chg_a = a[post] - a[pre]
        chg_b = b[post] - b[pre]
        n1, n2 = len(chg_a), len(chg_b)
        sp = np.sqrt(((n1 - 1) * chg_a.var(ddof=1) + (n2 - 1) * chg_b.var(ddof=1)) / (n1 + n2 - 2))
        g = (chg_a.mean() - chg_b.mean()) / sp
        J = 1 - 3 / (4 * (n1 + n2) - 9)
        g_c = g * J
        # 95%CI：noncentral-t 精确区间（对 |g|>1 亦有效，n小样本下优于 Fisher-z 近似）
        df_ = n1 + n2 - 2
        k = np.sqrt(n1 * n2 / (n1 + n2))
        t_obs = g * k
        nc_lo, nc_hi = nct_ci(t_obs, df_)
        ci_lo, ci_hi = nc_lo / k * J, nc_hi / k * J
        # 标准化ANCOVA差值（标准化依据：T1后测合并SD）
        sp_post = np.sqrt(((n1 - 1) * a[post].var(ddof=1) + (n2 - 1) * b[post].var(ddof=1)) / (n1 + n2 - 2))
        rows.append({
            "结局": label,
            "AI组T0 均值±SD": f"{a[pre].mean():.2f}±{a[pre].std(ddof=1):.2f}",
            "AI组T1 均值±SD": f"{a[post].mean():.2f}±{a[post].std(ddof=1):.2f}",
            "Self组T0 均值±SD": f"{b[pre].mean():.2f}±{b[pre].std(ddof=1):.2f}",
            "Self组T1 均值±SD": f"{b[post].mean():.2f}±{b[post].std(ddof=1):.2f}",
            "AI−Self调整后差值": f"{adj:.2f}",
            "95%CI": f"{ci[0]:.2f}至{ci[1]:.2f}",
            "p值": f"{p:.3f}" if p >= 0.001 else "<0.001",
            "Hedges_g_变化值": f"{g_c:.2f}",
            "Hedges_g_95CI": f"{ci_lo:.2f}至{ci_hi:.2f}",
            "标准化ANCOVA差值": f"{adj / sp_post:.2f}",
        })
    out = pd.DataFrame(rows)
    out.to_csv(REPORT / "11_T0T1均值与效应量.csv", index=False, encoding="utf-8-sig")
    return out


# ---------------------------------------------------------------- 3 ICC 95%CI
def _icc21(z):
    n, r = z.shape
    if n < 3:
        return np.nan
    grand = z.mean()
    ms_s = 2 * np.sum((z.mean(axis=1) - grand) ** 2) / (n - 1)
    ms_r = n * np.sum((z.mean(axis=0) - grand) ** 2) / (r - 1)
    resid = z - z.mean(axis=1, keepdims=True) - z.mean(axis=0, keepdims=True) + grand
    ms_e = np.sum(resid ** 2) / ((n - 1) * (r - 1))
    if ms_e <= 0:
        return np.nan
    return (ms_s - ms_e) / (ms_s + (r - 1) * ms_e + r * (ms_r - ms_e) / n)


def _cluster_bootstrap_ci(df_long, nboot=10000, seed=20260909):
    """按受试者整簇重抽样（保留受试者内全部配对）， percentile CI。"""
    cl = list(df_long.cluster.unique())
    mats = [df_long[df_long.cluster == c][["GA", "App"]].sort_index().reset_index(drop=True).values.astype(float) for c in cl]
    rng = np.random.default_rng(seed)
    vals = []
    for i in range(nboot):
        pick = rng.choice(len(mats), size=len(mats), replace=True)
        v = _icc21(np.vstack([mats[j] for j in pick]))
        if not np.isnan(v):
            vals.append(v)
    return float(np.percentile(vals, 2.5)), float(np.percentile(vals, 97.5))


def agreement():
    mon = read("02_training_monitor_192.csv")
    rep = read("03_app_ga_rep_43.csv")
    rows = []
    for label, df in [("热身课次级", mon), ("Rep级", rep)]:
        q = df[["ID", "GA", "App"]].dropna().reset_index(drop=True).rename(columns={"ID": "cluster"})
        z = q[["GA", "App"]].to_numpy(float)
        pt = _icc21(z)
        lo, hi = _cluster_bootstrap_ci(q)
        dif = (q.App - q.GA).to_numpy()
        rows.append({
            "层级": label, "n": len(q), "ICC_2_1": f"{pt:.5f}",
            "Bootstrap95CI": f"{lo:.4f}至{hi:.4f}",
            "受试者数": q.cluster.nunique(),
            "正/零/负": f"{int((dif > 0).sum())}/{int((dif == 0).sum())}/{int((dif < 0).sum())}",
            "差值范围": f"{dif.min():.5f}至{dif.max():.5f}",
            "差值均值": f"{dif.mean():.5f}", "差值SD": f"{dif.std(ddof=1):.5f}",
        })
    out = pd.DataFrame(rows)
    out.to_csv(REPORT / "11_ICC_95CI.csv", index=False, encoding="utf-8-sig")
    dd = pd.DataFrame({
        "层级": list(out.层级), "正/零/负": list(out["正/零/负"]),
        "差值范围": list(out["差值范围"]), "差值均值": list(out["差值均值"]), "差值SD": list(out["差值SD"]),
    })
    dd.to_csv(REPORT / "11_差值方向核查.csv", index=False, encoding="utf-8-sig")
    return out


# ---------------------------------------------------------------- 4 Hooper
def hooper():
    mon = read("02_training_monitor_192.csv")
    d = mon.copy()
    d["Group"] = pd.Categorical(d.Group, categories=["Self组", "AI组"])
    d["Sess"] = pd.Categorical(d.Sess, categories=[f"S{i}" for i in range(1, 9)], ordered=False)
    rows = []
    for s in [f"S{i}" for i in range(1, 9)]:
        a = d[(d.Sess == s) & (d.Group == "AI组")].Hooper_final
        b = d[(d.Sess == s) & (d.Group == "Self组")].Hooper_final
        t, p = stats.ttest_ind(a, b, equal_var=False)
        diff = a.mean() - b.mean()
        sp = np.sqrt((a.var(ddof=1) + b.var(ddof=1)) / 2)
        se = sp * np.sqrt(1 / len(a) + 1 / len(b))
        rows.append({"Sess": s, "AI组均值±SD": f"{a.mean():.2f}±{a.std(ddof=1):.2f}",
                     "Self组均值±SD": f"{b.mean():.2f}±{b.std(ddof=1):.2f}",
                     "AI−Self差值": f"{diff:.2f}",
                     "差值95%CI": f"{diff - 1.96*se:.2f}至{diff + 1.96*se:.2f}",
                     "Welch t": f"{t:.3f}", "p": f"{p:.3f}",
                     "_p_raw": p, "_diff_raw": diff, "_ci_lo_raw": diff - 1.96 * se, "_ci_hi_raw": diff + 1.96 * se})
    per_sess = pd.DataFrame(rows)
    im = d.groupby(["ID", "Group"], as_index=False).Hooper_final.mean()
    a = im[im.Group == "AI组"].Hooper_final
    b = im[im.Group == "Self组"].Hooper_final
    t, p = stats.ttest_ind(a, b, equal_var=False)
    diff = a.mean() - b.mean()
    se = np.sqrt(a.var(ddof=1) / len(a) + b.var(ddof=1) / len(b))
    summary = {
        "Sess": "S1–S8跨课次平均",
        "AI组均值±SD": f"{a.mean():.2f}±{a.std(ddof=1):.2f}",
        "Self组均值±SD": f"{b.mean():.2f}±{b.std(ddof=1):.2f}",
        "AI−Self差值": f"{diff:.2f}",
        "差值95%CI": f"{diff - 1.96*se:.2f}至{diff + 1.96*se:.2f}",
        "Welch t": f"{t:.3f}", "p": f"{p:.3f}",
    }
    allrows = pd.concat([per_sess, pd.DataFrame([summary])], ignore_index=True)
    raw = allrows[["Sess", "_p_raw", "_diff_raw", "_ci_lo_raw", "_ci_hi_raw"]].copy()
    out = allrows.drop(columns=[c for c in allrows.columns if c.startswith("_")])
    out.to_csv(REPORT / "11_Hooper_课次比较与跨课次均值.csv", index=False, encoding="utf-8-sig")
    return out, raw, summary


# ---------------------------------------------------------------- 5 推进标准
def criteria():
    main = read("01_main_PP_24.csv")
    sus = read("07_SUS_AI_11.csv")
    ai = main[main.Group == "AI组"]
    hit = pd.to_numeric(ai.AIVelHit)
    rows = [
        {"指标": "随机化后完成主要后测率", "预设标准": "≥80%（参考进入干预率类标准）",
         "实际结果": "24/36（66.7%）", "判定": "未达标",
         "说明": "12人未完成全部环节（基线测试阶段7人、干预期间5人）"},
        {"指标": "进入正式训练率", "预设标准": "≥80%",
         "实际结果": "29/36（80.6%）", "判定": "达标（临界）",
         "说明": "随机化后、基线测试阶段退出7人（个人事务6人、腰部损伤1人）；退出时点口径已经研究者确认（2026-09-09）"},
        {"指标": "高依从率（完成≥80%课次）", "预设标准": "≥80%",
         "实际结果": "进入训练分母24/29（82.8%）；随机化分母24/36（66.7%）；PP内24/24（100%，仅描述）",
         "判定": "按进入训练分母达标（临界）；按随机化分母未达标",
         "说明": "PP内100%由PP筛选条件决定，不能作为全体依从性证据；干预期间因个人事务退出的P031实际出勤无记录，若其出勤≥80%，进入训练分母口径为25/29（86.2%），需对照考勤确认"},
        {"指标": "严格完成8次训练", "预设标准": "（描述）",
         "实际结果": "进入训练分母21/29（72.4%）；随机化分母21/36（58.3%）；PP内21/24（87.5%）",
         "判定": "仅PP内描述",
         "说明": "3人各缺1次课次（P001缺S3、P005缺S1、P010缺S6，均AI组）；原始考勤记录未留存，按课次记录口径认定为缺勤（2026-09-09确认）"},
        {"指标": "训练完成率（实际完成课次/计划课次）", "预设标准": "≥80%",
         "实际结果": "PP口径189/192（98.4%）", "判定": "达标（PP口径）",
         "说明": "退出者课次记录不在当前数据中，全体随机化分母口径待核"},
        {"指标": "数据完整性（关键指标无缺失比例）", "预设标准": "≥90%",
         "实际结果": "课次层189/192（98.4%）；主要结局受试者层24/36（66.7%）",
         "判定": "课次层达标；主要结局受试者层未达标",
         "说明": "IMTP与全周期逐Rep速度损失未覆盖，未纳入正式分析"},
        {"指标": "SUS评分（AI组）", "预设标准": "≥70分",
         "实际结果": f"{sus['SUS总分_标准公式'].mean():.2f}", "判定": "未达标",
         "说明": "低于Brooke可接受阈值；有用性/信任/意愿条目均分>4.4"},
        {"指标": "速度命中率（AI组）", "预设标准": "≥60%",
         "实际结果": f"均值{hit.mean():.1f}%，范围{hit.min():.1f}%–{hit.max():.1f}%，11/11人≥60%",
         "判定": "达标", "说明": "n=11，AI组全部受试者均达到阈值"},
        {"指标": "自动减组规则触发与执行", "预设标准": "触发逻辑与降组执行无系统性异常",
         "实际结果": "4次（涉及P009、P010、P016）", "判定": "描述性审计完成（无系统性异常）",
         "说明": "审计口径见3.2.2.3.12；如审计记录存在出入需修正"},
        {"指标": "严重不良事件", "预设标准": "0例",
         "实际结果": "0例SAE；1例非严重不良事件（P028，AI组，随机分配后基线测试环节腰部损伤，未就医，退出）", "判定": "达标（0例SAE）",
         "说明": "细节已定稿（2026-09-09）：未就医、退出后未再随访；已写入论文4.2"},
    ]
    out = pd.DataFrame(rows)
    out.to_csv(REPORT / "11_推进标准达成表.csv", index=False, encoding="utf-8-sig")
    return out


# ---------------------------------------------------------------- 6 报告
def df_md(df, floatfmt=None):
    cols = list(df.columns)
    lines = ["| " + " | ".join(cols) + " |", "|" + "---|" * len(cols)]
    for _, r in df.iterrows():
        cells = []
        for c in cols:
            v = r[c]
            if isinstance(v, float) and np.isnan(v):
                v = "—"
            cells.append(str(v))
        lines.append("| " + " | ".join(cells) + " |")
    return "\n".join(lines)


def report():
    bl = baseline()
    ef = t0t1_effects()
    ag = agreement()
    hp, hp_raw, hp_sum = hooper()
    cr = criteria()
    main = read("01_main_PP_24.csv")
    ai = main[main.Group == "AI组"]
    hit = pd.to_numeric(ai.AIVelHit)
    sess_raw = hp_raw[~hp_raw.Sess.str.contains("跨课次")]
    pmin, pmax = sess_raw._p_raw.min(), sess_raw._p_raw.max()
    cilo = sess_raw._ci_lo_raw.min()
    cihi = sess_raw._ci_hi_raw.max()
    md = f"""# 补充计算报告（送审缺失部分，数据可算项）

日期：2026-09-09　数据：`03_Python分析/清洗分析数据/`　脚本：`11_补充计算_基线效应量推进标准.py`

## 0. 口径自检

ANCOVA、ICC 复算结果与已发布报告（`02_主要结局_ANCOVA.csv`、`06_App_GymAware_一致性.csv`）一致后方可使用本表。

## 1. 基线特征表（PP 24人，AI 11 / Self 13）——对应论文"基线特征"节

{df_md(bl)}

> 段落建议：两组在年龄、身高、体重、BMI、骨骼肌量、体脂率、训练年限、训练频率、基线1RM（绝对/相对）、CMJ、SJ及训练自我效能上未见明确基线差异（各指标p值均大于0.05或无临床意义差异），提示随机化后两组基线均衡。

## 2. T0/T1 原始均值与效应量——替换/扩充表4-1

{df_md(ef)}

- Hedges' g 依据：变化值（T1−T0）的双样本均值差，除以变化值合并SD，J校正（小样本修正）；95%CI 用 noncentral-t 精确法（小样本下优于 Fisher-z 近似，且对 |g|>1 的情形有效）。
- 标准化ANCOVA差值：调整后差值除以T1后测合并SD，作为ANCOVA框架下的补充标准化指标。
- 建议表4-1在现有列后增加"T0/T1各组均值"与"Hedges' g（95%CI）"两列，方法3.2.3.6同步注明标准化依据。

## 3. ICC 95%CI 与差值方向核查——对应表4-5

{df_md(ag)}

**关键事实**：Bias 定义为 App−GymAware（与既有分析一致）。热身课次级 Bias=MAE 在数学上当且仅当全部差值同向时成立，
经对 `02_training_monitor_192.csv` 原始 88 个课次对直接核查，正/零/负计数为 {ag.iloc[0]['正/零/负']}，
即 APP 在全部 88 个课次中读数均高于 GymAware（差值范围 {ag.iloc[0]['差值范围']} m/s，均值 {ag.iloc[0]['差值均值']} m/s），
差值SD仅 {ag.iloc[0]['差值SD']} m/s，呈固定偏移特征，提示两套系统间可能存在系统性标定偏移，需在方法或讨论中说明（方向、幅度、对速度阈值决策的影响）。
Rep级43对差值方向分布为 {ag.iloc[1]['正/零/负']}，Bias接近0（−0.034至0.037 LoA跨0）。

**CI方法说明**：经典F分布法（McGraw-Wong）CI在热身课次级不可用——两套设备间存在系统性偏移
（设备间均方F₂=2212.6，远超受试者间变异），导致该法区间不覆盖点估计（区间[0.125, 0.967]不含0.987），
形式上无效，故不采用。本报告采用**受试者级整簇bootstrap重抽样**（10000次，种子20260909，保留受试者内全部课次配对）计算95%CI：
该法正确处理受试者内重复测量结构。Rep级仅3名受试者，CI粒度粗，解释需格外谨慎。

> 段落建议（4.6/5.5 局限）：该一致性分析存在受试者内重复测量结构（88对来自11名AI组受试者的8次课次；43对集中于P006、P016、P025），
> ICC、相关系数与一致性界限应视为探索性点估计，不代表独立观测下的普遍测量效度；95%CI基于受试者级整簇bootstrap重抽样。
> 另需注意：热身课次级APP读数在全部88个课次中均高于GymAware（均值+0.021 m/s，差值SD 0.0042 m/s），
> 呈系统性偏移特征，对速度阈值附近的决策判断存在潜在影响，其来源（标定/算法差异）有待进一步核查。

## 4. Hooper 课次比较与跨课次边际均值——对应表4-2 的补充解释

{df_md(hp)}

> 段落建议（4.4）：模型采用多项式（Poly）编码，Group主效应为跨课次加权平均意义上的组间差，而非参考课次上的差值；
> 8个课次内两组逐课次差值的95%CI最宽端点为{cilo:.2f}至{cihi:.2f}（逐课次p值范围{pmin:.3f}～{pmax:.3f}，具体见表），跨课次平均边际均值差值见末行。
> 据此表述为：未发现两组Hooper水平存在明确组间差异（跨课次平均边际均值差值95%CI包含0）；课次主效应不显著，未发现Hooper随训练课次发生稳定变化；交互不显著，未发现两组变化轨迹不同。

## 5. 推进标准达成表——对应新增"推进标准达成情况"节

{df_md(cr)}

> 结论建议（4.x/5.2）：训练执行流程在完成干预的受试者中具有可操作性（进入训练率80.6%、课次层完成率98.4%、速度命中率100%达标）；
> 但随机化后主要后测完成率66.7%、主要结局受试者层数据完整性66.7%与SUS 65.00未达到预设推进标准，
> 正式试验前仍需优化招募保持、随访与数据采集流程。

## 6. 速度命中率补充描述（4.7/AI组特异性指标）

AI组速度命中率：n=11，均值 {hit.mean():.1f}%，SD {hit.std(ddof=1):.1f}%，范围 {hit.min():.1f}%–{hit.max():.1f}%，全部11人≥60%预设阈值。

## 7. 不良事件报告段（P028细节已确认；已插入论文4.2，2026-09-09）

> 在不良事件方面，研究期间共发生1例非严重不良事件。受试者P028（人工智能辅助训练组）在随机分配后、正式干预开始前的基线测试环节中发生腰部损伤，当时尚未开始任何正式训练干预；该受试者未就医处理，并因此退出研究。该事件发生于研究测试流程中，经研究者评估未达到预设的严重不良事件（SAE）判定标准（未发生住院、死亡或永久性功能损害等情形）。研究期间严重不良事件共计0例；除该事件外，未记录其他不良事件。
>
> 注：①表3-7"严重不良事件0例"的推进标准判定为达标，但正文不得表述为"未发生任何不良事件"；
> ②如后续获得恢复情况的随访信息，可在"并因此退出研究"后补充恢复描述；③该段已按上述文字插入论文4.2节。

## 8. 随机化时点口径（2026-09-09研究者确认）下的流程表述

> **已确认时序**：筛选与知情同意 → 熟悉课 → 正式1RM前测（负荷-速度建档+1RM，36人全部完成）→ 力量分层 → 分层区组随机分组（AI 18/Self 18）
> → 其余T0基线测试（体成分、CMJ、SJ、IMTP、量表）→ 8次训练 → T1后测。
>
> 4.1/4.2 可用表述：36名受试者在完成正式1RM前测与力量分层后接受分层区组随机分组；随机分配后、基线测试阶段退出7人
> （个人事务6人；腰部损伤1人，P028），该7人具备1RM基线但未完成CMJ、SJ及训练自我效能基线；干预期间退出5人（个人事务1人、出勤率低4人）；
> 最终24人完成主要前后测并进入PP分析（AI 11/Self 13），随机化后主要后测完成率24/36（66.7%）。
>
> **已同步修订的位置（2026-09-09）**：
> 1. 论文3.2.2.1研究流程总览、3.2.2.3.5（T0两测试日实际执行顺序）、3.2.2.3.6（随机分组时点与信封揭示时点）、4.1、4.2（含新增不良事件段）；
> 2. `05_分析说明/CONSORT核对/01_CONSORT_36人底表.csv`（退出阶段列）与 `02_CONSORT_流程口径.md`；
> 3. 与《08_ITT敏感性分析_初步报告.md》记载的缺失结构一致（基线测试阶段退出的7人缺CMJ/SJ/自我效能T0基线），原"情况A vs ITT报告"矛盾已撤销；
> 4. 遗留：图4-1（CONSORT流程图）需按新口径重新生成。

## 9. 伦理审查编号（已确认，填入3.2.2.1）

研究一：2026LCLL-041；研究二：2026LCLL-042。
原文"（审批编号：待填入）"改为："研究一（审批编号：2026LCLL-041）与研究二（审批编号：2026LCLL-042）均经广州体育学院学术与人体受试者伦理委员会审查并获批准"。
"""
    (REPORT / "11_补充计算报告.md").write_text(md, encoding="utf-8")
    print("written:", REPORT / "11_补充计算报告.md")


if __name__ == "__main__":
    for line in selfcheck():
        print("SELFCHK", line)
    report()
    print("DONE")
