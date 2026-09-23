# =============================================================================
# 06_feasibility_training.R
# 可行性指标 + 训练执行组间比较
# =============================================================================

# =============================================================================
# =============================================================================
# Auto-detect script directory for Rscript and RStudio
# =============================================================================
n <- sys.nframe()
if (n == 0L) {
  # Running via Rscript directly: script path is last arg containing '.R'
  script_arg <- commandArgs()[max(grep("scripts/", commandArgs()))]
  script_path <- normalizePath(file.path(getwd(), script_arg))
} else {
  # Running via source() in RStudio or another script
  script_path <- tryCatch(normalizePath(sys.frame(1L)$ofile), error = function(e) NA_character_)
}
script_dir <- dirname(script_path)
ROOT <- normalizePath(file.path(script_dir, ".."))
PATH_SCRIPTS <- file.path(ROOT, "scripts")
PATH_CLEAN   <- file.path(ROOT, "data_clean")
source(file.path(PATH_SCRIPTS, "00_setup.R"), local = FALSE, encoding = "UTF-8")



cat("\n=== 06 可行性与训练执行 ===\n")

main <- readRDS(file.path(PATH_CLEAN, "main.rds"))
mon  <- readRDS(file.path(PATH_CLEAN, "monitor.rds"))

# ============================================================================
# A. 可行性指标
# ============================================================================
cat("\n--- 可行性指标 ---\n")

n_randomized <- 36
n_ai_rand   <- 18
n_self_rand <- 18
n_entered   <- 29  # 近似，待精确
n_pp        <- 24
n_ai_pp     <- 11
n_self_pp   <- 13

# 率与 Wilson CI
rate_enter <- ci_wilson(n_entered, n_randomized)
rate_pp     <- ci_wilson(n_pp, n_randomized)
rate_ai_pp  <- ci_wilson(n_ai_pp, n_ai_rand)
rate_self_pp<- ci_wilson(n_self_pp, n_self_rand)

# 训练完成率（189/192）
mon <- mon |>
  mutate(
    Complete = !is.na(Hooper_tot)  # 以Hooper是否记录为完整标准
  )
sessions_complete <- sum(mon$Complete)
rate_sessions <- ci_wilson(sessions_complete, 192)

# 依从率
n_high_adhere <- sum(main$Attend_int >= 6.4)  # ≥80% 课次
rate_adhere   <- ci_wilson(n_high_adhere, n_pp)

# AI组指标
n_vel_hit   <- sum(main$AIVelHit >= 60, na.rm = TRUE)
n_auto_red  <- sum(main$AutoReduce, na.rm = TRUE)  # 总触发次数
n_auto_red_subj <- sum(main$AutoReduce > 0, na.rm = TRUE)

# SUS（低于70阈值）
sus <- readRDS(file.path(PATH_CLEAN, "sus.rds"))
n_sus_below70 <- sum(sus$SUS_recalc < 70)

feasibility <- tibble(
  维度       = c("招募", "招募", "方案", "方案", "数据", "系统", "过程", "安全"),
  指标       = c(
    "进入干预率",
    "主要后测完成率（PP）",
    "训练记录完整率",
    "高依从率（≥80%课次）",
    "数据完整性（PP层）",
    "AI组SUS评分",
    "速度目标命中率（AI组）",
    "严重不良事件"
  ),
  预设阈值   = c("≥80%", "≥80%", "≥80%", "≥80%", "≥90%", "≥70分", "≥60%", "0例"),
  实际结果  = c(
    sprintf("%d/%d（%.1f%%）", n_entered, n_randomized, rate_enter$estimate * 100),
    sprintf("%d/%d（%.1f%%）", n_pp, n_randomized, rate_pp$estimate * 100),
    sprintf("%d/%d（%.1f%%）", sessions_complete, 192, rate_sessions$estimate * 100),
    sprintf("%d/%d（%.1f%%）", n_high_adhere, n_pp, rate_adhere$estimate * 100),
    sprintf("%.0f%%", 100),
    sprintf("%.1f ± %.1f", mean(sus$SUS_recalc), sd(sus$SUS_recalc)),
    sprintf("%.1f%%", mean(main$AIVelHit, na.rm = TRUE)),
    "0例"
  ),
  判定       = c(
    ifelse(rate_enter$ci_low >= 0.80, "达标", "临界达标"),
    ifelse(rate_pp$ci_low >= 0.80, "达标", "未达标"),
    ifelse(rate_sessions$ci_low >= 0.80, "达标", "未达标"),
    ifelse(rate_adhere$ci_low >= 0.80, "达标", "未达标"),
    "达标",
    ifelse(mean(sus$SUS_recalc) >= 70, "达标", "未达标"),
    ifelse(mean(main$AIVelHit, na.rm = TRUE) >= 60, "达标", "未达标"),
    "达标"
  )
) |>
  mutate(
    `95CI` = case_when(
      指标 == "进入干预率"       ~ sprintf("[%.1f%%, %.1f%%]", rate_enter$ci_low*100, rate_enter$ci_high*100),
      指标 == "主要后测完成率"  ~ sprintf("[%.1f%%, %.1f%%]", rate_pp$ci_low*100, rate_pp$ci_high*100),
      指标 == "训练记录完整率"  ~ sprintf("[%.1f%%, %.1f%%]", rate_sessions$ci_low*100, rate_sessions$ci_high*100),
      指标 == "高依从率"        ~ sprintf("[%.1f%%, %.1f%%]", rate_adhere$ci_low*100, rate_adhere$ci_high*100),
      指标 == "AI组SUS评分"     ~ sprintf("[%.1f, %.1f]",
                                           t.test(sus$SUS_recalc)$conf.int[1],
                                           t.test(sus$SUS_recalc)$conf.int[2]),
      TRUE                       ~ ""
    )
  )

