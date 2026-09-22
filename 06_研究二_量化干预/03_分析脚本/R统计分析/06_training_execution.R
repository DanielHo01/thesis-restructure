# =============================================================================
# 06_training_execution.R
# 目的：训练执行质量指标分析（两组共享 + 各组特征）
# 依赖：00_setup.R, 01_data_load.R
# 输出：T4-5_training_execution.csv, T4-5a_load_structure.csv
# 对应论文：第 4 章 4.3-4.4 节
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始训练执行质量分析 ==========\n")

# ---- 1. 两组共享指标 ----
cat("→ 计算两组共享训练执行指标...\n")

shared_vars <- list(
  "全课总负荷（kg）" = "TotalLoad",
  "深蹲总负荷（kg）" = "SquatLoad",
  "平均每课次负荷（kg）" = "LoadPerSess",
  "总组数"             = "Sets",
  "总次数"             = "Reps",
  "全期平均 sRPE"     = "sRPE",
  "训练总时长（min）"  = "Duration",
  "个体 Hooper 均值"  = "Hooper_revised"
)

compare_two_groups <- function(var_label, var_name) {
  ai_v  <- as.numeric(df_main[[var_name]][df_main$Group == "AI组"])
  slf_v <- as.numeric(df_main[[var_name]][df_main$Group == "Self组"])

  ai_m <- mean(ai_v, na.rm=TRUE);  ai_s <- sd(ai_v, na.rm=TRUE)
  slf_m <- mean(slf_v, na.rm=TRUE); slf_s <- sd(slf_v, na.rm=TRUE)

  tt <- t.test(ai_v, slf_v, var.equal=FALSE)
  pooled_sd <- sqrt(((length(ai_v)-1)*ai_s^2 + (length(slf_v)-1)*slf_s^2) /
                    (length(ai_v)+length(slf_v)-2))
  g <- (ai_m - slf_m) / pooled_sd * (1 - 3/(4*(length(ai_v)+length(slf_v))-4))

  data.frame(
    Variable  = var_label,
    AI        = sprintf("%.1f ± %.1f", ai_m, ai_s),
    Self      = sprintf("%.1f ± %.1f", slf_m, slf_s),
    Diff      = sprintf("%.2f", ai_m - slf_m),
    t_p       = format_p(tt$p.value),
    Hedges_g  = sprintf("%.2f", g),
    stringsAsFactors = FALSE
  )
}

exec_tbl <- purrr::map2_dfr(names(shared_vars), shared_vars, ~compare_two_groups(.x, .y))

cat("\n【表 4-5】两组训练执行指标\n")
print(knitr::kable(exec_tbl, format="pipe", align="l"))

# ---- 2. 负荷结构派生指标 ----
cat("\n→ 计算负荷结构派生指标...\n")

load_struct <- df_main %>%
  group_by(Group) %>%
  summarise(
    n = n(),
    LoadPerRep = mean(SquatLoad / Reps, na.rm = TRUE),
    LoadPerSet = mean(SquatLoad / Sets, na.rm = TRUE),
    RepsPerSet = mean(Reps / Sets, na.rm = TRUE),
    LoadPerMin = mean(SquatLoad / Duration, na.rm = TRUE),
    .groups = "drop"
  )

cat("\n【负荷结构派生指标】\n")
print(knitr::kable(load_struct, format="pipe", digits=2))

# ---- 3. AI 组特征指标 ----
cat("\n→ AI 组特征指标...\n")

ai_sub <- df_main %>% filter(Group == "AI组")
hit <- ai_sub$AIVelHit
if (mean(hit, na.rm=TRUE) <= 1) hit <- hit * 100

cat(sprintf("  速度命中率：%.1f%% ± %.1f%% (范围: %.1f%%–%.1f%%)\n",
            mean(hit,na.rm=TRUE), sd(hit,na.rm=TRUE),
            min(hit,na.rm=TRUE), max(hit,na.rm=TRUE)))
cat(sprintf("  自动减组：%d 人触发，共 %d 次\n",
            sum(ai_sub$AutoReduce>0, na.rm=TRUE),
            sum(ai_sub$AutoReduce, na.rm=TRUE)))

# ---- 4. Self 组特征指标 ----
cat("\n→ Self 组特征指标...\n")

slf_sub <- df_main %>% filter(Group == "Self组")
cat(sprintf("  负荷自主调整次数：%.1f ± %.1f 次/人\n",
            mean(slf_sub$SelfAdj_revised, na.rm=TRUE),
            sd(slf_sub$SelfAdj_revised, na.rm=TRUE)))

# ---- 5. 保存 ----
save_table(exec_tbl,    "T4-5_training_execution")
save_table(load_struct, "T4-5a_load_structure")

report_path <- file.path(PATH_REPORTS, "06_training_execution_summary.txt")
sink(report_path)
cat("========== 训练执行质量分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n\n")
cat("【两组共享指标】\n")
print(knitr::kable(exec_tbl, format="pipe"))
cat("\n【负荷结构】\n")
print(knitr::kable(load_struct, format="pipe", digits=2))
sink()

cat("\n✓ 训练执行质量分析完成\n")
cat("  报告路径：", report_path, "\n")
