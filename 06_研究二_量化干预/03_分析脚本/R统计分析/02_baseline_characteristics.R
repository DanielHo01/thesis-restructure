# =============================================================================
# 02_baseline_characteristics.R
# -----------------------------------------------------------------------------
# 目的：生成基线特征表（PP 样本组间比较）
# 依赖：00_setup.R, 01_data_load.R
# 输出：
#   - outputs/tables/T4-1a_baseline_continuous.csv
#   - outputs/tables/T4-1b_baseline_categorical.csv
#   - outputs/reports/02_baseline_summary.txt
# 对应论文：第 4 章 表 4-1 基线特征
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始基线特征分析 ==========\n")

# ---- 1. 定义基线特征变量清单 ----
# 连续变量：中文标签 -> 列名
# 注意：只包含真正的数值型变量
continuous_vars <- list(
  "年龄（岁）"             = "Age",
  "身高（cm）"             = "Height_cm",
  "体重（kg）"             = "BW_kg",
  "BMI（kg/m²）"          = "BMI",
  "体脂率（%）"            = "PBF_pct",
  "骨骼肌量（kg）"         = "SMM_kg",
  "去脂体重（kg）"         = "FFM_kg",
  "实际出勤（次/周）"      = "Attend_int",
  "基线深蹲 1RM（kg）"   = "Meas1RM",
  "基线相对 1RM（kg/kg）" = "Rel1RM",
  "基线 CMJ 高度（cm）"   = "PreCMJ",
  "基线 SJ 高度（cm）"    = "PreSJ",
  "基线训练自我效能"       = "PreSE"
)

# 分类变量（字符或因子型）
categorical_vars <- list(
  "力量分层"      = "Stratum",
  "抗阻训练年限"   = "ResistYears",
  "入组前习惯训练频率"   = "Freq_wk"
)

# ---- 2. 连续变量的组间比较函数 ----
compare_continuous <- function(var_label, var_name) {
  ai_vec   <- df_main %>% filter(Group == "AI组") %>% pull(!!sym(var_name))
  self_vec <- df_main %>% filter(Group == "Self组") %>% pull(!!sym(var_name))

  # 确保数值型
  ai_vec   <- as.numeric(ai_vec)
  self_vec <- as.numeric(self_vec)

  ai_n   <- sum(!is.na(ai_vec));   self_n <- sum(!is.na(self_vec))
  ai_m   <- mean(ai_vec, na.rm = TRUE);   ai_sd <- sd(ai_vec, na.rm = TRUE)
  slf_m  <- mean(self_vec, na.rm = TRUE); slf_sd <- sd(self_vec, na.rm = TRUE)

  # Welch's t 检验
  tt <- t.test(ai_vec, self_vec, var.equal = FALSE, conf.level = 0.95)
  mean_diff <- tt$estimate[1] - tt$estimate[2]

  # Mann-Whitney U 检验（非参数备选）
  mw <- suppressWarnings(wilcox.test(ai_vec, self_vec))

  # Hedges' g（合并 SD，偏差校正）
  pooled_n  <- ai_n + self_n
  pooled_sd <- sqrt(((ai_n - 1) * ai_sd^2 + (self_n - 1) * slf_sd^2) /
                     max(pooled_n - 2, 1))
  g_value   <- (ai_m - slf_m) / pooled_sd * (1 - 3 / (4 * pooled_n - 4))
  se_g      <- sqrt(1 / ai_n + 1 / self_n + g_value^2 / (2 * pooled_n))
  g_lo <- g_value - 1.96 * se_g
  g_hi <- g_value + 1.96 * se_g

  data.frame(
    Variable      = var_label,
    n_AI          = ai_n,
    n_Self        = self_n,
    AI_Mean_SD    = sprintf("%.2f ± %.2f", ai_m, ai_sd),
    Self_Mean_SD  = sprintf("%.2f ± %.2f", slf_m, slf_sd),
    Mean_Diff     = sprintf("%.2f", mean_diff),
    CI_95         = sprintf("(%.2f, %.2f)", tt$conf.int[1], tt$conf.int[2]),
    t_p           = format_p(tt$p.value),
    MW_p          = format_p(mw$p.value),
    Hedges_g      = sprintf("%.2f", g_value),
    Hedges_g_CI   = sprintf("(%.2f, %.2f)", g_lo, g_hi),
    stringsAsFactors = FALSE
  )
}

