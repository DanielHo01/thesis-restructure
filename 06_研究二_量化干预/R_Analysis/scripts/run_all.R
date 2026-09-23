# =============================================================================
# run_all.R
# 全部分析管线主脚本
# 从原始数据导入到所有表格和图表输出
#
# 使用方法：
#   source("run_all.R")
#   # 或从 RStudio: Ctrl+Shift+S 运行全部
#
# 输出位置：outputs/tables/, outputs/figures/, outputs/diagnostics/
# =============================================================================

# ---- 全局设置 ----
cat("\n============================================================")
cat("\n毕业论文数据分析管线")
cat("\n研究二 Pilot RCT 量化分析")
cat("\n============================================================\n")

# 记录开始时间
start_time <- Sys.time()

# 设置路径（自动检测脚本所在目录）
if (!exists("ROOT")) {
  n <- sys.nframe()
  if (n == 0L) {
    # Rscript: extract --file= argument and use it directly
    fa <- commandArgs()[max(grep("^--file=", commandArgs()))]
    script_path <- sub("^--file=", "", fa)
    script_path <- if (file.exists(script_path)) normalizePath(script_path) else NA_character_
  } else {
    script_path <- tryCatch(normalizePath(sys.frame(1L)$ofile), error = function(e) NA_character_)
  }
  SCRIPT_DIR <- if (!is.na(script_path)) dirname(script_path) else getwd()
  ROOT <- normalizePath(file.path(SCRIPT_DIR, ".."))
} else {
  SCRIPT_DIR <- file.path(ROOT, "scripts")
}

PATH_SCRIPTS <- SCRIPT_DIR
PATH_CLEAN   <- file.path(ROOT, "data_clean")
PATH_RAW    <- file.path(ROOT, "data_raw")
PATH_OUT    <- file.path(ROOT, "outputs")
PATH_TBL    <- file.path(PATH_OUT, "tables")
PATH_FIG    <- file.path(PATH_OUT, "figures")
PATH_DGN    <- file.path(PATH_OUT, "diagnostics")

dir.create(PATH_CLEAN, showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_TBL,   showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_FIG,   showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_DGN,   showWarnings = FALSE, recursive = TRUE)

# ---- 全局包加载（仅在此文件中加载一次）----
cat("加载核心包...\n")
suppressPackageStartupMessages({
  invisible(lapply(c(
    "tidyverse", "lme4", "lmerTest", "car", "emmeans",
    "effectsize", "psych", "broom", "broom.mixed",
    "patchwork", "ggpubr", "ggsci", "here"
  ), library, character.only = TRUE))
})

# ---- 全局辅助函数（避免重复定义）----
GLOBAL_SEED <- 20260916
set.seed(GLOBAL_SEED)

fmt_p <- function(p) {
  case_when(
    is.na(p)         ~ "NA",
    p < 0.001        ~ "<0.001",
    p < 0.01         ~ sprintf("%.3f", p),
    TRUE              ~ sprintf("%.3f", p)
  )
}

save_tbl <- function(df, name) {
  readr::write_csv(df, file.path(PATH_TBL, paste0(name, ".csv")))
  sink(file.path(PATH_TBL, paste0(name, ".txt")))
  print(knitr::kable(df, format = "pipe"))
  sink()
  cat("  ✓ ", name, "\n", sep = "")
}

save_fig <- function(p, name, w = 14, h = 10) {
  ggplot2::ggsave(
    file.path(PATH_FIG, paste0(name, ".pdf")),
    p, width = w, height = h, units = "cm", device = cairo_pdf
  )
  ggplot2::ggsave(
    file.path(PATH_FIG, paste0(name, ".png")),
    p, width = w, height = h, units = "cm", dpi = 300, type = "cairo"
  )
  cat("  ✓ ", name, "\n", sep = "")
}

ci_wilson <- function(x, n, conf.level = 0.95) {
  z <- qnorm(1 - (1 - conf.level) / 2)
  p <- x / n
  denom <- 1 + z^2 / n
  center <- (p + z^2 / (2 * n)) / denom
  radius <- z * sqrt((p * (1 - p) + z^2 / (4 * n)) / n) / denom
  tibble(estimate = p,
          ci_low  = max(0, center - radius),
          ci_high = min(1, center + radius))
}

# ============================================================================
# STEP 00: 准备
# ============================================================================
cat("\n============================================================")
cat("\n[00] 环境准备")
cat("\n============================================================")
cat("  R 版本：", R.version.string, "\n")
cat("  随机种子：", GLOBAL_SEED, "\n")
cat("  输出路径：", PATH_OUT, "\n")

# ============================================================================
# STEP 01: 数据导入与清洗
# ============================================================================
cat("\n============================================================")
cat("\n[01] 数据导入与清洗")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "01_import_clean.R"))

# ============================================================================
# STEP 02: CONSORT 流程与基线特征
# ============================================================================
cat("\n============================================================")
cat("\n[02] CONSORT 流程与基线特征")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "02_flow_baseline.R"))

# ============================================================================
# STEP 03: Hooper LMM
# ============================================================================
cat("\n============================================================")
cat("\n[03] Hooper 主观恢复 LMM")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "03_hooper_lmm.R"))

# ============================================================================
# STEP 04: ANCOVA 主要结局
# ============================================================================
cat("\n============================================================")
cat("\n[04] ANCOVA 主要结局分析")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "04_ancova.R"))

# ============================================================================
# STEP 05: App-GA 现场一致性
# ============================================================================
cat("\n============================================================")
cat("\n[05] App-GA 现场一致性")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "05_app_ga_agreement.R"))

# ============================================================================
# STEP 06: 可行性与训练执行
# ============================================================================
cat("\n============================================================")
cat("\n[06] 可行性与训练执行")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "06_feasibility_training.R"))

# ============================================================================
# STEP 07: SUS 与 TAM 接受度
# ============================================================================
cat("\n============================================================")
cat("\n[07] SUS 与 TAM 接受度")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "07_sus_acceptance.R"))

# ============================================================================
# STEP 08: 敏感性分析（LOO + 训练频率）
# ============================================================================
cat("\n============================================================")
cat("\n[08] 敏感性分析")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "08_sensitivity.R"))

# ============================================================================
# STEP 09: Spearman 探索性相关
# ============================================================================
cat("\n============================================================")
cat("\n[09] 探索性 Spearman 相关")
cat("\n============================================================")
source(file.path(PATH_SCRIPTS, "09_spearman.R"))

# ============================================================================
# 完成
# ============================================================================
end_time <- Sys.time()
elapsed <- difftime(end_time, start_time, units = "secs")

cat("\n============================================================")
cat("\n✓ 全部分析完成！")
cat("\n============================================================")
cat(sprintf("\n  总耗时：%.1f 秒\n", as.numeric(elapsed)))
cat("  表格输出：", PATH_TBL, "\n")
cat("  图表输出：", PATH_FIG, "\n")
cat("  诊断输出：", PATH_DGN, "\n")

cat("\n输出文件清单：\n")
cat("  表格（CSV）：\n")
tbl_files <- list.files(PATH_TBL, pattern = ".csv$")
cat("   ", paste(sort(tbl_files), collapse = "\n   "), "\n")
cat("  图表（PDF/PNG）：\n")
fig_files <- list.files(PATH_FIG, pattern = ".(pdf|png)$")
cat("   ", paste(sort(fig_files), collapse = "\n   "), "\n")

cat("\n============================================================\n")
