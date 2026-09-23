setwd("D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预/03_分析脚本/R统计分析")
source("00_setup.R"); source("01_data_load.R")

cat("\n========== 核实07脚本SEM/MDC95 ==========\n")

# Rep级
diff_rep <- df_rep$App - df_rep$GA
sd_rep <- sd(diff_rep, na.rm=TRUE)
n_rep  <- length(diff_rep)
sem_rep <- sd_rep / sqrt(n_rep)
mdc95_rep <- 1.96 * sd_rep   # 正确公式: 1.96 x sd(diff)
loa_lo_rep <- mean(diff_rep, na.rm=TRUE) - 1.96*sd_rep
loa_hi_rep <- mean(diff_rep, na.rm=TRUE) + 1.96*sd_rep
mae_rep <- mean(abs(diff_rep), na.rm=TRUE)
icc_rep <- psych::ICC(data.frame(GA=df_rep$GA, App=df_rep$App), lmer=TRUE)$results$ICC[2]

cat(sprintf("Rep级 (n=%d):\n", n_rep))
cat(sprintf("  sd(diff)   = %.6f\n", sd_rep))
cat(sprintf("  SEM        = %.6f  (= sd/√n)\n", sem_rep))
cat(sprintf("  MDC95      = %.6f  (= 1.96 x sd(diff))  ← 正确公式\n", mdc95_rep))
cat(sprintf("  LoA        = [%.4f, %.4f]\n", loa_lo_rep, loa_hi_rep))
cat(sprintf("  ICC        = %.3f\n", icc_rep))
cat(sprintf("  MAE        = %.4f\n", mae_rep))
cat("\n")

# 热身课次
ga_w  <- df_warmup$GA
app_w <- df_warmup$App
diff_w <- app_w - ga_w
sd_w <- sd(diff_w, na.rm=TRUE)
n_w  <- length(diff_w)
sem_w <- sd_w / sqrt(n_w)
mdc95_w <- 1.96 * sd_w
loa_lo_w <- mean(diff_w, na.rm=TRUE) - 1.96*sd_w
loa_hi_w <- mean(diff_w, na.rm=TRUE) + 1.96*sd_w
icc_w <- psych::ICC(data.frame(GA=ga_w, App=app_w), lmer=TRUE)$results$ICC[2]

cat(sprintf("热身级 (n=%d):\n", n_w))
cat(sprintf("  sd(diff)   = %.6f\n", sd_w))
cat(sprintf("  SEM        = %.6f  (= sd/√n)\n", sem_w))
cat(sprintf("  MDC95      = %.6f  (= 1.96 x sd(diff))  ← 正确公式\n", mdc95_w))
cat(sprintf("  LoA        = [%.4f, %.4f]\n", loa_lo_w, loa_hi_w))
cat(sprintf("  ICC        = %.3f\n", icc_w))
cat(sprintf("  MAE        = %.4f\n", mean(abs(diff_w), na.rm=TRUE)))

cat("\n✓ 核实完成\n")
