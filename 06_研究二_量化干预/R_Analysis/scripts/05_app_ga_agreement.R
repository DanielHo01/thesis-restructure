# =============================================================================
# 05_app_ga_agreement.R
# 研究二现场 App-GA 一致性分析
# - 热身课次级：来自 192课次监控数据（AI组 GA+App 同时非空）
# - Rep级：来自 43对配对数据
# =============================================================================

# =============================================================================
# =============================================================================
# Auto-detect script directory for Rscript and RStudio
# =============================================================================
n <- sys.nframe()
if (n == 0L) {
  # Running via Rscript directly: script path is last arg containing '.R'
  script_arg <- commandArgs()[max(grep("scripts/", commandArgs()))]
  script_path <- normalizePath(file.path(getwd(), script_arg))
} else {
  # Running via source() in RStudio or another script
  script_path <- tryCatch(normalizePath(sys.frame(1L)$ofile), error = function(e) NA_character_)
}
script_dir <- dirname(script_path)
ROOT <- normalizePath(file.path(script_dir, ".."))
PATH_SCRIPTS <- file.path(ROOT, "scripts")
PATH_CLEAN   <- file.path(ROOT, "data_clean")
source(file.path(PATH_SCRIPTS, "00_setup.R"), local = FALSE, encoding = "UTF-8")



cat("\n=== 05 App-GA 现场一致性分析 ===\n")

mon <- readRDS(file.path(PATH_CLEAN, "monitor.rds"))
rep <- readRDS(file.path(PATH_CLEAN, "rep_pairs.rds"))

# ============================================================================
# 辅助函数：ICC(2,1) 手工计算（避免 psych::ICC 逐对调用的开销）
# ============================================================================
icc_21 <- function(x, y) {
  d <- na.omit(data.frame(x = x, y = y))
  if (nrow(d) < 2) return(NA_real_)
  # ICC(2,1) two-way random, absolute agreement
  m <- as.matrix(d)
  n <- nrow(m); k <- ncol(m)  # n subjects, k raters
  grand_mean <- mean(m)
  row_means <- rowMeans(m); col_means <- colMeans(m)
  SS_total <- sum((m - grand_mean)^2)
  SSR <- sum((row_means - grand_mean)^2) * k
  SSC <- sum((col_means - grand_mean)^2) * n
  SSE <- SS_total - SSR - SSC
  MSR <- SSR / (n - 1)   # between-subject MS
  MSC <- SSC / (k - 1)   # between-rater MS
  MSE <- SSE / ((n - 1) * (k - 1))  # error MS
  # ICC(2,1) = (MSC - MSE) / (MSC + (k-1)*MSE + (k/n)*(MSR-MSE))
  icc2 <- (MSC - MSE) / (MSC + (k - 1) * MSE + (k / n) * (MSR - MSE))
  if (is.na(icc2) || icc2 < -1 || icc2 > 1) return(NA_real_)
  icc2
}

# ============================================================================
# A. 热身课次级（来自192课次数据）
# ============================================================================
cat("\n--- 热身课次级 ---\n")

# 提取 GA+App 同时非空的行
warmup <- mon |>
  filter(!is.na(GA) & !is.na(App)) |>
  mutate(
    Diff  = App - GA,    # 正值=APP偏高
    Ratio = App / GA     # 比例偏差
  )

n_warmup    <- nrow(warmup)
n_warmup_ids <- length(unique(warmup$ID))

cat(sprintf("  有效配对：%d对（来自%d人）\n", n_warmup, n_warmup_ids))

