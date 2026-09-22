from __future__ import annotations

import json
import warnings
from pathlib import Path

import numpy as np
import pandas as pd
from scipy import stats
from patsy.contrasts import Poly
import statsmodels.api as sm
import statsmodels.formula.api as smf
from statsmodels.stats.anova import anova_lm
from statsmodels.stats.multitest import multipletests
from statsmodels.regression.linear_model import RegressionResults

warnings.filterwarnings("ignore")

ROOT = Path(__file__).resolve().parents[0].parent
DATA = Path(__file__).resolve().parent / "清洗分析数据"
REPORT = ROOT / "05_分析说明" / "Python重算报告"
FIG = ROOT / "10_最终交付包" / "04_最终图表" / "Python复核版"
REPORT.mkdir(parents=True, exist_ok=True)
FIG.mkdir(parents=True, exist_ok=True)


def read(name: str) -> pd.DataFrame:
    return pd.read_csv(DATA / name, encoding="utf-8-sig")


def mean_sd(x):
    x = pd.to_numeric(x, errors="coerce").dropna()
    return f"{x.mean():.2f}±{x.std(ddof=1):.2f}"


def cohens_d(a, b):
    a, b = np.asarray(a, dtype=float), np.asarray(b, dtype=float)
    a, b = a[~np.isnan(a)], b[~np.isnan(b)]
    sp = np.sqrt(((len(a)-1)*a.var(ddof=1)+(len(b)-1)*b.var(ddof=1))/(len(a)+len(b)-2))
    return (a.mean()-b.mean())/sp if sp else np.nan


def icc_21(x, y):
    """ICC(2,1), two-way random, absolute agreement, single measurement."""
    z = pd.DataFrame({"x": x, "y": y}).dropna().to_numpy(float)
    n = len(z)
    if n < 2: return np.nan
    grand = z.mean()
    ms_subject = 2 * np.sum((z.mean(axis=1)-grand)**2) / (n-1)
    ms_rater = n * np.sum((z.mean(axis=0)-grand)**2) / (2-1)
    residual = z - z.mean(axis=1, keepdims=True) - z.mean(axis=0, keepdims=True) + grand
    ms_error = np.sum(residual**2) / ((n-1)*(2-1))
    return (ms_subject-ms_error)/(ms_subject+(2-1)*ms_error+2*(ms_rater-ms_error)/n)


def audit(main, mon, rep, sepre, sepost, sus, acc):
    rows = []
    def add(module, item, result, status, note=""):
        rows.append({"模块": module, "核查项目": item, "结果": result, "状态": status, "说明": note})
    add("主表", "样本量", len(main), "PASS" if len(main)==24 else "FAIL")
    add("主表", "ID唯一", main.ID.nunique(), "PASS" if main.ID.nunique()==24 else "FAIL")
    add("主表", "分组", json.dumps(main.Group.value_counts().to_dict(), ensure_ascii=False), "PASS")
    add("训练监控", "记录数", len(mon), "PASS" if len(mon)==192 else "FAIL")
    add("训练监控", "每人记录数", json.dumps(mon.groupby("ID").size().value_counts().to_dict()), "PASS" if mon.groupby("ID").size().eq(8).all() else "FAIL")
    add("训练监控", "课次覆盖", ",".join(sorted(mon.Sess.dropna().unique())), "PASS")
    add("Rep配对", "记录数", len(rep), "PASS" if len(rep)==43 else "FAIL")
    add("Rep配对", "关键键重复", int(rep.duplicated(["ID","Session","Set","Rep"]).sum()), "PASS" if not rep.duplicated(["ID","Session","Set","Rep"]).any() else "FAIL")
    add("主结局", "主要变量缺失数", int(main[["Pre1RM","Post1RM","PreCMJ","PostCMJ","PreSJ","PostSJ","PreSE","PostSE"]].isna().sum().sum()), "PASS")
    for pre, post, delta in [("Pre1RM","Post1RM","d1RM"),("PreCMJ","PostCMJ","dCMJ"),("PreSJ","PostSJ","dSJ"),("PreSE","PostSE","dSE")]:
        err = ((main[post]-main[pre])-main[delta]).abs().max()
        add("主结局", f"{delta}=Post-Pre", float(err), "PASS" if err < 1e-9 else "FAIL")
    add("App配对", "热身有效配对数", int(mon[["GA","App"]].dropna().shape[0]), "INFO")
    add("量表", "自我效能Pre行数", len(sepre), "PASS" if len(sepre)==24 else "FAIL")
    add("量表", "自我效能Post行数", len(sepost), "PASS" if len(sepost)==24 else "FAIL")
    add("量表", "SUS行数", len(sus), "PASS" if len(sus)==11 else "FAIL")
    add("量表", "接受度行数", len(acc), "PASS" if len(acc)==11 else "FAIL")
    out = pd.DataFrame(rows)
    out.to_csv(REPORT/"01_数据审计结果.csv", index=False, encoding="utf-8-sig")
    return out


