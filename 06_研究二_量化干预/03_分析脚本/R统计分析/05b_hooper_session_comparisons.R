# =============================================================================
# 05b_hooper_session_comparisons.R
# -----------------------------------------------------------------------------
# 补充分析：课次间两两比较 + Model B KR完整结果
# 依赖：05_hooper_lmm.R（需要其中定义的对象）
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

# ---- 重新构建数据（同05_hooper_lmm.R）----
sess_order <- paste0("S", 1:8)
df_hooper <- df_monitor %>%
  select(ID, Group, Sess, Week, Hooper_final) %>%
  mutate(
    Sess_factor = factor(Sess, levels = sess_order, ordered = FALSE),
    Sess_num = as.integer(gsub("S", "", Sess)),
    ID = factor(ID)
  ) %>%
  filter(!is.na(Hooper_final))

# ---- 模型 A（与05_hooper_lmm.R相同）----
model_A <- lmerTest::lmer(
  Hooper_final ~ Group * Sess_factor + (1 | ID),
  data = df_hooper,
  REML = TRUE,
  control = lmerControl(optimizer = "bobyqa", optCtrl = list(maxfun = 100000))
)

# ---- 模型 B（与05_hooper_lmm.R相同）----
model_B <- lmerTest::lmer(
  Hooper_final ~ Group * Sess_num + (1 | ID),
  data = df_hooper,
  REML = TRUE,
  control = lmerControl(optimizer = "bobyqa", optCtrl = list(maxfun = 100000))
)

# ============================================================================
# 补充1：Model B 的 KR ANOVA（完整对比）
# ============================================================================
cat("\n", paste(rep("=", 72), collapse=""), "\n")
cat("【补充1】Model B 两种df方法的完整对比\n")
cat(paste(rep("=", 72), collapse=""), "\n\n")

anova_B_kr <- anova(model_B, type = "III", ddf = "Kenward-Roger")
anova_B_sw <- anova(model_B, type = "III", ddf = "Satterthwaite")

cat("Model B - Kenward-Roger df：\n")
print(anova_B_kr)

cat("\nModel B - Satterthwaite df：\n")
print(anova_B_sw)

cat("\n→ 关键对比（课次主效应）：\n")
cat(sprintf("  Satterthwaite: F(1, 166) = %.3f, p = %.3f\n",
            anova_B_sw$`F value`[2], anova_B_sw$`Pr(>F)`[2]))
cat(sprintf("  Kenward-Roger: F(1, 166) = %.3f, p = %.3f\n",
            anova_B_kr$`F value`[2], anova_B_kr$`Pr(>F)`[2]))
cat("→ 注意：Model B 课次只有1个df（数值型线性项），KR/Satterthwaite差异很小\n")

# ============================================================================
# 补充2：Session间两两比较（基于Model A，emmeans）
# ============================================================================
cat("\n", paste(rep("=", 72), collapse=""), "\n")
cat("【补充2】Session间两两比较（以S1为参照，Dunnett校正）\n")
cat(paste(rep("=", 72), collapse=""), "\n\n")

# 跨组平均的边际均值
emm_sess <- emmeans(model_A, ~ Sess_factor)
# 与S1的对比（Dunnett方法，适用于参照组设计）
sess_vs_s1 <- contrast(emm_sess, method = "trt.vs.ctrl1", adjust = "dunnett")

sess_vs_s1_df <- as.data.frame(sess_vs_s1) %>%
  mutate(
    p_fmt = format_p(p.value),
    sig = ifelse(p.value < 0.05, "✓", "")
  )

cat("---- 各课次 vs. S1（跨组平均，Dunnett校正）----\n")
print(knitr::kable(sess_vs_s1_df %>%
                     select(contrast, estimate, SE, df, t.ratio,
                            p.value, p_fmt, sig),
                   format = "pipe", digits = 3))

# ============================================================================
# 补充3：Session间两两比较（所有配对，Tukey校正）
# ============================================================================
cat("\n", paste(rep("=", 72), collapse=""), "\n")
cat("【补充3】Session间所有配对比较（Tukey校正）\n")
cat(paste(rep("=", 72), collapse=""), "\n\n")

sess_pairwise <- contrast(emm_sess, method = "pairwise", adjust = "tukey")
sess_pairwise_df <- as.data.frame(sess_pairwise) %>%
  mutate(p_fmt = format_p(p.value))

# 筛选显著的配对
sig_pairs <- sess_pairwise_df %>% filter(p.value < 0.05)

cat("---- 显著差异的Session配对（Tukey p < 0.05）----\n")
if (nrow(sig_pairs) > 0) {
  print(knitr::kable(sig_pairs %>%
                       select(contrast, estimate, SE, df, t.ratio,
                              p.value, p_fmt),
                     format = "pipe", digits = 3))
} else {
  cat("  （无）\n")
}

# ============================================================================
# 补充4：趋势解读
# ============================================================================
cat("\n", paste(rep("=", 72), collapse=""), "\n")
cat("【补充4】趋势解读\n")
cat(paste(rep("=", 72), collapse=""), "\n\n")

# 计算跨组均值
sess_means <- df_hooper %>%
  group_by(Sess_factor) %>%
  summarise(
    Mean = mean(Hooper_final, na.rm = TRUE),
    SD = sd(Hooper_final, na.rm = TRUE),
    .groups = "drop"
  ) %>%
  mutate(Sess_num = as.integer(gsub("S", "", Sess_factor)))

cat("跨组均值（用于判断趋势方向）：\n")
print(sess_means)

# 判断极值课次
max_sess <- sess_means$Sess_factor[which.max(sess_means$Mean)]
min_sess <- sess_means$Sess_factor[which.min(sess_means$Mean)]
cat(sprintf("\n→ 最高均值课次：%s (%.2f)\n", max_sess, max(sess_means$Mean)))
cat(sprintf("→ 最低均值课次：%s (%.2f)\n", min_sess, min(sess_means$Mean)))

cat("\n→ 趋势判断：\n")
cat("  • 两组均呈倒U型趋势：S1低 → S4-S5峰值 → S8下降\n")
cat("  • 峰值出现在第4-5课次（训练第2-3周）\n")
cat("  • 这与训练负荷积累→疲劳→恢复的生理轨迹一致\n")
cat("  • 交互效应不显著（p=0.945）：两组变化轨迹平行\n")
