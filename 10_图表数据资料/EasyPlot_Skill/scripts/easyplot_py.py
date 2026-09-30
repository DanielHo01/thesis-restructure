"""
EasyPlot Python Backend
科研图表Python模板（matplotlib/seaborn）

使用方法:
  from easyplot_py import *

配色方案:
  - OKABE_ITO: 色盲友好离散首选
  - PAUL_TOL: Paul Tol 12色
  - VIRIDIS: 连续/热图
  - CHINA_DANQING: 中国风丹青

示例:
  import matplotlib.pyplot as plt
  from easyplot_py import plot_group_bar_with_points, OKABE_ITO
  
  fig, ax = plt.subplots(figsize=(8.5, 6))
  plot_group_bar_with_points(ax, df, 'group', 'outcome', colors=OKABE_ITO)
  export_figure(fig, 'figure1')
"""

import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
import matplotlib
import seaborn as sns
from scipy import stats
from typing import Optional, List, Tuple, Union

# ==================== 配色方案 ====================

# Okabe-Ito 色盲友好配色
OKABE_ITO = [
    "#E69F00", "#56B4E9", "#009E73", "#F0E442",
    "#0072B2", "#CC79A7", "#D55E00", "#999999"
]

# Paul Tol 12色
PAUL_TOL_12 = [
    "#4477AA", "#EE6677", "#228833", "#CCBB44", "#66CCEE", "#AA3377",
    "#BBBBBB", "#332288", "#DDCC77", "#999933", "#882255", "#5B5B5B"
]

# Viridis 连续色带
VIRIDIS = ["#440154", "#414487", "#2A788E", "#22A884", "#7AD151", "#FDE725"]

# 中国风-丹青
CHINA_DANQING = [
    "#2C7BB6", "#5E4FA2", "#3288BD", "#66C2A5", "#ABD9A9", "#E6F598",
    "#FEE08B", "#FDAE61", "#F46D43", "#D53E4F", "#9E0142"
]

# ==================== 基础设置 ====================

# 设置中文字体
def set_chinese_font():
    """设置中文字体支持"""
    plt.rcParams['font.sans-serif'] = ['SimHei', 'Microsoft YaHei', 'Arial']
    plt.rcParams['axes.unicode_minus'] = False

# 设置科研图表默认样式
def set_scientific_style():
    """设置科研图表默认样式"""
    plt.rcParams.update({
        'font.family': 'sans-serif',
        'font.size': 10,
        'axes.titlesize': 11,
        'axes.labelsize': 9,
        'xtick.labelsize': 8,
        'ytick.labelsize': 8,
        'legend.fontsize': 8,
        'figure.titlesize': 12,
        'axes.linewidth': 0.5,
        'axes.spines.top': False,
        'axes.spines.right': False,
        'axes.grid': True,
        'grid.alpha': 0.3,
        'grid.linewidth': 0.3,
    })

# 初始化样式
set_scientific_style()


# ==================== 图表模板 ====================

def plot_group_bar_with_points(
    ax: plt.Axes,
    data: pd.DataFrame,
    x_var: str,
    y_var: str,
    group_var: Optional[str] = None,
    colors: List[str] = OKABE_ITO,
    y_label: Optional[str] = None,
    add_points: bool = True,
    errorbar_type: str = 'sd'  # 'sd' or 'se'
) -> plt.Axes:
    """
    分组柱状图 + 原始散点
    
    参数:
        ax: matplotlib Axes对象
        data: 数据框
        x_var: X轴分组变量
        y_var: Y轴结局变量
        group_var: 颜色分组变量（可选）
        colors: 颜色列表
        y_label: Y轴标签
        add_points: 是否叠加原始散点
        errorbar_type: 误差线类型 ('sd' 或 'se')
    """
    if group_var is None:
        # 无分组：单组柱状图
        means = data[y_var].mean()
        sds = data[y_var].std()
        
        bars = ax.bar([0], [means], color=colors[0], edgecolor='black', 
                      linewidth=0.5, width=0.6, alpha=0.8)
        
        # 误差线
        ax.errorbar([0], [means], yerr=[[sds], [sds]], 
                    fmt='none', color='black', capsize=4, linewidth=0.8)
        
        if add_points:
            x_jitter = np.random.normal(0, 0.08, size=len(data))
            ax.scatter(x_jitter, data[y_var], s=20, alpha=0.5, 
                      color='black', zorder=5)
        
        ax.set_xticks([0])
        ax.set_xticklabels([x_var])
        
    else:
        # 有分组：分组柱状图
        groups = data[x_var].unique()
        subgroups = data[group_var].unique() if group_var else [None]
        n_groups = len(groups)
        n_subgroups = len(subgroups)
        
        bar_width = 0.6 / n_subgroups
        positions = np.arange(n_groups)
        
        for i, subgroup in enumerate(subgroups):
            subgroup_data = data[data[group_var] == subgroup] if subgroup else data
            group_means = subgroup_data.groupby(x_var)[y_var].mean()
            group_sds = subgroup_data.groupby(x_var)[y_var].std()
            
            offset = (i - n_subgroups/2 + 0.5) * bar_width
            
            bars = ax.bar(positions + offset, group_means, bar_width,
                         color=colors[i % len(colors)], edgecolor='black',
                         linewidth=0.5, alpha=0.8, label=subgroup)
            
            # 误差线
            ax.errorbar(positions + offset, group_means,
                        yerr=group_sds, fmt='none', color='black',
                        capsize=3, linewidth=0.8)
            
            if add_points:
                for j, group in enumerate(groups):
                    points = subgroup_data[subgroup_data[x_var] == group][y_var]
                    x_pos = positions[j] + offset + np.random.normal(0, bar_width/4, size=len(points))
                    ax.scatter(x_pos, points, s=15, alpha=0.4, color='black', zorder=5)
        
        ax.set_xticks(positions)
        ax.set_xticklabels(groups)
        ax.legend(title=group_var, loc='lower right', framealpha=0.9)
    
    ax.set_ylabel(y_label)
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    
    return ax


