"""
图4.4 探索性结局森林图
使用 EasyPlot 原则绘制

设计原则：
- 克制的装饰（white canvas, no shadows）
- Okabe-Ito 色盲友好配色
- 期刊标准尺寸（单栏 8.5cm）
- 标准字体（Arial + SimHei）
- 300 DPI PNG + 矢量 PDF 导出
"""

import numpy as np
import matplotlib.pyplot as plt
import matplotlib
import matplotlib.patches as mpatches
from matplotlib.lines import Line2D

# ==================== EasyPlot 全局设置 ====================
# 字体设置（中文字体优先）
import matplotlib.font_manager as fm

# 查找可用的中文字体
chinese_fonts = []
for f in fm.fontManager.ttflist:
    if f.name in ['SimHei', 'Microsoft YaHei', 'SimSun', 'FangSong', 'Source Han Sans CN', 'Noto Sans CJK SC']:
        chinese_fonts.append(f.name)

# 中文字体优先，英文后备
if chinese_fonts:
    font_list = list(dict.fromkeys(chinese_fonts)) + ['Arial', 'DejaVu Sans']
else:
    font_list = ['Arial', 'DejaVu Sans']

plt.rcParams['font.family'] = 'sans-serif'
plt.rcParams['font.sans-serif'] = font_list
plt.rcParams['font.size'] = 9
plt.rcParams['axes.unicode_minus'] = False

# 科研标准样式
plt.rcParams['axes.linewidth'] = 0.6
plt.rcParams['axes.spines.top'] = False
plt.rcParams['axes.spines.right'] = False
plt.rcParams['axes.grid'] = True
plt.rcParams['grid.alpha'] = 0.15
plt.rcParams['grid.linewidth'] = 0.4
plt.rcParams['axes.labelpad'] = 4

# ==================== 配色方案（Okabe-Ito 色盲友好）====================
OKABE_BLUE = "#56B4E9"      # 主要蓝色
OKABE_ORANGE = "#E69F00"    # 主要橙色
OKABE_GREEN = "#009E73"      # 显著性高亮绿
OKABE_GRAY = "#999999"       # 非显著灰

# 显著性行背景色
SIG_BG = "#E8F5E9"          # 浅绿背景（显著行）

# ==================== 数据 ====================
outcomes = [
    "训练自我效能量表\n(T1-T0)",
    "摆臂反向纵跳 CMJ (cm)",
    "深蹲 1RM (kg)",
    "Hooper 恢复指数",
    "肌肉酸痛 VAS (cm)"
]

# Hedges' g 点估计
g_values = [2.70, 0.85, 0.46, 0.72, 0.43]

# 95% CI
ci_lower = [-0.11, 0.14, -0.11, 0.02, -0.17]
ci_upper = [3.75, 1.57, 1.25, 1.42, 1.04]

# FDR校正 p值
fdr_p = [0.001, 0.099, 0.303, 0.146, 0.403]

# 显著性标记
is_significant = [True, False, False, False, False]  # 仅训练自我效能量表显著

# ==================== 绘图 ====================
# 期刊单栏尺寸：8.5cm 宽，比例约 4:3
fig_width_cm = 12.0   # 略宽以便容纳右侧数字
fig_height_cm = 9.0
fig, ax = plt.subplots(figsize=(fig_width_cm / 2.54, fig_height_cm / 2.54))

# Y轴位置
y_positions = np.arange(len(outcomes))[::-1]  # 自下而上: 0,1,2,3,4 → 显示为 4,3,2,1,0

# 绘制参考线 (g = 0)
ax.axvline(x=0, color='#666666', linestyle='--', linewidth=0.8, zorder=1)

# 绘制每行的置信区间和点估计
for i, (y, g, lo, hi, fdr, sig) in enumerate(zip(
    y_positions, g_values, ci_lower, ci_upper, fdr_p, is_significant
)):
    # 显著性行背景
    if sig:
        ax.axhspan(y - 0.45, y + 0.45, color=SIG_BG, zorder=0, alpha=0.8)

    # 置信区间横线
    color = OKABE_GREEN if sig else '#555555'
    ax.plot([lo, hi], [y, y], color=color, linewidth=1.8, zorder=3)

    # 端点（小竖线）
    ax.plot([lo, lo], [y - 0.12, y + 0.12], color=color, linewidth=1.2, zorder=3)
    ax.plot([hi, hi], [y - 0.12, y + 0.12], color=color, linewidth=1.2, zorder=3)

    # 点估计（实心菱形）
    marker = 'D'  # 菱形
    ms = 8 if sig else 7
    ax.plot(g, y, marker=marker, color=color, markersize=ms,
            markeredgecolor='white', markeredgewidth=0.5, zorder=4)

    # 右侧显著性标记
    if sig:
        marker_text = '***'  # 三星标记（表示 p < 0.001）
        ax.text(hi + 0.18, y, marker_text, fontsize=10, color=OKABE_GREEN,
                va='center', ha='left', fontweight='bold')

