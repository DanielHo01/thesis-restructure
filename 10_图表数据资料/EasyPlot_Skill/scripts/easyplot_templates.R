# EasyPlot 科研图表模板
# 科研图表核心模板，包含常用图表类型的标准实现

# ==================== 依赖检查 ====================
.check_deps <- function(pkg) {
  if (!requireNamespace(pkg, quietly = TRUE)) {
    stop(paste("请安装:", pkg), call. = FALSE)
  }
}

# ==================== 配色方案 ====================

# Okabe-Ito 色盲友好配色（科研离散首选）
.okabe_ito <- c(
  "#E69F00",  # 橙
  "#56B4E9",  # 天蓝
  "#009E73",  # 青绿
  "#F0E442",  # 黄
  "#0072B2",  # 深蓝
  "#CC79A7",  # 粉
  "#D55E00",  # 橙红
  "#999999"   # 灰
)

# Paul Tol 12色
.paul_tol_12 <- c(
  "#4477AA", "#EE6677", "#228833", "#CCBB44", "#66CCEE", "#AA3377",
  "#BBBBBB", "#332288", "#DDCC77", "#999933", "#882255", "#5B5B5B"
)

# Viridis 连续色带
.viridis_colors <- c("#440154", "#414487", "#2A788E", "#22A884", "#7AD151", "#FDE725")

# 中国风-丹青
.china_danqing <- c(
  "#2C7BB6", "#5E4FA2", "#3288BD", "#66C2A5", "#ABD9A9", "#E6F598",
  "#FEE08B", "#FDAE61", "#F46D43", "#D53E4F", "#9E0142"
)

# ==================== 基础主题 ====================

#' 科研图表基础主题
#' @param base_size 基础字号
#' @param base_family 基础字体
#' @export
theme_easyplot <- function(base_size = 10, base_family = "Arial") {
  theme_bw(base_size = base_size, base_family = base_family) +
    theme(
      # 标题
      plot.title = element_text(face = "bold", size = 11, hjust = 0),
      plot.subtitle = element_text(size = 9, hjust = 0, color = "gray40"),
      plot.caption = element_text(size = 8, hjust = 1, color = "gray50"),
      plot.margin = margin(t = 5, r = 5, b = 5, l = 5),
      
      # 面板
      panel.grid.major = element_line(color = "gray90", size = 0.3),
      panel.grid.minor = element_blank(),
      panel.border = element_rect(color = "gray70", fill = NA, size = 0.5),
      
      # 轴
      axis.title = element_text(size = 9, face = "bold"),
      axis.text = element_text(size = 8),
      axis.ticks = element_line(color = "gray70", size = 0.3),
      
      # 图例
      legend.title = element_text(size = 8, face = "bold"),
      legend.text = element_text(size = 7),
      legend.margin = margin(t = 2, r = 2, b = 2, l = 2),
      legend.key.size = unit(0.5, "lines")
    )
}

# ==================== 图表模板 ====================

#' 分组柱状图 + 原始散点
#' @param data 数据框
#' @param x_var 分组变量
#' @param y_var 结局变量
#' @param group_var 颜色分组变量
#' @param colors 颜色向量
#' @param y_label Y轴标签
#' @param dodge_width 散点偏移量
#' @export
plot_group_bar_with_points <- function(
    data,
    x_var,
    y_var,
    group_var = NULL,
    colors = .okabe_ito,
    y_label = NULL,
    dodge_width = 0.8
) {
  
  .check_deps("ggplot2")
  library(ggplot2)
  
  p <- ggplot(data, aes(x = !!sym(x_var), y = !!sym(y_var)))
  
  # 如果有分组变量
  if (!is.null(group_var) && group_var %in% names(data)) {
    p <- p + aes(fill = !!sym(group_var))
    
    # 计算均值和标准差
    summary_data <- data %>%
      group_by(!!sym(x_var), !!sym(group_var)) %>%
      summarise(
        mean_val = mean(!!sym(y_var), na.rm = TRUE),
        sd_val = sd(!!sym(y_var), na.rm = TRUE),
        n = n(),
        se_val = sd_val / sqrt(n),
        .groups = "drop"
      )
    
    # 柱状图（窄柱）
    p <- p + 
      stat_summary(
        fun = "mean",
        geom = "bar",
        position = position_dodge(width = dodge_width),
        width = 0.6,
        color = "black",
        linewidth = 0.3
      ) +
      stat_summary(
        fun = "mean",
        geom = "errorbar",
        position = position_dodge(width = dodge_width),
        aes(
          ymin = after_stat(mean) - after_stat(sd),
          ymax = after_stat(mean) + afterstat(sd)
        ),
        width = 0.2,
        linewidth = 0.5
      ) +
      # 原始散点
      geom_jitter(
        position = position_jitterdodge(
          jitter.width = 0.1,
          dodge.width = dodge_width
        ),
        size = 1.2,
        alpha = 0.5
      ) +
      scale_fill_manual(values = colors)
    
  } else {
    # 无分组变量的简单柱状图
    p <- p + 
      stat_summary(
        fun = "mean",
        geom = "bar",
        fill = colors[1],
        color = "black",
        width = 0.6
      ) +
      stat_summary(
        fun = "mean",
        geom = "errorbar",
        aes(
          ymin = after_stat(mean) - afterstat(sd),
          ymax = after_stat(mean) + afterstat(sd)
        ),
        width = 0.2,
        linewidth = 0.5
      ) +
      geom_jitter(width = 0.1, size = 1.2, alpha = 0.5)
  }
  
  p <- p +
    theme_easyplot() +
    labs(y = y_label, x = NULL) +
    theme(legend.position = "bottom")
  
  return(p)
}


