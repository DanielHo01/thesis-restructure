# =============================================================================
# 10_itt_sensitivity.R
# 目的：留一法影响分析（Leave-One-Out Influence Analysis）
#       评估 PP 分析结论是否被单个个体过度驱动
# 依赖：00_setup.R, 01_data_load.R
# 输出：
#   - outputs/tables/T4-9_loo_influence_g_matrix.csv
#   - outputs/tables/T4-9_loo_influence_summary.csv
#   - outputs/reports/10_itt_sensitivity_summary.txt
# 对应论文：第 4 章 4.8 节
# 方法学：
#   • 对每个结局（n=24）依次剔除 1 名受试者，重估 ANCOVA 模型
#   • 模型：lm(Post ~ Group + Pre + Stratum)，与主分析一致
#   • 效应量：Hedges' g（基于变化值，含偏差校正）
#   • 调整后差值：emmeans 边际均值配对比较（AI vs. Self）
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始留一法影响分析 ==========\n")
cat("→ 当前 df_main 样本量：", nrow(df_main), "（PP 样本）\n\n")

# ---- 0. 预处理：计算衍生变量（与主分析一致）----
df_main <- df_main %>%
  mutate(Rel1RM_Post = Post1RM / BW_kg)

# ---- 1. 定义结局变量清单 ----
# 与 04_ancova_primary.R 的 outcomes 完全对齐
outcomes <- list(
  list(post = "Post1RM", pre = "Pre1RM",
       label = "Δ 深蹲绝对 1RM"),
  list(post = "PostCMJ", pre = "PreCMJ",
       label = "Δ CMJ 高度"),
  list(post = "PostSJ",  pre = "PreSJ",
       label = "Δ SJ 高度"),
  list(post = "PostSE",  pre = "PreSE",
       label = "Δ 训练自我效能")
)

# ---- 2. ANCOVA + Hedges' g 拟合函数 ----
run_ancova_hedges <- function(df_sub, post_var, pre_var) {

  # 提取所需列
  needed <- c("ID", "Group", post_var, pre_var, "Stratum")
  if (!all(needed %in% names(df_sub))) {
    return(list(g = NA, diff = NA,
                adj_ci_lo = NA, adj_ci_hi = NA,
                n_ai = NA, n_self = NA, n_total = NA))
  }

  df_tmp <- df_sub[, needed]
  df_tmp <- df_tmp[complete.cases(df_tmp), ]
  if (nrow(df_tmp) < 4) {
    return(list(g = NA, diff = NA,
                adj_ci_lo = NA, adj_ci_hi = NA,
                n_ai = NA, n_self = NA, n_total = NA))
  }

  # 组别人数（使用真实组别标签）
  n_ai   <- sum(df_tmp$Group == "AI组")
  n_self <- sum(df_tmp$Group == "Self组")
  if (n_ai < 2 || n_self < 2) {
    return(list(g = NA, diff = NA,
                adj_ci_lo = NA, adj_ci_hi = NA,
                n_ai = n_ai, n_self = n_self, n_total = NA))
  }

  # 拟合 ANCOVA：Post ~ Group + Pre + Stratum
  fml <- as.formula(sprintf("%s ~ Group + %s + Stratum", post_var, pre_var))
  model <- tryCatch(lm(fml, data = df_tmp), error = function(e) NULL)
  if (is.null(model)) {
    return(list(g = NA, diff = NA,
                adj_ci_lo = NA, adj_ci_hi = NA,
                n_ai = n_ai, n_self = n_self, n_total = nrow(df_tmp)))
  }

  # 调整后组间差值（emmeans 边际均值比较，AI vs. Self）
  emm  <- emmeans(model, ~ Group)
  cont <- as.data.frame(pairs(emm, reverse = FALSE, adjust = "none",
                              infer = c(TRUE, TRUE), level = 0.95))
  adj_diff  <- cont$estimate[1]
  adj_ci_lo <- cont$lower.CL[1]
  adj_ci_hi <- cont$upper.CL[1]

  # Hedges' g（基于变化值，与主分析完全一致）
  pre_vals  <- as.numeric(df_tmp[[pre_var]])
  post_vals <- as.numeric(df_tmp[[post_var]])
  ai_pre   <- pre_vals[df_tmp$Group == "AI组"]
  slf_pre  <- pre_vals[df_tmp$Group == "Self组"]
  ai_post  <- post_vals[df_tmp$Group == "AI组"]
  slf_post <- post_vals[df_tmp$Group == "Self组"]
  ai_chg   <- ai_post - ai_pre
  slf_chg  <- slf_post - slf_pre
  ai_n <- sum(!is.na(ai_chg)); slf_n <- sum(!is.na(slf_chg))
  ai_mc <- mean(ai_chg, na.rm = TRUE); ai_sc <- sd(ai_chg, na.rm = TRUE)
  sl_mc <- mean(slf_chg, na.rm = TRUE); sl_sc <- sd(slf_chg, na.rm = TRUE)
  pooled_sd <- sqrt(((ai_n - 1) * ai_sc^2 + (slf_n - 1) * sl_sc^2) /
                     max(ai_n + slf_n - 2, 1))
  g_val <- (ai_mc - sl_mc) / pooled_sd *
    (1 - 3 / (4 * (ai_n + slf_n) - 4))

  list(g = g_val, diff = adj_diff,
       adj_ci_lo = adj_ci_lo, adj_ci_hi = adj_ci_hi,
       n_ai = n_ai, n_self = n_self,
       n_total = nrow(df_tmp))
}

