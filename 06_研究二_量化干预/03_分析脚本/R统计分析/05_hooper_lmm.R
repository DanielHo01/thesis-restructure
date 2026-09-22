# =============================================================================
# 05_hooper_lmm.R
# -----------------------------------------------------------------------------
# 目的：Hooper 主观恢复指数的线性混合效应模型分析
# 依赖：00_setup.R, 01_data_load.R
# 输出：
#   - outputs/tables/T4-4_hooper_lmm_anova.csv
#   - outputs/tables/T4-4a_hooper_emmeans_by_session.csv
#   - outputs/tables/T4-4b_hooper_descriptive.csv
#   - outputs/figures/F4-4_hooper_trend.pdf/png
#   - outputs/reports/05_hooper_lmm_summary.txt
# 对应论文：第 4 章 4.5 节 Hooper 训练过程变化
# 方法学：
#   • 模型：lmer(Hooper_final ~ Group * Sess_factor + (1|ID))
#   • 检验：Kenward-Roger Type III ANOVA
#   • 事后比较：emmeans (pairwise by session)
#   • 图表：组别 × 课次趋势图
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始 Hooper LMM 分析 ==========\n")

# ---- 1. 数据准备 ----
# Sess 是字符型 "S1"-"S8"，需要转换为有序因子
sess_order <- paste0("S", 1:8)

df_hooper <- df_monitor %>%
  select(ID, Group, Sess, Week,
         Sleep, Stress, Fatigue, Soreness,
         Sleep_reversed, Hooper_final, HooperTot) %>%
  mutate(
    # Group 已经是 "AI组"/"Self组" 因子（来自 01_data_load.R）
    # 创建 Sess 的有序因子版本
    Sess_factor = factor(Sess, levels = sess_order, ordered = FALSE),
    # 数值型 Sess（1-8，用于模型 B）
    Sess_num = as.integer(gsub("S", "", Sess)),
    # ID 作为因子
    ID = factor(ID)
  ) %>%
  filter(!is.na(Hooper_final))

cat("→ Hooper 数据：", nrow(df_hooper), "行（",
    n_distinct(df_hooper$ID), "人 ×",
    n_distinct(df_hooper$Sess), "课次）\n")
cat("  Group levels:", levels(df_hooper$Group), "\n")
cat("  Sess_factor levels:", levels(df_hooper$Sess_factor), "\n")

# 描述统计
hooper_desc <- df_hooper %>%
  group_by(Group, Sess_factor) %>%
  summarise(
    n = n(),
    Mean = mean(Hooper_final, na.rm = TRUE),
    SD = sd(Hooper_final, na.rm = TRUE),
    Median = median(Hooper_final, na.rm = TRUE),
    Min = min(Hooper_final, na.rm = TRUE),
    Max = max(Hooper_final, na.rm = TRUE),
    .groups = "drop"
  ) %>%
  arrange(Group, Sess_factor)

cat("\n→ Hooper 描述统计（按组别 × 课次）：\n")
print(hooper_desc)

# ---- 2. 模型 A（Sess 因子型 + 交互项）----
cat("\n", paste(rep("=", 70), collapse=""), "\n")
cat("【模型 A】Sess 因子型 + Group × Sess 交互\n")
cat(paste(rep("=", 70), collapse=""), "\n")

model_A <- lmerTest::lmer(
  Hooper_final ~ Group * Sess_factor + (1 | ID),
  data = df_hooper,
  REML = TRUE,
  control = lmerControl(optimizer = "bobyqa",
                       optCtrl = list(maxfun = 100000))
)

cat("✓ 模型 A 拟合成功\n")

cat("\n---- 模型 A 摘要 ----\n")
print(summary(model_A))

# ---- 3. Kenward-Roger Type III ANOVA ----
cat("\n→ 执行 Kenward-Roger Type III ANOVA...\n")

anova_A <- anova(model_A, type = "III", ddf = "Kenward-Roger")

cat("\n---- Kenward-Roger ANOVA 结果（模型 A）----\n")
print(anova_A)

anova_A_df <- as.data.frame(anova_A) %>%
  rownames_to_column("Effect") %>%
  mutate(
    p_fmt = format_p(`Pr(>F)`)
  )

