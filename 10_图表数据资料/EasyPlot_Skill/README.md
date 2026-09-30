# EasyPlot - 科研绘图技能

**科研数据分析和可视化的一站式解决方案**

## 核心价值

EasyPlot 将以下经验积累蒸馏成模板、色带、排版规则和导出检查：

- **配色体系**：Okabe-Ito（色盲友好）、Paul Tol、Viridis、东方色带
- **保留原始数据**：散点 + 均值/误差线，让读者看到样本量和离散程度
- **克制的视觉语言**：有限装饰、一致字体、统一比例
- **投稿前检查**：灰度分离、字体、回落尺寸检查

## 安装

将本目录复制到 Pi 的 skills 目录：

```bash
# Windows
copy -r easyplot C:\Users\<用户名>\.pi\agent\skills\

# 或在 Pi 中使用
skill_manage: create, name="easyplot", scope="global"
```

## 快速开始

### R 示例

```r
# 加载模板
source("scripts/easyplot_templates.R")

# 分组柱状图 + 散点
p <- plot_group_bar_with_points(
  data = df,
  x_var = "group",
  y_var = "cmj_height",
  group_var = "condition",
  colors = .okabe_ito,
  y_label = "CMJ Height (cm)"
)

# Bland-Altman 图
p <- plot_bland_altman(df, "app_velocity", "ga_velocity", 
                       labels = c("App", "GymAware"))

# 森林图
p <- plot_forest(estimates, ci_low, ci_high, labels)

# 导出
export_figure(p, "figure1", width = 8.5, height = 6)
```

### Python 示例

```python
from easyplot_py import *

# 分组柱状图
fig, ax = plt.subplots(figsize=(8.5, 6))
plot_group_bar_with_points(ax, df, 'group', 'cmj_height', 
                          colors=OKABE_ITO, y_label='CMJ Height (cm)')

# Bland-Altman
fig, ax = plt.subplots(figsize=(6, 5))
plot_bland_altman(ax, df['app_velocity'], df['ga_velocity'],
                  labels=("App", "GymAware"))

# 导出
export_figure(fig, 'figure1')
```

## 配色方案

### 离散/分类变量

| ID | 名称 | 色盲友好 | 适用场景 |
|---|---|---|---|
| `okabe_ito` | Okabe-Ito | ✅ | 默认离散首选 |
| `paul_tol_12` | Paul Tol 12色 | ✅ | ≤12组 |
| `ggsci_lancet` | Lancet | ❌ | 医学期刊 |

### 连续变量

| ID | 名称 | 色盲友好 | 适用场景 |
|---|---|---|---|
| `viridis` | Viridis | ✅ | 热图/连续 |
| `cividis` | Cividis | ✅ | 色盲友好连续 |

### 中国风

| ID | 名称 | 风格 |
|---|---|---|
| `china_danqing` | 丹青 | 青绿山水 |
| `china_xiang` | 湘绣 | 湖湘绚烂 |
| `china_tian` | 天青 | 雨过天青 |

## 图表选择指南

| 数据结构 | 推荐图表 |
|---|---|
| 2组比较，有原始值 | 点图 + 误差线 |
| 多组比较 | 柱状图 + 散点叠加 |
| 重复测量/时间序列 | 线图 + 置信区间 |
| 一致性检验 | Bland-Altman 图 |
| 效应量展示 | 森林图 |
| 相关矩阵 | 热图 |

## 导出规范

```r
# R: 出版质量设置
# 宽度: 单栏 8.5cm / 双栏 17cm
# DPI: 300 (PNG) / 矢量 (PDF)
ggsave("fig.png", width = 8.5, height = 6, units = "cm", dpi = 300)
```

```python
# Python: 出版质量设置
export_figure(fig, 'fig', width_cm=8.5, height_cm=6, dpi=300)
```

## 配色选择建议

1. **无特别要求**：使用 `okabe_ito`（色盲友好，灰度可分）
2. **连续数据**：使用 `viridis`（感知均匀）
3. **中国期刊**：考虑 `china_danqing` 或 `china_xiang`
4. **医学期刊**：使用 `ggsci_jama` 或 `ggsci_lancet`

## 参考文档

- `references/palette-library.md` - 完整配色方案
- `references/style-guide.md` - 图表样式规范
- `references/analysis-workflow.md` - 数据分析工作流
- `scripts/easyplot_templates.R` - R 模板脚本
- `scripts/easyplot_py.py` - Python 模板脚本

## License

MIT
