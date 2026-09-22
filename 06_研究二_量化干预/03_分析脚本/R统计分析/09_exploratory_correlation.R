# =============================================================================
# 09_exploratory_correlation.R
# 目的：训练执行指标与体能结局变化的探索性相关分析
# 依赖：00_setup.R, 01_data_load.R
# 输出：
#   - outputs/tables/T4-8_spearman_correlations.csv
#   - outputs/figures/F4-7_correlation_heatmap.pdf/png
#   - outputs/reports/09_correlation_summary.txt
# 对应论文：第 4 章 4.4 节
# 方法学：Spearman ρ + Fisher z 95%CI + BH FDR 校正
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始探索性相关分析 ==========\n")

# ---- 1. 变量定义 ----
predictors <- c(
  "TotalLoad"      = "全期总负荷",
  "Reps"           = "总次数",
  "Sets"           = "总组数",
  "LoadPerSess"   = "平均每课次负荷",
  "sRPE"           = "全期平均 sRPE",
  "Duration"       = "训练总时长",
  "Hooper_revised" = "个体 Hooper 均值"
)

outcomes <- c(
  "d1RM" = "Δ 深蹲绝对 1RM",
  "dCMJ" = "Δ CMJ 高度",
  "dSJ"  = "Δ SJ 高度",
  "dSE"  = "Δ 训练自我效能"
)

cat("→ 预测变量：", length(predictors), "个\n")
cat("→ 结局变量：", length(outcomes), "个\n")
cat("→ 相关对数：", length(predictors) * length(outcomes), "对\n")

# ---- 2. Spearman 相关计算 ----
compute_spearman <- function(data, x_var, y_var, x_label, y_label) {
  x <- data[[x_var]]
  y <- data[[y_var]]
  complete_idx <- !is.na(x) & !is.na(y)
  x_c <- x[complete_idx]
  y_c <- y[complete_idx]
  n <- length(x_c)

  cor_result <- cor.test(x_c, y_c, method = "spearman", exact = FALSE)
  rho <- as.numeric(cor_result$estimate)

  # Fisher's z 95% CI
  z <- 0.5 * log((1 + rho) / (1 - rho))
  se_z <- 1 / sqrt(n - 3)
  z_lo <- z - 1.96 * se_z
  z_hi <- z + 1.96 * se_z
  rho_lo <- (exp(2*z_lo) - 1) / (exp(2*z_lo) + 1)
  rho_hi <- (exp(2*z_hi) - 1) / (exp(2*z_hi) + 1)

  data.frame(
    Predictor = x_label,
    Outcome   = y_label,
    n         = n,
    rho       = rho,
    CI_lower  = rho_lo,
    CI_upper  = rho_hi,
    p_value   = cor_result$p.value,
    stringsAsFactors = FALSE
  )
}

cat("→ 计算所有相关对...\n")
correlation_results <- expand.grid(
  x_var = names(predictors),
  y_var = names(outcomes),
  stringsAsFactors = FALSE
) %>%
  purrr::pmap_dfr(function(x_var, y_var) {
    compute_spearman(df_main, x_var, y_var,
                     predictors[[x_var]], outcomes[[y_var]])
  })

# ---- 3. FDR 校正 ----
correlation_results <- correlation_results %>%
  mutate(
    p_FDR            = p.adjust(p_value, method = "BH"),
    Significance_raw  = case_when(p_value < 0.001 ~ "***",
                                  p_value < 0.01  ~ "**",
                                  p_value < 0.05  ~ "*",
                                  TRUE            ~ ""),
    Significance_FDR = case_when(p_FDR < 0.001 ~ "***",
                                 p_FDR < 0.01  ~ "**",
                                 p_FDR < 0.05  ~ "*",
                                 TRUE           ~ "")
  )

# ---- 4. 主表 ----
main_table <- correlation_results %>%
  mutate(
    rho_display = sprintf("%.3f", rho),
    CI_display  = sprintf("(%.2f, %.2f)", CI_lower, CI_upper)
  ) %>%
  select(预测变量 = Predictor, 结局变量 = Outcome, n,
         rho = rho_display, `95% CI` = CI_display,
         `p 值` = p_value, `p (FDR)` = p_FDR,
         显著性 = Significance_raw, `显著性 (FDR)` = Significance_FDR)

