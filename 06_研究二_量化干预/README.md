# 研究二：4周下肢抗阻训练Pilot RCT（量化阶段）

## 研究概述

**研究目的**：评估AI辅助数智化监控方案对青年男性抗阻训练执行与适应的影响

**设计**：随机对照预试验（Pilot RCT）

**样本**：
- 总样本：24人
- AI辅助组：n=11
- 自我指导组：n=13
- 完成率：21/24严格全勤，24/24完成率≥80%

**干预**：4周，共8次训练课

---

## 目录结构

```
06_Study2_Quantitative/
├── README.md                    # 本文件
│
├── 01_Raw_Data/                 # 原始数据（只读）
│   ├── 01_主分析数据_24人.csv
│   ├── 02_训练监控数据_192课次.csv
│   ├── 03_Rep级App_GA配对_43对.csv
│   ├── 04_自我效能_Pre_24人.csv
│   ├── 05_自我效能_Post_24人.csv
│   ├── 06_自我效能_PrePost_24人.csv
│   ├── 07_SUS_AI组11人.csv
│   └── 08_PU_Trust_Intention_AI组11人.csv
│
├── 02_Cleaned_Data/             # 清洗后数据（供R读取）
│   ├── 01_main_PP_24.csv
│   ├── 02_training_monitor_192.csv
│   ├── 03_app_ga_rep_43.csv
│   └── ...
│
├── 03_Scripts/                  # 分析脚本
│   ├── Python_Pipeline/         # 数据清洗管线
│   │   ├── 00_clean_data.py    # 主清洗脚本
│   │   ├── 08_ITT敏感性分析.py
│   │   ├── 11_补充计算_基线效应量.py
│   │   ├── requirements.txt
│   │   └── README.md
│   │
│   └── R_Analysis/              # 统计分析管线
│       ├── 00_setup.R          # 环境配置
│       ├── 01_data_load.R      # 数据读取
│       ├── 02_baseline_characteristics.R  # 基线特征
│       ├── 03_feasibility_indicators.R    # 可行性指标
│       ├── 04_ancova_primary.R           # ANCOVA主要结局
│       ├── 05_hooper_lmm.R              # Hooper指数LMM
│       ├── 05b_hooper_session_comparisons.R
│       ├── 06_training_execution.R       # 训练执行分析
│       ├── 07_app_ga_agreement.R        # App-GA一致性
│       ├── 08_psych_acceptance.R        # 心理量表分析
│       ├── 09_exploratory_correlation.R # 探索性相关
│       ├── 10_itt_sensitivity.R         # ITT敏感性
│       ├── 11_forest_plots.R           # 森林图
│       └── 12_run_all.R               # 一键运行全部
│
└── 04_Figures_Tables/          # 论文图表输出
    ├── 正式图/
    │   ├── 图4-1_CONSORT流程图.pdf/png
    │   ├── 图4-2_Hooper_S1-S8趋势图.pdf/png
    │   ├── 图4-3_五项主要结局森林图.pdf/png
    │   ├── 图4-4_热身App-GA_Bland-Altman.pdf/png
    │   └── 图4-5_Rep级App-GA_Bland-Altman.pdf/png
    │
    └── 探索性图/
        └── G1-G3_探索性分析图.pdf/png
```

---

## 快速复现

### 1. 数据清洗
```bash
cd 03_Scripts/Python_Pipeline
pip install -r requirements.txt
python run_all.py
```

### 2. 统计分析（按顺序执行）
```bash
cd 03_Scripts/R_Analysis

# 或一键运行
Rscript 12_run_all.R

# 或分步执行
Rscript 00_setup.R
Rscript 01_data_load.R
Rscript 04_ancova_primary.R
Rscript 05_hooper_lmm.R
# ...
```

---

## 核心统计结果

### 主要结局（ANCOVA: Post ~ Group + Pre + Stratum）

| 指标 | AI组 | Self组 | 差值 | p值 |
|------|------|--------|------|-----|
| CMJ (cm) | 36.43±4.41 | 34.27±4.88 | +2.162 | **0.051** |
| 1RM深蹲 (kg) | 93.41±15.63 | 90.96±18.04 | +2.434 | 0.267 |
| 训练自我效能 | 82.45±6.12 | 72.57±7.05 | +9.881 | **<0.001** |

### 训练负荷可比性

| 指标 | AI组 | Self组 | p值 |
|------|------|--------|-----|
| 全课总负荷 (kg) | 17651.8 | 17461.5 | 0.854 |
| 深蹲总负荷 (kg) | 13654.6 | 13642.3 | 0.988 |

---

## 注意事项

1. **数据只读**：01_Raw_Data/ 下的文件仅供备份，严禁直接修改
2. **脚本执行顺序**：R脚本依赖数据加载顺序，请勿跳过 00_setup.R 和 01_data_load.R
3. **工作目录**：建议将R工作目录设置为 03_Scripts/R_Analysis/

---

*最后更新：2026-03-21*
