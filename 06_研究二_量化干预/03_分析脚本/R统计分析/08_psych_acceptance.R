# =============================================================================
# 08_psych_acceptance.R
# 目的：心理量表与系统接受度分析
# 依赖：00_setup.R, 01_data_load.R
# 输出：T4-7_psych_acceptance.csv, 08_psych_acceptance_summary.txt
# 对应论文：第 4 章 4.6 节
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始心理量表与接受度分析 ==========\n")

# ============================================================================
# 1. SUS 描述统计（AI 组）
# ============================================================================
cat("→ SUS 描述统计（AI 组 n=11）...\n")

# 列名确认：SUS总分_标准公式
sus_total <- df_sus[["SUS总分_标准公式"]]
cat(sprintf("  SUS 总分：%.1f ± %.1f (范围: %.0f–%.0f)\n",
            mean(sus_total, na.rm=TRUE), sd(sus_total, na.rm=TRUE),
            min(sus_total, na.rm=TRUE), max(sus_total, na.rm=TRUE)))
cat(sprintf("  中位数：%.1f\n", median(sus_total, na.rm=TRUE)))
cat(sprintf("  ≥70 分（可接受）：%d 人 (%.0f%%)\n",
            sum(sus_total >= 70, na.rm=TRUE),
            sum(sus_total >= 70, na.rm=TRUE) / sum(!is.na(sus_total)) * 100))

sus_desc <- tibble(
  指标 = c("均值 ± SD", "Min", "Max", "中位数", "≥70 分（可接受）", "<70 分（不可接受）"),
  数值 = c(
    sprintf("%.1f ± %.1f", mean(sus_total,na.rm=TRUE), sd(sus_total,na.rm=TRUE)),
    sprintf("%.0f", min(sus_total,na.rm=TRUE)),
    sprintf("%.0f", max(sus_total,na.rm=TRUE)),
    sprintf("%.1f", median(sus_total,na.rm=TRUE)),
    sprintf("%d 人 (%.0f%%)", sum(sus_total>=70,na.rm=TRUE),
            sum(sus_total>=70,na.rm=TRUE)/length(sus_total)*100),
    sprintf("%d 人 (%.0f%%)", sum(sus_total<70,na.rm=TRUE),
            sum(sus_total<70,na.rm=TRUE)/length(sus_total)*100)
  )
)

cat("\n【SUS 描述统计】\n")
print(sus_desc)

# ============================================================================
# 2. 接受度量表（PU, Trust, Intention）
# ============================================================================
cat("\n→ 接受度量表描述统计...\n")

# 列名确认：PU_均值, Trust_均值, Intention_均值
acc_tbl <- tibble(
  维度 = c("感知有用性 (PU)", "信任度 (Trust)", "使用意愿 (Intention)"),
  均值 = c(
    sprintf("%.3f", mean(df_acceptance$PU_均值, na.rm=TRUE)),
    sprintf("%.3f", mean(df_acceptance$Trust_均值, na.rm=TRUE)),
    sprintf("%.3f", mean(df_acceptance$Intention_均值, na.rm=TRUE))
  ),
  SD = c(
    sprintf("%.3f", sd(df_acceptance$PU_均值, na.rm=TRUE)),
    sprintf("%.3f", sd(df_acceptance$Trust_均值, na.rm=TRUE)),
    sprintf("%.3f", sd(df_acceptance$Intention_均值, na.rm=TRUE))
  ),
  范围 = c(
    sprintf("%.2f–%.2f", min(df_acceptance$PU_均值,na.rm=TRUE),
            max(df_acceptance$PU_均值,na.rm=TRUE)),
    sprintf("%.2f–%.2f", min(df_acceptance$Trust_均值,na.rm=TRUE),
            max(df_acceptance$Trust_均值,na.rm=TRUE)),
    sprintf("%.2f–%.2f", min(df_acceptance$Intention_均值,na.rm=TRUE),
            max(df_acceptance$Intention_均值,na.rm=TRUE))
  )
)

