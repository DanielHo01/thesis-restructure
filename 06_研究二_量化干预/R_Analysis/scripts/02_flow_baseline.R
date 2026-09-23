# =============================================================================
# 02_flow_baseline.R
# 受试者流转（CONSORT）+ 基线特征表
# =============================================================================

source(file.path(PATH_SCRIPTS, "00_setup.R"))
PATH_SCRIPTS <- file.path(ROOT, "scripts")
PATH_CLEAN   <- file.path(ROOT, "data_clean")
source(file.path(PATH_SCRIPTS, "01_import_clean.R"))

cat("\n=== 02 受试者流转与基线特征 ===\n")

# ============================================================================
# A. CONSORT 流程表
# ============================================================================
cat("\n--- CONSORT 流程 ---\n")

consort <- readRDS(file.path(PATH_CLEAN, "consort.rds"))

# 统计各节点人数
n_screened      <- nrow(consort)  # 完成知情同意
n_eligible      <- n_screened     # 符合标准（近似）
n_randomized     <- n_screened

n_ai_rand       <- sum(consort$Group == "AI组")
n_self_rand     <- sum(consort$Group == "Self组")

# 脱落阶段
consort <- consort |>
  mutate(
    stage_bin = case_when(
      is.na(DropoutStage)                        ~ "完成",
      DropoutStage == "随机分配后基线测试阶段"  ~ "T0脱落",
      DropoutStage == "干预过程中"              ~ "干预期脱落",
      TRUE                                      ~ "其他"
    )
  )

n_t0_dropout    <- sum(consort$stage_bin == "T0脱落")
n_int_dropout   <- sum(consort$stage_bin == "干预期脱落")
n_completed     <- sum(consort$stage_bin == "完成")

# 进入干预（近似：T0期未脱落）
n_entered_int   <- n_randomized - n_t0_dropout

# PP
n_pp            <- sum(consort$EnteredPP)
n_ai_pp         <- sum(consort$EnteredPP & consort$Group == "AI组")
n_self_pp       <- sum(consort$EnteredPP & consort$Group == "Self组")

# 率与 Wilson CI
rate_enter  <- ci_wilson(n_entered_int, n_randomized)
rate_pp     <- ci_wilson(n_pp, n_randomized)
rate_ai_pp  <- ci_wilson(n_ai_pp, n_ai_rand)
rate_self_pp<- ci_wilson(n_self_pp, n_self_rand)

cat(sprintf(
  "筛查知情: %d\n  随机化: %d (AI=%d, Self=%d)\n  T0期脱落: %d\n  干预期脱落: %d\n  进入干预: %d (率=%.1f%%, 95%%CI[%.1f%%,%.1f%%])\n  PP: %d (率=%.1f%%, 95%%CI[%.1f%%,%.1f%%])\n",
  n_screened,
  n_randomized, n_ai_rand, n_self_rand,
  n_t0_dropout, n_int_dropout,
  n_entered_int,
  rate_enter$estimate * 100,
  rate_enter$ci_low * 100, rate_enter$ci_high * 100,
  n_pp,
  rate_pp$estimate * 100,
  rate_pp$ci_low * 100, rate_pp$ci_high * 100
))

# 脱落原因统计
dropout_summary <- consort |>
  filter(stage_bin != "完成") |>
  count(stage_bin, DropoutReason, Group, .drop = FALSE) |>
  arrange(stage_bin, Group) |>
  rename(脱落阶段 = stage_bin, 脱落原因 = DropoutReason, 组别 = Group, 人数 = n)

