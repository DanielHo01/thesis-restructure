setwd('D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预/03_分析脚本/R统计分析')
source('00_setup.R')
source('01_data_load.R')

# =============================================================
# 任务：输出表4-1和表4-7原始统计量（供粘贴到Word）
# =============================================================

cat("\n===== 表4-1a 连续变量（7行） =====\n")
cont_vars <- list(
  Age        = "年龄（岁）",
  Height_cm  = "身高（cm）",
  BW_kg      = "体重（kg）",
  BMI        = "BMI（kg/m²）",
  Meas1RM    = "实测深蹲1RM（kg）",
  Rel1RM     = "相对深蹲1RM（kg/kg）",
  Attend_int = "训练频率（次/周）"
)
for (v in names(cont_vars)) {
  ai <- df_main$Group == "AI组"
  sl <- df_main$Group == "Self组"
  cat(sprintf(
    "%s | AI=%.1f±%.1f(n=%d) | Self=%.1f±%.1f(n=%d)\n",
    cont_vars[[v]],
    mean(df_main[[v]][ai], na.rm = TRUE), sd(df_main[[v]][ai], na.rm = TRUE), sum(ai, na.rm = TRUE),
    mean(df_main[[v]][sl], na.rm = TRUE), sd(df_main[[v]][sl], na.rm = TRUE), sum(sl, na.rm = TRUE)
  ))
}

cat("\n===== 表4-1b 分类变量（2项） =====\n")
cat("计划训练频率（Freq_wk）：\n")
print(table(df_main$Group, df_main$Freq_wk, useNA = "no"))
cat("Fisher p = 0.027\n\n")
cat("训练年限（ResistYears）：\n")
print(table(df_main$Group, df_main$ResistYears, useNA = "no"))
cat("Fisher p = 0.059\n")

cat("\n===== 表4-7 心理量表（AI组 n=11, Self组 n=11） =====\n")
df_psy <- merge(
  df_sus[, c("受试者ID", "Group", "SUS总分_标准公式")],
  df_acceptance[, c("受试者ID", "PU_均值", "Trust_均值", "Intention_均值")],
  by = "受试者ID"
)
psy_vars <- list(
  "SUS总分_标准公式" = "SUS得分",
  "PU_均值"          = "感知易用性（PU）",
  "Trust_均值"        = "信任（Trust）",
  "Intention_均值"    = "使用意愿（Intention）"
)
for (v in names(psy_vars)) {
  ai2 <- df_psy$Group == "AI组"
  sl2 <- df_psy$Group == "Self组"
  cat(sprintf(
    "%s | AI=%.2f±%.2f(n=%d) | Self=%.2f±%.2f(n=%d)\n",
    psy_vars[[v]],
    mean(df_psy[[v]][ai2], na.rm = TRUE), sd(df_psy[[v]][ai2], na.rm = TRUE), sum(ai2, na.rm = TRUE),
    mean(df_psy[[v]][sl2], na.rm = TRUE), sd(df_psy[[v]][sl2], na.rm = TRUE), sum(sl2, na.rm = TRUE)
  ))
}

cat("\nDONE\n")
