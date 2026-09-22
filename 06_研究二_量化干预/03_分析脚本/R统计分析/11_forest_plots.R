# =============================================================================
# 11_forest_plots.R
# 目的：主要结局效应量森林图
# 依赖：00_setup.R, 01_data_load.R, 04_ancova_primary.R 的输出
# 输出：
#   - outputs/figures/F4-8_forest_effect_sizes.pdf/png
# 对应论文：第 4 章 表 4-3 的可视化补充
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始森林图生成 ==========\n")

# ---- 1. 从 T4-3 ANCOVA 结果读取 ----
ancova_path <- file.path(PATH_TABLES, "T4-3_ancova_primary_results.csv")

if (!file.exists(ancova_path)) {
  stop("⚠ 未找到 ANCOVA 结果文件，请先运行 04_ancova_primary.R")
}

ancova_data <- read.csv(ancova_path, fileEncoding = "UTF-8", stringsAsFactors = FALSE)
cat("→ 已读取 ANCOVA 结果：", nrow(ancova_data), "行\n")
cat("   列名：", paste(names(ancova_data), collapse=", "), "\n")

# ---- 2. 解析效应量列 ----
# 尝试自动识别列名
g_col   <- grep("Hedges.*g|^g$", names(ancova_data), value=TRUE, ignore.case=TRUE)[1]
ci_col  <- grep("g_CI|^CI$", names(ancova_data), value=TRUE, ignore.case=TRUE)[1]
out_col <- grep("结局|Outcome|outcome", names(ancova_data), value=TRUE, ignore.case=TRUE)[1]

if (is.na(g_col) || is.na(ci_col) || is.na(out_col)) {
  cat("⚠ 未能自动识别列名，尝试手动提取...\n")
  print(names(ancova_data))
}

cat(sprintf("  g 列：%s\n  CI 列：%s\n  结局列：%s\n",
            g_col, ci_col, out_col))

forest_data <- ancova_data %>%
  mutate(
    Outcome_raw = .data[[out_col]],
    g_raw      = as.numeric(gsub("[^0-9.\\-]", "", .data[[g_col]])),
    ci_str     = gsub("[\\(\\)]", "", .data[[ci_col]]),
    ci_lower   = as.numeric(sapply(strsplit(ci_str, ", "), `[`, 1)),
    ci_upper   = as.numeric(sapply(strsplit(ci_str, ", "), `[`, 2))
  ) %>%
  select(Outcome = Outcome_raw, g = g_raw, CI_lower = ci_lower, CI_upper = ci_upper) %>%
  filter(!is.na(g) & !is.na(CI_lower))

# 按表格顺序排列（翻转使第一个在顶部）
forest_data$Outcome <- factor(forest_data$Outcome, levels = rev(forest_data$Outcome))

cat("\n森林图数据：\n")
print(forest_data)

# ---- 3. 计算 x 轴范围 ----
all_vals <- c(forest_data$g, forest_data$CI_lower, forest_data$CI_upper)
x_min <- floor(min(all_vals) * 1.2 * 10) / 10
x_max <- ceiling(max(all_vals) * 1.2 * 10) / 10
if (x_max < 1) x_max <- ceiling(max(all_vals) + 1)

cat(sprintf("\n→ X 轴范围：%.1f 至 %.1f\n", x_min, x_max))

# ---- 4. 绘制森林图 ----
forest_plot <- ggplot(forest_data, aes(x = g, y = Outcome)) +
  # 参考线组
  geom_vline(xintercept = 0,  linetype = "dashed", color = "#D32F2F", linewidth = 0.7) +
  geom_vline(xintercept = 0.2, linetype = "dotted", color = "#90A4AE", linewidth = 0.4) +
  geom_vline(xintercept =-0.2, linetype = "dotted", color = "#90A4AE", linewidth = 0.4) +
  # 置信区间误差棒
  geom_errorbarh(aes(xmin = CI_lower, xmax = CI_upper),
                 height = 0.35, linewidth = 0.9, color = "#37474F") +
  # 点估计
  geom_point(size = 5, shape = 22, fill = "#0072B5", color = "black") +
  # 标签
  geom_text(aes(label = sprintf("%.2f (%.2f, %.2f)", g, CI_lower, CI_upper),
                x = x_max + 0.1),
            hjust = 0, size = 3.2, color = "#37474F") +
  # 效应量大小标注
  annotate("text", x = x_min - 0.05, y = nrow(forest_data) + 0.3,
          label = "效应量参考：", size = 2.8, color = "grey50", hjust = 1) +
  annotate("text", x = x_min - 0.05, y = seq_len(nrow(forest_data)),
          label = c(rep(" ", nrow(forest_data))),
          size = 2.8, color = "grey50") +
  scale_x_continuous(
    breaks = seq(x_min, x_max, length.out = 8),
    limits = c(x_min - 0.2, x_max + 1.5),
    expand = expansion(mult = c(0, 0))
  ) +
  labs(
    title = "主要结局的效应量森林图",
    subtitle = expression("AI 组 vs. Self 组  |  Hedges g (95% CI)"),
    x = "Hedges g",
    y = NULL,
    caption = "参考线：0 = 无效应；±0.2 = 微小；±0.5 = 小；±0.8 = 中；>0.8 = 大"
  ) +
  theme_thesis +
  theme(
    axis.text.y = element_text(size = 11, face = "bold"),
    plot.caption = element_text(size = 9, color = "grey50", hjust = 0),
    plot.margin = margin(r = 6, unit = "cm")
  )

save_plot(forest_plot, "F4-8_forest_effect_sizes", width = 13, height = 8)

cat("\n✓ 森林图生成完成\n")
cat("  图表路径：", file.path(PATH_FIGURES, "F4-8_forest_effect_sizes.pdf"), "\n")