#' Bland-Altman 一致性图
#' @param data 数据框
#' @param method1_var 方法1变量
#' @param method2_var 方法2变量
#' @param subject_var 受试者ID变量
#' @param labels 方法1和方法2的名称
#' @export
plot_bland_altman <- function(
    data,
    method1_var,
    method2_var,
    subject_var = NULL,
    labels = c("Method 1", "Method 2")
) {
  
  .check_deps("ggplot2")
  library(ggplot2)
  
  # 计算差值和均值
  data <- data %>%
    mutate(
      mean_val = (!!sym(method1_var) + !!sym(method2_var)) / 2,
      diff_val = !!sym(method1_var) - !!sym(method2_var),
      diff_mean = mean(diff_val, na.rm = TRUE),
      diff_sd = sd(diff_val, na.rm = TRUE),
      loa_upper = diff_mean + 1.96 * diff_sd,
      loa_lower = diff_mean - 1.96 * diff_sd
    )
  
  p <- ggplot(data, aes(x = mean_val, y = diff_val)) +
    geom_point(size = 2, alpha = 0.7) +
    geom_hline(yintercept = data$diff_mean[1], linetype = "solid", color = "blue", linewidth = 0.8) +
    geom_hline(yintercept = data$loa_upper[1], linetype = "dashed", color = "red", linewidth = 0.6) +
    geom_hline(yintercept = data$loa_lower[1], linetype = "dashed", color = "red", linewidth = 0.6) +
    geom_hline(yintercept = 0, linetype = "dotted", color = "gray", linewidth = 0.4) +
    annotate(
      "text",
      x = max(data$mean_val),
      y = c(data$loa_upper[1], data$diff_mean[1], data$loa_lower[1]),
      label = c(
        paste0("+1.96 SD: ", round(data$loa_upper[1], 2)),
        paste0("Mean: ", round(data$diff_mean[1], 2)),
        paste0("-1.96 SD: ", round(data$loa_lower[1], 2))
      ),
      hjust = 1.1,
      vjust = c(-0.5, 0.5, 1.5),
      size = 3
    ) +
    theme_easyplot() +
    labs(
      x = paste("Mean of", labels[1], "&", labels[2]),
      y = paste(labels[1], "-", labels[2])
    )
  
  return(p)
}


#' 森林图
#' @param estimates 效应量点估计向量
#' @param ci_lower 置信区间下限
#' @param ci_upper 置信区间上限
#' @param labels 变量名称
#' @param ref_line 参考线（通常为0或1）
#' @export
plot_forest <- function(
    estimates,
    ci_lower,
    ci_upper,
    labels,
    ref_line = 0
) {
  
  .check_deps("ggplot2")
  library(ggplot2)
  
  n <- length(estimates)
  y_pos <- seq_len(n)
  
  df <- data.frame(
    estimate = estimates,
    ci_low = ci_lower,
    ci_high = ci_upper,
    label = labels,
    y = y_pos
  )
  
  p <- ggplot(df, aes(x = estimate, y = y)) +
    geom_vline(xintercept = ref_line, linetype = "dashed", color = "gray50", linewidth = 0.6) +
    geom_errorbarh(
      aes(xmin = ci_low, xmax = ci_high),
      height = 0.3,
      linewidth = 0.6
    ) +
    geom_point(shape = 18, size = 3) +
    geom_text(aes(x = estimate, label = sprintf("%.2f", estimate)), 
              hjust = -0.3, size = 3) +
    scale_y_continuous(
      breaks = y_pos,
      labels = labels,
      limits = c(0.5, n + 0.5)
    ) +
    theme_easyplot() +
    theme(
      panel.grid.major.y = element_blank(),
      axis.title.y = element_blank()
    ) +
    labs(x = "Effect Size (95% CI)")
  
  return(p)
}


