# =============================================================================
# 02_flow_baseline.R
# 受试者流转（CONSORT）+ 基线特征表
# 数字全部从 data_raw/00_participants_36.csv 自动计算，不得手工指定
# =============================================================================

# 自动定位脚本目录，加载全局配置和清洗数据
# 注意：只 source 00_setup.R（加载包和工具函数），
# 然后直接从 RDS 读取数据，避免 source 01_import_clean.R
# 导致 main 被 PP 过滤数据覆盖。
if (!exists("ROOT")) {
  script_dir <- if (!is.null(sys.frame(1)$ofile)) dirname(normalizePath(sys.frame(1)$ofile)) else getwd()
  ROOT <- normalizePath(file.path(script_dir, ".."))
}
PATH_SCRIPTS <- file.path(ROOT, "scripts")
PATH_CLEAN   <- file.path(ROOT, "data_clean")
source(file.path(PATH_SCRIPTS, "00_setup.R"), local = FALSE, encoding = "UTF-8")
cat("\n=== 02 受试者流转与基线特征 ===\n")

consort <- readRDS(file.path(PATH_CLEAN, "consort.rds"))
# 注意：01_import_clean.R 末尾将 main 覆盖为 PP 24人，此处重新加载。
main   <- readRDS(file.path(PATH_CLEAN, "main.rds"))
cat(sprintf("DEBUG: main nrow=%d, unique_group=%s\n",
            nrow(main), paste(unique(main$Group), collapse="|")))

# ============================================================================
# A. CONSORT 精确口径
# ============================================================================
cat("\n--- CONSORT 精确口径 ---\n")

# 从数据自动计算，不硬编码
consort <- consort |>
  mutate(
    stage_bin = case_when(
      is.na(DropoutStage)                           ~ "完成",
      DropoutStage == "随机分配后基线测试阶段"       ~ "T0脱落",
      DropoutStage == "干预过程中"                   ~ "干预期脱落",
      TRUE                                         ~ "其他"
    )
  )

n_rand   <- nrow(consort)                                           # 36
n_ai     <- sum(consort$Group == "AI组")                           # 18
n_self   <- sum(consort$Group == "Self组")                         # 18
n_t0     <- sum(consort$stage_bin == "T0脱落")                     # 7
n_int    <- sum(consort$stage_bin == "干预期脱落")                  # 5
n_entered <- n_rand - n_t0                                        # 29 = 36 - 7
n_pp     <- sum(consort$EnteredPP)                                # 24
n_ai_pp  <- sum(consort$EnteredPP & consort$Group == "AI组")     # 11
n_self_pp<- sum(consort$EnteredPP & consort$Group == "Self组")    # 13
n_ai_int <- n_ai - sum(consort$stage_bin == "T0脱落" & consort$Group == "AI组")   # 13
n_self_int <- n_self - sum(consort$stage_bin == "T0脱落" & consort$Group == "Self组") # 16

# CONSORT 闭合验证
consort_check <- as.integer(n_t0) + as.integer(n_int) + as.integer(n_pp)
if (consort_check != as.integer(n_rand)) {
  stop(sprintf("CONSORT 未闭合: %d + %d + %d = %d != %d",
               n_t0, n_int, n_pp, consort_check, n_rand))
}   # 7+5+24=36
cat(sprintf(
  "  随机化: %d人 (AI=%d, Self=%d)\n  T0期脱落: %d人\n  干预期脱落: %d人\n  进入干预: %d人 (AI=%d, Self=%d)\n  完成PP: %d人 (AI=%d, Self=%d)\n  CONSORT闭合: %d + %d + %d = %d (应=36)\n",
  n_rand, n_ai, n_self,
  n_t0, n_int,
  n_entered, n_ai_int, n_self_int,
  n_pp, n_ai_pp, n_self_pp,
  n_t0, n_int, n_pp, n_rand
))

# ============================================================================
# B. 脱落名单（分组别）
# ============================================================================
drops <- consort |>
  filter(!is.na(DropoutStage)) |>
  count(stage_bin, Group, DropoutReason, name = "n") |>
  arrange(stage_bin, Group) |>
  rename(脱落阶段 = stage_bin, 组别 = Group, 脱落原因 = DropoutReason, 人数 = n)

save_tbl(drops, "table_dropout_reasons")

# ============================================================================
# C. CONSORT 流程表
# ============================================================================
flow_table <- tribble(
  ~阶段,                            ~AI, ~Self, ~合计, ~备注,
  "完成筛查与知情同意",               NA,  NA,  n_rand,  "",
  "分层区组随机分配",                n_ai, n_self, n_rand,  "分层变量：基线相对深蹲1RM",
  "  T0期脱落",                      NA,  NA,   n_t0,   "见下方详情",
  "  进入正式干预",                   n_ai_int, n_self_int, n_entered, "进入干预率 29/36=80.6%",
  "  干预期脱落",                    NA,  NA,   n_int,   "见下方详情",
  "  完成主要后测（PP）",            n_ai_pp, n_self_pp, n_pp,   "主要后测完成率 24/36=66.7%"
) |>
  mutate(
    AI    = ifelse(is.na(AI),    as.character(NA), as.character(AI)),
    Self  = ifelse(is.na(Self),   as.character(NA), as.character(Self)),
    备注  = ifelse(备注 == "",   as.character(NA), 备注)
  )

save_tbl(flow_table, "table_flow")

# T0脱落详情
t0_detail <- consort |>
  filter(stage_bin == "T0脱落") |>
  select(ID, Group, DropoutReason) |>
  rename(脱落者ID = ID, 组别 = Group, 脱落原因 = DropoutReason)
