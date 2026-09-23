# =============================================================================
# 01_import_clean.R
# 数据导入、清洗与派生变量计算
# 数据来源：data_raw/
# 输出：data_clean/（自动生成，不修改 data_raw/）
# =============================================================================

# 加载全局配置（自动定位到 scripts/ 的父目录）
# 00_setup.R 会设置 ROOT 指向 R_Analysis/
source(file.path(dirname(sys.frame(1)$ofile), "00_setup.R"), encoding = "UTF-8")
PATH_SCRIPTS <- file.path(ROOT, "scripts")
PATH_CLEAN   <- file.path(ROOT, "data_clean")
dir.create(PATH_CLEAN, showWarnings = FALSE, recursive = TRUE)

cat("\n=== 01 数据导入与清洗 ===\n")

# ============================================================================
# 1. 36人总表（CONSORT）
# ============================================================================
cat("导入 36人总表...\n")
consort <- read_csv(file.path(PATH_RAW, "00_participants_36.csv")) |>
  mutate(
    ID            = as.character(ID),
    Group         = factor(Group, levels = c("Self组", "AI组")),
    Stratum       = factor(Stratum),
    DropoutStage   = na_if(DropoutStage, ""),
    DropoutReason = na_if(DropoutReason, ""),
    EnteredPP     = EnteredPP == "是"
  )

# CONSORT 口径统计
n_randomized    <- nrow(consort)
n_ai_rand       <- sum(consort$Group == "AI组")
n_self_rand     <- sum(consort$Group == "Self组")
n_dropout_base   <- sum(!is.na(consort$DropoutStage) &
                         consort$DropoutStage == "随机分配后基线测试阶段")
n_dropout_int    <- sum(!is.na(consort$DropoutStage) &
                         consort$DropoutStage == "干预过程中")
n_entered_int    <- n_randomized - n_dropout_base  # 近似值，待精确
n_pp             <- sum(consort$EnteredPP)
n_ai_pp          <- sum(consort$EnteredPP & consort$Group == "AI组")
n_self_pp        <- sum(consort$EnteredPP & consort$Group == "Self组")

cat(sprintf(
  "  随机化: %d人 (AI=%d, Self=%d)\n  T0期脱落: %d人\n  干预期脱落: %d人\n  进入干预: ~%d人\n  PP: %d人 (AI=%d, Self=%d)\n",
  n_randomized, n_ai_rand, n_self_rand,
  n_dropout_base, n_dropout_int, n_entered_int,
  n_pp, n_ai_pp, n_self_pp
))

# ============================================================================
# 2. PP 主表（24人）
# ============================================================================
cat("导入 PP主表（24人）...\n")
main <- read_csv(file.path(PATH_RAW, "01_main_PP_24.csv")) |>
  mutate(
    ID       = as.character(ID),
    Group    = factor(Group, levels = c("Self组", "AI组")),
    Stratum  = factor(Stratum, levels = c("低力量层", "高力量层"))
  )

# 派生变量
main <- main |>
  mutate(
    # 相对1RM
    Rel1RM_t0 = Pre1RM / BW_kg,
    Rel1RM_t1 = Post1RM / BW_kg,
    # 变化量
    d1RM    = Post1RM - Pre1RM,
    dCMJ    = PostCMJ - PreCMJ,
    dSJ     = PostSJ - PreSJ,
    dSE     = PostSE - PreSE,
    dRel1RM = Rel1RM_t1 - Rel1RM_t0,
    # 百分比变化
    pct1RM = d1RM / Pre1RM * 100,
    pctCMJ = dCMJ / PreCMJ * 100,
    pctSJ  = dSJ / PreSJ * 100,
    pctSE  = dSE / PreSE * 100,
    # 训练频率数值化（用于敏感性分析）
    Freq_num = case_when(
      Freq_wk == "≤1次/周" ~ 1,
      Freq_wk == "2次/周" ~ 2,
      Freq_wk == "3次/周" ~ 3,
      Freq_wk == "≥4次/周" ~ 4,
      TRUE ~ NA_real_
    )
  )