#' 趋势线图（带置信区间）
#' @param data 数据框
#' @param x_var X轴变量
#' @param y_var Y轴变量
#' @param group_var 分组变量
#' @param colors 颜色向量
#' @param y_label Y轴标签
#' @export
plot_line_with_ci <- function(
    data,
    x_var,
    y_var,
    group_var = NULL,
    colors = .okabe_ito,
    y_label = NULL
) {
  
  .check_deps("ggplot2")
  library(ggplot2)
  
  p <- ggplot(data, aes(x = !!sym(x_var), y = !!sym(y_var)))
  
  if (!is.null(group_var) && group_var %in% names(data)) {
    p <- p + aes(group = !!sym(group_var), color = !!sym(group_var)) +
      stat_summary(fun.data = "mean_se", geom = "line", linewidth = 0.8) +
      stat_summary(fun.data = "mean_se", geom = "ribbon", 
                   aes(fill = !!sym(group_var)), alpha = 0.2) +
      scale_color_manual(values = colors) +
      scale_fill_manual(values = colors)
  } else {
    p <- p +
      stat_summary(fun.data = "mean_se", geom = "line", linewidth = 1) +
      stat_summary(fun.data = "mean_se", geom = "ribbon", fill = colors[1], alpha = 0.2)
  }
  
  p <- p +
    theme_easyplot() +
    labs(y = y_label, x = NULL) +
    theme(legend.position = "bottom")
  
  return(p)
}


#' 相关矩阵热图
#' @param data 数据框（仅数值列）
#' @param method 相关系数方法（"pearson", "spearman", "kendall"）
#' @export
plot_correlation_matrix <- function(
    data,
    method = "pearson"
) {
  
  .check_deps("ggplot2")
  library(ggplot2)
  
  # 计算相关矩阵
  cor_matrix <- cor(data, use = "pairwise.complete.obs", method = method)
  
  # 转换为长格式
  cor_long <- reshape2::melt(cor_matrix)
  names(cor_long) <- c("Var1", "Var2", "value")
  
  p <- ggplot(cor_long, aes(x = Var1, y = Var2, fill = value)) +
    geom_tile(color = "white", linewidth = 0.5) +
    scale_fill_gradient2(
      low = "#4477AA",
      mid = "#F7F7F7",
      high = "#EE6677",
      midpoint = 0,
      limit = c(-1, 1),
      space = "Lab"
    ) +
    geom_text(aes(label = sprintf("%.2f", value)), size = 2.5) +
    theme_minimal(base_size = 9) +
    theme(
      axis.text.x = element_text(angle = 45, hjust = 1),
      panel.grid = element_blank(),
      legend.position = "bottom"
    ) +
    labs(fill = "Correlation")
  
  return(p)
}


# ==================== 导出工具 ====================

#' 出版质量导出
#' @param plot ggplot对象
#' @param filename 文件名（不含扩展名）
#' @param width 宽度（cm）
#' @param height 高度（cm）
#' @param device 设备类型
#' @export
export_figure <- function(
    plot,
    filename,
    width = 8.5,
    height = 6,
    device = "png"
) {
  
  .check_deps("ggplot2")
  library(ggplot2)
  
  # 确保目录存在
  dir.create(dirname(filename), showWarnings = FALSE, recursive = TRUE)
  
  # PNG导出
  if (device == "png") {
    ggsave(
      paste0(filename, ".png"),
      plot = plot,
      width = width,
      height = height,
      units = "cm",
      dpi = 300,
      bg = "white"
    )
  }
  
  # PDF导出（矢量格式，支持中文）
  if (device == "pdf") {
    ggsave(
      paste0(filename, ".pdf"),
      plot = plot,
      width = width,
      height = height,
      units = "cm",
      device = cairo_pdf
    )
  }
  
  message(paste("导出完成:", filename, ".(png/pdf)"))
}


# ==================== 导出清单 ====================

# 导出完成后的检查清单（返回文本）
get_export_checklist <- function() {
  checklist <- c(
    "□ 图幅尺寸：确认最终发表尺寸下的可读性",
    "□ 分辨率：PNG ≥ 300 DPI",
    "□ 字体：中文和英文均正确显示",
    "□ 配色：检查色盲友好和灰度打印效果",
    "□ 图例：位置合适，标签清晰",
    "□ 标题/注释：图内或图外标注正确",
    "□ 数据点：原始数据未遭篡改",
    "□ 比例尺：轴范围和刻度合理"
  )
  return(cat(paste(checklist, collapse = "\n")))
}