# ---- 3. 分类变量的组间比较函数 ----
compare_categorical <- function(var_label, var_name) {
  # 交叉表
  ct <- table(df_main[[var_name]], df_main$Group)

  fisher_test <- tryCatch(
    fisher.test(ct),
    error = function(e) list(p.value = NA)
  )

  levels_vec <- rownames(ct)
  ai_v   <- ct[, "AI组"]; self_v <- ct[, "Self组"]

  ai_desc   <- paste(sprintf("%s: n=%d (%.1f%%)", levels_vec, ai_v, ai_v / sum(ai_v) * 100),
                     collapse = "; ")
  self_desc <- paste(sprintf("%s: n=%d (%.1f%%)", levels_vec, self_v, self_v / sum(self_v) * 100),
                     collapse = "; ")

  data.frame(
    Variable          = var_label,
    AI_Distribution  = ai_desc,
    Self_Distribution = self_desc,
    Fisher_p          = format_p(fisher_test$p.value),
    stringsAsFactors  = FALSE
  )
}

# ---- 4. 计算所有变量 ----
cat("→ 计算连续变量组间比较（Welch's t + Mann-Whitney + Hedges' g）...\n")
continuous_results <- purrr::map2_dfr(
  continuous_vars, names(continuous_vars),
  ~ compare_continuous(.y, .x)
)

cat("→ 计算分类变量组间比较（Fisher 确切检验）...\n")
categorical_results <- purrr::map2_dfr(
  categorical_vars, names(categorical_vars),
  ~ compare_categorical(.y, .x)
)

# ---- 5. 打印结果 ----
cat("\n", paste(rep("=", 65), collapse = ""), "\n")
cat("【表 4-1a】连续变量基线特征（PP 样本，AI组 n=11, Self组 n=13）\n")
cat(paste(rep("=", 65), collapse = ""), "\n\n")

print(knitr::kable(continuous_results,
                   col.names = c("变量", "n(AI)", "n(Self)",
                                 "AI组 (M±SD)", "Self组 (M±SD)",
                                 "差值", "95% CI",
                                 "t检验 p", "MW p",
                                 "Hedges' g", "g 95% CI"),
                   format = "pipe", align = "l"))

cat("\n", paste(rep("=", 65), collapse = ""), "\n")
cat("【表 4-1b】分类变量基线特征（PP 样本）\n")
cat(paste(rep("=", 65), collapse = ""), "\n\n")

print(knitr::kable(categorical_results,
                   col.names = c("变量", "AI组分布", "Self组分布", "Fisher p"),
                   format = "pipe", align = "l"))

# ---- 6. 样本量汇总 ----
sample_summary <- df_main %>%
  group_by(Group) %>%
  summarise(n = n(), .groups = "drop")

cat("\n", paste(rep("=", 65), collapse = ""), "\n")
cat("【汇总】样本量\n")
cat(paste(rep("=", 65), collapse = ""), "\n\n")
print(sample_summary)

# ---- 7. 保存表格 ----
save_table(continuous_results, "T4-1a_baseline_continuous")
save_table(categorical_results, "T4-1b_baseline_categorical")

# ---- 8. 完整报告 ----
report_path <- file.path(PATH_REPORTS, "02_baseline_summary.txt")
sink(report_path)
cat("========== 基线特征分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n")
cat("样本：PP 样本（AI组 n=11, Self组 n=13）\n\n")

cat("【表 4-1a】连续变量基线特征\n")
print(knitr::kable(continuous_results, format = "pipe"))
cat("\n\n【表 4-1b】分类变量基线特征\n")
print(knitr::kable(categorical_results, format = "pipe"))
cat("\n\n【样本量】\n")
print(sample_summary)

cat("\n---------- 方法学说明 ----------\n")
cat("• 连续变量：Welch's t 检验（不假设方差齐性）+ Mann-Whitney U 检验（非参数）\n")
cat("• 分类变量：Fisher 确切检验\n")
cat("• 效应量：Hedges' g（基于合并标准差，含偏差校正）\n")
cat("• 95% CI：基于 Welch's t 检验的均值差置信区间\n")
cat("• 注：入组前习惯训练频率为基线问卷自报入组前习惯训练频率\n")
sink()

cat("\n✓ 基线特征分析完成\n")
cat("  报告路径：", report_path, "\n")