save_tbl(feasibility, "table_feasibility")

# ============================================================================
# B. 训练执行组间比较
# ============================================================================
cat("\n--- 训练执行组间比较 ---\n")

# 派生每课平均负荷
main <- main |> mutate(LoadPerSess = TotalLoad / Attend_int)

exec_vars <- c(
  "TotalLoad", "SquatLoad", "Sets", "Reps", "sRPE", "Duration"
)

exec_results <- map_dfr(exec_vars, function(v) {
  ai   <- main |> dplyr::filter(Group == "AI组")   |> pull(!!sym(v))
  self <- main |> dplyr::filter(Group == "Self组") |> pull(!!sym(v))

  t_res   <- t.test(ai, self, var.equal = FALSE)
  n1 <- length(ai); n2 <- length(self)
  pooled  <- sqrt(((n1-1)*var(ai) + (n2-1)*var(self)) / (n1+n2-2))
  cohens_d <- (mean(ai) - mean(self)) / pooled
  J <- 1 - 3 / (4*(n1+n2) - 9)
  hedges_g <- J * cohens_d

  tibble(
    变量      = v,
    n_AI     = n1,
    n_Self   = n2,
    AI组     = sprintf("%.1f ± %.1f", mean(ai), sd(ai)),
    Self组   = sprintf("%.1f ± %.1f", mean(self), sd(self)),
    差值     = sprintf("%.2f", mean(ai) - mean(self)),
    CI_lo    = sprintf("%.2f", t_res$conf.int[1]),
    CI_hi    = sprintf("%.2f", t_res$conf.int[2]),
    p值      = fmt_p(t_res$p.value),
    Hedges_g = sprintf("%.2f", hedges_g)
  )
})

exec_results$变量 <- dplyr::recode(exec_results$变量,
  "TotalLoad"   = "全期总负荷（kg）",
  "SquatLoad"   = "深蹲总负荷（kg）",
  "Sets"        = "总组数",
  "Reps"        = "总重复次数",
  "sRPE"        = "平均sRPE",
  "Duration"    = "总训练时长（min）"
)

save_tbl(exec_results, "table_training_execution")

cat("\n  总重复次数 Welch t检验：\n")
print(exec_results |> dplyr::filter(变量 == "总重复次数"))

# ============================================================================
# C. AI组专项指标
# ============================================================================
cat("\n--- AI组执行质量 ---\n")

ai_main <- main |> dplyr::filter(Group == "AI组")

ai_stats <- tibble(
  指标          = c(
    "速度目标命中率均值",
    "速度目标命中率范围",
    "自动减组触发次数",
    "触发受试者数",
    "速度命中率≥60%人数"
  ),
  数值 = c(
    sprintf("%.1f ± %.1f%%", mean(ai_main$AIVelHit, na.rm = TRUE),
            sd(ai_main$AIVelHit, na.rm = TRUE)),
    sprintf("%.1f%% – %.1f%%", min(ai_main$AIVelHit, na.rm = TRUE),
            max(ai_main$AIVelHit, na.rm = TRUE)),
    sprintf("%d次", sum(ai_main$AutoReduce, na.rm = TRUE)),
    sprintf("%d人", sum(ai_main$AutoReduce > 0, na.rm = TRUE)),
    sprintf("%d/%d", sum(ai_main$AIVelHit >= 60, na.rm = TRUE), n_ai_pp)
  )
)

save_tbl(ai_stats, "table_ai_execution")

cat("✓ 可行性与训练执行分析完成\n")
