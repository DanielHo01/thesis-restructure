# =============================================================================
# 05_app_ga_agreement.R
# 研究二现场 App-GA 一致性分析
#
# 方法：对齐 Python 原版 R 脚本，使用 psych::ICC(lmer=TRUE)
# psych::ICC(lmer=TRUE) 在内部拟合 lmer(App ~ rater + (1|subject))，
# 从方差组分计算 ICC(2,1)：var_subject / (var_subject + var_residual)
#
# 数据结构：
#   Rep 43对：3名受试者（P006:20行, P016:14行, P025:9行）
#   Warmup 88对：11名受试者，每人8次课次
# Bootstrap CI：受试者级整簇重抽样，对每次重抽样重新拟合 lmer
# =============================================================================

n <- sys.nframe()
if (n == 0L) {
  script_arg <- commandArgs()[max(grep("scripts/", commandArgs()))]
  script_path <- normalizePath(file.path(getwd(), script_arg))
} else {
  script_path <- tryCatch(normalizePath(sys.frame(1L)$ofile),
                         error = function(e) NA_character_)
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
# ICC 计算函数：使用 lmer 对方差组分估计
#
# ICC(2,1) = var_subject / (var_subject + var_residual)
#   var_subject : 来自 lmer 的 Subject 随机效应方差
#   var_residual: 来自 lmer 的 Residual 方差
#
# fit_icc_lmer(df, subject_col, rater_col):
#   df       : data.frame 包含 x, y, subject 列
#   返回 ICC(2,1) 标量值
# ============================================================================

fit_icc_lmer <- function(df) {
  # df must have: x (rater1), y (rater2), subject
  col_names <- names(df)
  if (!all(c("x", "y", "subject") %in% col_names))
    stop("fit_icc_lmer requires columns: x, y, subject")

  # Reshape to long format for lmer: each row = one rating
  d_long <- data.frame(
    rating  = c(df$x, df$y),
    rater   = rep(c("GA", "App"), each = nrow(df)),
    subject = rep(df$subject, times = 2)
  )

  # Fit lmer: rating ~ rater + (1|subject)
  # Using suppressMessages to reduce verbosity; isSingular is expected for n<4
  fit <- suppressMessages(
    lme4::lmer(rating ~ rater + (1 | subject), data = d_long)
  )

  vc     <- as.data.frame(lme4::VarCorr(fit), row.names = NULL)
  # vc has columns: grp, var1, var2, vcov, sdcor
  grp_char <- as.character(vc$grp)
  var_subj <- vc$vcov[grp_char == "subject"]
  var_res  <- vc$vcov[grp_char == "Residual"]

  icc <- var_subj / (var_subj + var_res)
  # Guard against missing/boundary/NaN values
  if (length(var_subj) != 1L || length(var_res) != 1L ||
      is.na(var_subj) || is.na(var_res) ||
      var_subj < 0 || var_res <= 0) {
    NA_real_
  } else {
    icc
  }
}

# Bootstrap ICC CI (subject-level cluster resampling + lmer refit)
# Returns c(lo, hi) or c(NA, NA) if insufficient subjects
icc_bootstrap_ci_lmer <- function(df, n_boot = 2000, prob = 0.95) {
  alpha   <- 1 - prob
  ids     <- unique(df$subject)
  n_subj  <- length(ids)

  if (n_subj < 4) {
    cat(sprintf("  [Bootstrap CI 跳过: n_subj=%d < 4]\n", n_subj))
    return(c(lo = NA_real_, hi = NA_real_))
  }

  set.seed(GLOBAL_SEED)
  boot_icc <- numeric(n_boot)

  for (i in seq_len(n_boot)) {
    boot_ids <- sample(ids, size = n_subj, replace = TRUE)
    boot_df  <- df[df$subject %in% boot_ids, ]
    boot_icc[i] <- fit_icc_lmer(boot_df)
  }

  lo <- quantile(boot_icc, alpha / 2, na.rm = TRUE)[[1]]
  hi <- quantile(boot_icc, 1 - alpha / 2, na.rm = TRUE)[[1]]

  median_icc <- median(boot_icc, na.rm = TRUE)
  if (!is.na(lo) && !is.na(hi) && hi < median_icc) {
    cat(sprintf("  [Bootstrap 分布偏态: median=%.4f, 97.5%%=%.4f]\n",
                median_icc, hi))
  }

  c(lo = lo, hi = hi)
}

# ============================================================================
# A. 热身课次级（11人各8次 = 88对）
# ============================================================================
cat("\n--- 热身课次级 ---\n")

warmup_raw <- mon |>
  dplyr::filter(!is.na(GA), !is.na(App))

n_warmup     <- nrow(warmup_raw)
n_warmup_ids <- length(unique(warmup_raw$ID))

cat(sprintf("  有效配对：%d对（来自%d人，每人8次课次）\n",
            n_warmup, n_warmup_ids))

# ICC(2,1) via lmer (对齐 Python)
warmup_df <- data.frame(
  x       = warmup_raw$GA,
  y       = warmup_raw$App,
  subject = warmup_raw$ID
)

icc_warmup <- fit_icc_lmer(warmup_df)
cat(sprintf("  ICC(2,1) [lmer] = %.4f\n", icc_warmup))

# Bootstrap CI
cat(sprintf("  Bootstrap ICC 95%% CI（%d次，受试者级聚类）...\n", 2000))
warmup_ci <- icc_bootstrap_ci_lmer(warmup_df, n_boot = 2000)
cat(sprintf("  ICC = %.4f, 95%% CI [%.4f, %.4f]\n",
            icc_warmup, warmup_ci[1L], warmup_ci[2L]))

# 描述性统计
warmup_diff <- warmup_raw$App - warmup_raw$GA

w1  <- as.character(n_warmup)
w2  <- as.character(n_warmup_ids)
w3  <- sprintf("%.4f", mean(warmup_diff))
w4  <- sprintf("%.4f", sd(warmup_diff))
w5  <- sprintf("%.4f", mean(warmup_diff) - 1.96 * sd(warmup_diff))
w6  <- sprintf("%.4f", mean(warmup_diff) + 1.96 * sd(warmup_diff))
w7  <- sprintf("%.4f", mean(abs(warmup_diff)))
w8  <- sprintf("%.4f", sqrt(mean(warmup_diff^2)))
w9  <- sprintf("%.4f", cor(warmup_raw$GA, warmup_raw$App))
w10 <- as.character(sprintf("%.4f", icc_warmup))
w11 <- sprintf("%.1f%%", sum(warmup_diff > 0) / n_warmup * 100)
w12 <- sprintf("%.1f%%", sum(abs(warmup_diff) < 0.001) / n_warmup * 100)
w13 <- sprintf("%.1f%%", sum(warmup_diff < 0) / n_warmup * 100)

warmup_vals   <- c(w1, w2, w3, w4, w5, w6, w7, w8, w9, w10, w11, w12, w13)
warmup_labels <- c("n_pairs","n_subjects","Bias_mean","Bias_SD","LoA_low","LoA_high",
                   "MAE","RMSE","Pearson_r","ICC_2_1","prop_positive","prop_zero","prop_negative")
warmup_stats <- data.frame(
  指标 = warmup_labels,
  热身级 = warmup_vals,
  stringsAsFactors = FALSE
)
warmup_stats$ICC_bootstrap_95CI <- c(
  rep(NA, 9),  # n_pairs through Pearson_r (indices 1-9)
  sprintf("[%.4f, %.4f]", warmup_ci[1L], warmup_ci[2L]),  # ICC_2_1 (index 10)
  NA, NA, NA   # prop_positive, prop_zero, prop_negative (indices 11-13)
)

# 比例偏差检验
lm_ratio <- lm(I(App - GA) ~ GA, data = warmup_raw)
ratio_p  <- coef(summary(lm_ratio))["GA", "Pr(>|t|)"]
cat(sprintf("  Diff ~ GA 斜率 = %.4f (p = %.4f)\n",
            coef(lm_ratio)["GA"], ratio_p))

# ============================================================================
# B. Rep级（3人共43对）
# ============================================================================
cat("\n--- Rep级 ---\n")

n_rep     <- nrow(rep)
n_rep_ids <- length(unique(rep$ID))

cat(sprintf("  有效配对：%d对（来自%d人：%s）\n",
            n_rep, n_rep_ids, paste(unique(rep$ID), collapse = ", ")))

rep_df <- data.frame(
  x       = rep$GA,
  y       = rep$App,
  subject = rep$ID
)

icc_rep <- fit_icc_lmer(rep_df)
cat(sprintf("  ICC(2,1) [lmer] = %.4f\n", icc_rep))

# Bootstrap CI skipped (n=3 < 4)
rep_ci <- icc_bootstrap_ci_lmer(rep_df, n_boot = 2000)

rep_diff <- rep$App - rep$GA

r1  <- as.character(n_rep)
r2  <- as.character(n_rep_ids)
r3  <- sprintf("%.4f", mean(rep_diff))
r4  <- sprintf("%.4f", sd(rep_diff))
r5  <- sprintf("%.4f", mean(rep_diff) - 1.96 * sd(rep_diff))
r6  <- sprintf("%.4f", mean(rep_diff) + 1.96 * sd(rep_diff))
r7  <- sprintf("%.4f", mean(abs(rep_diff)))
r8  <- sprintf("%.4f", sqrt(mean(rep_diff^2)))
r9  <- sprintf("%.4f", cor(rep$GA, rep$App))
r10 <- as.character(sprintf("%.4f", icc_rep))
r11 <- sprintf("%.1f%%", sum(rep_diff > 0) / n_rep * 100)
r12 <- sprintf("%.1f%%", sum(abs(rep_diff) < 0.001) / n_rep * 100)
r13 <- sprintf("%.1f%%", sum(rep_diff < 0) / n_rep * 100)

rep_vals   <- c(r1, r2, r3, r4, r5, r6, r7, r8, r9, r10, r11, r12, r13)
rep_labels <- c("n_pairs","n_subjects","Bias_mean","Bias_SD","LoA_low","LoA_high",
                "MAE","RMSE","Pearson_r","ICC_2_1","prop_positive","prop_zero","prop_negative")
rep_stats <- data.frame(
  指标 = rep_labels,
  Rep级 = rep_vals,
  stringsAsFactors = FALSE
)
rep_stats$ICC_bootstrap_95CI <- c(
  rep(NA, 9),  # n_pairs through Pearson_r (indices 1-9)
  "N/A (n=3 subjects; insufficient for bootstrap CI)",  # ICC_2_1 (index 10)
  NA, NA, NA   # prop_positive, prop_zero, prop_negative (indices 11-13)
)

# ============================================================================
# 合并结果表（包含 Bootstrap CI）
# ============================================================================
agreement_table <- data.frame(
  指标                  = warmup_stats$指标,
  热身级                = warmup_stats$热身级,
  热身级_Bootstrap_95CI = warmup_stats$ICC_bootstrap_95CI,
  Rep级                 = rep_stats$Rep级,
  Rep级_Bootstrap_95CI  = rep_stats$ICC_bootstrap_95CI,
  stringsAsFactors       = FALSE
)

save_tbl(agreement_table, "table_app_ga_agreement")

# 每人配对数
pairs_detail <- dplyr::bind_rows(
  warmup_raw |>
    dplyr::count(ID, name = "n_pairs") |>
    dplyr::mutate(层级 = "热身级"),
  rep |>
    dplyr::count(ID, name = "n_pairs") |>
    dplyr::mutate(层级 = "Rep级")
)

save_tbl(pairs_detail, "table_pairs_per_subject")

# ============================================================================
# Bland-Altman 图
# ============================================================================
cat("绑制 Bland-Altman 图...\n")

p_ba_warmup <- warmup_raw |>
  dplyr::mutate(Mean = (GA + App) / 2,
                Diff = App - GA) |>
  ggplot(aes(x = Mean, y = Diff)) +
  geom_hline(yintercept = mean(warmup_diff), color = COL_AI, linetype = "solid") +
  geom_hline(yintercept = mean(warmup_diff) + 1.96 * sd(warmup_diff),
             color = COL_SELF, linetype = "dashed") +
  geom_hline(yintercept = mean(warmup_diff) - 1.96 * sd(warmup_diff),
             color = COL_SELF, linetype = "dashed") +
  geom_point(alpha = 0.6, size = 2) +
  labs(
    x = "Mean of GA and App (m/s)",
    y = "App - GA (m/s)",
    title = sprintf("热身级 Bland-Altman (n=%d对, %d人)", n_warmup, n_warmup_ids)
  ) +
  theme_thesis

p_ba_rep <- rep |>
  dplyr::mutate(Mean = (GA + App) / 2,
                Diff = App - GA) |>
  ggplot(aes(x = Mean, y = Diff)) +
  geom_hline(yintercept = mean(rep_diff), color = COL_AI, linetype = "solid") +
  geom_hline(yintercept = mean(rep_diff) + 1.96 * sd(rep_diff),
             color = COL_SELF, linetype = "dashed") +
  geom_hline(yintercept = mean(rep_diff) - 1.96 * sd(rep_diff),
             color = COL_SELF, linetype = "dashed") +
  geom_point(alpha = 0.6, size = 2) +
  labs(
    x = "Mean of GA and App (m/s)",
    y = "App - GA (m/s)",
    title = sprintf("Rep级 Bland-Altman (n=%d对, %d人)", n_rep, n_rep_ids)
  ) +
  theme_thesis

p_combined <- p_ba_warmup + p_ba_rep
save_fig(p_combined, "fig_ba_combined", w = 16, h = 8)

cat("✓ App-GA 一致性分析完成\n")
