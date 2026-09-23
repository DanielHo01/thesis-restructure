setwd("D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预/03_分析脚本/R统计分析")
source("00_setup.R")
source("01_data_load.R")

cat("\n========== 核实 Fisher 精确检验 ========\n\n")

# ---- 任务3: ResistYears x 组别 ----
cat("【抗阻训练年限 x 组别】\n")
freq_tbl_years <- table(df_main$ResistYears, df_main$Group)
print(freq_tbl_years)
ft_years <- fisher.test(freq_tbl_years)
cat(sprintf("Fisher 精确检验 p = %.4f\n\n", ft_years$p.value))

# ---- 任务1续: Freq_wk x 组别 ----
cat("【计划训练频率 x 组别】\n")
freq_tbl_freq <- table(df_main$Freq_wk, df_main$Group)
print(freq_tbl_freq)
ft_freq <- fisher.test(freq_tbl_freq)
cat(sprintf("Fisher 精确检验 p = %.4f\n\n", ft_freq$p.value))

# ---- 任务4: 运行 08_psych_acceptance.R 生成 T4-7 ----
cat("\n========== 生成表 4-7 ========\n")
source("08_psych_acceptance.R")
cat("\n=== 全部完成 ===\n")