def plot_bland_altman(
    ax: plt.Axes,
    method1: np.ndarray,
    method2: np.ndarray,
    labels: Tuple[str, str] = ("Method 1", "Method 2")
) -> plt.Axes:
    """
    Bland-Altman 一致性图
    
    参数:
        ax: matplotlib Axes对象
        method1: 方法1测量值
        method2: 方法2测量值
        labels: 方法名称元组
    """
    mean_vals = (method1 + method2) / 2
    diff_vals = method1 - method2
    
    mean_diff = np.mean(diff_vals)
    sd_diff = np.std(diff_vals, ddof=1)
    loa_upper = mean_diff + 1.96 * sd_diff
    loa_lower = mean_diff - 1.96 * sd_diff
    
    # 散点图
    ax.scatter(mean_vals, diff_vals, s=30, alpha=0.6, edgecolors='black', linewidth=0.3)
    
    # 参考线
    ax.axhline(y=mean_diff, color='blue', linestyle='-', linewidth=1, label=f'Mean: {mean_diff:.2f}')
    ax.axhline(y=loa_upper, color='red', linestyle='--', linewidth=0.8, label=f'+1.96 SD: {loa_upper:.2f}')
    ax.axhline(y=loa_lower, color='red', linestyle='--', linewidth=0.8, label=f'-1.96 SD: {loa_lower:.2f}')
    ax.axhline(y=0, color='gray', linestyle=':', linewidth=0.5)
    
    ax.set_xlabel(f'Mean of {labels[0]} & {labels[1]}')
    ax.set_ylabel(f'{labels[0]} - {labels[1]}')
    ax.legend(loc='upper right', fontsize=8)
    ax.spines['top'].set_visible(False)
    
    return ax


def plot_forest(
    estimates: np.ndarray,
    ci_lowers: np.ndarray,
    ci_uppers: np.ndarray,
    labels: List[str],
    ref_line: float = 0,
    figsize: Tuple[float, float] = (8, 6)
) -> plt.Figure:
    """
    森林图
    
    参数:
        estimates: 效应量点估计
        ci_lowers: 置信区间下限
        ci_uppers: 置信区间上限
        labels: 变量名称列表
        ref_line: 参考线位置
        figsize: 图形尺寸
    """
    fig, ax = plt.subplots(figsize=figsize)
    
    y_positions = np.arange(len(estimates))
    
    # 参考线
    ax.axvline(x=ref_line, color='gray50', linestyle='--', linewidth=0.8)
    
    # 误差线和点
    for i, (est, low, high) in enumerate(zip(estimates, ci_lowers, ci_uppers)):
        ax.plot([low, high], [i, i], color='black', linewidth=1)
        ax.plot(est, i, 's', color='black', markersize=6)
        ax.text(est, i + 0.2, f'{est:.2f}', ha='left', va='bottom', fontsize=8)
    
    ax.set_yticks(y_positions)
    ax.set_yticklabels(labels)
    ax.set_xlabel('Effect Size (95% CI)')
    ax.set_ylim(-0.5, len(estimates))
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    
    return fig


