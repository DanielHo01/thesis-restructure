# =============================================================================
# 00_setup.R
# -----------------------------------------------------------------------------
# 目的：全局环境配置、包加载、参数设置
# 作者：何天元
# 日期：2026-09-16
# 关联章节：第 3 章 3.2.3 节
# -----------------------------------------------------------------------------
# 使用方法：所有分析脚本首行 source("00_setup.R")
# =============================================================================

# ---- 1. 清空环境 ----
rm(list = ls())
graphics.off()

# ---- 2. 设置全局参数 ----
options(
  digits = 4,
  scipen = 999,
  encoding = "UTF-8",
  stringsAsFactors = FALSE
)

GLOBAL_SEED <- 20260916
set.seed(GLOBAL_SEED)

# ---- 3. 加载核心包 ----
required_packages <- c(
  "tidyverse",
  "lme4",
  "lmerTest",
  "car",
  "emmeans",
  "effectsize",
  "psych",
  "broom",
  "broom.mixed",
  "patchwork",
  "ggpubr",
  "ggsci",
  "here"
)

missing_packages <- setdiff(required_packages, rownames(installed.packages()))
if (length(missing_packages) > 0) {
  cat("正在安装缺失包：", paste(missing_packages, collapse = ", "), "\n")
  install.packages(missing_packages, dependencies = TRUE)
}

suppressPackageStartupMessages({
  invisible(lapply(required_packages, library, character.only = TRUE))
})

# 可选包检查
optional_packages <- c("mice", "irr", "blandr", "naniar", "kableExtra", "gt", "viridis")
missing_optional <- setdiff(optional_packages, rownames(installed.packages()))
if (length(missing_optional) > 0) {
  cat("⚠ 可选包未安装：", paste(missing_optional, collapse = ", "), "\n")
}

cat("✓ 核心 R 包已加载\n")

# ---- 4. 路径设置 ----
# 原始数据路径（相对于项目根目录）
PATH_RAW <- here::here("06_研究二_量化干预", "01_原始数据")

# 清洗分析数据路径（权威版本）
PATH_DATA_CLEAN <- here::here("06_研究二_量化干预", "02_清洗后数据")

# 输出路径
PATH_OUTPUT  <- here::here("outputs")
PATH_TABLES  <- here::here("outputs", "tables")
PATH_FIGURES <- here::here("outputs", "figures")
PATH_REPORTS <- here::here("outputs", "reports")

dir.create(PATH_OUTPUT,  showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_TABLES,  showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_FIGURES, showWarnings = FALSE, recursive = TRUE)
dir.create(PATH_REPORTS, showWarnings = FALSE, recursive = TRUE)

# ---- 5. 图表主题 ----
theme_thesis <- theme_minimal(base_size = 11) +
  theme(
    text = element_text(family = "serif"),
    plot.title   = element_text(size = 13, face = "bold", hjust = 0.5),
    axis.title   = element_text(size = 11),
    axis.text    = element_text(size = 10, color = "black"),
    legend.title = element_text(size = 11),
    legend.text  = element_text(size = 10),
    legend.position = "top",
    panel.grid.minor = element_blank(),
    panel.grid.major = element_line(linewidth = 0.3),
    strip.text = element_text(size = 11, face = "bold")
  )

GROUP_COLORS <- c("AI组" = "#0072B5", "Self组" = "#BC3C29")

# ---- 6. 辅助函数 ----

# 格式化 p 值
format_p <- function(p) {
  ifelse(is.na(p), "NA",
         ifelse(p < 0.001, "<0.001", sprintf("%.3f", p)))
}

# 格式化均值 ± SD
format_msd <- function(x, digits = 2) {
  m <- mean(x, na.rm = TRUE)
  s <- sd(x, na.rm = TRUE)
  sprintf(paste0("%#.", digits, "f ± %.", digits, "f"), m, s)
}

# 计算 95% CI（返回字符串）
get_ci <- function(x, conf.level = 0.95) {
  n   <- length(x)
  m   <- mean(x, na.rm = TRUE)
  s   <- sd(x, na.rm = TRUE)
  t_v <- qt((1 - conf.level) / 2, df = n - 1, lower.tail = FALSE)
  se  <- s / sqrt(n)
  lo  <- m - t_v * se
  hi  <- m + t_v * se
  sprintf("%.2f [%.2f, %.2f]", m, lo, hi)
}

# 保存表格（CSV + TXT 双格式）
save_table <- function(df, filename) {
  csv_path <- file.path(PATH_TABLES, paste0(filename, ".csv"))
  txt_path <- file.path(PATH_TABLES, paste0(filename, ".txt"))

  write.csv(df, csv_path, row.names = FALSE, fileEncoding = "UTF-8")

  sink(txt_path)
  print(knitr::kable(df, format = "pipe"))
  sink()

  cat("  ✓ 表格：", filename, "\n")
}

# 保存图表（PDF + PNG 双格式）
save_plot <- function(plot, filename,
                      width = 14, height = 10,
                      units = "cm", dpi = 300) {
  pdf_path <- file.path(PATH_FIGURES, paste0(filename, ".pdf"))
  png_path <- file.path(PATH_FIGURES, paste0(filename, ".png"))

  ggsave(pdf_path, plot, width = width, height = height, units = units,
         device = cairo_pdf)
  ggsave(png_path, plot, width = width, height = height, units = units,
         dpi = dpi, type = "cairo")

  cat("  ✓ 图表：", filename, "\n")
}

# ---- 7. 启动信息 ----
cat("✓ 全局环境配置完成\n")
cat("  - R 版本：", R.version.string, "\n")
cat("  - 随机种子：", GLOBAL_SEED, "\n")
cat("  - 原始数据路径：", PATH_RAW, "\n")
cat("  - 清洗数据路径：", PATH_DATA_CLEAN, "\n")
cat("  - 输出路径：", PATH_OUTPUT, "\n")