cat("\n---- ANOVA 表（整理版）----\n")
print(knitr::kable(anova_A_df, format = "pipe", digits = 4))

# ---- 4. 模型 B（Sess 数值型，与 Python 可比）----
cat("\n", paste(rep("=", 70), collapse=""), "\n")
cat("【模型 B】Sess 数值型 + Group × Sess 交互\n")
cat(paste(rep("=", 70), collapse=""), "\n")

model_B <- lmerTest::lmer(
  Hooper_final ~ Group * Sess_num + (1 | ID),
  data = df_hooper,
  REML = TRUE,
  control = lmerControl(optimizer = "bobyqa",
                       optCtrl = list(maxfun = 100000))
)

cat("✓ 模型 B 拟合成功\n")

# 模型 B 的多种 df 近似方法
anova_B_kr  <- anova(model_B, type = "III", ddf = "Kenward-Roger")
anova_B_sw  <- anova(model_B, type = "III", ddf = "Satterthwaite")

cat("\n---- 模型 B（Satterthwaite df）----\n")
print(anova_B_sw)

# ---- 5. 与 Python 结果对比 ----
cat("\n", paste(rep("=", 70), collapse=""), "\n")
cat("【与 Python 结果对比】\n")
cat(paste(rep("=", 70), collapse=""), "\n\n")

cat("Python 结果（statsmodels，渐近 Wald χ²）：\n")
cat("  组别主效应：χ²(1) = 0.67, p = 0.412\n")
cat("  课次主效应：χ²(7) = 11.47, p = 0.119\n")
cat("  组别×课次交互：χ²(7) = 2.22, p = 0.947\n\n")

cat("R 结果（lmerTest，Kenward-Roger F）：\n")
cat("模型 A（Sess 因子型）：\n")
for (i in 1:nrow(anova_A)) {
  row <- anova_A_df[i, ]
  cat(sprintf("  %s：F(%d, %.1f) = %.3f, p = %s\n",
               row$Effect, row$NumDF, row$DenDF,
               row$`F value`, row$p_fmt))
}

cat("\n模型 B（Sess 数值型，Satterthwaite）：\n")
b_sw_df <- as.data.frame(anova_B_sw) %>% rownames_to_column("Effect")
for (i in 1:nrow(b_sw_df)) {
  row <- b_sw_df[i, ]
  cat(sprintf("  %s：F(%d, %.1f) = %.3f, p = %s\n",
               row$Effect, row$NumDF, row$DenDF,
               row$`F value`, format_p(row$`Pr(>F)`)))
}

cat("\n⚠ 解读说明：\n")
cat("• Wald χ² 与 KR F 的检验统计量不可直接比较\n")
cat("• 应关注结论方向：是否都 p > 0.05（均不显著）\n")
cat("• KR 修正对 df 的影响是本批次核查的核心\n")

# ---- 6. 各课次组间差异（emmeans）----
cat("\n", paste(rep("=", 70), collapse=""), "\n")
cat("【各课次组间差异】\n")
cat(paste(rep("=", 70), collapse=""), "\n\n")

emm_by_sess <- emmeans(model_A, ~ Group | Sess_factor)
pairwise_sess <- pairs(emm_by_sess, adjust = "BH")

pairwise_sess_df <- as.data.frame(pairwise_sess) %>%
  mutate(p_BH_fmt = format_p(p.value))

cat("---- 各课次 AI vs. Self 组间差异（BH 校正）----\n")
print(knitr::kable(pairwise_sess_df %>%
                     select(Sess_factor, estimate, SE, df, t.ratio,
                            p.value, p_BH_fmt),
                   format = "pipe", digits = 3))

# 跨课次平均边际均值
emm_overall <- emmeans(model_A, ~ Group)
overall_cont <- pairs(emm_overall, adjust = "none", infer = c(TRUE, TRUE))

cat("\n---- 跨课次平均组间差异（未校正）----\n")
print(as.data.frame(overall_cont))

# ---- 7. 模型诊断 ----
cat("\n", paste(rep("=", 70), collapse=""), "\n")
cat("【模型诊断】\n")
cat(paste(rep("=", 70), collapse=""), "\n\n")