# Y轴标签（指标名称）
ax.set_yticks(y_positions)
ax.set_yticklabels(outcomes, fontsize=9.5, ha='right')

# X轴
ax.set_xlabel("Hedges' g  (AI 辅助组 vs 规范化自我指导组)",
               fontsize=9.5, labelpad=6)
ax.set_xlim(-0.9, 5.2)

# 刻度
ax.xaxis.set_major_locator(plt.MultipleLocator(1.0))
ax.tick_params(axis='x', labelsize=8.5, length=2)
ax.tick_params(axis='y', labelsize=9, length=2, labelrotation=0)

# 右侧标注区：添加 Hedges' g 和 FDR p 值
right_x = 4.2  # 右侧标注起始位置
ax.text(right_x - 0.5, 4.8, "Hedges' g  [95% CI]", fontsize=7.5,
        ha='right', va='bottom', color='#444444', style='italic')
ax.text(right_x - 0.5, 4.55, "FDR 校正 p", fontsize=7.5,
        ha='right', va='bottom', color='#444444', style='italic')

for i, (g, lo, hi, fdr, sig) in enumerate(zip(g_values, ci_lower, ci_upper, fdr_p, is_significant)):
    y = y_positions[i]
    color = OKABE_GREEN if sig else '#555555'

    # g [95% CI]
    if fdr < 0.001:
        g_text = f"< 0.001"
    else:
        g_text = f"{fdr:.3f}"

    ax.text(right_x, y, f"{g:.2f}  [{lo:.2f}, {hi:.2f}]", fontsize=8,
            va='center', ha='left', color=color)
    ax.text(right_x, y - 0.35, g_text, fontsize=8,
            va='center', ha='left', color=color)

# 顶部横线（分隔表头）
ax.plot([0.5, right_x + 2.0], [5.05, 5.05], color='#888888', linewidth=0.6)

# 左上角说明
ax.text(0.02, 0.98, "Positive g = AI group better",
        transform=ax.transAxes, fontsize=7.5,
        va='top', ha='left', color='#333333',
        bbox=dict(boxstyle='round,pad=0.3', facecolor='#FFF8E1',
                  edgecolor='#FFB300', linewidth=0.5, alpha=0.9))

# 右下角图例
legend_elements = [
    mpatches.Patch(facecolor=OKABE_GREEN, edgecolor='white', linewidth=0.5,
                   label='*** FDR p < 0.001 显著'),
    mpatches.Patch(facecolor='#555555', edgecolor='white', linewidth=0.5,
                   label='非显著 (FDR p >= 0.05)'),
    Line2D([0], [0], marker='D', color='w', markerfacecolor='#555555',
           markersize=7, label='Hedges g 点估计'),
]
ax.legend(handles=legend_elements, loc='lower right', fontsize=7.5,
          framealpha=0.95, edgecolor='#CCCCCC', fancybox=False,
          handlelength=1.2, handleheight=0.8)

# 标题（可选，取消注释启用）
# ax.set_title("图4.4 探索性结局森林图", fontsize=11, pad=10, fontweight='bold')

plt.tight_layout(pad=0.8)

# ==================== 导出（EasyPlot 标准）====================
output_dir = "D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预/04_图表输出/正式图"
filename_base = f"{output_dir}/图4.4_探索性结局森林图"

# PNG 300 DPI
fig.savefig(f"{filename_base}.png", dpi=300, bbox_inches='tight',
           facecolor='white', edgecolor='none')
print(f"✓ PNG 导出完成: {filename_base}.png")

# PDF 矢量格式
fig.savefig(f"{filename_base}.pdf", bbox_inches='tight',
           facecolor='white', edgecolor='none')
print(f"✓ PDF 导出完成: {filename_base}.pdf")

plt.close()
print("\n导出检查清单:")
print("□ 图幅尺寸: 12×9 cm (单栏略宽)")
print("□ 分辨率: PNG 300 DPI")
print("□ 配色: Okabe-Ito 色盲友好")
print("□ 字体: Arial + SimHei")
print("□ 显著性: ★ 标记 + 浅绿背景高亮")
print("□ 图例: 右下角，离散度线被纯白背景覆盖")
