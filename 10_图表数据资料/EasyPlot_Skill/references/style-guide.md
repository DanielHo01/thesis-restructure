# 科研图表 Style Guide

## 核心原则

1. **克制的装饰** — 图表的主要功能是传达数据，不是展示技术
2. **保留原始数据** — 除非有充分理由，始终显示散点/原始值
3. **尺寸优先** — 以最终发表尺寸为准设计，而非屏幕尺寸
4. **一致的语言** — 图表的视觉语言（颜色、符号、线条）在整个项目中保持一致

## 图表类型选择

### 连续变量比较（组间）

| 数据结构 | 推荐图表 | 何时避免 |
|---|---|---|
| 2组，有原始值 | 点图 + 误差线 | 样本量>30时散点过多 |
| 多组，有原始值 | 柱状图 + 散点叠加 | 组间差异不大时柱高相似 |
| 重复测量/时间序列 | 线图 + 置信区间 | 时间点>8时过于拥挤 |
| 分布比较 | 小提琴图/箱线图 | 需要展示原始值时 |

### 分类/计数数据

| 数据类型 | 推荐图表 |
|---|---|
| 频率/计数 | 柱状图 |
| 比例 | 堆叠柱/饼图（谨慎） |
| 多维分类 | 马赛克图/热图 |

### 关系/相关

| 变量类型 | 推荐图表 |
|---|---|
| 连续 vs 连续 | 散点图 + 回归线 |
| 分类 vs 连续 | 分组散点/点图 |
| 多变量 | 热图/相关矩阵 |

## 颜色使用规范

### 离散变量颜色规则

1. **色盲友好优先** — 使用 Okabe-Ito 或 Paul Tol 系列
2. **区分度最大化** — 第一组用最鲜明的颜色，后续组逐渐降低饱和度
3. **语义一致性** — 同一颜色在整个项目中代表同一组
4. **灰度可分** — 打印成灰度后仍能区分

### 连续变量颜色规则

1. **感知均匀** — 使用 Viridis 等感知均匀色带
2. **避免彩虹** — 除极特殊情况外不推荐 rainbow 色带
3. **明确范围** — 图例清晰标注数值范围和单位

## 字体规范

### R (ggplot2)

```r
# 推荐字体组合
theme_set(theme_bw(base_size = 10, base_family = "Arial"))

# 中文支持
library(showtext)
font_add("SimHei", regular = "C:/Windows/Fonts/simhei.ttf")
font_add("SimSun", regular = "C:/Windows/Fonts/simsun.ttc")
showtext_auto()

# 常用排版
theme(
  axis.title = element_text(size = 10, face = "bold"),
  axis.text = element_text(size = 9),
  legend.title = element_text(size = 9, face = "bold"),
  legend.text = element_text(size = 8),
  panel.grid.major = element_line(color = "gray90"),
  panel.grid.minor = element_blank()
)
```

### Python (matplotlib/seaborn)

```python
import matplotlib.pyplot as plt
import matplotlib
matplotlib.rcParams['font.family'] = ['DejaVu Sans', 'Arial']
matplotlib.rcParams['font.size'] = 10

# 中文支持
plt.rcParams['font.sans-serif'] = ['SimHei', 'Microsoft YaHei']
plt.rcParams['axes.unicode_minus'] = False

# seaborn 风格
sns.set_style("whitegrid")
sns.set_context("paper", font_scale=0.9)
```

## 尺寸和导出规范

### 期刊常见尺寸

| 期刊类型 | 单栏宽度 | 双栏宽度 | 高度上限 |
|---|---|---|---|
| 通用科学 | 8-9 cm | 17-18 cm | 20-23 cm |
| Nature 系列 | 8.5 cm | 17.5 cm | 22 cm |
| 医学/生物 | 7-9 cm | 14-16 cm | 20 cm |

### 导出设置

```r
# R: 出版质量导出
ggsave(
  "figure1.png",
  width = 8.5,
  height = 6,
  units = "cm",
  dpi = 300,
  bg = "white"
)

ggsave(
  "figure1.pdf",
  width = 8.5,
  height = 6,
  units = "cm",
  device = cairo_pdf  # 矢量格式，支持中文
)
```

```python
# Python: 出版质量导出
fig, ax = plt.subplots(figsize=(8.5, 6), dpi=300)

# 矢量格式
fig.savefig('figure1.pdf', bbox_inches='tight', pad_inches=0.05)
fig.savefig('figure1.png', dpi=300, bbox_inches='tight')
```

## Panel 排列规范

### 多面板组合原则

1. **对齐优先** — 所有面板的 Y 轴在同一水平线上
2. **共用比例** — 相似的度量使用相同的轴范围
3. **标签共享** — 同一行的面板可以共用 Y 轴标签
4. **间隔一致** — panel 之间的间隔保持统一

### 常见布局

| 布局 | 适用场景 | 示例 |
|---|---|---|
| 1×2 水平 | 2个对比图 | Before/After |
| 2×1 垂直 | 时间序列两个阶段 | Pre/Post |
| 2×2 网格 | 4个独立对比 | 四组×两指标 |
| 3×1 行 | 三个阶段 | 时间序列 |

### 标签规范

```r
# R: 面板标签（大写字母）
labs(
  title = "",
  subtitle = "",
  caption = ""
)

# 在图内左上角标注 A, B, C, D
geom_text(aes(x =, y =, label = "A"), ...)
```

## 误差线规范

### 显示什么

| 误差类型 | 含义 | 适用场景 |
|---|---|---|
| SD | 标准差 | 描述性统计，展示变异 |
| SEM | 标准误 | 估计精确度 |
| 95% CI | 置信区间 | 推断性统计 |
| MD ± SD | 均值±标准差 | 组间比较 |

### 显示规则

1. **明确标注** — 图例或注释说明误差线类型
2. **避免重叠** — 误差线与散点不遮挡
3. **零基线** — 柱状图从零开始
4. **差异明显** — 两组误差线不重叠时优先SEM

## 图例规范

1. **精简** — 只显示必要的图例项
2. **位置** — 放在不遮挡数据的区域
3. **方向** — 横向图例适合≤5项，竖向适合>5项
4. **标题** — 图例标题说明分组变量名称

## 常见错误

### 配色问题

- ❌ 使用彩虹色带（彩虹偏见）
- ❌ 颜色区分度不足（红绿区分）
- ❌ 深色背景配浅色文字
- ✅ Okabe-Ito / Viridis / Paul Tol

### 字体问题

- ❌ 字号太小（<8pt at 最终尺寸）
- ❌ 多种字体混用
- ❌ 斜体物种名未斜体
- ✅ 统一字体体系，中英文分开

### 图表选择问题

- ❌ 饼图用于比较（用人眼不敏感的弧度比较）
- ❌ 3D 柱状图（扭曲数量感知）
- ❌ 双Y轴（容易误导）
- ✅ 简单直接的图表

### 导出问题

- ❌ 96 DPI 屏幕截图
- ❌ 丢失矢量格式
- ❌ 导出后手动编辑（重新打开AI篡改数据）
- ✅ 300+ DPI 栅格或矢量导出
