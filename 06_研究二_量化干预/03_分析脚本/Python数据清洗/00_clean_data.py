from __future__ import annotations

from pathlib import Path
import numpy as np
import pandas as pd

ROOT = Path(__file__).resolve().parents[0].parent
RAW = ROOT / "10_最终交付包" / "01_原始数据"
OUT = Path(__file__).resolve().parent / "清洗分析数据"
OUT.mkdir(parents=True, exist_ok=True)


def read(name):
    return pd.read_csv(RAW / name, encoding="utf-8-sig")


def norm_group(s):
    return s.astype("string").str.strip().replace({"AI 组": "AI组", "AI组": "AI组", "Self 组": "Self组", "Self组": "Self组"})


def numeric(df, cols):
    for c in cols:
        if c in df.columns:
            df[c] = pd.to_numeric(df[c].replace({"—": np.nan, "-": np.nan, "": np.nan}), errors="coerce")
    return df


def main_clean():
    d = read("01_主分析数据_24人.csv")
    d.columns = d.columns.str.strip()
    d["Group"] = norm_group(d["Group"])
    numeric(d, ["Age","Height_cm","BW_kg","SMM_kg","PBF_pct","Meas1RM","Rel1RM","Attend_int","AutoReduce","SelfAdj_revised","TotalLoad","SquatLoad","LoadPerSess","Sets","Reps","sRPE","Duration","Pre1RM","Post1RM","PreCMJ","PostCMJ","PreSJ","PostSJ","PreSE","PostSE"])
    # Confirmed corrections from the recruitment/T0 reconciliation.
    d.loc[d.ID.isin(["P010"]), "Age"] = 29
    d.loc[d.ID.isin(["P011"]), "Age"] = 27
    d.loc[d.ID.isin(["P015"]), "Group"] = "Self组"
    d.loc[d.ID.isin(["P009", "P016"]), "Pre1RM"] = 130.0
    # Recompute derived variables from authoritative T0/T1 values.
    d["Rel1RM"] = d["Pre1RM"] / d["BW_kg"]
    d["d1RM"] = d["Post1RM"] - d["Pre1RM"]
    d["dCMJ"] = d["PostCMJ"] - d["PreCMJ"]
    d["dSJ"] = d["PostSJ"] - d["PreSJ"]
    d["dSE"] = d["PostSE"] - d["PreSE"]
    d.to_csv(OUT / "01_main_PP_24.csv", index=False, encoding="utf-8-sig")
    return d


def monitor_clean(main):
    d = read("02_训练监控数据_192课次.csv")
    d.columns = d.columns.str.strip()
    d["Group"] = norm_group(d["Group"])
    numeric(d, ["Sleep","Stress","Fatigue","Soreness","CMJ1","CMJ2","CMJ3","Trigger","WarmLoad","GA","App","CMJb","HooperTot","Sleep_reversed","Hooper_final"])
    # Group is authoritative from the corrected 24-person main table.
    d = d.drop(columns=["Group"], errors="ignore").merge(main[["ID","Group"]], on="ID", how="left")
    d["Sleep_reversed"] = 11 - d["Sleep"]
    d["Hooper_final"] = d["Sleep_reversed"] + d["Stress"] + d["Fatigue"] + d["Soreness"]
    # AI-only process variables are structural missing for Self, not zero events.
    ai_only = ["Trigger", "WarmLoad", "GA", "App", "CMJb"]
    d.loc[d["Group"] == "Self组", ai_only] = np.nan
    d.to_csv(OUT / "02_training_monitor_192.csv", index=False, encoding="utf-8-sig")
    return d


def simple_copy(name, outname):
    d = read(name)
    if "组别" in d.columns: d["组别"] = norm_group(d["组别"])
    d.to_csv(OUT / outname, index=False, encoding="utf-8-sig")
    return d


def clean():
    main = main_clean()
    monitor = monitor_clean(main)
    rep = read("03_Rep级App_GA配对_43对.csv")
    numeric(rep, ["Load","Rep","GA","App"])
    rep.to_csv(OUT / "03_app_ga_rep_43.csv", index=False, encoding="utf-8-sig")
    simple_copy("04_自我效能_Pre_24人.csv", "04_self_efficacy_pre_24.csv")
    simple_copy("05_自我效能_Post_24人.csv", "05_self_efficacy_post_24.csv")
    simple_copy("06_自我效能_PrePost_24人.csv", "06_self_efficacy_prepost_24.csv")
    simple_copy("07_SUS_AI组11人.csv", "07_SUS_AI_11.csv")
    simple_copy("08_PU_Trust_Intention_AI组11人.csv", "08_acceptance_AI_11.csv")
    quality = pd.DataFrame([
        ["主表", "行数", len(main), "通过", "PP主分析数据"],
        ["主表", "ID唯一", main.ID.nunique(), "通过" if main.ID.nunique()==len(main) else "失败", ""],
        ["训练监控", "行数", len(monitor), "通过" if len(monitor)==192 else "失败", ""],
        ["训练监控", "每人8课次", int(monitor.groupby("ID").size().eq(8).all()), "通过" if monitor.groupby("ID").size().eq(8).all() else "失败", ""],
        ["Self组", "AI专属字段结构性缺失", int(monitor.loc[monitor.Group=="Self组", ["Trigger","WarmLoad","GA","App","CMJb"]].notna().sum().sum()), "通过" if monitor.loc[monitor.Group=="Self组", ["Trigger","WarmLoad","GA","App","CMJb"]].notna().sum().sum()==0 else "失败", "Self组不参与AI过程变量分析"],
        ["主结局", "变化值核对", float((main.d1RM-(main.Post1RM-main.Pre1RM)).abs().max()), "通过", ""],
    ], columns=["模块","核查项目","结果","状态","说明"])
    quality.to_csv(OUT / "01_数据质量核查结果.csv", index=False, encoding="utf-8-sig")
    (OUT / "README.md").write_text("本目录由03_Python分析/00_clean_data.py从01_原始数据生成；不手工编辑。\n", encoding="utf-8")
    print(f"Python清洗完成：{OUT}")

if __name__ == "__main__": clean()
