# =============================================================================
# check_freq.R
# 目的：核实训练年限/频率的原始数据类型 + 生成16格计划vs实际交叉表
# =============================================================================

setwd("D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预/03_分析脚本/R统计分析")
source("00_setup.R")
source("01_data_load.R")

cat("\n", paste(rep("=", 78), collapse = ""), "\n")
cat("【一、原始问卷字段分布】\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")

cat("▶ ResistYears（训练年限）唯一值：\n")
print(sort(unique(df_main$ResistYears)))
cat("\n▶ Freq_wk（计划训练频率）唯一值：\n")
print(sort(unique(df_main$Freq_wk)))
cat("\n▶ Freq_wk 按组合计数：\n")
print(table(df_main$Group, df_main$Freq_wk))

cat("\n", paste(rep("=", 78), collapse = ""), "\n")
cat("【二、Fisher 精确检验（Freq_wk 组间差异）】\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")

ft <- fisher.test(table(df_main$Group, df_main$Freq_wk))
cat("Fisher 精确检验 p =", ft$p.value, "\n")
cat("Odds Ratio =", ft$estimate, "\n")
cat("95% CI = (", ft$conf.int[1], ",", ft$conf.int[2], ")\n")

cat("\n", paste(rep("=", 78), collapse = ""), "\n")
cat("【三、计划 vs 实际频率 16 格交叉表】\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")

# Freq_wk 计划频率（原始分类）
plan <- df_main$Freq_wk
names(plan) <- df_main$Group

# Attend_int 实际出勤次数（整数 7 或 8）
actual <- df_main$Attend_int
names(actual) <- df_main$Group

# AI 组
ai <- df_main[df_main$Group == "AI组", ]
sl <- df_main[df_main$Group == "Self组", ]

cat("▶ AI组（n=", nrow(ai), "）\n")
cat("  计划频率分布：\n")
print(table(ai$Freq_wk))
cat("  实际出勤次数分布：\n")
print(table(ai$Attend_int))
cat("  计划×实际交叉表：\n")
print(table(ai$Freq_wk, ai$Attend_int))

cat("\n▶ Self组（n=", nrow(sl), "）\n")
cat("  计划频率分布：\n")
print(table(sl$Freq_wk))
cat("  实际出勤次数分布：\n")
print(table(sl$Attend_int))
cat("  计划×实际交叉表：\n")
print(table(sl$Freq_wk, sl$Attend_int))

cat("\n▶ 全体（n=", nrow(df_main), "）\n")
cat("  计划×实际 16 格表：\n")
plan_actual <- table(df_main$Freq_wk, df_main$Attend_int)
print(plan_actual)
cat("\n  边际：\n")
cat("  按计划（行）：", paste(margin.table(plan_actual, 1), collapse = ", "), "\n")
cat("  按实际（列）：", paste(margin.table(plan_actual, 2), collapse = ", "), "\n")

cat("\n", paste(rep("=", 78), collapse = ""), "\n")
cat("【四、Attend_int 统计量（实际出勤次数）】\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")

ai_att <- as.numeric(ai$Attend_int)
sl_att <- as.numeric(sl$Attend_int)
cat("AI组  : M =", mean(ai_att), ", SD =", sd(ai_att),
    ", 范围 = [", min(ai_att), ",", max(ai_att), "]\n")
cat("Self组: M =", mean(sl_att), ", SD =", sd(sl_att),
    ", 范围 = [", min(sl_att), ",", max(sl_att), "]\n")
tt <- t.test(ai_att, sl_att)
cat("独立样本 t 检验: t =", tt$statistic,
    ", df =", tt$parameter,
    ", p =", tt$p.value, "\n")
cat("Cohen's d =", (mean(ai_att) - mean(sl_att)) / sqrt((sd(ai_att)^2 + sd(sl_att)^2)/2), "\n")

cat("\n", paste(rep("=", 78), collapse = ""), "\n")
cat("【五、ResistYears 组间比较（Fisher 精确检验）】\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")

cat("训练年限交叉表：\n")
print(table(df_main$Group, df_main$ResistYears))
ft_ry <- fisher.test(table(df_main$Group, df_main$ResistYears))
cat("\nFisher 精确检验 p =", ft_ry$p.value, "\n")

cat("\n", paste(rep("=", 78), collapse = ""), "\n")
cat("【六、结论】\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")
cat("Freq_wk（计划训练频率）：有序分类变量（≤1/2/3/≥4 次/周），组间分布不均衡\n")
cat("  - AI组：≤1次/周 =", sum(ai$Freq_wk == "≤1次/周"),
    ", 2次/周 =", sum(ai$Freq_wk == "2次/周"),
    ", 3次/周 =", sum(ai$Freq_wk == "3次/周"), "\n")
cat("  - Self组：2次/周 =", sum(sl$Freq_wk == "2次/周"),
    ", 3次/周 =", sum(sl$Freq_wk == "3次/周"),
    ", ≥4次/周 =", sum(sl$Freq_wk == "≥4次/周"), "\n")
cat("Attend_int（实际出勤次数）：整数型连续变量（仅 7 或 8），组间无显著差异\n")
cat("ResistYears（训练年限）：有序分类变量（1-2/2-3/3年以上），Fisher p =", ft_ry$p.value, "\n")

cat("\n✅ 诊断完成\n")