cat("\n---- Spearman 相关分析结果 ----\n")
print(knitr::kable(main_table, format="simple", digits=c(0,0,0,3,0,3,3,0,0)))

# ---- 5. 显著相关摘要 ----
sig_raw <- correlation_results %>% filter(p_value < 0.05)
sig_fdr <- correlation_results %>% filter(p_FDR < 0.05)

cat("\n---- 显著相关摘要 ----\n")
cat(sprintf("原始 p < 0.05：%d 对\n", nrow(sig_raw)))
cat(sprintf("FDR 校正后 p < 0.05：%d 对\n", nrow(sig_fdr)))

if (nrow(sig_raw) > 0) {
  cat("\n▶ 原始显著相关（未校正）：\n")
  print(knitr::kable(sig_raw %>%
    select(预测变量= Predictor, 结局变量= Outcome,
           rho, p_value) %>%
    mutate(rho = sprintf("%.3f", rho),
           p_value = format_p(p_value)),
    format="simple"))
}

if (nrow(sig_fdr) > 0) {
  cat("\n▶ FDR 校正后显著相关：\n")
  print(knitr::kable(sig_fdr %>%
    select(预测变量= Predictor, 结局变量= Outcome,
           rho, p_FDR) %>%
    mutate(rho = sprintf("%.3f", rho),
           p_FDR = format_p(p_FDR)),
    format="simple"))
}

# ---- 6. 相关热图 ----
cat("\n→ 生成相关矩阵热图...\n")

h_data <- correlation_results %>%
  mutate(
    sig_star = ifelse(p_value < 0.05, "*", ""),
    label    = sprintf("%.2f%s", rho, sig_star),
    rho_raw  = rho
  )

star_color <- ifelse(abs(h_data$rho_raw) > 0.5, "white", "black")

heatmap_plot <- ggplot(h_data, aes(x = Outcome, y = Predictor, fill = rho_raw)) +
  geom_tile(color = "white", linewidth = 0.3) +
  geom_text(aes(label = label), color = star_color, size = 3.5, fontface = "bold") +
  scale_fill_gradient2(low = "#3B4CC0", mid = "white", high = "#B40426",
                       midpoint = 0, limits = c(-1, 1), name = "Spearman ρ") +
  labs(title = "训练执行指标与体能结局变化的相关矩阵",
       subtitle = "* 表示原始 p < 0.05（未 FDR 校正）",
       x = NULL, y = NULL) +
  theme_thesis +
  theme(axis.text.x = element_text(angle = 25, hjust = 1, size = 10),
        axis.text.y = element_text(size = 10),
        panel.grid = element_blank(),
        legend.position = "bottom")

save_plot(heatmap_plot, "F4-7_correlation_heatmap", width = 10, height = 8)

# ---- 7. 保存 ----
save_table(main_table, "T4-8_spearman_correlations")

report_path <- file.path(PATH_REPORTS, "09_correlation_summary.txt")
sink(report_path)
cat("========== 探索性相关分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n")
cat("样本：PP 样本（n=24）\n\n")
cat("---- 方法学 ----\n")
cat("• 相关方法：Spearman ρ（非参数，对非正态数据稳健）\n")
cat("• 95% CI：基于 Fisher's z 变换\n")
cat("• 多重比较校正：Benjamini-Hochberg FDR\n")
cat("• 校正范围：", nrow(correlation_results), " 对相关\n\n")
cat("---- 显著相关摘要 ----\n")
cat(sprintf("原始 p < 0.05：%d 对\n", nrow(sig_raw)))
cat(sprintf("FDR 校正后 p < 0.05：%d 对\n", nrow(sig_fdr)))
if (nrow(sig_raw) > 0) {
  cat("\n▶ 原始显著相关：\n")
  print(knitr::kable(sig_raw %>% select(预测变量= Predictor, 结局变量= Outcome,
    rho, p_value) %>% mutate(rho=sprintf("%.3f", rho), p_value=format_p(p_value)),
    format="simple"))
}
cat("\n---- 完整结果 ----\n")
print(knitr::kable(main_table, format="simple", digits=c(0,0,0,3,0,3,3,0,0)))
sink()

cat("\n✓ 探索性相关分析完成\n")
cat("  报告路径：", report_path, "\n")
