# =============================================================================
# 01_data_load.R
# -----------------------------------------------------------------------------
# 目的：读取所有清洗后的数据文件，进行基础验证
# 依赖：00_setup.R
# 输出：内存中的数据对象（供后续脚本使用）
# =============================================================================

source("00_setup.R")

cat("\n========== 开始数据读取 ==========\n")

# ---- 1. 读取主分析数据（24 人 PP 样本）----
df_main <- read.csv(
  file.path(PATH_DATA_CLEAN, "01_main_PP_24.csv"),
  fileEncoding = "UTF-8",
  stringsAsFactors = FALSE
) %>%
  # 类型转换
  mutate(
    Group = factor(Group, levels = c("AI组", "Self组")),
    Stratum = factor(Stratum, levels = c("高力量层", "低力量层")),
    ID = as.character(ID)
  )

cat("✓ 主分析数据：", nrow(df_main), "人 ×", ncol(df_main), "变量\n")

# 数据完整性检查
if (nrow(df_main) != 24) {
  warning("⚠ 主分析数据行数不是 24，实际：", nrow(df_main))
}

# 组别分布
cat("  分组分布：\n")
print(table(df_main$Group))

# ---- 2. 读取训练监控数据（192 课次）----
df_monitor <- read.csv(
  file.path(PATH_DATA_CLEAN, "02_training_monitor_192.csv"),
  fileEncoding = "UTF-8",
  stringsAsFactors = FALSE
) %>%
  mutate(
    Group = factor(Group, levels = c("AI组", "Self组")),
    ID = as.character(ID),
    Sess = as.character(Sess),  # 保持字符型 "S1" 到 "S8"
    Week = as.character(Week)
  )

cat("✓ 训练监控数据：", nrow(df_monitor), "行\n")

# 课次完整性检查
sessions_per_id <- df_monitor %>%
  group_by(ID) %>%
  summarise(n_sessions = n_distinct(Sess), .groups = "drop")

if (any(sessions_per_id$n_sessions != 8)) {
  warning("⚠ 部分受试者课次不是 8 次：")
  print(sessions_per_id %>% filter(n_sessions != 8))
}

# ---- 3. 读取 App-GA 配对数据（43 对 Rep 级）----
df_rep <- read.csv(
  file.path(PATH_DATA_CLEAN, "03_app_ga_rep_43.csv"),
  fileEncoding = "UTF-8",
  stringsAsFactors = FALSE
) %>%
  mutate(
    ID = as.character(ID),
    Session = as.character(Session),  # 保持字符型 "S1" 到 "S8"
    Set = as.integer(Set),
    Rep = as.integer(Rep)
  )

cat("✓ App-GA Rep 级配对数据：", nrow(df_rep), "对\n")

# ---- 4. 读取自我效能数据 ----
df_se_pre <- read.csv(
  file.path(PATH_DATA_CLEAN, "04_self_efficacy_pre_24.csv"),
  fileEncoding = "UTF-8", stringsAsFactors = FALSE
) %>% 
  rename(ID = "受试者ID") %>%
  mutate(ID = as.character(ID))

df_se_post <- read.csv(
  file.path(PATH_DATA_CLEAN, "05_self_efficacy_post_24.csv"),
  fileEncoding = "UTF-8", stringsAsFactors = FALSE
) %>% 
  rename(ID = "受试者ID") %>%
  mutate(ID = as.character(ID))

df_se_prepost <- read.csv(
  file.path(PATH_DATA_CLEAN, "06_self_efficacy_prepost_24.csv"),
  fileEncoding = "UTF-8", stringsAsFactors = FALSE
) %>% 
  rename(ID = "受试者ID") %>%
  mutate(ID = as.character(ID))

cat("✓ 自我效能 Pre：", nrow(df_se_pre), "人\n")
cat("✓ 自我效能 Post：", nrow(df_se_post), "人\n")
cat("✓ 自我效能 PrePost 配对：", nrow(df_se_prepost), "人\n")

# ---- 5. 读取 SUS 数据（AI 组 11 人）----
df_sus <- read.csv(
  file.path(PATH_DATA_CLEAN, "07_SUS_AI_11.csv"),
  fileEncoding = "UTF-8", stringsAsFactors = FALSE
) %>% 
  rename(ID = "受试者ID") %>%
  mutate(ID = as.character(ID))

cat("✓ SUS 数据：", nrow(df_sus), "人\n")

# ---- 6. 读取接受度数据（AI 组 11 人）----
df_acceptance <- read.csv(
  file.path(PATH_DATA_CLEAN, "08_acceptance_AI_11.csv"),
  fileEncoding = "UTF-8", stringsAsFactors = FALSE
) %>% 
  rename(ID = "受试者ID") %>%
  mutate(ID = as.character(ID))

cat("✓ 接受度数据：", nrow(df_acceptance), "人\n")

# ---- 7. 提取热身课次级配对数据（从 df_monitor 的 GA/App 列）----
# 88 对 = 11 名 AI 组受试者 × 8 次训练课次
# 数据来源：df_monitor 中的 GA（热身 GymAware MCV）和 App（热身 App MCV）列
df_warmup <- df_monitor %>%
  filter(Group == "AI组") %>%  # 只取 AI 组
  select(ID, Sess, GA, App) %>%  # 选择关键列
  drop_na(GA, App)  # 移除缺失值

cat("✓ 热身课次级配对数据（df_warmup）：", nrow(df_warmup), "对（来自", length(unique(df_warmup$ID)), "名AI组受试者）\n")

# 同时保存热身偏移分析报告的路径（用于参考）
warmup_report_path <- file.path(
  here::here("..", "..", "..", "05_分析说明", "Python重算报告"),
  "12_热身课次级偏移特征.csv"
)

# ---- 8. 数据快照汇总 ----
cat("\n========== 数据读取完成 ==========\n")
cat("在内存中的数据对象：\n")
data_summary <- data.frame(
  数据集 = c(
    "df_main（主分析数据）",
    "df_monitor（训练监控）",
    "df_rep（Rep 级 App-GA）",
    "df_se_pre（自我效能 Pre）",
    "df_se_post（自我效能 Post）",
    "df_se_prepost（自我效能配对）",
    "df_sus（SUS）",
    "df_acceptance（接受度）",
    "df_warmup（热身配对）"
  ),
  行数 = c(
    nrow(df_main), nrow(df_monitor), nrow(df_rep),
    nrow(df_se_pre), nrow(df_se_post), nrow(df_se_prepost),
    nrow(df_sus), nrow(df_acceptance),
    ifelse(is.null(df_warmup), NA, nrow(df_warmup))
  ),
  变量数 = c(
    ncol(df_main), ncol(df_monitor), ncol(df_rep),
    ncol(df_se_pre), ncol(df_se_post), ncol(df_se_prepost),
    ncol(df_sus), ncol(df_acceptance),
    ifelse(is.null(df_warmup), NA, ncol(df_warmup))
  )
)

print(data_summary)

# ---- 9. 保存数据快照到磁盘 ----
snapshot_path <- file.path(PATH_REPORTS, "01_data_snapshot.txt")
sink(snapshot_path)
cat("========== 数据读取快照 ==========\n")
cat("生成时间：", format(Sys.time(), "%Y-%m-%d %H:%M:%S"), "\n\n")

cat("---- df_main（主分析数据）----\n")
str(df_main)
cat("\n---- df_monitor（训练监控）----\n")
str(df_monitor)
cat("\n---- df_rep（Rep 级 App-GA）----\n")
str(df_rep)
sink()

cat("\n✓ 数据快照已保存至：", snapshot_path, "\n")