# 残差正态性
resid_A <- residuals(model_A)
sw_p <- shapiro.test(resid_A)$p.value
cat("  残差 Shapiro-Wilk：W p =", format_p(sw_p), "\n")
cat("  诊断：", if(sw_p > 0.05) "✓ 残差正态" else "⚠ 残差偏离正态", "\n")

# 方差分量
vc <- as.data.frame(VarCorr(model_A))
cat("\n  方差分量：\n")
print(vc)

# ICC
if (requireNamespace("performance", quietly = TRUE)) {
  icc_val <- performance::icc(model_A)
  cat("\n  ICC（条件）=", sprintf("%.3f", icc_val$ICC_adjusted), "\n")
}

# R²
if (requireNamespace("performance", quietly = TRUE)) {
  r2_val <- performance::r2(model_A)
  cat("  边际 R² =", sprintf("%.3f", r2_val$R2_marginal), "\n")
  cat("  条件 R² =", sprintf("%.3f", r2_val$R2_conditional), "\n")
}

# ---- 8. 趋势图 ----
cat("\n→ 生成 Hooper 趋势图...\n")

# 计算 SE
hooper_desc <- hooper_desc %>%
  mutate(SE = SD / sqrt(n))

hooper_plot <- ggplot(hooper_desc,
                      aes(x = Sess_factor, y = Mean,
                          color = Group, group = Group,
                          linetype = Group)) +
  geom_line(linewidth = 1.0, position = position_dodge(width = 0.2)) +
  geom_point(size = 3, position = position_dodge(width = 0.2)) +
  geom_errorbar(aes(ymin = Mean - SE, ymax = Mean + SE),
                width = 0.15, linewidth = 0.8,
                position = position_dodge(width = 0.2)) +
  scale_color_manual(values = GROUP_COLORS, labels = c("AI组", "Self组")) +
  scale_linetype_manual(values = c("solid", "dashed"), labels = c("AI组", "Self组")) +
  scale_x_discrete(breaks = sess_order, labels = sess_order) +
  labs(
    title = "Hooper 主观恢复指数变化趋势",
    subtitle = "均值 ± SE（组别 × 课次）",
    x = "训练课次", y = "Hooper 总分",
    color = "组别", linetype = "组别"
  ) +
  theme_thesis +
  theme(
    legend.position = c(0.85, 0.85),
    panel.grid.minor.x = element_blank()
  )

save_plot(hooper_plot, "F4-4_hooper_trend",
           width = 16, height = 10)

# ---- 9. 保存输出 ----
save_table(anova_A_df, "T4-4_hooper_lmm_anova")
save_table(pairwise_sess_df, "T4-4a_hooper_emmeans_by_session")
save_table(hooper_desc, "T4-4b_hooper_descriptive")

# ---- 10. 完整报告 ----
report_path <- file.path(PATH_REPORTS, "05_hooper_lmm_summary.txt")
sink(report_path)
cat("========== Hooper LMM 分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n")
cat("样本：", n_distinct(df_hooper$ID), "人 ×",
    n_distinct(df_hooper$Sess), "课次 =", nrow(df_hooper), "行\n\n")

cat("【方法学】\n")
cat("• 模型 A：lmer(Hooper_final ~ Group * Sess_factor + (1|ID))\n")
cat("• 模型 B：lmer(Hooper_final ~ Group * Sess_num + (1|ID))\n")
cat("• 主检验：Kenward-Roger Type III ANOVA\n")
cat("• 备选检验：Satterthwaite Type III ANOVA\n")
cat("• 事后比较：emmeans pairwise (BH 校正)\n\n")

cat("【模型 A 结果（Sess 因子型，Kenward-Roger）】\n")
print(anova_A)
cat("\n")

cat("【模型 B 结果（Sess 数值型，Satterthwaite）】\n")
print(anova_B_sw)
cat("\n")

cat("【各课次组间差异】\n")
print(knitr::kable(pairwise_sess_df, format = "pipe", digits = 3))
cat("\n")

cat("【跨课次平均组间差异】\n")
print(as.data.frame(overall_cont))
cat("\n")

cat("【模型诊断】\n")
cat("残差正态性 Shapiro-Wilk p =", format_p(sw_p), "\n")
print(vc)
sink()

cat("\n✓ Hooper LMM 分析完成\n")
cat("  报告路径：", report_path, "\n")
