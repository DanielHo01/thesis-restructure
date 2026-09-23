# =============================================================================
# 00_setup.R
# 全局环境配置、包加载、参数设置
# ============================================================================

# NOTE: rm(list = ls()) removed to avoid clearing caller environment
# when this file is sourced by other scripts (local = FALSE).
# graphics.off()

options(
  digits   = 4,
  scipen   = 999,
  encoding = "UTF-8",
  stringsAsFactors = FALSE
)

GLOBAL_SEED <- 20260916
set.seed(GLOBAL_SEED)

# ---- 路径设置（基于本脚本所在目录自动推断）----
# 自动识别 scripts/ 的父目录（即 R_Analysis/
SCRIPT_DIR <- dirname(sys.frame(1)$ofile)
if (SCRIPT_DIR == "" || is.na(SCRIPT_DIR)) {
  SCRIPT_DIR <- getwd()  # fallback
}
ROOT     <- normalizePath(file.path(SCRIPT_DIR, ".."))
PATH_RAW <- file.path(ROOT, "data_raw")
PATH_OUT <- file.path(ROOT, "outputs")
PATH_TBL <- file.path(PATH_OUT, "tables")
PATH_FIG <- file.path(PATH_OUT, "figures")
PATH_DGN <- file.path(PATH_OUT, "diagnostics")

dir.create(PATH_OUT, showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_TBL, showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_FIG, showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_DGN, showWarnings = FALSE, recursive = TRUE)

# ---- 包加载 ----
pkgs_required <- c(
  "tidyverse", "lme4", "lmerTest", "car", "emmeans",
  "effectsize", "psych", "broom", "broom.mixed",
  "patchwork", "ggpubr", "ggsci", "here", "boot", "pwr"
)

pkgs_missing <- setdiff(pkgs_required, rownames(installed.packages()))
if (length(pkgs_missing) > 0) {
  cat("安装缺失包：", paste(pkgs_missing, collapse = ", "), "\n")
  install.packages(pkgs_missing, dependencies = TRUE, quiet = TRUE)
}

suppressPackageStartupMessages(
  invisible(lapply(pkgs_required, library, character.only = TRUE))
)

cat("✓ 核心 R 包已加载\n")

# ---- 图表主题 ----
theme_thesis <- theme_minimal(base_size = 11) +
  theme(
    text        = element_text(family = "serif"),
    plot.title  = element_text(size = 13, face = "bold", hjust = 0.5),
    axis.title  = element_text(size = 11),
    axis.text   = element_text(size = 10, color = "black"),
    legend.title = element_text(size = 11),
    legend.text  = element_text(size = 10),
    legend.position = "top",
    panel.grid.minor = element_blank(),
    panel.grid.major = element_line(linewidth = 0.3)
  )

COL_AI   <- "#0072B5"
COL_SELF <- "#BC3C29"
COLORS_GROUPS <- c("AI组" = COL_AI, "Self组" = COL_SELF)

# ---- 辅助函数 ----
fmt_p <- function(p) {
  case_when(
    is.na(p)         ~ "NA",
    p < 0.001        ~ "<0.001",
    p < 0.01         ~ sprintf("%.3f", p),
    TRUE              ~ sprintf("%.3f", p)
  )
}

fmt_ci <- function(est, lo, hi, d = 2) {
  sprintf(paste0("%#.", d, "f [%,.2f, %,.2f]"), est, lo, hi)
}

fmt_msd <- function(x, d = 2) {
  m <- mean(x, na.rm = TRUE)
  s <- sd(x, na.rm = TRUE)
  sprintf(paste0("%#.", d, "f ± %.", d, "f"), m, s)
}

ci_wilson <- function(x, n, conf.level = 0.95) {
  z   <- qnorm(1 - (1 - conf.level) / 2)
  p   <- x / n
  denom <- 1 + z^2 / n
  center <- (p + z^2 / (2 * n)) / denom
  radius <- z * sqrt((p * (1 - p) + z^2 / (4 * n)) / n) / denom
  lo <- center - radius
  hi <- center + radius
  tibble(estimate = p, ci_low = max(0, lo), ci_high = min(1, hi))
}

save_tbl <- function(df, name) {
  readr::write_csv(df, file.path(PATH_TBL, paste0(name, ".csv")))
  sink(file.path(PATH_TBL, paste0(name, ".txt")))
  print(knitr::kable(df, format = "pipe"))
  sink()
  cat("  ✓ 表格：", name, "\n")
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
  cat("  ✓ 图表：", name, "\n")
}

cat("✓ 全局环境配置完成\n")
cat("  - R 版本：", R.version.string, "\n")
cat("  - 随机种子：", GLOBAL_SEED, "\n")
cat("  - 原始数据路径：", PATH_RAW, "\n")
cat("  - 输出路径：", PATH_OUT, "\n")
