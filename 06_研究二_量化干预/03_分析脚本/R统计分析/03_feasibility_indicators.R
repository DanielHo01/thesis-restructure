# =============================================================================
# 03_feasibility_indicators.R
# -----------------------------------------------------------------------------
# 目的：计算可行性指标与推进标准对照
# 依赖：00_setup.R, 01_data_load.R
# 输出：
#   - outputs/tables/T4-2a_recruitment_funnel.csv
#   - outputs/tables/T4-2b_progression_criteria.csv
#   - outputs/tables/T4-2c_adherence_by_group.csv
#   - outputs/reports/03_feasibility_summary.txt
# 对应论文：第 4 章 表 4-2 可行性指标
# =============================================================================

source("00_setup.R")
source("01_data_load.R")

cat("\n========== 开始可行性指标分析 ==========\n")

# ============================================================================
# 【待补数据】请根据实际招募记录补填以下变量
# ============================================================================
N_ENROLLED       <- NA_real_  # 正式报名人数（知情同意前）
N_SCREENED       <- NA_real_  # 符合筛选与知情同意人数
N_RANDOMIZED     <- NA_real_  # 完成前测并接受随机分组人数
N_COMPLETED_8    <- NA_real_  # 完成全部 8 次训练的人数（可由下方自动计算）
N_ADVERSE_EVENTS <- 0         # 严重不良事件数（SAE，填 0 如无）

# ============================================================================

# ---- 1. 招募漏斗 ----
cat("→ 构建招募漏斗...\n")

recruitment_funnel <- tibble(
  阶段 = c(
    "正式报名人数",
    "符合筛选与知情同意人数",
    "完成 1RM 前测并接受随机分组人数",
    "完成全部 T0 测试进入正式干预人数",
    "完成全部 8 次训练干预人数",
    "纳入 PP 分析人数"
  ),
  人数 = c(N_ENROLLED, N_SCREENED, N_RANDOMIZED, 29, N_COMPLETED_8, 24),
  备注 = c(
    ifelse(is.na(N_ENROLLED),    "⚠ 待补（请查阅招募记录）", ""),
    ifelse(is.na(N_SCREENED),    "⚠ 待补（请查阅招募记录）", ""),
    ifelse(is.na(N_RANDOMIZED),  "⚠ 待补（请查阅知情同意书数量）", ""),
    "来自随机化 36 人（AI=18, Self=18）",
    "⚠ 如与 24 不符请核实",
    "PP 样本 = 完成 T0 + 完成 T1"
  )
)

cat("\n【表 4-2a】招募漏斗\n")
print(recruitment_funnel)

# ---- 2. 训练完成率 ----
cat("\n→ 计算训练完成率...\n")

# 每人的实际训练课次数
sessions_per_id <- df_monitor %>%
  group_by(ID, Group) %>%
  summarise(n_sessions = n_distinct(Sess), .groups = "drop")

planned_sessions_per_person <- 8
planned_total <- nrow(df_main) * planned_sessions_per_person  # 24 × 8 = 192
actual_total  <- sum(sessions_per_id$n_sessions)
completion_rate_total <- actual_total / planned_total * 100

# 完成全部 8 次的人数（自动更新变量）
N_COMPLETED_8 <- sum(sessions_per_id$n_sessions >= 8)

cat(sprintf("\n  总完成率：%d / %d = %.1f%%\n",
            actual_total, planned_total, completion_rate_total))
cat(sprintf("  完成全部 8 次的人数：%d / %d\n", N_COMPLETED_8, nrow(df_main)))

# ---- 3. 高依从率（≥80% 课次，即 ≥7 次）----
cat("\n→ 计算高依从率（≥80% 课次）...\n")

adherence_threshold <- ceiling(0.80 * planned_sessions_per_person)  # = 7
sessions_per_id <- sessions_per_id %>%
  mutate(
    is_high_adherence = n_sessions >= adherence_threshold,
    adherence_pct = n_sessions / planned_sessions_per_person * 100
  )

high_adherence_count <- sum(sessions_per_id$is_high_adherence)
high_adherence_rate <- high_adherence_count / nrow(sessions_per_id) * 100

cat(sprintf("  高依从率（≥%d 次）：%d / %d = %.1f%%\n",
            adherence_threshold, high_adherence_count, nrow(sessions_per_id),
            high_adherence_rate))

# 按组别统计
adherence_by_group <- sessions_per_id %>%
  group_by(Group) %>%
  summarise(
    n_total          = n(),
    n_high_adherence = sum(is_high_adherence),
    rate_pct         = n_high_adherence / n_total * 100,
    mean_sessions    = mean(n_sessions),
    sd_sessions      = sd(n_sessions),
    .groups = "drop"
  )

