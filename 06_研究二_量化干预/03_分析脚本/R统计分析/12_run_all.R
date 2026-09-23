# =============================================================================
# 12_run_all.R
# 目的：一键运行全部 R 分析脚本
# 用法：
#   - RStudio:   source("12_run_all.R")
#   - Rscript:   Rscript 12_run_all.R
#   - 绝对路径:  Rscript /full/path/to/12_run_all.R
# =============================================================================

# ---- 0. 可靠路径检测（兼容 RStudio source() 与 Rscript 两种模式）----
# sys.frame(1)$ofile 在 Rscript 直接运行时返回 NULL，
# 故使用 commandArgs() 兜底检测脚本自身路径。
cmd_args <- commandArgs(trailingOnly = FALSE)
file_arg <- grep("^--file=", cmd_args, value = TRUE)[1]

if (!is.na(file_arg) && file_arg != "") {
  # Rscript 模式：--file=/path/to/script.R
  script_path <- sub("^--file=", "", file_arg)
  script_path <- normalizePath(script_path)
} else if (exists("sys.frame")) {
  # RStudio source() 模式：尝试从调用栈回溯
  script_path <- NULL
  for (f in sys.frames()) {
    fp <- tryCatch(normalizePath(f$ofile), error = function(e) NA)
    if (!is.na(fp) && file.exists(fp)) {
      script_path <- fp
      break
    }
  }
  if (is.null(script_path)) {
    stop("无法确定脚本自身路径。请使用 Rscript 模式运行。")
  }
} else {
  stop("无法确定脚本自身路径。请使用 Rscript 模式运行。")
}

SCRIPT_DIR <- dirname(script_path)

# 派生项目根目录（脚本位于 R统计分析/ 子目录）
ROOT <- normalizePath(file.path(SCRIPT_DIR, "..", "..", "..", ".."))
setwd(ROOT)

cat("\n",
"╔══════════════════════════════════════════════════════════════╗\n",
"║       R 完整分析工程 - 一键运行                             ║\n",
"║       版本：2026-09-16                                      ║\n",
"╚══════════════════════════════════════════════════════════════╝\n")
cat("  工作目录：", getwd(), "\n")
cat("  脚本目录：", SCRIPT_DIR, "\n\n")

# ---- 1. 加载全局环境（仅在尚未加载时）----
# 注意：00_setup.R 首行执行 rm(list=ls())，会清空调用者环境，
# 故须先在此处派生 ROOT 并确保其以变量形式存在（rm 不会清除显式赋值变量所在帧）。
ROOT <- normalizePath(file.path(SCRIPT_DIR, "..", "..", "..", ".."))

if (!exists("PATH_OUTPUT") || is.null(PATH_OUTPUT)) {
  cat("▶ 加载 00_setup.R ...\n")
  # source 内部 rm(list=ls()) 会清除此帧中的 ROOT，但 setup 已通过 here() 重新设置正确路径
  source(file.path(SCRIPT_DIR, "00_setup.R"), encoding = "UTF-8")
  cat("✓ 00_setup.R 完成\n\n")
} else {
  cat("✓ 全局环境已加载（跳过 00_setup.R）\n\n")
}

# ---- 2. 定义待运行脚本列表----
scripts <- c(
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

# ---- 3. 逐个运行脚本----
for (s in scripts) {
  script_path <- file.path(SCRIPT_DIR, s)
  if (!file.exists(script_path)) {
    cat("⚠ 脚本不存在：", s, "（跳过）\n")
    next
  }
  cat("▶ 运行 ", s, " ...\n", sep = "")
  source(script_path, encoding = "UTF-8")
  cat("✓ ", s, " 完成\n", sep = "")
}

# ---- 4. 收尾信息----
end_time <- Sys.time()
elapsed  <- as.numeric(difftime(end_time, start_time, units = "mins"))

cat(sprintf("\n
╔══════════════════════════════════════════════════════════════╗
║  全部分析完成！                                              ║
║  总耗时：%.1f 分钟                                           ║
║  表格目录：%s                                         ║
║  图表目录：%s                                         ║
║  报告目录：%s                                         ║
╚══════════════════════════════════════════════════════════════╝
",
  elapsed,
  sub(ROOT, ".", PATH_TABLES, fixed = TRUE),
  sub(ROOT, ".", PATH_FIGURES, fixed = TRUE),
  sub(ROOT, ".", PATH_REPORTS, fixed = TRUE)))