# ---- 3. 主循环：对每个结局执行留一法 ----
cat("→ 对", length(outcomes), "个结局逐一执行留一法影响分析...\n")

all_results <- vector("list", length(outcomes))

for (i in seq_along(outcomes)) {
  oc <- outcomes[[i]]
  post_var <- oc$post
  pre_var  <- oc$pre
  label    <- oc$label

  cat("  分析中：", label, "\n")

  # 全样本基准
  full_res  <- run_ancova_hedges(df_main, post_var, pre_var)
  full_g    <- full_res$g
  full_diff <- full_res$diff

  # 预分配向量
  n  <- nrow(df_main)
  g_vals     <- numeric(n)
  diff_vals  <- numeric(n)
  ci_lo_vals <- numeric(n)
  ci_hi_vals <- numeric(n)
  ids_loo    <- character(n)
  group_loo  <- character(n)

  # 逐个剔除（n = 24 次）
  for (j in seq_len(n)) {
    df_loo  <- df_main[-j, ]
    loo_res <- run_ancova_hedges(df_loo, post_var, pre_var)
    g_vals[j]     <- loo_res$g
    diff_vals[j]  <- loo_res$diff
    ci_lo_vals[j] <- loo_res$adj_ci_lo
    ci_hi_vals[j] <- loo_res$adj_ci_hi
    ids_loo[j]    <- as.character(df_main$ID[j])
    group_loo[j]  <- as.character(df_main$Group[j])
  }

  # 有效值
  gv   <- g_vals[!is.na(g_vals)]
  ids_v <- ids_loo[!is.na(g_vals)]
  grp_v <- group_loo[!is.na(g_vals)]

  # 最大影响者
  if (length(gv) > 0) {
    max_pos_idx <- which.max(gv)[1]
    max_neg_idx <- which.min(gv)[1]
    max_pos_id  <- ids_v[max_pos_idx]
    max_neg_id  <- ids_v[max_neg_idx]
    max_pos_g   <- gv[max_pos_idx]
    max_neg_g   <- gv[max_neg_idx]
    max_pos_grp <- grp_v[max_pos_idx]
    max_neg_grp <- grp_v[max_neg_idx]
    # 最大绝对漂移
    abs_shift   <- abs(gv - full_g)
    max_abs_idx <- which.max(abs_shift)[1]
    max_abs_id  <- ids_v[max_abs_idx]
    max_abs_g   <- gv[max_abs_idx]
    max_abs_grp <- grp_v[max_abs_idx]
  } else {
    max_pos_id <- max_neg_id <- max_abs_id <- NA_character_
    max_pos_g  <- max_neg_g  <- max_abs_g  <- NA
    max_pos_grp <- max_neg_grp <- max_abs_grp <- NA_character_
  }

  # 稳健性判断
  crosses_zero      <- (length(gv) > 0 && min(gv) < 0 && max(gv) > 0)
  direction_consistent <- (length(gv) > 0 && (all(gv > 0) || all(gv < 0)))
  if (length(gv) == 0) {
    robustness <- "数据不足"
  } else if (crosses_zero) {
    robustness <- "临界稳健"
  } else if (direction_consistent) {
    robustness <- "稳健"
  } else {
    robustness <- "待评估"
  }

  # 相对漂移率
  if (!is.na(full_g) && abs(full_g) > 1e-6 && length(gv) > 0) {
    rel_drift_pct <- max(abs(gv - full_g)) / abs(full_g) * 100
  } else {
    rel_drift_pct <- NA
  }

  cat(sprintf("    全样本 g = %.3f | LOO g 范围：[%.3f, %.3f]\n",
              full_g, min(gv), max(gv)))
  cat(sprintf("    最大正向漂移：%s（%s，g = %.3f）\n",
              max_pos_id, max_pos_grp, max_pos_g))
  cat(sprintf("    最大负向漂移：%s（%s，g = %.3f）\n",
              max_neg_id, max_neg_grp, max_neg_g))
  cat(sprintf("    稳健性：%s\n\n", robustness))

  # 保存各次重估结果
  loo_df <- data.frame(
    ID          = ids_loo,
    Group       = group_loo,
    Hedges_g    = g_vals,
    Adj_Diff    = diff_vals,
    Adj_CI_lo   = ci_lo_vals,
    Adj_CI_hi   = ci_hi_vals,
    g_Shift     = g_vals - full_g,
    stringsAsFactors = FALSE
  ) %>% arrange(Hedges_g)

  all_results[[i]] <- list(
    outcome_label = label,
    post_var      = post_var,
    pre_var       = pre_var,
    full_g        = full_g,
    full_diff     = full_diff,
    full_ci_lo    = full_res$adj_ci_lo,
    full_ci_hi    = full_res$adj_ci_hi,
    loo_df        = loo_df,
    g_vals        = g_vals,
    gv_valid      = gv,
    max_pos_id    = max_pos_id,
    max_pos_grp   = max_pos_grp,
    max_pos_g     = max_pos_g,
    max_neg_id    = max_neg_id,
    max_neg_grp   = max_neg_grp,
    max_neg_g     = max_neg_g,
    max_abs_id    = max_abs_id,
    max_abs_grp   = max_abs_grp,
    max_abs_g     = max_abs_g,
    crosses_zero  = crosses_zero,
    direction_consistent = direction_consistent,
    robustness    = robustness,
    rel_drift_pct = rel_drift_pct
  )
}