cat("\n  按组别依从率：\n")
print(adherence_by_group)

# 详细列出未达高依从的受试者
low_adherence <- sessions_per_id %>% filter(!is_high_adherence)
if (nrow(low_adherence) > 0) {
  cat("\n  ⚠ 未达高依从（<", adherence_threshold, "次）的受试者：\n")
  print(low_adherence %>% select(ID, Group, n_sessions, adherence_pct))
} else {
  cat("\n  ✓ 所有受试者均达到高依从标准\n")
}

# ---- 4. 数据完整性 ----
cat("\n→ 计算关键结局指标完整率...\n")

key_outcomes <- c("Post1RM", "PostCMJ", "PostSJ", "PostSE")
completeness_per_var <- df_main %>%
  summarise(across(all_of(key_outcomes),
                   ~ sprintf("%d/%d (%.0f%%)", sum(!is.na(.x)), n(),
                             sum(!is.na(.x)) / n() * 100),
                   .names = "{.col}")) %>%
  pivot_longer(everything(), names_to = "变量", values_to = "完整率")

all_complete <- df_main %>%
  select(all_of(key_outcomes)) %>%
  transmute(全部完整 = complete.cases(.)) %>%
  pull() %>%
  sum()

overall_complete_rate <- all_complete / nrow(df_main) * 100

cat("\n  关键结局指标完整率：\n")
print(completeness_per_var)
cat(sprintf("  全部 4 项指标均完整：%d / %d (%.0f%%)\n",
            all_complete, nrow(df_main), overall_complete_rate))

# ---- 5. AI 组速度命中率 ----
cat("\n→ 计算 AI 组速度命中率...\n")

ai_subgroup <- df_main %>% filter(Group == "AI组")
ai_vel_hit <- ai_subgroup$AIVelHit

# 判断是小数（0-1）还是百分数
if (mean(ai_vel_hit, na.rm = TRUE) <= 1) {
  ai_vel_hit_pct <- ai_vel_hit * 100
} else {
  ai_vel_hit_pct <- ai_vel_hit
}

ai_vel_hit_mean <- mean(ai_vel_hit_pct, na.rm = TRUE)
ai_vel_hit_sd   <- sd(ai_vel_hit_pct, na.rm = TRUE)
ai_vel_hit_min  <- min(ai_vel_hit_pct, na.rm = TRUE)
ai_vel_hit_max  <- max(ai_vel_hit_pct, na.rm = TRUE)

cat(sprintf("  AI 组速度命中率：%.1f%% ± %.1f%% (范围: %.1f%%–%.1f%%)\n",
            ai_vel_hit_mean, ai_vel_hit_sd, ai_vel_hit_min, ai_vel_hit_max))

# ---- 6. 自动减组触发情况 ----
cat("\n→ 计算自动减组触发情况...\n")

auto_reduce <- ai_subgroup$AutoReduce
cat(sprintf("  触发人次：%d 人\n", sum(!is.na(auto_reduce) & auto_reduce > 0)))
cat(sprintf("  总触发次数：%d 次\n", sum(auto_reduce, na.rm = TRUE)))
cat(sprintf("  人均触发：%.2f 次\n", mean(auto_reduce, na.rm = TRUE)))

if (sum(auto_reduce > 0, na.rm = TRUE) > 0) {
  cat("  触发详情：\n")
  print(ai_subgroup %>% select(ID, AutoReduce) %>% filter(AutoReduce > 0))
}

# ---- 7. 推进标准对照 ----
cat("\n→ 构建推进标准对照表...\n")