# 完整流程表
flow_table <- tibble(
  阶段                    = c(
    "完成筛查与知情同意",
    "随机化",
    "  AI辅助组",
    "  自我指导组",
    "随机分配后基线测试阶段脱落",
    "  个人事务",
    "  腰部损伤（P028）",
    "进入正式干预",
    "  AI辅助组",
    "  自我指导组",
    "干预过程中脱落",
    "  个人事务",
    "  出勤率低",
    "完成主要后测（PP）",
    "  AI辅助组",
    "  自我指导组"
  ),
  人数_AI = c(NA, n_ai_rand, NA, NA,
              sum(consort$stage_bin == "T0脱落" & consort$Group == "AI组"),
              sum(consort$DropoutReason == "个人事务" & consort$Group == "AI组"),
              sum(consort$DropoutReason == "腰部损伤" & consort$Group == "AI组"),
              n_ai_rand - n_t0_dropout,
              NA, NA,
              sum(consort$stage_bin == "干预期脱落" & consort$Group == "AI组"),
              sum(consort$DropoutReason == "个人事务" & consort$Group == "AI组" & consort$stage_bin == "干预期脱落"),
              sum(consort$DropoutReason == "出勤率低" & consort$Group == "AI组" & consort$stage_bin == "干预期脱落"),
              n_ai_pp, NA, NA),
  人数_Self = c(NA, n_self_rand, NA, NA,
                sum(consort$stage_bin == "T0脱落" & consort$Group == "Self组"),
                sum(consort$DropoutReason == "个人事务" & consort$Group == "Self组"),
                0,
                n_self_rand - n_t0_dropout,
                NA, NA,
                sum(consort$stage_bin == "干预期脱落" & consort$Group == "Self组"),
                sum(consort$DropoutReason == "个人事务" & consort$Group == "Self组" & consort$stage_bin == "干预期脱落"),
                sum(consort$DropoutReason == "出勤率低" & consort$Group == "Self组" & consort$stage_bin == "干预期脱落"),
                n_self_pp, NA, NA),
  备注 = c("", "分层区组随机化", "", "",
           "含1名P028（AI组）", "6人", "1人", "近似值，待精确", "", "",
           "P031", "P032,P033,P035,P036", "", "", "")
)

save_tbl(flow_table, "table_flow")

# 脱落原因表
if (nrow(dropout_summary) > 0) {
  save_tbl(dropout_summary, "table_dropout_reasons")
}

# ============================================================================
# B. 基线特征表（PP样本 24人）
# ============================================================================
cat("\n--- 基线特征表 ---\n")

main <- readRDS(file.path(PATH_CLEAN, "main.rds"))

# 连续变量描述
cont_vars <- c("Age", "Height_cm", "BW_kg", "Pre1RM", "Rel1RM_t0",
               "PreCMJ", "PreSJ", "PreSE")

baseline_cont <- map_dfr(cont_vars, function(v) {
  ai   <- main |> filter(Group == "AI组")   |> pull(!!sym(v)) |> discard(is.na)
  self <- main |> filter(Group == "Self组") |> pull(!!sym(v)) |> discard(is.na)

  # Welch t检验
  t_res <- t.test(ai, self, var.equal = FALSE)

  # Hedges' g（变化值口径的标准化均差）
  pooled_sd <- sqrt(((length(ai) - 1) * var(ai) + (length(self) - 1) * var(self)) /
                    (length(ai) + length(self) - 2))
  hedges_g  <- (mean(ai) - mean(self)) / pooled_sd
  # 小样本校正
  J <- 1 - 3 / (4 * (length(ai) + length(self)) - 9)
  hedges_g  <- J * hedges_g

  tibble(
    变量 = v,
    n_AI   = length(ai),
    n_Self = length(self),
    AI组   = sprintf("%.1f ± %.1f", mean(ai), sd(ai)),
    Self组 = sprintf("%.1f ± %.1f", mean(self), sd(self)),
    p值    = fmt_p(t_res$p.value),
    Hedges_g = sprintf("%.2f", hedges_g)
  )
})

baseline_cont$变量 <- recode(baseline_cont$变量,
  "Age"         = "年龄（岁）",
  "Height_cm"   = "身高（cm）",
  "BW_kg"       = "体重（kg）",
  "Pre1RM"      = "基线深蹲1RM（kg）",
  "Rel1RM_t0"   = "基线相对1RM（kg/kg）",
  "PreCMJ"      = "基线CMJ（cm）",
  "PreSJ"       = "基线SJ（cm）",
  "PreSE"       = "基线训练自我效能（分）"
)

# 分类变量
cat_vars <- c("Stratum", "Freq_wk", "ResistYears")
baseline_cat <- map_dfr(cat_vars, function(v) {
  t <- table(main$Group, main[[v]]) |>
    addmargins() |>
    as.data.frame() |>
    setNames(c("Group", "Value", "n")) |>
    pivot_wider(names_from = Group, values_from = n) |>
    filter(Value != "Sum") |>
    rename(变量 = Value)
  tibble(
    变量     = paste0(v, ": ", t$变量),
    AI组     = as.character(t$AI组),
    Self组   = as.character(t$Self组),
    p值       = "",
    Hedges_g  = ""
  )
})

baseline_table <- bind_rows(
  baseline_cont,
  baseline_cat
) |>
  select(变量, `AI组 (n=11)`, `Self组 (n=13)`, p值, Hedges_g)

names(baseline_table)[2:3] <- c("AI组 (n=11)", "Self组 (n=13)")

save_tbl(baseline_table, "table_baseline")

cat("✓ CONSORT 流程表和基线特征表已保存\n")