# ---- 4. 打印汇总 ----
cat(paste(rep("=", 78), collapse = ""), "\n")
cat("【表 4-9】留一法影响分析结果（PP 样本，n=24）\n")
cat(paste(rep("=", 78), collapse = ""), "\n\n")

summary_rows <- purrr::map_dfr(seq_along(all_results), function(i) {
  res <- all_results[[i]]
  gv  <- res$g_vals
  tibble(
    结局           = res$outcome_label,
    全样本_g        = sprintf("%.3f", res$full_g),
    g_最小值        = sprintf("%.3f", min(gv)),
    g_P25分位       = sprintf("%.3f", quantile(gv, 0.25)),
    g_中位数        = sprintf("%.3f", median(gv)),
    g_均值          = sprintf("%.3f", mean(gv)),
    g_P75分位       = sprintf("%.3f", quantile(gv, 0.75)),
    g_最大值        = sprintf("%.3f", max(gv)),
    g_全距          = sprintf("%.3f", diff(range(gv))),
    最大正向漂移者   = paste0(res$max_pos_id, "（", res$max_pos_grp, "，g=",
                             sprintf("%.3f", res$max_pos_g), ")"),
    最大负向漂移者   = paste0(res$max_neg_id, "（", res$max_neg_grp, "，g=",
                             sprintf("%.3f", res$max_neg_g), ")"),
    跨越零点         = ifelse(res$crosses_zero, "⚠ 是", "✓ 否"),
    方向一致         = ifelse(res$direction_consistent, "✓ 是", "⚠ 否"),
    相对漂移率       = sprintf("%.1f%%", res$rel_drift_pct),
    结论             = res$robustness
  )
})
print(knitr::kable(summary_rows, format = "pipe", align = "l"))

# ---- 5. 保存表格 ----
# 5a. 宽表：每位受试者被剔除后的 g 矩阵
loo_wide_g <- data.frame(
  ID    = df_main$ID,
  Group = df_main$Group,
  stringsAsFactors = FALSE
)
for (i in seq_along(all_results)) {
  res  <- all_results[[i]]
  oc   <- outcomes[[i]]
  # 清理列名
  col_name <- gsub("[()%\\s-]", "_", oc$label)
  col_name <- gsub("_+", "_", gsub("^_|_$", "", col_name))
  loo_wide_g[[paste0("g_", col_name)]]   <- res$g_vals
  loo_wide_g[[paste0("diff_", col_name)]] <- res$loo_df$Adj_Diff[
    match(loo_wide_g$ID, res$loo_df$ID)]
}
save_table(loo_wide_g, "T4-9_loo_influence_g_matrix")

