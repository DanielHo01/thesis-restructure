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

  # 用行名定位 Group 行（避免因模型协变量数量变化导致位置偏移）
  rownms <- rownames(atbl)
  grp_idx <- which(rownms == "Group")
  grp_F   <- as.numeric(atbl[grp_idx, "F value"])
  grp_df1  <- as.numeric(atbl[grp_idx, "Df"])
  grp_p    <- as.numeric(atbl[grp_idx, "Pr(>F)"])
  res_df   <- as.numeric(atbl[nrow(atbl), "Df"])

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

# =============================================================================
# ---- 11. 敏感性分析：加入计划训练频率（Freq_wk）作为协变量 ----
# =============================================================================
cat("\n", paste(rep("=", 78), collapse = ""), "\n")
cat("【敏感性分析】计划训练频率协变量敏感性\n")
cat(paste(rep("=", 78), collapse = ""), "\n")
cat("目的：检验基线计划训练频率（Freq_wk）是否影响主分析结论\n")
cat("敏感性模型：lm(Post ~ Group + Pre + Stratum + Freq_wk_ord)\n\n")

# ---- 11a. 构建 Freq_wk 序数变量 ----
# ≤1次/周=1, 2次/周=2, 3次/周=3, ≥4次/周=4
freq_map <- c("≤1次/周" = 1, "2次/周" = 2, "3次/周" = 3, "≥4次/周" = 4)
df_main <- df_main %>%
  mutate(Freq_wk_ord = as.numeric(freq_map[Freq_wk]))

cat("→ Freq_wk 序数编码：\n")
print(tibble(
  ID    = df_main$ID,
  Group = df_main$Group,
  Freq_wk = df_main$Freq_wk,
  Freq_wk_ord = df_main$Freq_wk_ord
))

# ---- 11b. 敏感性分析函数 ----
run_ancova_sens <- function(data, outcome) {
  pv  <- outcome$post
  pre_v <- outcome$pre
  lbl  <- outcome$label

  g    <- data$Group
  pre_vals  <- as.numeric(data[[pre_v]])
  post_vals <- as.numeric(data[[pv]])

  ai_pre  <- pre_vals[g == "AI组"];   slf_pre  <- pre_vals[g == "Self组"]
  ai_post <- post_vals[g == "AI组"];  slf_post <- post_vals[g == "Self组"]
  ai_chg  <- ai_post - ai_pre;       slf_chg  <- slf_post - slf_pre
  ai_n <- sum(!is.na(ai_chg)); slf_n <- sum(!is.na(slf_chg))
  ai_mc <- mean(ai_chg, na.rm = TRUE); ai_sc <- sd(ai_chg, na.rm = TRUE)
  sl_mc <- mean(slf_chg, na.rm = TRUE); sl_sc <- sd(slf_chg, na.rm = TRUE)

  # 敏感性 ANCOVA（含 Freq_wk_ord）
  fmla_s <- as.formula(sprintf("%s ~ Group + %s + Stratum + Freq_wk_ord", pv, pre_v))
  mod_s  <- lm(fmla_s, data = data)
  atbl_s <- car::Anova(mod_s, type = "III")

  rownms_s <- rownames(atbl_s)
  grp_idx  <- which(rownms_s == "Group")
  grp_F_s  <- as.numeric(atbl_s[grp_idx, "F value"])
  grp_p_s  <- as.numeric(atbl_s[grp_idx, "Pr(>F)"])
  res_df_s  <- as.numeric(atbl_s[nrow(atbl_s), "Df"])
  grp_df1_s <- as.numeric(atbl_s[grp_idx, "Df"])

  # 调整后差值
  emm_s  <- emmeans(mod_s, ~ Group)
  cont_s <- as.data.frame(pairs(emm_s, reverse = FALSE, adjust = "none",
                                 infer = c(TRUE, TRUE), level = 0.95))
  adj_diff_s  <- cont_s$estimate[1]
  adj_ci_lo_s <- cont_s$lower.CL[1]
  adj_ci_hi_s <- cont_s$upper.CL[1]

  # Freq_wk 协变量 p 值（用行名定位）
  freq_p_s <- if ("Freq_wk_ord" %in% rownms_s) {
    as.numeric(atbl_s["Freq_wk_ord", "Pr(>F)"])
  } else {
    NA
  }

  # Hedges' g（基于变化值，与主分析一致）
  pooled_sd <- sqrt(((ai_n - 1) * ai_sc^2 + (slf_n - 1) * sl_sc^2) /
                     max(ai_n + slf_n - 2, 1))
  g_val_s <- (ai_mc - sl_mc) / pooled_sd * (1 - 3 / (4 * (ai_n + slf_n) - 4))

  list(
    label      = lbl,
    category   = outcome$category,
    adj_diff_s = adj_diff_s,
    adj_ci_lo_s = adj_ci_lo_s,
    adj_ci_hi_s = adj_ci_hi_s,
    F_val_s    = grp_F_s,
    df_s       = sprintf("%d, %d", grp_df1_s, res_df_s),
    p_raw_s    = grp_p_s,
    p_fmt_s    = format_p(grp_p_s),
    g_s        = g_val_s,
    freq_p_s   = freq_p_s
  )
}

