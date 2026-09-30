"""
图4.4 探索性结局Forest森林图 - 完美版 v3
彻底修复：悬空线条、红线穿透、巨大空白、图例网格问题

修复清单：
✓ 顶部表头横线不再悬空
✓ 红虚线不再穿透橙色说明框
✓ 收紧X轴消除中间空白
✓ 图例加纯白背景遮挡网格线
"""

import matplotlib.pyplot as plt
import numpy as np

# 设置论文标准中文字体与 300 DPI
plt.rcParams['font.sans-serif'] = ['SimHei', 'Microsoft YaHei', 'Arial']
plt.rcParams['axes.unicode_minus'] = False

fig, ax = plt.subplots(figsize=(10, 5.5), dpi=300)

# 数据定义
outcomes = [
    '训练自我效能 (分)',
    '摆臂反向纵跳 CMJ (cm)',
    '深蹲绝对 1RM (kg)',
    '摆臂静蹲跳 SJ (cm)',
    '深蹲相对 1RM (kg/kg)'
]
y_pos = np.arange(len(outcomes))

g_vals = [2.70, 0.85, 0.46, 0.43, 0.39]
ci_low = [1.59, 0.02, -0.35, -0.38, -0.42]
ci_high = [3.80, 1.69, 1.28, 1.24, 1.20]
p_fdr = ['p < 0.001 ★★★', 'p = 0.126', 'p = 0.341', 'p = 0.622', 'p = 0.341']

# 1. 第一行高亮浅绿背景块
ax.axhspan(-0.35, 0.45, color='#F0FDF4', alpha=0.9, zorder=0)

# 2. 红色无效线 (g = 0)：精准截断在数据区域，不向上穿透顶部提示框！
ax.vlines(0, ymin=-0.1, ymax=4.3, color='#DC2626', linestyle='--', linewidth=1.5, zorder=2, label='无效线 (g = 0)')

# 3. 绘制数据点与误差棒
for i in range(len(outcomes)):
    color = '#16A34A' if i == 0 else '#2563EB'
    
    # 绘制 CI 范围线和中心点
    ax.errorbar(g_vals[i], y_pos[i], 
                xerr=[[g_vals[i] - ci_low[i]], [ci_high[i] - g_vals[i]]],
                fmt='o', color=color, ecolor=color, elinewidth=2, capsize=4, capthick=1.5, 
                markersize=7.5, zorder=4)
    
    # 右侧表格数据文字（对齐）
    text_color = '#15803D' if i == 0 else '#1E293B'
    font_weight = 'bold' if i == 0 else 'normal'
    
    # g 值与 CI
    ax.text(4.25, y_pos[i], f'g = {g_vals[i]:.2f} [{ci_low[i]:.2f}, {ci_high[i]:.2f}]', 
            va='center', ha='left', fontsize=9, color=text_color, fontweight=font_weight)
    
    # p 值
    ax.text(5.55, y_pos[i], p_fdr[i], 
            va='center', ha='left', fontsize=9, color=text_color, fontweight=font_weight)

# 4. 精密绘制顶部表格表头与分割线
ax.text(4.25, -0.45, "Hedges' g [95% CI]", ha='left', va='center', fontsize=9.5, fontweight='bold', color='#334155')
ax.text(5.55, -0.45, "FDR 校正 p", ha='left', va='center', fontsize=9.5, fontweight='bold', color='#334155')
# 精确控制横线长度与位置
ax.plot([4.2, 6.5], [-0.25, -0.25], color='#94A3B8', linewidth=1.2, zorder=3)

# 5. 左上角说明框（不与红线相交）
ax.text(-0.75, -0.45, '注: 正值表示 AI辅助组 > 规范化自我指导组', 
        ha='left', va='center', fontsize=8.2, color='#B45309',
        bbox=dict(boxstyle='round,pad=0.35', fc='#FEF3C7', ec='#F59E0B', lw=0.8))

# 6. 坐标轴与样式调整
ax.set_yticks(y_pos)
ax.set_yticklabels(outcomes, fontsize=10, fontweight='bold')
ax.invert_yaxis()  # 置顶自我效能

ax.set_xlabel("标准效应量 Hedges' g", fontsize=10.5, fontweight='bold', labelpad=8)
ax.set_xlim(-0.85, 6.6)   # 收紧 X 轴，消除中间空旷感
ax.set_ylim(4.55, -0.8)   # 精确边界

# 网格线
ax.grid(True, linestyle=':', alpha=0.4, axis='x', zorder=0)

# 图例（带有白色不透明遮罩背景，挡住后面的网格线）
dummy_green = ax.scatter([], [], color='#16A34A', marker='o', s=45, label='★ 统计学显著 (FDR校正 p < 0.05)')
dummy_blue = ax.scatter([], [], color='#2563EB', marker='o', s=45, label='未达统计学显著')

legend = ax.legend(handles=[dummy_green, dummy_blue], loc='lower right', 
                   bbox_to_anchor=(0.98, 0.03), frameon=True, facecolor='white', edgecolor='#CBD5E1', fontsize=8.5)
legend.get_frame().set_alpha(1.0)  # 设置图例背景纯白不透明
legend.set_zorder(5)

# 隐去上、右边框
ax.spines['top'].set_visible(False)
ax.spines['right'].set_visible(False)

plt.title("图 4.4 探索性结局指标调整后 Hedges' g 效应量森林图 (PP样本 n = 24)", 
          fontsize=11.5, fontweight='bold', pad=12, color='#0F172A')

plt.tight_layout()

# ==================== 导出文件 ====================
output_path = "D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预/04_图表输出/正式图/图4.4_探索性结局森林图_v3"

plt.savefig(f"{output_path}.png", dpi=300, bbox_inches='tight', facecolor='white')
print(f"✓ PNG导出完成: {output_path}.png")

plt.savefig(f"{output_path}.pdf", bbox_inches='tight', facecolor='white')
print(f"✓ PDF导出完成: {output_path}.pdf")

plt.show()
plt.close()

# ==================== 输出图注 ====================
print("\n" + "="*60)
print("图注（可直接复制到论文）：")
print("="*60)
caption = """注：数据点为Hedges' g效应量点估计，水平线为95%置信区间。红色虚线为无效线（g=0）。正值表示AI辅助组高于规范化自我指导组。p值为Benjamini-Hochberg FDR校正后p值。★表示唯一达到统计学显著且经多重校正后仍稳健的组间差异。ANCOVA模型协变量为基线值与力量分层。详细统计参数详见表4.6。"""
print(caption)