cat("\n【接受度量表（AI 组 n=11）】\n")
print(acc_tbl)

# ============================================================================
# 3. 训练自我效能前后配对（组间对比）
# ============================================================================
cat("\n→ 训练自我效能前后配对（两组对比）...\n")

# df_se_prepost: 受试者ID, 组别, PreSE_条目均值, PostSE_条目均值, ...
# 注意列名可能有中文
se_vars <- names(df_se_prepost)
cat("  自我效能 PrePost 列名：", paste(se_vars, collapse=", "), "\n")

# 尝试识别列
pre_col  <- grep("PreSE|Pre_SE|pre_se|条目均值", se_vars, value=TRUE)[1]
post_col <- grep("PostSE|Post_SE|post_se", se_vars, value=TRUE)[1]

if (!is.na(pre_col) && !is.na(post_col)) {
  se_change <- df_se_prepost[[post_col]] - df_se_prepost[[pre_col]]
  group_se  <- df_se_prepost[["组别"]]
  ai_se_chg  <- se_change[group_se == "AI组"]
  slf_se_chg <- se_change[group_se == "Self组"]

  if (length(ai_se_chg) > 0 && length(slf_se_chg) > 0) {
    tt_se <- t.test(ai_se_chg, slf_se_chg, var.equal=FALSE)
    cat(sprintf("  AI 组变化：%.2f ± %.2f\n",
                mean(ai_se_chg,na.rm=TRUE), sd(ai_se_chg,na.rm=TRUE)))
    cat(sprintf("  Self 组变化：%.2f ± %.2f\n",
                mean(slf_se_chg,na.rm=TRUE), sd(slf_se_chg,na.rm=TRUE)))
    cat(sprintf("  组间差异：t p = %s\n", format_p(tt_se$p.value)))
  }
}

# ============================================================================
# 4. 汇总表
# ============================================================================
psych_tbl <- tibble(
  类别 = c("SUS（AI组）", "SUS（AI组）", "SUS（AI组）",
            "接受度 PU（AI组）", "接受度 Trust（AI组）", "接受度 Intention（AI组）"),
  指标 = c("均值 ± SD", "中位数", "≥70分比例",
            "均值 ± SD", "均值 ± SD", "均值 ± SD"),
  数值 = c(
    sprintf("%.1f ± %.1f", mean(sus_total,na.rm=TRUE), sd(sus_total,na.rm=TRUE)),
    sprintf("%.1f", median(sus_total,na.rm=TRUE)),
    sprintf("%.0f%%", sum(sus_total>=70,na.rm=TRUE)/length(sus_total)*100),
    sprintf("%.3f ± %.3f", mean(df_acceptance$PU_均值,na.rm=TRUE),
            sd(df_acceptance$PU_均值,na.rm=TRUE)),
    sprintf("%.3f ± %.3f", mean(df_acceptance$Trust_均值,na.rm=TRUE),
            sd(df_acceptance$Trust_均值,na.rm=TRUE)),
    sprintf("%.3f ± %.3f", mean(df_acceptance$Intention_均值,na.rm=TRUE),
            sd(df_acceptance$Intention_均值,na.rm=TRUE))
  )
)

cat("\n【心理量表汇总】\n")
print(psych_tbl)

# ============================================================================
# 5. 保存
# ============================================================================
save_table(psych_tbl, "T4-7_psych_acceptance")

report_path <- file.path(PATH_REPORTS, "08_psych_acceptance_summary.txt")
sink(report_path)
cat("========== 心理量表与接受度分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n\n")
cat("【SUS（AI组 n=11）】\n")
print(sus_desc)
cat("\n【接受度量表（AI组 n=11）】\n")
print(acc_tbl)
cat("\n【心理量表汇总】\n")
print(psych_tbl)
sink()

cat("\n✓ 心理量表与接受度分析完成\n")
cat("  报告路径：", report_path, "\n")
