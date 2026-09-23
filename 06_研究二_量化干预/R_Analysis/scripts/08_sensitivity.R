# =============================================================================
# 08_sensitivity.R
# 留一法（LOO）敏感性分析 + 训练频率敏感性
# =============================================================================

source(file.path(PATH_SCRIPTS, "00_setup.R"))
PATH_SCRIPTS <- file.path(ROOT, "scripts")
PATH_CLEAN   <- file.path(ROOT, "data_clean")
source(file.path(PATH_SCRIPTS, "01_import_clean.R"))

cat("\n=== 08 敏感性分析 ===\n")

main <- readRDS(file.path(PATH_CLEAN, "main.rds"))

# ============================================================================
# A. 留一法（LOO）
# ============================================================================
cat("\n--- 留一法敏感性分析 ---\n")

loo_hedges_g <- function(data, outcome_var, pre_var, group_var = "Group") {
  # 全样本 Hedges' g
  ai_d   <- data |> filter(!!sym(group_var) == "AI组")   |> pull(outcome_var)
  self_d <- data |> filter(!!sym(group_var) == == "Self组") |> pull(outcome_var)
  # 修正：去掉多余的 ==
  ai_d   <- data |> filter(.data[[group_var]] == "AI组")   |> pull(outcome_var)
  self_d <- data |> filter(.data[[group_var]] == "Self组") |> pull(outcome_var)

  n1 <- length(ai_d); n2 <- length(self_d)
  pooled <- sqrt(((n1-1)*var(ai_d) + (n2-1)*var(self_d)) / (n1+n2-2))
  g_full <- (mean(ai_d) - mean(self_d)) / pooled * (1 - 3/(4*(n1+n2)-9))

  # LOO
  ids_ai   <- data |> filter(.data[[group_var]] == "AI组")   |> pull(ID)
  ids_self <- data |> filter(.data[[group_var]] == "Self组") |> pull(ID)

  g_loo_ai   <- sapply(ids_ai, function(id) {
    d <- data |> filter(.data[[group_var]] == "AI组", ID != id)
    ai_d   <- d |> pull(outcome_var)
    self_d <- data |> filter(.data[[group_var]] == "Self组") |> pull(outcome_var)
    n1 <- length(ai_d); n2 <- length(self_d)
    pooled <- sqrt(((n1-1)*var(ai_d) + (n2-1)*var(self_d)) / (n1+n2-2))
    (mean(ai_d) - mean(self_d)) / pooled * (1 - 3/(4*(n1+n2)-9))
  })

  g_loo_self <- sapply(ids_self, function(id) {
    d <- data |> filter(.data[[group_var]] == "Self组", ID != id)
    self_d <- d |> pull(outcome_var)
    ai_d   <- data |> filter(.data[[group_var]] == "AI组") |> pull(outcome_var)
    n1 <- length(ai_d); n2 <- length(self_d)
    pooled <- sqrt(((n1-1)*var(ai_d) + (n2-1)*var(self_d)) / (n1+n2-2))
    (mean(ai_d) - mean(self_d)) / pooled * (1 - 3/(4*(n1+n2)-9))
  })

  tibble(
    g_full = g_full,
    g_loo  = c(g_loo_ai, g_loo_self),
    id_loo = c(ids_ai, ids_self),
    diff    = g_loo - g_full
  )
}

# 需要计算变化量的辅助列
main <- main |>
  mutate(
    delta_1RM = Post1RM - Pre1RM,
    delta_CMJ  = PostCMJ - PreCMJ,
    delta_SJ   = PostSJ - PreSJ,
    delta_SE   = PostSE - PreSE
  )

outcomes_loo <- list(
  list(label = "Δ绝对1RM", var = "delta_1RM"),
  list(label = "ΔCMJ",     var = "delta_CMJ"),
  list(label = "ΔSJ",      var = "delta_SJ"),
  list(label = "Δ自我效能", var = "delta_SE")
)

loo_all <- map_dfr(outcomes_loo, function(o) {
  loo_hedges_g(main, o$var, NULL) |>
    mutate(结局 = o$label)
})

# 完整 LOO 表
loo_full <- loo_all |>
  group_by(结局) |>
  summarise(
    g_full       = first(g_full),
    g_loo_min   = min(g_loo),
    g_loo_max   = max(g_loo),
    g_loo_range = max(g_loo) - min(g_loo),
    max_drop_id  = id_loo[which.min(g_loo)],
    max_rise_id  = id_loo[which.max(g_loo)],
    max_drop    = min(diff),
    max_rise    = max(diff),
    drift_rate  = max(abs(diff)) / abs(first(g_full)) * 100)
  ) |>
  ungroup()

# 方向是否改变
loo_full <- loo_full |>
  mutate(
    方向改变 = case_when(
      g_full > 0 & g_loo_min > 0 ~ "无",
      g_full < 0 & g_loo_max < 0 ~ "无",
      TRUE ~ "改变"
    ),
    相对漂移率 = sprintf("%.1f%%", drift_rate * 100)
  ) |>
  select(结局, g_full, g_loo_min, g_loo_max, 方向改变, 相对漂移率,
         最大下降ID = max_drop_id, 最大上升ID = max_rise_id)

save_tbl(loo_full, "table_loo_summary")

# 每人LOO详细表
loo_detail <- loo_all |>
  mutate(g_loo = round(g_loo, 3), diff = round(diff, 3)) |>
  arrange(结局, id_loo)

save_tbl(loo_detail, "table_loo_detail")

cat("  LOO敏感性结果：\n")
print(loo_full)

# ============================================================================
# B. 训练频率敏感性分析
# ============================================================================
cat("\n--- 训练频率敏感性分析 ---\n")

# 频率数值化
main <- main |>
  mutate(
    Freq_val = case_when(
      Freq_wk == "≤1次/周" ~ 1,
      Freq_wk == "1次/周"   ~ 1,
      Freq_wk == "2次/周"   ~ 2,
      Freq_wk == "3次/周"   ~ 3,
      Freq_wk == "≥4次/周"  ~ 4,
      TRUE ~ NA_real_
    )
  )

cat_vars <- c("delta_1RM", "delta_CMJ", "delta_SJ", "delta_SE")
freq_results <- map_dfr(cat_vars, function(dv) {
  fmla <- as.formula(paste0(dv, " ~ Group + ", gsub("delta_", "", dv), " + Stratum + Freq_val"))

  fit <- tryCatch(
    lm(fmla, data = main),
    error = function(e) NULL
  )

  if (is.null(fit)) return(tibble(结局 = dv, group_p = NA, freq_p = NA, n = NA))

  tibble(
    结局    = dv,
    n       = nobs(fit),
    group_p = fmt_p(coef(summary(fit))["GroupAI组", "Pr(>|t|)"]),
    freq_p  = fmt_p(coef(summary(fit))["Freq_val", "Pr(>|t|)"])
  )
})

freq_results$结局 <- recode(freq_results$结局,
  "delta_1RM" = "Δ绝对1RM",
  "delta_CMJ" = "ΔCMJ",
  "delta_SJ"  = "ΔSJ",
  "delta_SE"  = "Δ自我效能"
)

names(freq_results) <- c("结局", "n", "组别_p值", "训练频率_p值")

save_tbl(freq_results, "table_frequency_sensitivity")

cat("  注意：训练频率存在错填，该分析仅作方向性参考\n")
cat("✓ 敏感性分析完成\n")
