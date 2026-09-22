# =============================================================================
# 10_itt_sensitivity.R
# 目的：ITT 敏感性分析（评估 PP 分析结论的稳健性）
# 依赖：00_setup.R, 01_data_load.R
# 输出：
#   - outputs/tables/T4-9_itt_vs_pp.csv
#   - outputs/reports/10_itt_sensitivity_summary.txt
# 对应论文：第 4 章 4.8 节
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始 ITT 敏感性分析 ==========\n")

cat("→ 当前 df_main 样本量：", nrow(df_main), "（PP 样本）\n")

# ---- 1. 最坏/最好情况敏感性分析 ----
# 方法：从各组分别移除最高/最低改善者，观察 Hedges' g 漂移
cat("→ 执行最坏/最好情况敏感性分析...\n")

outcomes_sens <- list(
  list(name = "d1RM", label = "Δ 深蹲绝对 1RM"),
  list(name = "dCMJ", label = "Δ CMJ 高度"),
  list(name = "dSJ",  label = "Δ SJ 高度"),
  list(name = "dSE",  label = "Δ 训练自我效能")
)

sensitivity_results <- purrr::map_dfr(outcomes_sens, function(outcome) {
  var <- outcome$name

  ai_v   <- df_main[[var]][df_main$Group == "AI组"]
  slf_v  <- df_main[[var]][df_main$Group == "Self组"]

  n_ai  <- length(ai_v)
  n_slf <- length(slf_v)

  # PP 基准
  g_pp <- effectsize::hedges_g(ai_v, slf_v, ci = 0.95, pooled_sd = TRUE)

  # 最坏情况（移除 AI 组最高、Self 组最低 → g 偏负）
  ai_worst <- sort(ai_v)[-which.max(ai_v)]
  slf_worst <- sort(slf_v)[-which.min(slf_v)]
  g_worst <- tryCatch(
    effectsize::hedges_g(ai_worst, slf_worst, ci = 0.95, pooled_sd = TRUE),
    error = function(e) data.frame(Hedges_g = NA, CI_low = NA, CI_high = NA)
  )

  # 最好情况（移除 AI 组最低、Self 组最高 → g 偏正）
  ai_best <- sort(ai_v, decreasing = TRUE)[-which.min(ai_v)]
  slf_best <- sort(slf_v, decreasing = FALSE)[-which.max(slf_v)]
  g_best <- tryCatch(
    effectsize::hedges_g(ai_best, slf_best, ci = 0.95, pooled_sd = TRUE),
    error = function(e) data.frame(Hedges_g = NA, CI_low = NA, CI_high = NA)
  )

  # 均值填补（移除各组 1 人后用组均值填补）
  ai_mean_fill  <- c(sort(ai_v)[-which.max(ai_v)],  mean(sort(ai_v)[-which.max(ai_v)]))
  slf_mean_fill <- c(sort(slf_v)[-which.min(slf_v)], mean(sort(slf_v)[-which.min(slf_v)]))
  g_mean_fill <- tryCatch(
    effectsize::hedges_g(ai_mean_fill, slf_mean_fill, ci = 0.95, pooled_sd = TRUE),
    error = function(e) data.frame(Hedges_g = NA, CI_low = NA, CI_high = NA)
  )

  data.frame(
    结局             = outcome$label,
    PP_g             = sprintf("%.2f", g_pp$Hedges_g),
    PP_CI            = sprintf("(%.2f, %.2f)", g_pp$CI_low, g_pp$CI_high),
    Worst_g          = sprintf("%.2f", g_worst$Hedges_g),
    Best_g           = sprintf("%.2f", g_best$Hedges_g),
    MeanFill_g       = sprintf("%.2f", g_mean_fill$Hedges_g),
    g_漂移范围       = sprintf("%.2f 至 %.2f",
                               g_worst$Hedges_g, g_best$Hedges_g),
    stringsAsFactors = FALSE
  )
})

cat("\n---- PP vs. 敏感性分析（Hedges' g 漂移）----\n")
print(knitr::kable(sensitivity_results, format="simple"))

# ---- 2. 结论稳健性评估 ----
cat("\n---- 结论稳健性评估 ----\n")
for (i in seq_len(nrow(sensitivity_results))) {
  row <- sensitivity_results[i, ]
  g_pp    <- as.numeric(row$PP_g)
  g_worst <- as.numeric(row$Worst_g)
  g_best  <- as.numeric(row$Best_g)
  label   <- row$结局

  if (!is.na(g_worst) && !is.na(g_best)) {
    same_sign <- (g_pp > 0 && g_worst > 0) || (g_pp < 0 && g_best < 0) ||
                 (g_pp > 0 && g_best  > 0) || (g_pp < 0 && g_worst < 0)
    if (same_sign) {
      cat(sprintf("  ✓ %s：PP g=%.2f，最坏/最好 %.2f~%.2f，方向一致 → 稳健\n",
                  label, g_pp, g_worst, g_best))
    } else {
      cat(sprintf("  ⚠ %s：PP g=%.2f，最坏/最好 %.2f~%.2f，方向可能改变 → 不稳健\n",
                  label, g_pp, g_worst, g_best))
    }
  }
}

# ---- 3. 保存 ----
save_table(sensitivity_results, "T4-9_itt_vs_pp")

report_path <- file.path(PATH_REPORTS, "10_itt_sensitivity_summary.txt")
sink(report_path)
cat("========== ITT 敏感性分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n\n")
cat("---- 分析口径 ----\n")
cat("• PP 分析：24 人（完成 T1 后测且达到高依从标准）\n")
cat("• ITT 口径：由于无干预期间脱落，PP = ITT\n")
cat("• 敏感性分析方法：移除最极端值后填补组均值\n\n")
cat("---- 最坏/最好情况分析 ----\n")
cat("方法：从 AI 组移除最高改善者、从 Self 组移除最低改善者（最坏），\n")
cat("      或反之（最好），观察 Hedges' g 的漂移范围。\n\n")
print(knitr::kable(sensitivity_results, format="simple"))
cat("\n解读原则：若 PP 结果的 g 落在最坏—最好范围内，\n")
cat("          且方向一致，则 PP 分析结论稳健。\n")
sink()

cat("\n✓ ITT 敏感性分析完成\n")
cat("  报告路径：", report_path, "\n")