# 描述性统计（逐值构建，避免 c() 内部函数调用导致崩溃）
w1 <- as.character(n_warmup)
w2 <- as.character(n_warmup_ids)
w3 <- sprintf("%.4f", mean(warmup$Diff))
w4 <- sprintf("%.4f", sd(warmup$Diff))
w5 <- sprintf("%.4f", mean(warmup$Diff) - 1.96 * sd(warmup$Diff))
w6 <- sprintf("%.4f", mean(warmup$Diff) + 1.96 * sd(warmup$Diff))
w7 <- sprintf("%.4f", mean(abs(warmup$Diff)))
w8 <- sprintf("%.4f", sqrt(mean(warmup$Diff^2)))
w9 <- sprintf("%.4f", cor(warmup$GA, warmup$App))
w10 <- as.character(sprintf("%.4f", suppressWarnings(icc_21(warmup$GA, warmup$App))))
w11 <- sprintf("%.1f%%", sum(warmup$Diff > 0) / n_warmup * 100)
w12 <- sprintf("%.1f%%", sum(abs(warmup$Diff) < 0.001) / n_warmup * 100)
w13 <- sprintf("%.1f%%", sum(warmup$Diff < 0) / n_warmup * 100)
warmup_vals <- c(w1, w2, w3, w4, w5, w6, w7, w8, w9, w10, w11, w12, w13)
warmup_labels <- c("n_pairs","n_subjects","Bias_mean","Bias_SD","LoA_low","LoA_high","MAE","RMSE","Pearson_r","ICC_2_1","prop_positive","prop_zero","prop_negative")
warmup_stats <- data.frame(指标 = warmup_labels, 热身级 = warmup_vals, stringsAsFactors = FALSE)

# Bootstrap ICC 95% CI（受试者级整簇重抽样）
cat("  Bootstrap ICC 95% CI（10000次重抽样）...\n")

set.seed(GLOBAL_SEED)
n_boot <- 2000
ids <- unique(warmup$ID)
icc_boot <- numeric(n_boot)

for (i in seq_len(n_boot)) {
  boot_ids <- sample(ids, replace = TRUE)
  boot_dat <- warmup[warmup$ID %in% boot_ids, ]
  icc_boot[i] <- icc_21(boot_dat$GA, boot_dat$App)
}

icc_ci_lo <- quantile(icc_boot, 0.025, na.rm = TRUE)
icc_ci_hi <- quantile(icc_boot, 0.975, na.rm = TRUE)

cat(sprintf("  ICC = %.4f, 95%% CI [%.4f, %.4f]\n",
  icc_21(warmup$GA, warmup$App), icc_ci_lo, icc_ci_hi))

warmup_stats$ICC_bootstrap_95CI <- c(
  NA, NA, NA, NA, NA, NA, NA, NA, NA, NA,
  sprintf("[%.4f, %.4f]", icc_ci_lo, icc_ci_hi),
  NA, NA
)

# 比例偏差回归
cat("  比例偏差检验...\n")
lm_ratio <- lm(Diff ~ GA, data = warmup)
ratio_p  <- coef(summary(lm_ratio))["GA", "Pr(>|t|)"]
cat(sprintf("  Diff ~ GA 斜率 = %.4f (p = %.4f)\n",
  coef(lm_ratio)["GA"], ratio_p))

# 每人配对数
pairs_per_subject <- warmup |>
  count(ID, name = "n_pairs") |>
  pull(n_pairs)

cat(sprintf("  每人配对数：均值=%.1f, 范围=%d–%d\n",
  mean(pairs_per_subject), min(pairs_per_subject), max(pairs_per_subject)))

# ============================================================================
# B. Rep级（来自43对数据）
# ============================================================================
cat("\n--- Rep级 ---\n")

n_rep    <- nrow(rep)
n_rep_ids <- length(unique(rep$ID))

cat(sprintf("  有效配对：%d对（来自%d人：%s）\n",
  n_rep, n_rep_ids, paste(unique(rep$ID), collapse = ", ")))

rep <- rep |>
  mutate(
    Diff  = App - GA,
    Ratio = App / GA
  )

r1 <- as.character(n_rep)
r2 <- as.character(n_rep_ids)
r3 <- sprintf("%.4f", mean(rep$Diff))
r4 <- sprintf("%.4f", sd(rep$Diff))
r5 <- sprintf("%.4f", mean(rep$Diff) - 1.96 * sd(rep$Diff))
r6 <- sprintf("%.4f", mean(rep$Diff) + 1.96 * sd(rep$Diff))
r7 <- sprintf("%.4f", mean(abs(rep$Diff)))
r8 <- sprintf("%.4f", sqrt(mean(rep$Diff^2)))
r9 <- sprintf("%.4f", cor(rep$GA, rep$App))
r10 <- as.character(sprintf("%.4f", suppressWarnings(icc_21(rep$GA, rep$App))))
r11 <- sprintf("%.1f%%", sum(rep$Diff > 0) / n_rep * 100)
r12 <- sprintf("%.1f%%", sum(abs(rep$Diff) < 0.001) / n_rep * 100)
r13 <- sprintf("%.1f%%", sum(rep$Diff < 0) / n_rep * 100)
rep_vals <- c(r1, r2, r3, r4, r5, r6, r7, r8, r9, r10, r11, r12, r13)
rep_labels   <- c("n_pairs","n_subjects","Bias_mean","Bias_SD","LoA_low","LoA_high","MAE","RMSE","Pearson_r","ICC_2_1","prop_positive","prop_zero","prop_negative")
rep_stats <- data.frame(指标 = rep_labels, Rep级 = rep_vals, stringsAsFactors = FALSE)

