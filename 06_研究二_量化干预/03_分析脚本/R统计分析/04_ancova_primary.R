# =============================================================================
# 04_ancova_primary.R
# -----------------------------------------------------------------------------
# 目的：主要结局的 ANCOVA 分析（5 项探索性连续结局）
# 依赖：00_setup.R, 01_data_load.R
# 输出：
#   - outputs/tables/T4-3_ancova_primary_results.csv
#   - outputs/tables/T4-3a_ancova_details_by_outcome.csv
#   - outputs/reports/04_ancova_summary.txt
# 对应论文：第 4 章 表 4-1 主要结局 ANCOVA
# 方法学：
#   • 模型：lm(Post ~ Group + Pre + Stratum)
#   • ANOVA 类型：Type III (car::Anova)
#   • 调整后差值：emmeans (pairwise contrast)
#   • 效应量：Hedges' g (基于变化值，含偏差校正)
#   • FDR 校正：Benjamini-Hochberg
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始主要结局 ANCOVA 分析 ==========\n")

# ---- 0. 数据预处理 ----
df_main <- df_main %>%
  mutate(Rel1RM_Post = Post1RM / BW_kg)

cat("→ Rel1RM_Post 已计算完成\n")

# ---- 1. 定义结局变量清单 ----
outcomes <- list(
  list(name = "Squat_Absolute_1RM",
       label = "深蹲绝对 1RM（kg）",
       pre = "Pre1RM", post = "Post1RM",
       category = "核心探索"),
  list(name = "Squat_Relative_1RM",
       label = "深蹲相对 1RM（kg/kg）",
       pre = "Rel1RM", post = "Rel1RM_Post",
       category = "核心探索"),
  list(name = "CMJ_Height",
       label = "CMJ 高度（cm）",
       pre = "PreCMJ", post = "PostCMJ",
       category = "核心探索"),
  list(name = "SJ_Height",
       label = "SJ 高度（cm）",
       pre = "PreSJ", post = "PostSJ",
       category = "附加探索"),
  list(name = "Training_Self_Efficacy",
       label = "训练自我效能",
       pre = "PreSE", post = "PostSE",
       category = "附加探索")
)

# ---- 2. 单结局 ANCOVA 函数 ----
run_ancova <- function(data, outcome) {

  pv <- outcome$post  # 因变量（后测）
  xv <- outcome$pre   # 协变量（前测）
  lbl <- outcome$label

  # ---- 描述统计 ----
  g <- data$Group
  pre_v  <- as.numeric(data[[xv]])
  post_v <- as.numeric(data[[pv]])

  ai_pre  <- pre_v[g == "AI组"];   slf_pre  <- pre_v[g == "Self组"]
  ai_post <- post_v[g == "AI组"];  slf_post <- post_v[g == "Self组"]

  ai_chg  <- ai_post - ai_pre;  slf_chg <- slf_post - slf_pre

  ai_n  <- sum(!is.na(ai_chg));  slf_n <- sum(!is.na(slf_chg))
  ai_mc <- mean(ai_chg, na.rm = TRUE);  ai_sc <- sd(ai_chg, na.rm = TRUE)
  sl_mc <- mean(slf_chg, na.rm = TRUE); sl_sc <- sd(slf_chg, na.rm = TRUE)

  # ---- ANCOVA ----
  fmla <- as.formula(sprintf("%s ~ Group + %s + Stratum", pv, xv))
  mod  <- lm(fmla, data = data)
  atbl <- car::Anova(mod, type = "III")

  # 提取 Group 行（固定第 2 行）
  grp_F   <- as.numeric(atbl[["F value"]][2])
  grp_df1  <- as.numeric(atbl[["Df"]][2])
  grp_p    <- as.numeric(atbl[["Pr(>F)"]][2])
  res_df   <- as.numeric(atbl[["Df"]][nrow(atbl)])

  # ---- 调整后差值 ----
  emm  <- emmeans(mod, ~ Group)
  cont <- as.data.frame(pairs(emm, reverse = FALSE, adjust = "none",
                              infer = c(TRUE, TRUE), level = 0.95))
  adj_diff  <- cont$estimate[1]
  adj_ci_lo <- cont$lower.CL[1]
  adj_ci_hi <- cont$upper.CL[1]
  adj_p     <- cont$p.value[1]

  # ---- Hedges' g ----
  pooled_sd <- sqrt(((ai_n - 1) * ai_sc^2 + (slf_n - 1) * sl_sc^2) /
                     max(ai_n + slf_n - 2, 1))
  g_val <- (ai_mc - sl_mc) / pooled_sd * (1 - 3 / (4 * (ai_n + slf_n) - 4))
  se_g  <- sqrt(1/ai_n + 1/slf_n + g_val^2 / (2 * (ai_n + slf_n)))
  g_lo  <- g_val - 1.96 * se_g
  g_hi  <- g_val + 1.96 * se_g

  # ---- 模型诊断 ----
  r2     <- summary(mod)$r.squared
  shap_p <- shapiro.test(residuals(mod))$p.value

  # ---- 返回 ----
  data.frame(
    Outcome     = lbl,
    Category    = outcome$category,
    AI_Pre_MSD = sprintf("%.2f ± %.2f", mean(ai_pre, na.rm=TRUE), sd(ai_pre, na.rm=TRUE)),
    AI_Pst_MSD = sprintf("%.2f ± %.2f", mean(ai_post,na.rm=TRUE), sd(ai_post,na.rm=TRUE)),
    AI_Change   = sprintf("%.2f ± %.2f", ai_mc, ai_sc),
    Sl_Pre_MSD  = sprintf("%.2f ± %.2f", mean(slf_pre, na.rm=TRUE), sd(slf_pre, na.rm=TRUE)),
    Sl_Pst_MSD  = sprintf("%.2f ± %.2f", mean(slf_post,na.rm=TRUE), sd(slf_post,na.rm=TRUE)),
    Sl_Change   = sprintf("%.2f ± %.2f", sl_mc, sl_sc),
    Adj_Diff    = sprintf("%.2f", adj_diff),
    Adj_CI      = sprintf("(%.2f, %.2f)", adj_ci_lo, adj_ci_hi),
    F_val       = sprintf("%.3f", grp_F),
    df          = sprintf("%d, %d", grp_df1, res_df),
    p_raw       = grp_p,
    p_fmt       = format_p(grp_p),
    Hedges_g    = sprintf("%.3f", g_val),
    g_CI        = sprintf("(%.3f, %.3f)", g_lo, g_hi),
    R2          = r2,
    Shap_p      = shap_p,
    stringsAsFactors = FALSE
  )
}

