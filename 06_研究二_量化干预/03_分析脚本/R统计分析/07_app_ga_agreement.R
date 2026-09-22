# =============================================================================
# 07_app_ga_agreement.R
# 目的：App vs. GymAware 生态效度分析（ICC + Bland-Altman）
# 依赖：00_setup.R, 01_data_load.R
# 输出：T4-6_app_ga_agreement.csv, F4-5/F4-6 Bland-Altman 图
# 对应论文：第 4 章 4.7 节
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始 App-GA 生态效度分析 ==========\n")

# ============================================================================
# 1. Rep 级配对（43 对）
# ============================================================================
cat("→ Rep 级配对分析（43 对）...\n")

icc_rep <- psych::ICC(data.frame(GA=df_rep$GA, App=df_rep$App), lmer=TRUE)

cat("\n【Rep 级 ICC(2,1)】\n")
print(icc_rep$results)

diff_rep <- df_rep$App - df_rep$GA
mean_rep <- (df_rep$App + df_rep$GA) / 2

ba_rep <- data.frame(
  Level  = "Rep 级 (n=43)",
  ICC    = sprintf("%.3f", icc_rep$results$ICC[2]),
  Bias   = sprintf("%.4f", mean(diff_rep, na.rm=TRUE)),
  LoA_lo = sprintf("%.4f", mean(diff_rep,na.rm=TRUE) - 1.96*sd(diff_rep,na.rm=TRUE)),
  LoA_hi = sprintf("%.4f", mean(diff_rep,na.rm=TRUE) + 1.96*sd(diff_rep,na.rm=TRUE)),
  MAE    = sprintf("%.4f", mean(abs(diff_rep), na.rm=TRUE)),
  RMSE   = sprintf("%.4f", sqrt(mean(diff_rep^2, na.rm=TRUE))),
  r      = sprintf("%.4f", cor(df_rep$GA, df_rep$App, use="complete.obs")),
  stringsAsFactors=FALSE
)

cat("\nRep 级 Bland-Altman 统计：\n")
cat(sprintf("  偏差（App−GA）：%.4f m/s\n", mean(diff_rep, na.rm=TRUE)))
cat(sprintf("  95%% LoA：[%s, %s] m/s\n",
            ba_rep$LoA_lo, ba_rep$LoA_hi))
cat(sprintf("  MAE：%s m/s\n", ba_rep$MAE))
cat(sprintf("  Pearson r：%s\n", ba_rep$r))

# Rep 级 Bland-Altman 图
ba_p1 <- ggplot(df_rep, aes(x = mean_rep, y = diff_rep)) +
  geom_point(alpha = 0.7, color = "#0072B5", size = 3) +
  geom_hline(yintercept = mean(diff_rep, na.rm=TRUE), color = "#D32F2F", linewidth = 1) +
  geom_hline(yintercept = mean(diff_rep,na.rm=TRUE) - 1.96*sd(diff_rep,na.rm=TRUE),
               color = "#D32F2F", linetype = "dashed", linewidth = 0.8) +
  geom_hline(yintercept = mean(diff_rep,na.rm=TRUE) + 1.96*sd(diff_rep,na.rm=TRUE),
               color = "#D32F2F", linetype = "dashed", linewidth = 0.8) +
  geom_hline(yintercept = 0, color = "grey60", linetype = "dotted", linewidth = 0.8) +
  annotate("text", x = max(mean_rep)*0.05, y = mean(diff_rep,na.rm=TRUE)+0.005,
           label = sprintf("Bias = %.4f", mean(diff_rep,na.rm=TRUE)),
           color = "#D32F2F", size = 3.5, hjust = 0) +
  labs(title = "Bland-Altman: App vs. GymAware（Rep 级, n=43）",
       x = "均值 MCV (m/s)", y = "差值 App − GA (m/s)") +
  theme_thesis + theme(legend.position = "none")

save_plot(ba_p1, "F4-5_bland_altman_rep")

# ============================================================================
# 2. 热身课次配对（88 对）
# ============================================================================
cat("\n→ 热身课次配对分析（88 对）...\n")

# df_warmup: ID, Sess, GA, App
ga_vals  <- df_warmup$GA
app_vals <- df_warmup$App
diff_warm  <- app_vals - ga_vals
mean_warm  <- (app_vals + ga_vals) / 2

