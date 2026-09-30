# 科研绘图配色全集

## 内置命名色带（按语义和使用场景）

### 离散/分类变量（≤10组）

| ID | 名称 | 色盲友好 | 灰度可分 | 适合场景 |
|---|---|---|---|---|
| `okabe_ito` | Okabe-Ito | ✅ | ✅ | 默认离散首选 |
| `colorblind_hex` | ColorBrewer Set1 | ✅ | ✅ | 通用离散 |
| `paul_tol_7` | Paul Tol 7色 | ✅ | ✅ | ≤7组 |
| `paul_tol_12` | Paul Tol 12色 | ✅ | ✅ | ≤12组 |
| `ggsci_lancet` | Lancet | ❌ | ⚠️ | 医学/生物 |
| `ggsci_jama` | JAMA | ⚠️ | ✅ | 医学期刊 |
| `ggsci_nejm` | NEJM | ❌ | ⚠️ | 医学顶刊 |
| `ggsci_d3_category` | D3 | ⚠️ | ⚠️ | 通用 |
| `viridis` | Viridis | ✅ | ✅ | 连续/热图首选 |

### 连续变量

| ID | 名称 | 色盲友好 | 适用 |
|---|---|---|---|
| `viridis` | Viridis | ✅ | 通用连续 |
| `plasma` | Plasma | ✅ | 通用连续 |
| `inferno` | Inferno | ✅ | 通用连续 |
| `cividis` | Cividis | ✅ | 色盲友好连续 |

### 顺序/单向渐变

| ID | 名称 | 适用 |
|---|---|---|
| `blues` | Blues | 低→高 |
| `oranges` | Oranges | 低→高 |
| `greys` | Greys | 中性 |

### 发散/双向（偏离中心）

| ID | 名称 | 中心值 |
|---|---|---|
| `rdbu` | Red-Blue | 0 |
| `piyg` | Pink-Green | 0 |
| `prgn` | Purple-Green | 0 |

### 中国风/东方色带

| ID | 名称 | 风格描述 |
|---|---|---|
| `china_ink` | 水墨 | 淡墨浓墨 |
| `china_danqing` | 丹青 | 青绿山水 |
| `china_fenghua` | 枫华 | 霜叶红于二月花 |
| `china_xue` | 雪韵 | 冰清玉洁 |
| `china_xiang` | 湘绣 | 湖湘绚烂 |
| `china_tian` | 天青 | 雨过天青 |
| `china_cang` | 苍山 | 青山绿水 |
| `china_molv` | 墨绿 | 松柏苍翠 |

## R 中的调用方式

```r
# 安装/加载
library(ggsci)
library(viridis)
library(paletteer)

# Okabe-Ito（科研离散首选）
scale_fill_manual(values = paletteer_d("rcartocolor::Bold"))
scale_color_manual(values = paletteer_d("rcartocolor::Bold"))

# Viridis 连续
scale_fill_viridis_d(option = "D")
scale_fill_viridis_c(option = "viridis")

# ggsci 系列
scale_color_nejm()
scale_color_lancet()

# 中国风
# 需要加载 custom_palettes.R
source("scripts/custom_palettes.R")
scale_color_manual(values = china_danqing)
```

## Python 中的调用方式

```python
import matplotlib.pyplot as plt
import seaborn as sns

# Matplotlib 内置
plt.rcParams['axes.prop_cycle'] = plt.cycler(color=['#0072B2', '#E69F00', '#009E73', '#F0E442', '#CC79A7', '#D55E00', '#56B4E9', '#999999'])

# Seaborn 配色
sns.set_palette("husl")

# 色盲友好推荐：Okabe-Ito
okabe_ito = ['#E69F00', '#56B4E9', '#009E73', '#F0E442', '#0072B2', '#CC79A7', '#D55E00', '#999999']
sns.set_palette(okabe_ito)

# 中国风色带（需加载 palette_dongfang.py）
from palette_dongfang import china_danqing, china_xiang
```

## 色盲友好检查

```r
# R: 使用 colorblindcheck 包
library(colorblindcheck)
palette <- c("#E69F00", "#56B4E9", "#009E73", "#F0E442")
colorblindcheck(palette, simulate = TRUE)

# Python: 使用 checklist 库
# pip install checklist
from checklist.collaborator import check_cvd
```

## 灰度打印检查

```r
# R: 将颜色转为灰度值
gray_value <- function(hex) {
  rgb <- col2rgb(hex)
  0.299 * rgb[1] + 0.587 * rgb[2] + 0.114 * rgb[3]
}

# 打印检查
grayscale_palette <- sapply(your_palette, gray_value) / 255
print(grayscale_palette)
```

## 配色选择决策树

```
Q1: 数据类型？
├── 分类/离散 → Q2
└── 连续/数值 → Q3

Q2: 是否需要色盲友好？
├── 是 → okabe_ito / paul_tol
└── 否 → ggsci系列 / 自定义

Q3: 数据方向？
├── 单向（低→高） → viridis / blues
└── 双向（有中心） → rdbu / piyg
```

## 常见场景推荐

| 场景 | 推荐配色 |
|---|---|
| 两组对比 | `okabe_ito` 或灰度 + 形状 |
| 多组（≤7） | `paul_tol_7` |
| 多组（8-12） | `paul_tol_12` |
| 热图/矩阵 | `viridis` |
| 地图连续值 | `cividis` |
| 正负偏离 | `rdbu` |
| 中国期刊/美学 | `china_danqing` / `china_xiang` |
| 医学/生物 | `ggsci_jama` / `ggsci_lancet` |
