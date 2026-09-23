# =============================================================================
# 03_hooper_lmm.R
# Hooper 主观恢复状态纵向混合效应模型
# 模型：Hooper_total ~ Group * Session + (1|ID)
# 采用 Kenward-Roger Type III F 检验
# =============================================================================

# 自动定位脚本目录，加载全局配置
if (!exists("ROOT")) {
  script_dir <- if (!is.null(sys.frame(1)$ofile)) dirname(normalizePath(sys.frame(1)$ofile)) else getwd()
  ROOT <- normalizePath(file.path(script_dir, ".."))
}
source(file.path(ROOT, "scripts", "01_import_clean.R"), encoding = "UTF-8")
cat("\n=== 03 Hooper LMM 分析 ===\n")

mon <- readRDS(file.path(PATH_CLEAN, "monitor.rds"))

# ============================================================================
# 模型拟合（REML，Kenward-Roger）
# ============================================================================
cat("拟合 LMM...\n")

mon <- mon |>
  mutate(
    Sess_f = ordered(Sess, levels = paste0("S", 1:8)),
    Sess_num = as.integer(Sess)
  )

fit <- lmerTest::lmer(
  Hooper_tot ~ Group * Sess_f + (1 | ID),
  data    = mon,
  REML    = TRUE,
  control = lmerControl(optimizer = "bobyqa")
)

# ============================================================================
# 方差分析表（Type III, Kenward-Roger）
# ============================================================================
# 使用 base R 直接构造表，避免列名歧义
anova_raw <- as.data.frame(anova(fit, type = "III"))
cat("DEBUG anova cols:", paste(colnames(anova_raw), collapse=", "), "\n")

anova_res <- data.frame(
  变异来源 = rownames(anova_raw),
  F值      = round(anova_raw[["F value"]], 2),
  NumDF    = anova_raw[["NumDF"]],
  DenDF    = round(anova_raw[["DenDF"]], 1),
  p值      = sapply(anova_raw[["Pr(>F)"]], fmt_p),
  stringsAsFactors = FALSE
)

save_tbl(anova_res, "table_hooper_lmm")

cat("  LMM Type III 方差分析：\n")
print(anova_res)

# ============================================================================
# 组间边际均值（emmeans）
# ============================================================================
cat("\n计算边际均值...\n")

# 组间主效应
emm_group <- emmeans(fit, ~ Group) |>
  summary() |>
  as.data.frame()

# 每课次组间比较
emm_sess <- emmeans(fit, ~ Group | Sess_f) |>
  contrast(method = "pairwise") |>
  summary() |>
  as.data.frame() |>
  select(-df, -t.ratio) |>
  rename(
    Sess        = Sess_f,
    AI组_均值   = AI组,
    Self组_均值 = Self组,
    差值        = estimate,
    SE         = SE,
    CI_lo = lower.CL,
    CI_hi = upper.CL,
    p值        = p.value
  )

# 每课次描述性均值
desc_sess <- mon |>
  group_by(Group, Sess_f) |>
  summarise(
    n      = n(),
    均值   = mean(Hooper_tot, na.rm = TRUE),
    SD     = sd(Hooper_tot, na.rm = TRUE),
    .groups = "drop"
  ) |>
  pivot_wider(names_from = Group, values_from = c(n, 均值, SD)) |>
  mutate(
    差值 = 均值_AI组 - 均值_Self组
  )

session_results <- desc_sess |>
  left_join(
    emm_sess |> select(Sess, 差值, `95%CI_lo`, `95%CI_hi`, p值),
    by = "Sess"
  ) |>
  mutate(
    p值 = fmt_p(as.numeric(p值))
  )

save_tbl(session_results, "table_hooper_sessions")

# BH 校正
p_raw <- as.numeric(session_results$p值)
p_raw_numeric <- p_raw[!is.na(p_raw)]
p_bh <- p.adjust(p_raw_numeric, method = "BH")
session_results$BH_p值 <- c(fmt_p(p_bh), rep("NA", sum(is.na(p_raw))))

# ============================================================================
# 图：Hooper 轨迹
# ============================================================================
cat("绑制 Hooper 轨迹图...\n")

p_trajectory <- mon |>
  group_by(ID, Group, Sess_f) |>
  summarise(hooper = mean(Hooper_tot, na.rm = TRUE), .groups = "drop") |>
  group_by(Group, Sess_f) |>
  summarise(
    mean = mean(hooper, na.rm = TRUE),
    se   = sd(hooper, na.rm = TRUE) / sqrt(n()),
    .groups = "drop"
  ) |>
  mutate(
    Sess_num = as.integer(gsub("S", "", Sess_f))
  ) |>
  ggplot(aes(x = Sess_num, y = mean, color = Group, linetype = Group)) +
  geom_line(linewidth = 1.2) +
  geom_point(size = 2) +
  geom_errorbar(aes(ymin = mean - 1.96 * se, ymax = mean + 1.96 * se),
                width = 0.2) +
  scale_color_manual(values = COLORS_GROUPS) +
  scale_x_continuous(breaks = 1:8, labels = paste0("S", 1:8)) +
  labs(
    x = "训练课次",
    y = "Hooper 总分（均值 ± 95% CI）",
    color = "组别", linetype = "组别"
  ) +
  theme_thesis

save_fig(p_trajectory, "fig_hooper_trajectory", w = 16, h = 10)

# ============================================================================
# 模型摘要
# ============================================================================
cat("\nLMM 模型摘要：\n")
cat("组别主效应 F =", round(summary(fit)$coefficients[
  grep("Group", rownames(summary(fit)$coefficients))[1], "t value"]^2, 2), "\n")
cat("ICC（个体间变异比例）≈",
    round(as.numeric(VarCorr(fit)[1]) /
          (as.numeric(VarCorr(fit)[1]) + sigma(fit)^2), 3), "\n")

cat("✓ Hooper LMM 分析完成\n")
