# 图表数据资料包

## 📁 目录结构

```
10_图表数据资料/
├── README.md                        # 本文件 - 使用说明
│
├── 01_图4.4_森林图数据.md          # 图4.4森林图完整数据
├── 02_图4.5_BlandAltman数据.md     # 图4.5 Bland-Altman数据
│
├── 03_表格数据.md                   # 各章节表格数据汇总
│
├── EasyPlot_Skill/                  # EasyPlot科研绘图Skill
│   ├── SKILL.md                     # 核心入口
│   ├── README.md                    # 使用说明
│   ├── scripts/
│   │   ├── easyplot_templates.R     # R绘图模板
│   │   └── easyplot_py.py           # Python绘图模板
│   └── references/
│       ├── palette-library.md        # 配色方案
│       ├── style-guide.md           # 图表样式规范
│       └── analysis-workflow.md      # 数据分析工作流
│
└── 原数据文件/
    ├── 06_研究二_量化干预/01_原始数据/
    │   ├── 01_主分析数据_24人.csv
    │   ├── 02_训练监控数据_192课次.csv
    │   ├── 03_Rep级App_GA配对_43对.csv
    │   └── 自我效能相关数据.csv
    │
    └── 05_研究一_效度验证/
        └── (效度研究原始数据)
```

---

## 📊 需要绘制的图表清单

### 研究二 第四章

| 图号 | 图表类型 | 数据状态 | 优先级 |
|------|---------|---------|:------:|
| 图4.1 | CONSORT流程图 | 需从数据推导 | 高 |
| 图4.2 | Hooper指数趋势图 | 需整理数据 | 高 |
| 图4.3 | 主要结局森林图 | ✅ 已整理 | 高 |
| 图4.4 | 探索性结局森林图 | ✅ 已整理 | 高 |
| 图4.5 | Bland-Altman一致性图 | ✅ 已整理 | 高 |

### 研究一 第三章（待补充）

| 图号 | 图表类型 | 数据状态 |
|------|---------|---------|
| 图3.X | 效度验证散点图 | 需整理 |
| 图3.X | Bland-Altman图 | 需整理 |

---

## 🎨 EasyPlot Skill 使用方法

### 方法一：使用R (推荐)
```r
# 加载EasyPlot模板
source("EasyPlot_Skill/scripts/easyplot_templates.R")

# 使用配色
.okabe_ito  # 色盲友好配色
.paul_tol_12  # Paul Tol 12色

# 绘制森林图
plot_forest(estimates, ci_lower, ci_upper, labels)

# 绘制Bland-Altman图
plot_bland_altman(data, "App", "GA", labels = c("App", "GymAware"))

# 导出
export_figure(plot, "output", width = 8.5, height = 6)
```

### 方法二：使用Python
```python
from EasyPlot_Skill.scripts.easyplot_py import *

# 绘制森林图
fig, ax = plt.subplots(figsize=(10, 6))
plot_forest(estimates, ci_lower, ci_upper, labels)

# 导出
export_figure(fig, 'output', width_cm=10, height_cm=6, dpi=300)
```

### 方法三：使用其他工具
- **Origin** - 导入CSV数据，按照规格说明绘图
- **GraphPad Prism** - 专业统计绘图工具
- **Excel** - 基础图表

---

## 📋 图4.4 森林图数据摘要

```
图表类型：Forest森林图
样本量：PP样本 n = 24

数据（按Hedges' g降序）：
1. 训练自我效能: g=2.70 [1.59, 3.80], p<0.001 ★★★
2. CMJ高度: g=0.85 [0.02, 1.69], p=0.126
3. 深蹲1RM: g=0.46 [-0.35, 1.28], p=0.341
4. SJ高度: g=0.43 [-0.38, 1.24], p=0.622
5. 相对1RM: g=0.39 [-0.42, 1.20], p=0.341

配色：
- 显著行：绿色 #16A34A，背景 #F0FDF4
- 非显著行：蓝灰色 #2563EB
- 无效线：红色虚线 #DC2626
```

---

## 📋 图4.5 Bland-Altman数据摘要

```
图表类型：Bland-Altman一致性图
样本量：43对Rep级测量

数据文件：06_研究二_量化干预/01_原始数据/03_Rep级App_GA配对_43对.csv

X轴：两种方法平均值 (GA + App) / 2
Y轴：差值 App - GA
参考线：
  - 实线：差值均值
  - 虚线：±1.96 SD（95%一致性界限）

配色：蓝灰色系
```

---

## 📋 表格数据摘要

### 表4.2 基线特征
见 `03_表格数据.md`

### 表4.6 主要结局
见 `03_表格数据.md`

---

## 📞 绘图建议

### 推荐配色方案（色盲友好）
1. **Okabe-Ito** - 科研默认首选
   - `#E69F00` 橙, `#56B4E9` 天蓝, `#009E73` 青绿, `#F0E442` 黄, `#0072B2` 深蓝

2. **Paul Tol 12色** - 多组比较
   - `#4477AA`, `#EE6677`, `#228833`, `#CCBB44`, `#66CCEE`, `#AA3377`

3. **Viridis** - 连续变量
   - `#440154` → `#FDE725`

### 导出设置
- **分辨率**：PNG ≥ 300 DPI
- **尺寸**：单栏 8.5cm / 双栏 17cm
- **格式**：同时导出PNG和PDF（矢量）

---

*最后更新：2026-09*