progression_criteria <- tibble(
  指标 = c(
    "进入干预率（完成 T0 / 随机分组）",
    "训练完成率（实际课次 / 计划课次）",
    "高依从率（≥80% 课次）",
    "关键结局指标完整率",
    "AI 组速度命中率",
    "自动减组触发人次",
    "严重不良事件数"
  ),
  推进阈值 = c(
    "≥80%",
    "≥80%",
    "≥80%",
    "≥90%",
    "≥60%",
    "—",
    "0 例"
  ),
  实际值 = c(
    ifelse(is.na(N_RANDOMIZED), "⚠ 待补",
           sprintf("%.1f%%", 29 / N_RANDOMIZED * 100)),
    sprintf("%.1f%%", completion_rate_total),
    sprintf("%.1f%%", high_adherence_rate),
    sprintf("%.0f%%", overall_complete_rate),
    sprintf("%.1f%%", ai_vel_hit_mean),
    sprintf("%d 人次（总触发 %d 次）",
            sum(auto_reduce > 0, na.rm = TRUE),
            sum(auto_reduce, na.rm = TRUE)),
    as.character(N_ADVERSE_EVENTS)
  ),
  是否达标 = c(
    ifelse(is.na(N_RANDOMIZED), "⚠",
           ifelse(29 / N_RANDOMIZED >= 0.80, "✓", "✗")),
    ifelse(completion_rate_total >= 80, "✓", "✗"),
    ifelse(high_adherence_rate >= 80, "✓", "✗"),
    ifelse(overall_complete_rate >= 90, "✓", "✗"),
    ifelse(ai_vel_hit_mean >= 60, "✓", "✗"),
    "—",
    ifelse(N_ADVERSE_EVENTS == 0, "✓", "✗")
  )
)

cat("\n【表 4-2b】推进标准对照\n")
print(progression_criteria)

# ---- 8. 保存所有表格 ----
save_table(recruitment_funnel, "T4-2a_recruitment_funnel")
save_table(progression_criteria, "T4-2b_progression_criteria")
save_table(adherence_by_group, "T4-2c_adherence_by_group")
save_table(completeness_per_var, "T4-2d_completeness")
save_table(sessions_per_id, "T4-2e_sessions_detail")

# ---- 9. 完整报告 ----
report_path <- file.path(PATH_REPORTS, "03_feasibility_summary.txt")
sink(report_path)
cat("========== 可行性指标分析报告 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n\n")

cat("【1. 招募漏斗】\n")
print(recruitment_funnel)
cat("\n注：⚠ 标记的行为待补数据，请填入实际数字\n\n")

cat("【2. 训练完成率】\n")
cat(sprintf("  实际完成：%d / %d = %.1f%%\n", actual_total, planned_total, completion_rate_total))
cat(sprintf("  完成全部 8 次：%d / %d 人\n\n", N_COMPLETED_8, nrow(df_main)))

cat("【3. 高依从率（≥%d 次训练）】\n", adherence_threshold)
cat(sprintf("  总体：%d / %d = %.1f%%\n", high_adherence_count, nrow(df_main), high_adherence_rate))
print(adherence_by_group)
cat("\n未达高依从的受试者：\n")
if (nrow(low_adherence) > 0) {
  print(low_adherence %>% select(ID, Group, n_sessions, adherence_pct))
} else {
  cat("  （无）\n")
}

cat("\n【4. 关键结局指标完整率】\n")
print(completeness_per_var)
cat(sprintf("  全部完整：%d / %d (%.0f%%)\n\n", all_complete, nrow(df_main), overall_complete_rate))

cat("【5. AI 组速度命中率】\n")
cat(sprintf("  %.1f%% ± %.1f%% (范围: %.1f%%–%.1f%%)\n\n",
            ai_vel_hit_mean, ai_vel_hit_sd, ai_vel_hit_min, ai_vel_hit_max))

cat("【6. 自动减组触发】\n")
cat(sprintf("  触发人次：%d / %d，总触发次数：%d\n\n",
            sum(auto_reduce > 0, na.rm = TRUE), nrow(ai_subgroup), sum(auto_reduce, na.rm = TRUE)))

cat("【7. 推进标准对照】\n")
print(progression_criteria)

cat("\n---------- 方法学说明 ----------\n")
cat("• 训练完成率：实际总课次 / 计划总课次（24人×8次=192）\n")
cat("• 高依从率：完成 ≥7 次训练的受试者比例（80%×8=6.4，向上取整）\n")
cat("• 数据完整性：Post1RM、PostCMJ、PostSJ、PostSE 全部非缺失的比例\n")
sink()

cat("\n✓ 可行性指标分析完成\n")
cat("  报告路径：", report_path, "\n")

if (is.na(N_ENROLLED) || is.na(N_SCREENED) || is.na(N_RANDOMIZED)) {
  cat("\n⚠ 请补齐招募漏斗数据后重新运行脚本以获得完整结果：\n")
  cat("  在脚本第 18-20 行修改：\n")
  cat("    N_ENROLLED   <- ", N_ENROLLED, "  ← 填入正式报名人数\n", sep = "")
  cat("    N_SCREENED   <- ", N_SCREENED, "  ← 填入通过筛选人数\n", sep = "")
  cat("    N_RANDOMIZED <- ", N_RANDOMIZED, "  ← 填入接受随机分组人数\n", sep = "")
}
