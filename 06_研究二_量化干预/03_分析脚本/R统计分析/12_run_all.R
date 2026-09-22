# =============================================================================
# 12_run_all.R
# 目的：一键运行全部 R 分析脚本
# 用法：source("12_run_all.R")
# =============================================================================

cat("\n",
"╔══════════════════════════════════════════════════════════════╗\n",
"║       R Final 分析工程 - 一键运行                           ║\n",
"║       版本：2026-09-16                                      ║\n",
"╚══════════════════════════════════════════════════════════════╝\n")

start_time <- Sys.time()

scripts <- c(
  "00_setup.R",
  "01_data_load.R",
  "02_baseline_characteristics.R",
  "03_feasibility_indicators.R",
  "04_ancova_primary.R",
  "05_hooper_lmm.R",
  "05b_hooper_session_comparisons.R",
  "06_training_execution.R",
  "07_app_ga_agreement.R",
  "08_psych_acceptance.R",
  "09_exploratory_correlation.R",
  "10_itt_sensitivity.R",
  "11_forest_plots.R"
)

for (s in scripts) {
  cat("\n▶ 运行 ", s, " ...\n", sep = "")
  source(s)
  cat("✓ ", s, " 完成\n", sep = "")
}

end_time <- Sys.time()
elapsed  <- as.numeric(difftime(end_time, start_time, units = "mins"))

cat(sprintf("\n
╔══════════════════════════════════════════════════════════════╗
║  全部分析完成！                                              ║
║  总耗时：%.1f 分钟                                           ║
║  表格目录：outputs/tables/                                   ║
║  图表目录：outputs/figures/                                  ║
║  报告目录：outputs/reports/                                  ║
╚══════════════════════════════════════════════════════════════╝
", elapsed))