# ---- 3. 运行所有结局 ----
cat("→ 对", length(outcomes), "个结局逐一进行 ANCOVA...\n\n")

results <- purrr::map_dfr(outcomes, ~ run_ancova(df_main, .x))

# ---- 4. FDR 校正 ----
results <- results %>%
  mutate(p_FDR    = p.adjust(p_raw, method = "BH"),
         p_FDR_fmt = format_p(p_FDR))

# ---- 5. 打印主表 ----
cat(paste(rep("=", 78), collapse=""), "\n")
cat("【表 4-3】主要结局 ANCOVA 结果（PP 样本，n=24，AI组=11, Self组=13）\n")
cat(paste(rep("=", 78), collapse=""), "\n\n")

main_tbl <- results %>%
  select(结局=Outcome, 类别=Category,
         `AI T0`=AI_Pre_MSD, `AI T1`=AI_Pst_MSD, `AI Δ`=AI_Change,
         `Self T0`=Sl_Pre_MSD, `Self T1`=Sl_Pst_MSD, `Self Δ`=Sl_Change,
         `Δ差值`=Adj_Diff, `95%CI`=Adj_CI,
         F=F_val, df, p=p_fmt, `p(FDR)`=p_FDR_fmt,
         g=Hedges_g, `g 95%CI`=g_CI)

print(knitr::kable(main_tbl, format="pipe", align="l"))

# ---- 6. 模型诊断 ----
cat("\n", paste(rep("=", 78), collapse=""), "\n")
cat("【模型诊断】\n")
diag_tbl <- tibble(
  结局     = results$Outcome,
  R2       = sprintf("%.3f", results$R2),
  Shapiro_p = sprintf("%.3f", results$Shap_p),
  结论     = ifelse(results$Shap_p > 0.05,
                    "✓ 残差正态", "⚠ 残差偏离正态"))
print(diag_tbl)

# ---- 7. FDR 对照 ----
cat("\n", paste(rep("=", 78), collapse=""), "\n")
cat("【FDR 校正前后】\n")
fdr_tbl <- tibble(
  结局       = results$Outcome,
  原始_p     = sprintf("%.4f", results$p_raw),
  FDR后_p    = sprintf("%.4f", results$p_FDR),
  原始显著   = ifelse(results$p_raw < 0.05, "✓", "ns"),
  FDR后显著 = ifelse(results$p_FDR < 0.05, "✓", "ns"))
print(fdr_tbl)

# ---- 8. 详细结果 ----
cat("\n", paste(rep("=", 78), collapse=""), "\n")
for (i in seq_len(nrow(results))) {
  r <- results[i,]
  cat(sprintf("▶ %s [%s]\n", r$Outcome, r$Category))
  cat(sprintf("  F(%s)=%s, p=%s, p(FDR)=%s\n", r$df, r$F_val, r$p_fmt, r$p_FDR_fmt))
  cat(sprintf("  Hedges' g = %s %s\n", r$Hedges_g, r$g_CI))
  cat(sprintf("  调整后差值 = %s %s\n", r$Adj_Diff, r$Adj_CI))
  cat(sprintf("  模型 R² = %.3f, 残差正态性 p = %.3f\n\n", r$R2, r$Shap_p))
}

# ---- 9. 保存 ----
save_table(select(results, -p_raw, -p_FDR, -Shap_p, -R2),
           "T4-3_ancova_primary_results")
save_table(results, "T4-3a_ancova_details_by_outcome")

# ---- 10. 报告 ----
report_path <- file.path(PATH_REPORTS, "04_ancova_summary.txt")
sink(report_path)
cat("========== ANCOVA 主要结局分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n")
cat("样本：PP 样本（AI组 n=11, Self组 n=13）\n\n")
cat("【方法学】\n")
cat("• 模型：lm(Post ~ Group + Pre + Stratum)\n")
cat("• ANOVA 类型：Type III (car::Anova)\n")
cat("• 调整后差值：emmeans (pairwise contrast, AI vs. Self)\n")
cat("• 效应量：Hedges' g（基于变化值，含偏差校正）\n")
cat("• 多重比较校正：Benjamini-Hochberg FDR（5 个结局）\n\n")
cat("【主要结局 ANCOVA 结果】\n")
print(knitr::kable(main_tbl, format="pipe"))
cat("\n\n【模型诊断】\n"); print(diag_tbl)
cat("\n\n【FDR 校正】\n"); print(fdr_tbl)
sink()

cat("\n✓ ANCOVA 分析完成\n")
cat("  报告路径：", report_path, "\n")
