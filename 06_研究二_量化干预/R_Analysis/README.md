# 毕业论文数据分析项目

## 项目说明

本目录包含研究二 Pilot RCT 全部量化分析脚本，从原始数据自动生成所有表格和图表。

**注意：研究一数据目前不可用，无法重跑。**

---

## 目录结构

```
R_Analysis/
├─ R_Analysis.Rproj          # RStudio 项目文件
├─ README.md                  # 本文件
├─ data_raw/                 # 只读原始数据
│   ├─ 00_participants_36.csv   # 36人总表（CONSORT）
│   ├─ 01_main_PP_24.csv        # PP样本24人主数据
│   ├─ 02_training_monitor_192.csv  # 训练监控192课次
│   ├─ 03_app_ga_rep_43.csv   # Rep级43对配对
│   ├─ 04_sus_ai_11.csv       # SUS条目（AI组）
│   ├─ 05_acceptance_ai_11.csv # PU/Trust/Intention（无原始分）
│   ├─ 06_selfeff_pre_24.csv   # T0自我效能（6题版）
│   └─ 07_selfeff_post_24.csv  # T1自我效能（6题版）
├─ data_clean/                # 自动生成（RDS格式）
├─ scripts/
│   ├─ 00_setup.R             # 全局配置
│   ├─ 01_import_clean.R      # 数据导入与清洗
│   ├─ 02_flow_baseline.R     # CONSORT流程 + 基线特征
│   ├─ 03_hooper_lmm.R      # Hooper LMM
│   ├─ 04_ancova.R           # ANCOVA + Hedges' g
│   ├─ 05_app_ga_agreement.R # App-GA现场一致性
│   ├─ 06_feasibility_training.R  # 可行性 + 训练执行
│   ├─ 07_sus_acceptance.R   # SUS + TAM接受度
│   ├─ 08_sensitivity.R      # LOO + 训练频率敏感性
│   ├─ 09_spearman.R        # Spearman探索性相关
│   └─ run_all.R             # 一键运行全部脚本
└─ outputs/                   # 自动生成
   ├─ tables/                 # 所有表格（CSV + TXT）
   ├─ figures/                # 所有图表（PDF + PNG）
   └─ diagnostics/             # 诊断报告
```

## 运行方法

### 方法一：RStudio
1. 双击 `R_Analysis.Rproj` 在 RStudio 中打开项目
2. 打开 `scripts/run_all.R`
3. Ctrl+Shift+S 运行全部

### 方法二：命令行
```bash
cd path/to/R_Analysis
Rscript -e "source('scripts/run_all.R')"
```

### 方法三：交互式（逐步运行）
```r
source("scripts/00_setup.R")
source("scripts/01_import_clean.R")
source("scripts/02_flow_baseline.R")
# ... 以此类推
```

## 分析覆盖

| 脚本 | 输出内容 |
|---|---|
| `01` | 清洗后数据 + 导入诊断 |
| `02` | CONSORT流程表、基线特征表 |
| `03` | Hooper LMM方差分析表、每课次比较、轨迹图 |
| `04` | 5项ANCOVA + 模型诊断 + 森林图 |
| `05` | App-GA Bland-Altman图 + ICC bootstrap CI |
| `06` | 可行性指标表、训练执行组间比较 |
| `07` | SUS重算 + TAM描述 + 接受度图 |
| `08` | LOO敏感性表 + 训练频率敏感性 |
| `09` | Spearman相关矩阵 + 热图 |

## 已知限制

1. **研究一数据不存在**——无法重跑研究一的任何分析
2. **自我效能量表是6题版**——正文中若写8题需要核对
3. **PU/Trust/Intention无原始分**——无法计算Cronbach's α
4. **CONSORT流程需精确化**——36人名单中脱落9人vs论文描述7人的矛盾待解决

## 随机种子

统一使用 `GLOBAL_SEED = 20260916`，所有随机过程均可复现。

## 依赖包

```r
# 核心包
tidyverse, lme4, lmerTest, car, emmeans,
effectsize, psych, broom, broom.mixed,
patchwork, ggpubr, ggsci, here, boot, pwr

# 安装（首次运行自动安装）
install.packages(c("tidyverse","lme4","lmerTest","car","emmeans",
                   "effectsize","psych","broom","broom.mixed",
                   "patchwork","ggpubr","ggsci","here","boot","pwr"))
```