def plot_line_with_ci(
    ax: plt.Axes,
    data: pd.DataFrame,
    x_var: str,
    y_var: str,
    group_var: Optional[str] = None,
    colors: List[str] = OKABE_ITO,
    y_label: Optional[str] = None
) -> plt.Axes:
    """
    趋势线图（带置信区间）
    
    参数:
        ax: matplotlib Axes对象
        data: 数据框
        x_var: X轴变量
        y_var: Y轴变量
        group_var: 分组变量
        colors: 颜色列表
        y_label: Y轴标签
    """
    if group_var:
        for i, group in enumerate(data[group_var].unique()):
            group_data = data[data[group_var] == group]
            grouped = group_data.groupby(x_var)[y_var].agg(['mean', 'std', 'count'])
            grouped['se'] = grouped['std'] / np.sqrt(grouped['count'])
            
            x = grouped.index
            mean = grouped['mean']
            se = grouped['se']
            
            # 线
            ax.plot(x, mean, 'o-', color=colors[i % len(colors)], 
                   linewidth=1.5, label=group, markersize=5)
            
            # 置信区间
            ax.fill_between(x, mean - 1.96*se, mean + 1.96*se, 
                          color=colors[i % len(colors)], alpha=0.2)
    else:
        grouped = data.groupby(x_var)[y_var].agg(['mean', 'std', 'count'])
        grouped['se'] = grouped['std'] / np.sqrt(grouped['count'])
        
        x = grouped.index
        mean = grouped['mean']
        se = grouped['se']
        
        ax.plot(x, mean, 'o-', color=colors[0], linewidth=1.5, markersize=5)
        ax.fill_between(x, mean - 1.96*se, mean + 1.96*se, 
                       color=colors[0], alpha=0.2)
    
    ax.set_ylabel(y_label)
    ax.set_xlabel(x_var)
    if group_var:
        ax.legend(title=group_var, loc='best')
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    
    return ax


def plot_correlation_matrix(
    data: pd.DataFrame,
    method: str = 'pearson',
    figsize: Tuple[float, float] = (10, 8),
    cmap: str = 'RdBu_r'
) -> plt.Figure:
    """
    相关矩阵热图
    
    参数:
        data: 数据框（仅数值列）
        method: 相关系数方法 ('pearson', 'spearman', 'kendall')
        figsize: 图形尺寸
        cmap: 色带
    """
    corr = data.corr(method=method)
    
    fig, ax = plt.subplots(figsize=figsize)
    
    im = ax.imshow(corr, cmap=cmap, vmin=-1, vmax=1)
    
    # 刻度和标签
    ax.set_xticks(np.arange(len(corr.columns)))
    ax.set_yticks(np.arange(len(corr.index)))
    ax.set_xticklabels(corr.columns, rotation=45, ha='right')
    ax.set_yticklabels(corr.index)
    
    # 数值标注
    for i in range(len(corr.index)):
        for j in range(len(corr.columns)):
            text = ax.text(j, i, f'{corr.iloc[i, j]:.2f}',
                         ha='center', va='center', fontsize=8)
    
    # 色条
    plt.colorbar(im, ax=ax, label=f'{method.capitalize()} r')
    
    return fig


# ==================== 导出工具 ====================

def export_figure(
    fig: plt.Figure,
    filename: str,
    width_cm: float = 8.5,
    height_cm: float = 6,
    dpi: int = 300
) -> None:
    """
    出版质量导出
    
    参数:
        fig: matplotlib Figure对象
        filename: 文件名（不含扩展名）
        width_cm: 宽度（cm）
        height_cm: 高度（cm）
        dpi: PNG分辨率
    """
    # 转换为英寸（厘米/2.54）
    width_in = width_cm / 2.54
    height_in = height_cm / 2.54
    
    fig.set_size_inches(width_in, height_in)
    fig.tight_layout()
    
    # PNG
    fig.savefig(f'{filename}.png', dpi=dpi, bbox_inches='tight', 
                facecolor='white', edgecolor='none')
    
    # PDF（矢量格式）
    fig.savefig(f'{filename}.pdf', bbox_inches='tight', 
                facecolor='white', edgecolor='none')
    
    plt.close(fig)
    print(f"导出完成: {filename}.(png/pdf)")


def get_export_checklist() -> str:
    """返回导出检查清单"""
    checklist = """
    □ 图幅尺寸：确认最终发表尺寸下的可读性
    □ 分辨率：PNG ≥ 300 DPI
    □ 字体：中文和英文均正确显示
    □ 配色：检查色盲友好和灰度打印效果
    □ 图例：位置合适，标签清晰
    □ 标题/注释：图内或图外标注正确
    □ 数据点：原始数据未遭篡改
    □ 比例尺：轴范围和刻度合理
    """
    return checklist


# ==================== 版本信息 ====================

__version__ = '1.0.0'
__author__ = 'EasyPlot'
