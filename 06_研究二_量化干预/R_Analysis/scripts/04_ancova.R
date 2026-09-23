# =============================================================================
# 04_ancova.R
# 五项探索性结局 ANCOVA
# 模型：Post ~ Group + Pre + Stratum
# 采用 Type III SS
# =============================================================================

source(file.path(PATH_SCRIPTS, "00_setup.R"))
PATH_SCRIPTS <- file.path(ROOT, "scripts")
PATH_CLEAN   <- file.path(ROOT, "data_clean")
source(file.path(PATH_SCRIPTS, "01_import_clean.R"))

cat("\n=== 04 ANCOVA 主要结局分析 ===\n")

main <- readRDS(file.path(PATH_CLEAN, "main.rds"))

# ============================================================================
# 五项结局定义
# ============================================================================
outcomes <- list(
  list(var = "Post1RM",    pre = "Pre1RM",    label = "深蹲绝对1RM",    unit = "kg"),
  list(var = "Rel1RM_t1",  pre = "Rel1RM_t0",  label = "深蹲相对1RM",    unit = "kg/kg"),
  list(var = "PostCMJ",    pre = "PreCMJ",     label = "CMJ高度",         unit = "cm"),
  list(var = "PostSJ",     pre = "PreSJ",      label = "SJ高度",          unit = "cm"),
  list(var = "PostSE",     pre = "PreSE",      label = "训练自我效能",    unit = "分")
)

# ============================================================================
# 主 ANCOVA
# ============================================================================
cat("拟合 ANCOVA 模型...\n\n")

ancova_results <- map_dfr(outcomes, function(o) {
  fmla <- as.formula(paste0(o$var, " ~ Group + ", o$pre, " + Stratum"))

  fit <- lm(fmla, data = main)

  # Type III SS
  anova_res <- car::Anova(fit, type = "III") |>
    as.data.frame() |>
    rownames_to_column("term")

  # 组间效应
  group_coef <- coef(fit)["GroupAI组"]
  group_p    <- summary(fit)$coefficients["GroupAI组", "Pr(>|t|)"]

  # 置信区间（对原始残差自由度）
  ci <- confint(fit)["GroupAI组", ]

  # 调整后组间均值（emmeans）
  # 固定 Pre 和 Stratum 在各自均值
  pre_mean   <- mean(main[[o$pre]], na.rm = TRUE)
  stratum_rep <- table(main$Stratum) / nrow(main)

  # 近似调整后均值：使用模型截距 + 组效应（协变量居中）
  pre_cen <- main[[o$pre]] - pre_mean

  # 重新拟合（协变量中心化）
  fmla_c  <- as.formula(paste0(o$var, " ~ Group + scale(", o$pre, ") + Stratum"))
  fit_c   <- lm(fmla_c, data = main)
  group_coef_c <- coef(fit_c)["GroupAI组"]
  ci_c   <- confint(fit_c)["GroupAI组", ]

  # 组别均值（观察值）
  ai_mean_t0   <- mean(main |> filter(Group == "AI组")   |> pull(!!sym(o$pre)), na.rm = TRUE)
  ai_mean_t1   <- mean(main |> filter(Group == "AI组")   |> pull(!!sym(o$var)), na.rm = TRUE)
  self_mean_t0 <- mean(main |> filter(Group == "Self组") |> pull(!!sym(o$pre)), na.rm = TRUE)
  self_mean_t1 <- mean(main |> filter(Group == "Self组") |> pull(!!sym(o$var)), na.rm = TRUE)

  tibble(
    结局          = o$label,
    单位          = o$unit,
    n             = sum(!is.na(main[[o$var]]) & !is.na(main[[o$pre]])),
    n_AI          = sum(!is.na(main |> filter(Group == "AI组")   |> pull(!!sym(o$var)))),
    n_Self        = sum(!is.na(main |> filter(Group == "Self组") |> pull(!!sym(o$var)))),
    AI_T0_mean    = sprintf("%.2f", ai_mean_t0),
    AI_T1_mean    = sprintf("%.2f", ai_mean_t1),
    Self_T0_mean  = sprintf("%.2f", self_mean_t0),
    Self_T1_mean  = sprintf("%.2f", self_mean_t1),
    调整后差值    = sprintf("%.2f", group_coef_c),
    差值_95CI_lo  = sprintf("%.2f", ci_c[1]),
    差值_95CI_hi  = sprintf("%.2f", ci_c[2]),
    p值           = fmt_p(group_p),
    R2            = sprintf("%.3f", summary(fit)$r.squared)
  )
})

save_tbl(ancova_results, "table_ancova")

print(ancova_results)

# ============================================================================
# 变化量 Hedges' g（同时计算）
# ============================================================================
cat("\n计算 Hedges' g...\n")

