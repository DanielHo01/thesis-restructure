# -*- coding: utf-8 -*-
"""
12_热身课次级偏移特征：刻画 APP−GymAware 88对热身课次级配对的系统性正向偏移
输出：05_分析说明/Python重算报告/12_热身课次级偏移特征.csv
用途：论文4.7节偏移来源讨论（固定标定差异 vs 比例误差）的数据依据。
"""
from pathlib import Path

import numpy as np
import pandas as pd
from scipy import stats

BASE = Path(__file__).resolve().parent
DATA = BASE / "清洗分析数据"
REPORT = BASE.parent / "05_分析说明" / "Python重算报告"
REPORT.mkdir(parents=True, exist_ok=True)

mon = pd.read_csv(DATA / "02_training_monitor_192.csv", encoding="utf-8-sig")
q = mon[["ID", "Sess", "Group", "WarmLoad", "GA", "App"]].dropna(subset=["GA", "App"]).copy()
q["dif"] = q.App - q.GA
assert len(q) == 88, len(q)

rows = []

# 1) 总体
r, p = stats.pearsonr(q.WarmLoad, q.dif)
r_ga, p_ga = stats.pearsonr(q.GA, q.dif)
sl, ic, _, pv, _ = stats.linregress(q.GA, q.dif)
rows.append({
    "分析": "总体（88对）", "统计量": "均值±SD",
    "数值": f"{q.dif.mean():.5f}±{q.dif.std(ddof=1):.5f} m/s",
    "细节": f"范围{q.dif.min():.3f}至{q.dif.max():.3f}；正/零/负={int((q.dif>0).sum())}/{int((q.dif==0).sum())}/{int((q.dif<0).sum())}"
})
rows.append({"分析": "偏移~热身负荷", "统计量": "Pearson r（p）", "数值": f"r={r:.3f}（p={p:.3f}）", "细节": "检验偏移是否随负荷变化"})
rows.append({"分析": "偏移~GymAware速度", "统计量": "Pearson r（p）", "数值": f"r={r_ga:.3f}（p={p_ga:.3f}）", "细节": "检验偏移是否随速度水平（比例误差）变化"})
rows.append({"分析": "偏移~GymAware速度", "统计量": "线性回归斜率（p）", "数值": f"斜率={sl:.3f}（p={pv:.3f}）", "细节": "斜率≈0且截距≈+0.021则支持固定加性偏移"})

# 2) 受试者间
per_sub = q.groupby("ID").dif.agg(["mean", "std", "count"])
rows.append({
    "分析": "受试者间（11人）", "统计量": "个体均值 范围/极差",
    "数值": f"{per_sub['mean'].min():.4f}至{per_sub['mean'].max():.4f}（极差{per_sub['mean'].max()-per_sub['mean'].min():.4f}）",
    "细节": "；".join(f"{i}:{m:.4f}" for i, m in per_sub["mean"].items())
})

# 3) 课次间
per_sess = q.groupby("Sess").dif.agg(["mean", "std", "count"])
rows.append({
    "分析": "课次间（S1–S8）", "统计量": "课次均值 范围/极差",
    "数值": f"{per_sess['mean'].min():.4f}至{per_sess['mean'].max():.4f}（极差{per_sess['mean'].max()-per_sess['mean'].min():.4f}）",
    "细节": "；".join(f"{i}:{m:.4f}" for i, m in per_sess["mean"].items())
})

# 4) 变异分解：受试者间SD vs 受试者内SD
between = per_sub["mean"].std(ddof=1)
within = q.groupby("ID").dif.std(ddof=1).mean()
rows.append({
    "分析": "变异分解", "统计量": "受试者间SD vs 受试者内SD",
    "数值": f"{between:.4f} vs {within:.4f}",
    "细节": "组间≈组内→偏移幅度不因受试者而异"
})

out = pd.DataFrame(rows)
out.to_csv(REPORT / "12_热身课次级偏移特征.csv", index=False, encoding="utf-8-sig")
print(out.to_string(index=False))
print("\nwritten:", REPORT / "12_热身课次级偏移特征.csv")