save_tbl(t0_detail, "table_t0_dropouts")

# 干预脱落详情
int_detail <- consort |>
  filter(stage_bin == "干预期脱落") |>
  select(ID, Group, DropoutReason) |>
  rename(脱落者ID = ID, 组别 = Group, 脱落原因 = DropoutReason)
save_tbl(int_detail, "table_int_dropouts")

# ============================================================================
# D. P028 专项审计
# ============================================================================
cat("\n--- P028 专项审计 ---\n")
p028 <- consort[consort$ID == "P028", ]
cat(sprintf(
  "  ID: %s\n  组别: %s\n  脱落阶段: %s\n  脱落原因: %s\n  是否完成1RM: %s\n  是否进入干预: %s\n",
  p028$ID, p028$Group, p028$DropoutStage, p028$DropoutReason,
  p028$Completed1RM, ifelse(p028$DropoutStage == "随机分配后基线测试阶段", "否", "是")
))
# P028 在 T0 脱落，1RM 在随机化前完成，故有 1RM 基线

# ============================================================================
# E. 率与 Wilson CI
# ============================================================================
cat("\n--- 关键率与 95%CI ---\n")
rate_enter  <- ci_wilson(n_entered, n_rand)
rate_pp     <- ci_wilson(n_pp, n_rand)
rate_ai_pp  <- ci_wilson(n_ai_pp, n_ai)
rate_self_pp<- ci_wilson(n_self_pp, n_self)

cat(sprintf(
  "  进入干预率: %d/%d = %.1f%% [%.1f%%, %.1f%%]\n  主要后测完成率: %d/%d = %.1f%% [%.1f%%, %.1f%%]\n  AI组PP率: %d/%d = %.1f%%\n  Self组PP率: %d/%d = %.1f%%\n",
  n_entered, n_rand, rate_enter$estimate*100,
  rate_enter$ci_low*100, rate_enter$ci_high*100,
  n_pp, n_rand, rate_pp$estimate*100,
  rate_pp$ci_low*100, rate_pp$ci_high*100,
  n_ai_pp, n_ai, rate_ai_pp$estimate*100,
  n_self_pp, n_self, rate_self_pp$estimate*100
))

# ============================================================================
# F. 基线特征表（PP 24人）
# ============================================================================
cat("\n--- 基线特征表（PP 24人）---\n")
cat(sprintf("DEBUG2: main nrow=%d, Group class=%s, n_AI=%d\n",
            nrow(main), class(main$Group)[1],
            sum(main$Group == "AI组", na.rm=TRUE)))

# ============================================================================
# F. 基线特征表（PP 24人）
# 用 base R 直接构建，避免 dplyr/purrr 兼容性问题
# ============================================================================

cont_vars  <- c("Age","Height_cm","BW_kg","Pre1RM","Rel1RM_t0","PreCMJ","PreSJ","PreSE")
cn_labels  <- c("年龄（岁）","身高（cm）","体重（kg）","基线深蹲1RM（kg）",
                 "基线相对1RM（kg/kg）","基线CMJ（cm）","基线SJ（cm）",
                 "基线训练自我效能（分）")

ai_main <- main[main$Group == "AI组", ]
self_main <- main[main$Group == "Self组", ]

baseline_rows <- lapply(seq_along(cont_vars), function(i) {
  v   <- cont_vars[i]
  ai_v <- as.numeric(ai_main[[v]])
  sl_v <- as.numeric(self_main[[v]])
  t_res <- t.test(ai_v, sl_v, var.equal = FALSE)
  n1 <- length(ai_v); n2 <- length(sl_v)
  pooled <- sqrt(((n1-1)*var(ai_v) + (n2-1)*var(sl_v)) / (n1+n2-2))
  g <- (1 - 3/(4*(n1+n2)-9)) * (mean(ai_v) - mean(sl_v)) / pooled
  data.frame(
    变量 = cn_labels[i],
    n_AI = n1,
    n_Self = n2,
    AI组 = sprintf("%.1f ± %.1f", mean(ai_v), sd(ai_v)),
    Self组 = sprintf("%.1f ± %.1f", mean(sl_v), sd(sl_v)),
    p值 = fmt_p(t_res$p.value),
    Hedges_g = sprintf("%.2f", g),
    stringsAsFactors = FALSE
  )
})
baseline_cont <- do.call(rbind, baseline_rows)

# 分类变量（Stratum、Freq_wk、ResistYears）
cat_lines <- list(Stratum = "力量分层", Freq_wk = "每周训练频率", ResistYears = "抗阻训练年限")
cat_rows <- lapply(names(cat_lines), function(v) {
  label <- cat_lines[[v]]
  ct <- table(main$Group, main[[v]])
  rownms <- rownames(ct)
  collb <- if (is.factor(main[[v]])) levels(main[[v]]) else sort(unique(main[[v]]))
  lapply(collb, function(lvl) {
    ai_n <- ct["AI组", lvl]
    sl_n <- ct["Self组", lvl]
    data.frame(变量 = paste0(label, "：", lvl),
               n_AI = NA_integer_, n_Self = NA_integer_,
               AI组 = as.character(ai_n), Self组 = as.character(sl_n),
               p值 = "", Hedges_g = "",
               stringsAsFactors = FALSE)
  })
})
baseline_cat <- do.call(rbind, unlist(cat_rows, recursive = FALSE))

baseline_table <- rbind(baseline_cont, baseline_cat)
names(baseline_table)[2:3] <- c("AI组 (n=11)", "Self组 (n=13)")

save_tbl(baseline_table, "table_baseline")

cat("✓ CONSORT 流程表和基线特征表已保存\n")