# Bootstrap ICC
set.seed(GLOBAL_SEED)
ids_rep <- unique(rep$ID)
icc_boot_rep <- numeric(n_boot)

for (i in seq_len(n_boot)) {
  boot_ids <- sample(ids_rep, replace = TRUE)
  boot_dat <- rep[rep$ID %in% boot_ids, ]
  icc_boot_rep[i] <- icc_21(boot_dat$GA, boot_dat$App)
}

icc_ci_lo_rep <- quantile(icc_boot_rep, 0.025, na.rm = TRUE)
icc_ci_hi_rep <- quantile(icc_boot_rep, 0.975, na.rm = TRUE)

rep_stats$ICC_bootstrap_95CI <- c(
  NA, NA, NA, NA, NA, NA, NA, NA, NA, NA,
  sprintf("[%.4f, %.4f]", icc_ci_lo_rep, icc_ci_hi_rep),
  NA, NA
)

cat(sprintf("  ICC = %.4f, 95%% CI [%.4f, %.4f]\n",
  icc_21(rep$GA, rep$App), icc_ci_lo_rep, icc_ci_hi_rep))

# ============================================================================
# 合并结果表
# ============================================================================
agreement_table <- data.frame(
  指标   = warmup_stats$指标,
  热身级 = warmup_stats$热身级,
  Rep级  = rep_stats$Rep级,
  stringsAsFactors = FALSE
)

save_tbl(agreement_table, "table_app_ga_agreement")

# 每人配对数表
pairs_detail <- bind_rows(
  warmup |> count(ID, name = "n_pairs") |>
    mutate(层级 = "热身级"),
  rep |> count(ID, name = "n_pairs") |>
    mutate(层级 = "Rep级")
)

save_tbl(pairs_detail, "table_pairs_per_subject")

# ============================================================================
# Bland-Altman 图
# ============================================================================
cat("绑制 Bland-Altman 图...\n")

# 热身级
p_ba_warmup <- warmup |>
  mutate(Mean = (GA + App) / 2) |>
  ggplot(aes(x = Mean, y = Diff)) +
  geom_hline(yintercept = mean(warmup$Diff), color = "blue", linetype = "solid") +
  geom_hline(yintercept = mean(warmup$Diff) + 1.96 * sd(warmup$Diff),
             color = "red", linetype = "dashed") +
  geom_hline(yintercept = mean(warmup$Diff) - 1.96 * sd(warmup$Diff),
             color = "red", linetype = "dashed") +
  geom_point(alpha = 0.6, size = 2) +
  labs(
    x = "Mean (m/s)",
    y = "App - GA (m/s)",
    title = sprintf("热身级 Bland-Altman (n=%d对)", n_warmup)
  ) +
  theme_thesis

# Rep级
p_ba_rep <- rep |>
  mutate(Mean = (GA + App) / 2) |>
  ggplot(aes(x = Mean, y = Diff)) +
  geom_hline(yintercept = mean(rep$Diff), color = "blue", linetype = "solid") +
  geom_hline(yintercept = mean(rep$Diff) + 1.96 * sd(rep$Diff),
             color = "red", linetype = "dashed") +
  geom_hline(yintercept = mean(rep$Diff) - 1.96 * sd(rep$Diff),
             color = "red", linetype = "dashed") +
  geom_point(alpha = 0.6, size = 2) +
  labs(
    x = "Mean (m/s)",
    y = "App - GA (m/s)",
    title = sprintf("Rep级 Bland-Altman (n=%d对)", n_rep)
  ) +
  theme_thesis

p_combined <- p_ba_warmup + p_ba_rep
save_fig(p_combined, "fig_ba_combined", w = 16, h = 8)

cat("✓ App-GA 一致性分析完成\n")