g_results <- map_dfr(outcomes, function(o) {
  ai_d   <- main |> filter(Group == "AI组")   |> pull(!!sym(paste0("d", substr(o$var, 5, nchar(o$var)))))
  self_d <- main |> filter(Group == "Self组") |> pull(!!sym(paste0("d", substr(o$var, 5, nchar(o$var)))))

  # 只处理 Post 变化量（d变量）
  d_var <- switch(o$var,
    "Post1RM"   = "d1RM",
    "Rel1RM_t1" = "dRel1RM",
    "PostCMJ"   = "dCMJ",
    "PostSJ"    = "dSJ",
    "PostSE"    = "dSE"
  )

  ai_d   <- main |> filter(Group == "AI组")   |> pull(!!sym(d_var))
  self_d <- main |> filter(Group == "Self组") |> pull(!!sym(d_var))

  n1 <- length(ai_d); n2 <- length(self_d)
  pooled_sd <- sqrt(((n1 - 1) * var(ai_d) + (n2 - 1) * var(self_d)) / (n1 + n2 - 2))
  diff_mean <- mean(ai_d) - mean(self_d)

  # Hedges' g（小样本校正）
  J   <- 1 - 3 / (4 * (n1 + n2) - 9)
  g   <- J * diff_mean / pooled_sd

  # 非中心t置信区间（近似）
  se_g <- sqrt((n1 + n2) / (n1 * n2) + g^2 / (2 * (n1 + n2)))
  ci_lo <- g - 1.96 * se_g
  ci_hi <- g + 1.96 * se_g

  tibble(
    结局    = o$label,
    AI变化  = sprintf("%.2f ± %.2f", mean(ai_d), sd(ai_d)),
    Self变化= sprintf("%.2f ± %.2f", mean(self_d), sd(self_d)),
    Hedges_g = sprintf("%.2f", g),
    g_95CI   = sprintf("(%.2f, %.2f)", ci_lo, ci_hi),
    方向     = ifelse(g > 0, "AI>Self", "Self>AI")
  )
})

save_tbl(g_results, "table_effect_sizes")

# ============================================================================
# 模型诊断
# ============================================================================
cat("\n模型诊断...\n")

diagnostics <- map_dfr(outcomes, function(o) {
  fmla <- as.formula(paste0(o$var, " ~ Group + ", o$pre, " + Stratum"))
  fit  <- lm(fmla, data = main)

  res  <- residuals(fit)
  fit_v<- fitted(fit)

  # Shapiro-Wilk
  sw   <- shapiro.test(res)

  # 残差相关性（Durbin-Watson）
  dw   <- car::durbinWatsonTest(res)

  # 组间斜率同质性（交互项）
  int_fmla <- as.formula(paste0(o$var, " ~ Group * ", o$pre, " + Stratum"))
  int_fit   <- lm(int_fmla, data = main)
  int_p     <- coef(summary(int_fit))[paste0("GroupAI组:", o$pre), "Pr(>|t|)"]

  tibble(
    结局       = o$label,
    Shapiro_Wilk_p = fmt_p(sw$p.value),
    DW_stat     = sprintf("%.3f", dw$statistic),
    交互项_p    = fmt_p(int_p),
    残差正态性  = ifelse(sw$p.value > 0.05, "通过", "偏离"),
    斜率同质性  = ifelse(int_p > 0.05, "通过", "偏离")
  )
})

save_tbl(diagnostics, "table_ancova_diagnostics")

# ============================================================================
# BH 多重比较校正
# ============================================================================
cat("\nBH 多重比较校正...\n")

p_raw  <- as.numeric(ancova_results$p值)
p_char <- ancova_results$p值
p_num  <- as.numeric(p_char)
p_bh   <- p.adjust(p_num, method = "BH")

ancova_results$FDR_p值 <- sapply(p_bh, fmt_p)
ancova_results$显著性  <- ifelse(p_num < 0.05, "*", "")

save_tbl(ancova_results, "table_ancova")

# ============================================================================
# 森林图
# ============================================================================
cat("绑制森林图...\n")

forest_data <- ancova_results |>
  mutate(
    g_val     = as.numeric(g_results$Hedges_g),
    g_lo      = as.numeric(gsub("\\(|,|\\)", "",
                           str_extract(g_results$g_95CI, "\\([^,]+\\,"))),
    g_hi      = as.numeric(gsub("\\(|,|\\)", "",
                           str_extract(g_results$g_95CI, ",.+\\)"))),
    label_x   = paste0(调整后差值, " ", p值)
  )

p_forest <- ggplot(ancova_results,
                    aes(x = reorder(结局, as.numeric(Hedges_g$Hedges_g)),
                        y = as.numeric(Hedges_g$Hedges_g))) +
  geom_point(size = 3, color = COL_AI) +
  geom_errorbar(
    aes(ymin = as.numeric(gsub("\\(|,|\\)", "",
                        str_extract(g_results$g_95CI, "\\([^,]+\\,"))),
        ymax = as.numeric(gsub("\\(|,|\\)", "",
                        str_extract(g_results$g_95CI, ",.+\\)")))),
    width = 0.2, color = COL_AI
  ) +
  geom_hline(yintercept = 0, linetype = "dashed", color = "gray50") +
  coord_flip() +
  labs(
    x = "", y = "Hedges' g (AI - Self)",
    title = "五项探索性结局标准化效应量森林图"
  ) +
  theme_thesis +
  theme(legend.position = "none")

save_fig(p_forest, "fig_outcome_forest", w = 14, h = 10)

cat("✓ ANCOVA 分析完成\n")