icc_warm <- psych::ICC(data.frame(GA=ga_vals, App=app_vals), lmer=TRUE)
cat("\n【热身级 ICC(2,1)】\n")
print(icc_warm$results)

ba_warm <- data.frame(
  Level  = "热身课次级 (n=88)",
  ICC    = sprintf("%.3f", icc_warm$results$ICC[2]),
  Bias   = sprintf("%.4f", mean(diff_warm, na.rm=TRUE)),
  LoA_lo = sprintf("%.4f", mean(diff_warm,na.rm=TRUE) - 1.96*sd(diff_warm,na.rm=TRUE)),
  LoA_hi = sprintf("%.4f", mean(diff_warm,na.rm=TRUE) + 1.96*sd(diff_warm,na.rm=TRUE)),
  MAE    = sprintf("%.4f", mean(abs(diff_warm), na.rm=TRUE)),
  RMSE   = sprintf("%.4f", sqrt(mean(diff_warm^2, na.rm=TRUE))),
  r      = sprintf("%.4f", cor(ga_vals, app_vals, use="complete.obs")),
  stringsAsFactors=FALSE
)

cat("\n热身级 Bland-Altman 统计：\n")
cat(sprintf("  偏差（App−GA）：%.4f m/s\n", mean(diff_warm, na.rm=TRUE)))
cat(sprintf("  95%% LoA：[%s, %s] m/s\n",
            ba_warm$LoA_lo, ba_warm$LoA_hi))
cat(sprintf("  MAE：%s m/s\n", ba_warm$MAE))
cat(sprintf("  正向偏移比例：%d/%d (%.1f%%)\n",
            sum(diff_warm > 0, na.rm=TRUE), length(diff_warm),
            sum(diff_warm > 0, na.rm=TRUE)/length(diff_warm)*100))

# 热身级 Bland-Altman 图
ba_p2 <- ggplot(df_warmup, aes(x = mean_warm, y = diff_warm)) +
  geom_point(alpha = 0.5, color = "#BC3C29", size = 2.5) +
  geom_hline(yintercept = mean(diff_warm, na.rm=TRUE), color = "#D32F2F", linewidth = 1) +
  geom_hline(yintercept = mean(diff_warm,na.rm=TRUE) - 1.96*sd(diff_warm,na.rm=TRUE),
               color = "#D32F2F", linetype = "dashed", linewidth = 0.8) +
  geom_hline(yintercept = mean(diff_warm,na.rm=TRUE) + 1.96*sd(diff_warm,na.rm=TRUE),
               color = "#D32F2F", linetype = "dashed", linewidth = 0.8) +
  geom_hline(yintercept = 0, color = "grey60", linetype = "dotted", linewidth = 0.8) +
  annotate("text", x = max(mean_warm)*0.05, y = mean(diff_warm,na.rm=TRUE)+0.003,
           label = sprintf("Bias = %.4f", mean(diff_warm,na.rm=TRUE)),
           color = "#D32F2F", size = 3.5, hjust = 0) +
  labs(title = "Bland-Altman: App vs. GymAware（热身课次级, n=88）",
       x = "均值 MCV (m/s)", y = "差值 App − GA (m/s)") +
  theme_thesis + theme(legend.position = "none")

save_plot(ba_p2, "F4-6_bland_altman_warmup")

# ============================================================================
# 3. 汇总表
# ============================================================================
agreement_summary <- rbind(ba_rep, ba_warm)

cat("\n【生态效度汇总】\n")
print(agreement_summary)

save_table(agreement_summary, "T4-6_app_ga_agreement")

report_path <- file.path(PATH_REPORTS, "07_app_ga_summary.txt")
sink(report_path)
cat("========== App-GA 生态效度分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n\n")
cat("【方法学】\n")
cat("• ICC(2,1)：双向随机效应，单次测量，绝对一致性\n")
cat("• Bland-Altman：偏差 = App − GA，95% LoA = mean ± 1.96×SD\n\n")
print(knitr::kable(agreement_summary, format="pipe"))
sink()

cat("\n✓ App-GA 生态效度分析完成\n")
cat("  报告路径：", report_path, "\n")
