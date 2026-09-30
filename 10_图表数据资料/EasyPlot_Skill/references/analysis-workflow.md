# 数据分析工作流

## 标准工作流

### 1. 数据理解阶段

**关键问题**：
- 实验单位是什么？（个体、动物、样本、场地？）
- 重复结构是怎样的？（独立组、重复测量、嵌套设计？）
- 自变量和因变量各是什么？类型和单位？
- 是否有缺失值？缺失机制是什么？（MCAR/MAR/MNAR）

**数据质量检查清单**：

```r
# R: 数据初检
summary(df)
str(df)
colSums(is.na(df))
table(df$group, useNA = "ifany")
```

```python
# Python: 数据初检
import pandas as pd
import numpy as np

df.info()
df.describe()
df.isnull().sum()
df.groupby('group').size()
```

### 2. 探索性分析（EDA）

**必做检查**：
- [ ] 分布形态（正态性）
- [ ] 异常值/离群点
- [ ] 方差齐性
- [ ] 变量相关性
- [ ] 缺失模式

```r
# R: 分布检查
library(ggplot2)
ggplot(df, aes(x = outcome)) + 
  geom_histogram(bins = 30) + 
  facet_wrap(~group)

# 正态性检验
shapiro.test(df$outcome[df$group == "A"])
shapiro.test(df$outcome[df$group == "B"])

# R: 离群点
boxplot(outcome ~ group, data = df)
```

```python
# Python: 分布检查
import seaborn as sns

sns.histplot(data=df, x='outcome', hue='group', bins=30)
sns.boxplot(data=df, x='group', y='outcome')

# Shapiro-Wilk 检验
from scipy import stats

for group in df['group'].unique():
    stat, p = stats.shapiro(df[df['group'] == group]['outcome'])
    print(f"{group}: W={stat:.4f}, p={p:.4f}")
```

### 3. 统计方法选择决策树

```
数据特征 → 推荐方法

├── 2组独立
│   ├── 正态 + 方差齐 → 独立t检验
│   ├── 正态 + 方差齐 → Welch t检验（推荐）
│   ├── 非正态 → Mann-Whitney U检验
│   └── 有基线协变量 → ANCOVA
│
├── 2组配对/重复测量
│   ├── 正态 → 配对t检验
│   └── 非正态 → Wilcoxon符号秩检验
│
├── ≥3组独立
│   ├── 正态 + 方差齐 → 单因素ANOVA → post-hoc Tukey
│   ├── 正态 + 方差齐 → Welch ANOVA（推荐）
│   ├── 非正态 → Kruskal-Wallis → post-hoc Dunn
│   └── 有协变量 → ANCOVA
│
├── 重复测量/时间序列
│   ├── 正态 → 重复测量ANOVA / 混合效应模型
│   └── 非正态 → GEE / 广义估计方程
│
└── 嵌套/分层设计
    └── 混合效应模型（随机效应=嵌套单位）
```

### 4. 效应量报告

**必须报告的效应量**：

| 检验类型 | 效应量指标 | 解释标准 |
|---|---|---|
| t检验 | Cohen's d | 0.2小/0.5中/0.8大 |
| ANOVA | η² / η²p | .01小/.06中/.14大 |
| 相关系数 | r | .1小/.3中/.5大 |
| 卡方 | Cramér's V | .1小/.3中/.5大 |
| Mann-Whitney | r (= Z/√N) | 同上 |

```r
# R: 效应量计算
library(effsize)

# Cohen's d
cohen.d(outcome ~ group, data = df)

# η² from ANOVA
anova(lm(outcome ~ group, data = df))
eta_sq <- summary(aov(outcome ~ group, data = df))[[1]]$`Sum Sq`[1] / 
          sum(summary(aov(outcome ~ group, data = df))[[1]]$`Sum Sq`)
```

### 5. 结果报告模板

```
## 3.2 主要结局

描述性统计以均值±标准差 (M±SD) 报告。组间比较采用独立样本t检验（或Welch's t检验），效应量以Cohen's d报告。

AI组 (n=11) 基线CMJ高度为 36.8±4.2 cm，干预后为 36.4±4.4 cm，
变化量为 -0.4±2.1 cm。自我指导组 (n=13) 基线为 34.2±4.1 cm，
干预后为 34.3±4.9 cm，变化量为 +0.1±2.4 cm。

两组CMJ变化量的组间差值为 0.5 cm (95% CI [-1.2, 2.2])，
Welch's t检验结果为 t(21.3) = 0.62, p = 0.54，
效应量 Cohen's d = 0.25 [small]。
```

### 6. 可视化选择

**数据分析图表决策**：

| 分析目标 | 推荐图表 |
|---|---|
| 展示原始数据分布 | 散点图 / 抖动图 |
| 展示组间均值差异 | 柱状图 + 误差线 + 散点叠加 |
| 展示变化趋势 | 线图 + 置信区间 |
| 展示相关关系 | 散点图 + 回归线 |
| 展示效应量 | 森林图 |
| 展示一致性/差异 | Bland-Altman图 |

### 7. 敏感性分析

**常见敏感性分析**：
- [ ] 完整案例分析（Complete Case）
- [ ] 意向性分析（ITT，保留所有随机化对象）
- [ ] 缺失值处理变化（多重插补 vs 列表删除）
- [ ] 极端值处理变化（包含 vs 排除 >3 SD）
- [ ] 模型假设变化（正态 vs 非参）

### 8. 统计软件和环境

```r
# R: 必备包
install.packages(c(
  "tidyverse",    # 数据操作
  "ggplot2",      # 可视化
  "psych",         # 描述统计
  "effsize",       # 效应量
  "car",           # 方差齐性检验
  "lawstat",       # 离群点检验
  "mice"           # 缺失值处理
))

# 检查版本
sessionInfo()
```

```python
# Python: 必备包
pip install numpy pandas scipy statsmodels matplotlib seaborn pingouin

# 检查版本
import importlib.metadata
print(importlib.metadata.version('numpy'))
print(importlib.metadata.version('pandas'))
```

### 9. 可重复性检查清单

- [ ] 随机种子设置（`set.seed()` / `np.random.seed()`）
- [ ] 软件版本记录
- [ ] 脚本注释清晰
- [ ] 数据路径使用相对路径
- [ ] 输出结果可追溯

```r
# R: 可重复性
set.seed(12345)

# 保存session信息
sink("sessionInfo.txt")
sessionInfo()
sink()
```

```python
# Python: 可重复性
np.random.seed(42)

# 保存环境信息
import platform
print(platform.platform())
print(f"Python: {platform.python_version()}")
```