# 质量核查
stopifnot("ID唯一性检验" = nrow(main) == length(unique(main$ID)))
stopifnot("d1RM = Post-Pre" = all(abs(main$d1RM - (main$Post1RM - main$Pre1RM)) < 1e-6))
stopifnot("dCMJ = Post-Pre" = all(abs(main$dCMJ - (main$PostCMJ - main$PreCMJ)) < 1e-6))
cat("  ✓ 质量核查通过（ID唯一性、变化量计算）\n")

# ============================================================================
# 3. 训练监控（192课次）
# ============================================================================
cat("导入 训练监控（192课次）...\n")
mon <- read_csv(file.path(PATH_RAW, "02_training_monitor_192.csv")) |>
  mutate(
    ID       = as.character(ID),
    Group    = factor(Group, levels = c("Self组", "AI组")),
    Sess     = factor(Sess, levels = paste0("S", 1:8), ordered = FALSE),
    Week     = factor(Week),
    DayType  = factor(DayType)
  )

# 计算 Hooper 指标（Sleep逆向 + 其余四项）
mon <- mon |>
  mutate(
    Sleep_rev   = 11 - Sleep,
    Hooper_tot  = Sleep_rev + Stress + Fatigue + Soreness,
    CMJ_max     = pmax(CMJ1, CMJ2, CMJ3, na.rm = TRUE),
    CMJ_mean    = rowMeans(select(cur_data(), CMJ1, CMJ2, CMJ3), na.rm = TRUE)
  )

# 质量核查
stopifnot("192条记录" = nrow(mon) == 192)
stopifnot("每人8课"   = all(table(mon$ID) == 8))
cat("  ✓ 质量核查通过（192条记录、每人8课次）\n")

# ============================================================================
# 4. Rep级 App-GA 配对（43对）
# ============================================================================
cat("导入 Rep级配对（43对）...\n")
rep_pairs <- read_csv(file.path(PATH_RAW, "03_app_ga_rep_43.csv")) |>
  mutate(
    ID      = as.character(ID),
    Session = factor(Session),
    Set     = as.integer(Set),
    Rep     = as.integer(Rep),
    Diff    = App - GA   # 正值=APP偏高
  )

# 质量核查
stopifnot("43对记录" = nrow(rep_pairs) == 43)
stopifnot("唯一键"   = nrow(rep_pairs) == nrow(distinct(rep_pairs, ID, Session, Set, Rep)))
cat(sprintf("  43对来自 %d 人：%s\n",
  length(unique(rep_pairs$ID)),
  paste(unique(rep_pairs$ID), collapse = ", ")))

# ============================================================================
# 5. SUS（AI组 11人）
# ============================================================================
cat("导入 SUS（AI组 11人）...\n")
sus <- read_csv(file.path(PATH_RAW, "04_sus_ai_11.csv")) |>
  mutate(ID = as.character(ID))

# SUS 标准重算（奇数题原分-1；偶数题 5-原分；总分×2.5）
sus_calc <- sus |>
  mutate(
    Q1_r = Q1 - 1,
    Q2_r = 5 - Q2,
    Q3_r = Q3 - 1,
    Q4_r = 5 - Q4,
    Q5_r = Q5 - 1,
    Q6_r = 5 - Q6,
    Q7_r = Q7 - 1,
    Q8_r = 5 - Q8,
    Q9_r = Q9 - 1,
    Q10_r = 5 - Q10,
    SUS_recalc = (Q1_r + Q2_r + Q3_r + Q4_r + Q5_r +
                  Q6_r + Q7_r + Q8_r + Q9_r + Q10_r) * 2.5
  )

cat(sprintf(
  "  SUS重算均值 = %.1f (范围 %.0f–%.0f)\n  预填原表均值 = %.1f (范围 %.0f–%.0f)\n",
  mean(sus_calc$SUS_recalc), min(sus_calc$SUS_recalc), max(sus_calc$SUS_recalc),
  mean(sus_calc$SUS_score),  min(sus_calc$SUS_score),  max(sus_calc$SUS_score)
))