def ancova(main):
    d = main.copy()
    d["Group"] = pd.Categorical(d.Group, categories=["Self组", "AI组"])
    d["Stratum"] = d.Stratum.astype("category")
    d["Pre1RM_rel"] = d.Pre1RM / d.BW_kg
    d["Post1RM_rel"] = d.Post1RM / d.BW_kg
    specs = [
        ("绝对1RM", "Post1RM", "Pre1RM", "kg"),
        ("相对1RM", "Post1RM_rel", "Pre1RM_rel", "kg/kg"),
        ("CMJ", "PostCMJ", "PreCMJ", "cm"),
        ("SJ", "PostSJ", "PreSJ", "cm"),
        ("训练自我效能", "PostSE", "PreSE", "分"),
    ]
    out=[]
    for label, post, pre, unit in specs:
        fit = smf.ols(f"{post} ~ C(Group) + {pre} + C(Stratum)", data=d).fit()
        term = "C(Group)[T.AI组]"
        est = fit.params[term]
        ci = fit.conf_int().loc[term]
        out.append({"结局":label,"n":int(fit.nobs),"AI−Self调整后差值":est,"CI_low":ci[0],"CI_high":ci[1],"p值":fit.pvalues[term],"R2":fit.rsquared,"单位":unit})
    out=pd.DataFrame(out)
    out.to_csv(REPORT/"02_主要结局_ANCOVA.csv",index=False,encoding="utf-8-sig")
    return out, d


def mixed_hooper(mon):
    d=mon.copy()
    d["Group"]=pd.Categorical(d.Group,categories=["Self组","AI组"])
    d["Sess"]=pd.Categorical(d.Sess,categories=[f"S{i}" for i in range(1,9)],ordered=False)
    fit=smf.mixedlm("Hooper_final ~ C(Group)*C(Sess, Poly)",d,groups=d["ID"],re_formula="1").fit(reml=True, method="powell", maxiter=2000, disp=False)
    coef=pd.DataFrame({"term":fit.params.index,"estimate":fit.params.values,"SE":fit.bse.values,"p":fit.pvalues.values})
    coef.to_csv(REPORT/"03_Hooper_LMM_系数.csv",index=False,encoding="utf-8-sig")
    wald=fit.wald_test_terms(skip_single=False).table.reset_index().rename(columns={"index":"term","statistic":"Wald_chi2","pvalue":"p","df_constraint":"df"})
    wald.to_csv(REPORT/"03_Hooper_LMM_Wald整体检验.csv",index=False,encoding="utf-8-sig")
    desc=d.groupby(["Group","Sess"],observed=False).Hooper_final.agg(n="count",mean="mean",SD="std").reset_index()
    desc.to_csv(REPORT/"03_Hooper_S1-S8_描述.csv",index=False,encoding="utf-8-sig")
    with open(REPORT/"03_Hooper_LMM_模型摘要.txt","w",encoding="utf-8") as f: f.write(str(fit.summary()))
    return fit, desc


def training(main, mon):
    d=main.copy()
    hm=mon.groupby(["ID","Group"],as_index=False).Hooper_final.mean().rename(columns={"Hooper_final":"HooperMean"})
    d=d.merge(hm,on=["ID","Group"],how="left")
    vars=["Attend_int","TotalLoad","SquatLoad","LoadPerSess","Sets","Reps","sRPE","Duration","HooperMean"]
    desc=[]; tests=[]
    for v in vars:
        for g in ["AI组","Self组"]:
            x=d.loc[d.Group==g,v].dropna()
            desc.append({"变量":v,"Group":g,"n":len(x),"均值":x.mean(),"SD":x.std(ddof=1),"中位数":x.median(),"最小值":x.min(),"最大值":x.max()})
        a=d.loc[d.Group=="AI组",v].dropna(); b=d.loc[d.Group=="Self组",v].dropna()
        t=stats.ttest_ind(a,b,equal_var=False)
        ci=stats.t if False else None
        diff=a.mean()-b.mean(); se=np.sqrt(a.var(ddof=1)/len(a)+b.var(ddof=1)/len(b)); df=(a.var(ddof=1)/len(a)+b.var(ddof=1)/len(b))**2/((a.var(ddof=1)/len(a))**2/(len(a)-1)+(b.var(ddof=1)/len(b))**2/(len(b)-1))
        crit=stats.t.ppf(.975,df)
        tests.append({"变量":v,"n_AI":len(a),"n_Self":len(b),"AI均值":a.mean(),"Self均值":b.mean(),"AI−Self":diff,"CI_low":diff-crit*se,"CI_high":diff+crit*se,"p值":t.pvalue,"Cohen_d":cohens_d(a,b)})
    pd.DataFrame(desc).to_csv(REPORT/"04_训练执行_组别描述.csv",index=False,encoding="utf-8-sig")
    pd.DataFrame(tests).to_csv(REPORT/"04_训练执行_组间比较.csv",index=False,encoding="utf-8-sig")
    feas=pd.DataFrame({"指标":["严格完成8/8课次","达到至少80%出席","训练监控记录完整","AI组自动减组触发事件","Self组自主调整次数总和"],"数值":[int((d.Attend_int==8).sum()),int((d.Attend_int>=6.4).sum()),len(mon),int(mon.Trigger.sum()),int(d.SelfAdj_revised.sum())]})
    feas.to_csv(REPORT/"04_训练执行_可行性.csv",index=False,encoding="utf-8-sig")
    return d