# 5b. 汇总表
save_table(summary_rows, "T4-9_loo_influence_summary")

# ---- 6. 文本报告 ----
report_path <- file.path(PATH_REPORTS, "10_itt_sensitivity_summary.txt")
sink(report_path)

cat("========== 留一法影响分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n")
cat("PP 样本：n = 24（AI组 = 11, Self组 = 13）\n\n")

cat("【方法学】\n")
cat("• 分析方法：留一法影响分析（Leave-One-Out Influence Analysis）\n")
cat("• 对每个结局，从 PP 样本中依次剔除 1 人（n = 24 次），重新拟合 ANCOVA\n")
cat("• ANCOVA 模型：lm(Post ~ Group + Pre + Stratum)，与主分析完全一致\n")
cat("• 效应量：Hedges' g（基于变化值，含偏差校正）\n")
cat("• 调整后差值：emmeans 边际均值配对比较（AI vs. Self）\n\n")

cat("【解读原则】\n")
cat("• 若所有 24 次重估的 g 方向一致（全部 > 0 或全部 < 0）→ 结果稳健\n")
cat("• 若 g 跨越零点（存在重估使 g 变号）→ 单个个体可能过度驱动结论\n")
cat("• 相对漂移率 = max(|漂移|) / |全样本 g| × 100%，越大越敏感\n\n")

cat(paste(rep("=", 78), collapse = ""), "\n")
cat("【各结局详细结果】\n\n")

for (i in seq_along(all_results)) {
  res <- all_results[[i]]
  gv  <- res$g_vals
  cat("━━ ", res$outcome_label, " ━━\n\n")

  cat("  【全样本基准】\n")
  cat(sprintf("    全样本 Hedges' g = %.3f [%.2f, %.2f]\n",
              res$full_g, res$full_ci_lo, res$full_ci_hi))
  cat(sprintf("    全样本调整后差值 = %.3f\n\n", res$full_diff))

  cat("  【24 次重估统计分布】\n")
  cat(sprintf("    最小值  = %.4f\n",   min(gv)))
  cat(sprintf("    P25分位 = %.4f\n",   quantile(gv, 0.25)))
  cat(sprintf("    中位数  = %.4f\n",   median(gv)))
  cat(sprintf("    均值    = %.4f\n",   mean(gv)))
  cat(sprintf("    P75分位 = %.4f\n",   quantile(gv, 0.75)))
  cat(sprintf("    最大值  = %.4f\n",   max(gv)))
  cat(sprintf("    全距    = %.4f\n\n", diff(range(gv))))

  cat("  【效应量最大影响者】\n")
  cat(sprintf("    最大正向漂移：%s（%s），g = %.4f（漂移 +%.4f）\n",
              res$max_pos_id, res$max_pos_grp, res$max_pos_g,
              res$max_pos_g - res$full_g))
  cat(sprintf("    最大负向漂移：%s（%s），g = %.4f（漂移 %.4f）\n",
              res$max_neg_id, res$max_neg_grp, res$max_neg_g,
              res$max_neg_g - res$full_g))
  cat(sprintf("    最大绝对影响：%s（%s），LOO g = %.4f\n\n",
              res$max_abs_id, res$max_abs_grp, res$max_abs_g))

  cat("  【稳健性判断】\n")
  cat(sprintf("    是否跨越零点：%s\n",
              ifelse(res$crosses_zero, "是（⚠ 临界稳健）", "否")))
  cat(sprintf("    方向是否一致：%s\n",
              ifelse(res$direction_consistent, "是", "否")))
  cat(sprintf("    相对漂移率：%.1f%%\n", res$rel_drift_pct))
  cat(sprintf("    结论：%s\n\n", res$robustness))
}

cat(paste(rep("=", 78), collapse = ""), "\n")
cat("【综合汇总表】\n\n")
print(knitr::kable(summary_rows, format = "pipe", align = "l"))

sink()

cat("\n  ✓ 文本报告已保存：", report_path, "\n")
cat("\n========== 留一法影响分析完成 ==========\n")
cat("输出文件：\n")
cat("  1.", file.path(PATH_TABLES, "T4-9_loo_influence_g_matrix.csv"), "\n")
cat("  2.", file.path(PATH_TABLES, "T4-9_loo_influence_summary.csv"), "\n")
cat("  3.", report_path, "\n")
