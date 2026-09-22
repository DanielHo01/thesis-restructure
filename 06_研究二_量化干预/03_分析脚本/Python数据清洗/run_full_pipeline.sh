#!/usr/bin/env bash
# 一键复现研究二全部分析：清洗(00) → 主分析(01–07) → ITT敏感性(08) → 补充计算(11) → 偏移特征(12)
# 用法：./run_full_pipeline.sh    （可用 PYTHON=... 指定解释器，须已安装 requirements.txt）
set -euo pipefail
cd "$(dirname "$0")"
PY="${PYTHON:-python3}"

echo "[1/5] 00_clean_data.py（清洗：01_原始数据 → 清洗分析数据/）"
"$PY" 00_clean_data.py
echo "[2/5] run_all.py（主分析：报告 01–07）"
"$PY" run_all.py
echo "[3/5] 08_ITT敏感性分析.py（报告 08_*）"
"$PY" 08_ITT敏感性分析.py
echo "[4/5] 11_补充计算_基线效应量推进标准.py（报告 11_*，含口径自检）"
"$PY" 11_补充计算_基线效应量推进标准.py
echo "[5/5] 12_热身课次级偏移特征.py（报告 12_*）"
"$PY" 12_热身课次级偏移特征.py

echo "完成。报告输出于 ../05_分析说明/Python重算报告/"