def exploratory(d):
    x=d.copy()
    x["Load_per_rep"]=x.TotalLoad/x.Reps
    x["Load_per_set"]=x.TotalLoad/x.Sets
    x["Reps_per_set"]=x.Reps/x.Sets
    x["Load_per_min"]=x.TotalLoad/x.Duration
    rows=[]
    for v in ["Load_per_rep","Load_per_set","Reps_per_set","Load_per_min"]:
        a=x.loc[x.Group=="AI组",v].dropna(); b=x.loc[x.Group=="Self组",v].dropna(); t=stats.ttest_ind(a,b,equal_var=False); rows.append({"指标":v,"AI均值":a.mean(),"AI_SD":a.std(ddof=1),"Self均值":b.mean(),"Self_SD":b.std(ddof=1),"AI−Self":a.mean()-b.mean(),"p值":t.pvalue,"Cohen_d":cohens_d(a,b)})
    pd.DataFrame(rows).to_csv(REPORT/"05_负荷结构_组间比较.csv",index=False,encoding="utf-8-sig")
    corr=[]
    for v in ["TotalLoad","Reps","Sets","Load_per_rep","Load_per_set","Load_per_min","HooperMean","sRPE","Duration"]:
        for y in ["d1RM","dCMJ","dSJ"]:
            q=x[[v,y]].dropna()
            if len(q)>=6:
                r,p=stats.spearmanr(q[v],q[y]); corr.append({"预测变量":v,"结局":y,"n":len(q),"rho":r,"p值":p})
    corr=pd.DataFrame(corr)
    if len(corr): corr["p_FDR"] = multipletests(corr.p值, method="fdr_bh")[1]
    corr.to_csv(REPORT/"05_探索性_Spearman.csv",index=False,encoding="utf-8-sig")
    return x


def app_ag(mon, rep):
    def agreement(d,label):
        q=d[["GA","App"]].dropna().copy(); dif=q.App-q.GA
        b=dif.mean(); sd=dif.std(ddof=1)
        return {"层级":label,"n":len(q),"Bias":b,"LoA_low":b-1.96*sd,"LoA_high":b+1.96*sd,"MAE":dif.abs().mean(),"RMSE":np.sqrt((dif**2).mean()),"Pearson_r":q.GA.corr(q.App),"ICC_2_1":icc_21(q.GA,q.App),"误差≤0.05比例":(dif.abs()<=.05).mean()}
    out=pd.DataFrame([agreement(mon,"热身课次级"),agreement(rep,"Rep级")])
    out.to_csv(REPORT/"06_App_GymAware_一致性.csv",index=False,encoding="utf-8-sig")
    return out


def scales(sepre,sepost,sus,acc):
    def alpha(d):
        z=d.apply(pd.to_numeric,errors="coerce").to_numpy(float); k=z.shape[1]; item=z.var(axis=0,ddof=1).sum(); total=np.nansum(z,axis=1); return k/(k-1)*(1-item/total.var(ddof=1))
    pre=sepre.filter(regex=r"^Pre_Q[1-6]_原始分$"); post=sepost.filter(regex=r"^Post_Q[1-6]_原始分$")
    rows=[{"指标":"自我效能Pre","n":len(pre),"Cronbach_alpha":alpha(pre)},{"指标":"自我效能Post","n":len(post),"Cronbach_alpha":alpha(post)}]
    for v in ["SUS总分_标准公式"]: rows.append({"指标":"SUS","n":len(sus),"均值":sus[v].mean(),"SD":sus[v].std(ddof=1),"最小值":sus[v].min(),"最大值":sus[v].max()})
    for v in ["PU_均值","Trust_均值","Intention_均值"]: rows.append({"指标":v,"n":len(acc),"均值":acc[v].mean(),"SD":acc[v].std(ddof=1),"最小值":acc[v].min(),"最大值":acc[v].max()})
    out=pd.DataFrame(rows); out.to_csv(REPORT/"07_心理量表与接受度.csv",index=False,encoding="utf-8-sig"); return out


def main_run():
    main=read("01_main_PP_24.csv"); mon=read("02_training_monitor_192.csv"); rep=read("03_app_ga_rep_43.csv"); sepre=read("04_self_efficacy_pre_24.csv"); sepost=read("05_self_efficacy_post_24.csv"); sus=read("07_SUS_AI_11.csv"); acc=read("08_acceptance_AI_11.csv")
    audit(main,mon,rep,sepre,sepost,sus,acc)
    ancova(main)
    mixed_hooper(mon)
    d=training(main,mon)
    exploratory(d)
    app_ag(mon,rep)
    scales(sepre,sepost,sus,acc)
    (REPORT/"00_运行完成.txt").write_text("Python分析管线完成。结果仅写入05_分析说明/Python重算报告，未覆盖原始数据、清洗数据或正文。\n",encoding="utf-8")
    print(f"完成：{REPORT}")

if __name__ == "__main__":
    main_run()