# ---- 11c. 运行敏感性分析 ----
cat("→ 运行敏感性分析（+ Freq_wk_ord）...\n\n")

sens_results <- purrr::map(outcomes, ~ run_ancova_sens(df_main, .x))

# ---- 11d. 主分析与敏感性分析并列比较 ----
cat(paste(rep("-", 78), collapse = ""), "\n")
cat("【并列比较】主分析 vs. 敏感性分析（+ Freq_wk_ord）\n")
cat(paste(rep("-", 78), collapse = ""), "\n\n")

comp_tbl <- purrr::map_dfr(seq_along(outcomes), function(i) {
  oc  <- outcomes[[i]]
  r   <- results[i, ]  # data.frame 行索引
  sr  <- sens_results[[i]]
  tibble(
    结局            = oc$label,
    主分析_g        = r$Hedges_g,
    主分析_g_CI    = r$g_CI,
    主分析_p        = r$p_fmt,
    敏感性_g        = sprintf("%.3f", sr$g_s),
    敏感性_调整差值 = sprintf("%.2f (%.2f, %.2f)",
                              sr$adj_diff_s, sr$adj_ci_lo_s, sr$adj_ci_hi_s),
    敏感性_p        = sr$p_fmt_s,
    Freq_p          = format_p(sr$freq_p_s),
    g变化           = sprintf("%+.3f", sr$g_s - as.numeric(r$Hedges_g)),
    方向一致        = ifelse((as.numeric(r$Hedges_g) > 0 && sr$g_s > 0) ||
                              (as.numeric(r$Hedges_g) < 0 && sr$g_s < 0),
                              "✓", "⚠")
  )
})

print(knitr::kable(comp_tbl, format = "pipe", align = "l"))

# ---- 11e. 详细敏感性分析结果 ----
cat("\n", paste(rep("-", 78), collapse = ""), "\n")
cat("【敏感性分析详细结果】\n\n")
for (i in seq_along(sens_results)) {
  sr  <- sens_results[[i]]
  oc  <- outcomes[[i]]
  r   <- results[i, ]  # data.frame 行索引
  cat(sprintf("▶ %s [%s]\n", oc$label, oc$category))
  cat(sprintf("  主分析 g = %s %s, p = %s\n", r$Hedges_g, r$g_CI, r$p_fmt))
  cat(sprintf("  敏感性 g = %.3f，调整差值 = %.2f (%.2f, %.2f)，p = %s\n",
              sr$g_s, sr$adj_diff_s, sr$adj_ci_lo_s, sr$adj_ci_hi_s, sr$p_fmt_s))
  cat(sprintf("  Freq_wk_ord 协变量 p = %s\n", format_p(sr$freq_p_s)))
  g_chg <- sr$g_s - as.numeric(r$Hedges_g)
  cat(sprintf("  g 漂移：%+.4f（%s方向一致）\n\n",
              g_chg,
              ifelse((as.numeric(r$Hedges_g) > 0 && sr$g_s > 0) ||
                     (as.numeric(r$Hedges_g) < 0 && sr$g_s < 0),
                     "✓", "⚠")))
}