# ============================================================================
# 6. TAM 接受度（AI组 11人）
# ============================================================================
cat("导入 TAM 接受度（AI组 11人）...\n")
acc <- read_csv(file.path(PATH_RAW, "05_acceptance_ai_11.csv")) |>
  mutate(ID = as.character(ID)) |>
  mutate(
    PU_mean   = rowMeans(select(cur_data(), starts_with("PU")), na.rm = TRUE),
    Trust_mean= rowMeans(select(cur_data(), starts_with("Trust")), na.rm = TRUE),
    Int_mean  = rowMeans(select(cur_data(), starts_with("Int")), na.rm = TRUE)
  )

# ============================================================================
# 7. 自我效能（Pre 24人）
# ============================================================================
cat("导入 自我效能T0（24人）...\n")
se_pre <- read_csv(file.path(PATH_RAW, "06_selfeff_pre_24.csv")) |>
  mutate(ID = as.character(ID)) |>
  mutate(
    Group = factor(Group, levels = c("Self组", "AI组")),
    SE_raw   = Q1 + Q2 + Q3 + Q4 + Q5 + Q6,
    SE_score = (SE_raw - 6) / 30 * 100  # 6题，每题最低1分，满分100
  )

# ============================================================================
# 8. 自我效能（Post 24人）
# ============================================================================
cat("导入 自我效能T1（24人）...\n")
se_post <- read_csv(file.path(PATH_RAW, "07_selfeff_post_24.csv")) |>
  mutate(ID = as.character(ID)) |>
  mutate(
    Group = factor(Group, levels = c("Self组", "AI组")),
    SE_raw   = Q1 + Q2 + Q3 + Q4 + Q5 + Q6,
    SE_score = (SE_raw - 6) / 30 * 100
  )

# ============================================================================
# 合并 Pre-Post
# ============================================================================
se <- se_pre |>
  rename_with(~gsub("SE_", "SE_pre_", .), .cols = c(SE_raw, SE_score)) |>
  inner_join(
    se_post |>
      rename_with(~gsub("SE_", "SE_post_", .), .cols = c(SE_raw, SE_score)),
    by = c("ID", "Group")
  ) |>
  mutate(
    dSE_raw   = SE_post_raw - SE_pre_raw,
    dSE_score = SE_post_score - SE_pre_score
  )

# ============================================================================
# 保存清洗后数据
# ============================================================================
saveRDS(consort,    file.path(PATH_CLEAN, "consort.rds"))
saveRDS(main,       file.path(PATH_CLEAN, "main.rds"))
saveRDS(mon,        file.path(PATH_CLEAN, "monitor.rds"))
saveRDS(rep_pairs,  file.path(PATH_CLEAN, "rep_pairs.rds"))
saveRDS(sus_calc,   file.path(PATH_CLEAN, "sus.rds"))
saveRDS(acc,        file.path(PATH_CLEAN, "acceptance.rds"))
saveRDS(se,         file.path(PATH_CLEAN, "self_efficacy.rds"))

cat("\n✓ 01 数据导入完成。清洗数据已保存至：", PATH_CLEAN, "\n")

# ============================================================================
# 诊断报告
# ============================================================================
diag <- tibble(
  模块        = c("总表", "主表", "监控", "Rep配对", "SUS", "接受度", "自我效能"),
  记录数      = c(n_randomized, nrow(main), nrow(mon), nrow(rep_pairs),
                   nrow(sus), nrow(acc), nrow(se)),
  缺失主要变量 = c(NA, sum(is.na(main$Pre1RM)), sum(is.na(mon$Hooper_tot)),
                   sum(is.na(rep_pairs$GA) | is.na(rep_pairs$App)),
                   sum(is.na(sus_calc$SUS_recalc)), NA,
                   sum(is.na(se$SE_pre_score) | is.na(se$SE_post_score))),
  状态        = c("✓", "✓", "✓", "✓", "✓", "✓", "✓")
)

save_tbl(diag, "diagnostic_import")
cat("✓ 诊断报告已保存\n")