# ---- 11f. 保存 ----
# 合并主分析与敏感性结果
full_comp <- purrr::map_dfr(seq_along(outcomes), function(i) {
  oc  <- outcomes[[i]]
  r   <- results[i, ]  # data.frame 行索引
  sr  <- sens_results[[i]]
  data.frame(
    结局              = oc$label,
    类别              = oc$category,
    主分析_g          = sprintf("%.3f", as.numeric(r$Hedges_g)),
    主分析_g_CI       = r$g_CI,
    主分析_p          = r$p_fmt,
    主分析_调整差值   = paste(r$Adj_Diff, r$Adj_CI),
    敏感性_g          = sprintf("%.3f", sr$g_s),
    敏感性_g_CI       = sprintf("(%.2f, %.2f)", sr$adj_ci_lo_s, sr$adj_ci_hi_s),
    敏感性_p          = sr$p_fmt_s,
    敏感性_调整差值   = sprintf("%.2f (%.2f, %.2f)",
                                 sr$adj_diff_s, sr$adj_ci_lo_s, sr$adj_ci_hi_s),
    Freq协变量_p      = format_p(sr$freq_p_s),
    g变化             = sprintf("%+.3f", sr$g_s - as.numeric(r$Hedges_g)),
    方向一致性        = ifelse((as.numeric(r$Hedges_g) > 0 && sr$g_s > 0) ||
                                (as.numeric(r$Hedges_g) < 0 && sr$g_s < 0),
                                "一致", "不一致"),
    stringsAsFactors  = FALSE
  )
})

save_table(comp_tbl,      "T4-3b_sensitivity_freqwkw_comparison")
save_table(full_comp,    "T4-3c_sensitivity_freqwkw_full")

# ---- 11g. 报告追加 ----
sink(report_path, append = TRUE)
cat("\n\n")
cat(paste(rep("=", 78), collapse = ""), "\n")
cat("【敏感性分析】计划训练频率协变量稳健性\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")
cat("敏感性模型：lm(Post ~ Group + Pre + Stratum + Freq_wk_ord)\n")
cat("Freq_wk 序数编码：≤1次/周=1, 2次/周=2, 3次/周=3, ≥4次/周=4\n\n")
cat("【并列比较表】\n")
print(knitr::kable(comp_tbl, format = "pipe", align = "l"))
cat("\n\n【结论】\n")
consistent <- sum(comp_tbl$方向一致 == "✓")
cat(sprintf("  %d / %d 个结局方向一致（%s）\n",
            consistent, nrow(comp_tbl),
            ifelse(consistent == nrow(comp_tbl), "结论稳健", "部分结局方向改变")))
for (i in seq_len(nrow(comp_tbl))) {
  row <- comp_tbl[i, ]
  if (row$方向一致 == "⚠") {
    cat(sprintf("  ⚠ %s：方向改变，需关注\n", row$结局))
  }
}
cat("\n")
cat("注：若 Freq_wk_ord 协变量 p < 0.05，说明计划训练频率对结局有显著预测力，\n")
cat("    加入后 g 方向若仍一致，说明主分析结论稳健，不受基线训练频率影响。\n")
sink()

cat("\n✓ 敏感性分析完成（+ Freq_wk_ord）\n")
cat("  对比表：", file.path(PATH_TABLES, "T4-3b_sensitivity_freqwkw_comparison.csv"), "\n")
cat("  完整表：", file.path(PATH_TABLES, "T4-3c_sensitivity_freqwkw_full.csv"), "\n")
cat("  报告已追加至：", report_path, "\n")
