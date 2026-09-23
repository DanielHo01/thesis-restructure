# =============================================================================
# 07_sus_acceptance.R
# SUS可用性 + TAM接受度分析（AI组 11人）
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



cat("\n=== 07 SUS 与 TAM 接受度 ===\n")

sus <- readRDS(file.path(PATH_CLEAN, "sus.rds"))
acc <- readRDS(file.path(PATH_CLEAN, "acceptance.rds"))

# ============================================================================
# A. SUS（使用重算分）
# ============================================================================
cat("\n--- SUS 可用性量表 ---\n")

sus_sum <- tibble(
  指标              = c("n", "均值", "SD", "最小值", "最大值",
                          "≥70分人数", "≥70分比例", "SUS评分95%CI"),
  数值 = c(
    nrow(sus),
    sprintf("%.1f", mean(sus$SUS_recalc)),
    sprintf("%.1f", sd(sus$SUS_recalc)),
    sprintf("%.1f", min(sus$SUS_recalc)),
    sprintf("%.1f", max(sus$SUS_recalc)),
    sum(sus$SUS_recalc >= 70),
    sprintf("%.1f%%", mean(sus$SUS_recalc >= 70) * 100),
    sprintf("[%.1f, %.1f]",
            t.test(sus$SUS_recalc)$conf.int[1],
            t.test(sus$SUS_recalc)$conf.int[2])
  )
)

cat(sprintf(
  "  SUS重算均值 = %.1f ± %.1f (范围 %.0f–%.0f)\n  ≥70分：%d/%d (%.1f%%)\n",
  mean(sus$SUS_recalc), sd(sus$SUS_recalc),
  min(sus$SUS_recalc), max(sus$SUS_recalc),
  sum(sus$SUS_recalc >= 70), nrow(sus),
  mean(sus$SUS_recalc >= 70) * 100
))

# 条目级描述
sus_items <- sus |>
  summarise(across(starts_with("Q"), list(mean = ~mean(., na.rm=TRUE), sd = ~sd(., na.rm=TRUE)))) |>
  pivot_longer(everything(), names_to = c("Item", ".value"), names_pattern = "Q(.+)_(.+)")

sus_table <- tibble(
  条目 = paste0("SUS_Q", 1:10),
  均值  = sapply(1:10, function(i) sprintf("%.1f", mean(sus[[paste0("Q", i)]]))),
  SD    = sapply(1:10, function(i) sprintf("%.1f", sd(sus[[paste0("Q", i)]])))
)

save_tbl(sus_sum, "table_sus_summary")
save_tbl(sus_table, "table_sus_items")

# ============================================================================
# B. TAM 接受度（无原始分，只有均值）
# ============================================================================
cat("\n--- TAM 接受度 ---\n")

cat("  注意：PU/Trust/Intention仅有均值，无条目级原始分，无法重算Cronbach's α\n")

tam_sum <- tibble(
  维度          = c("感知有用性（PU）", "系统信任度（Trust）", "使用意愿（Intention）"),
  n           = c(nrow(acc), nrow(acc), nrow(acc)),
  均值        = c(
    sprintf("%.2f", mean(acc$PU_mean)),
    sprintf("%.2f", mean(acc$Trust_mean)),
    sprintf("%.2f", mean(acc$Int_mean))
  ),
  SD          = c(
    sprintf("%.2f", sd(acc$PU_mean)),
    sprintf("%.2f", sd(acc$Trust_mean)),
    sprintf("%.2f", sd(acc$Int_mean))
  ),
  范围        = c(
    sprintf("%.0f–%.0f", min(acc$PU_mean), max(acc$PU_mean)),
    sprintf("%.0f–%.0f", min(acc$Trust_mean), max(acc$Trust_mean)),
    sprintf("%.0f–%.0f", min(acc$Int_mean), max(acc$Int_mean))
  ),
  `95%CI`     = c(
    sprintf("[%.2f, %.2f]",
            t.test(acc$PU_mean)$conf.int[1],
            t.test(acc$PU_mean)$conf.int[2]),
    sprintf("[%.2f, %.2f]",
            t.test(acc$Trust_mean)$conf.int[1],
            t.test(acc$Trust_mean)$conf.int[2]),
    sprintf("[%.2f, %.2f]",
            t.test(acc$Int_mean)$conf.int[1],
            t.test(acc$Int_mean)$conf.int[2])
  )
)

save_tbl(tam_sum, "table_tam_acceptance")

cat(sprintf("  PU均值 = %.2f ± %.2f\n", mean(acc$PU_mean), sd(acc$PU_mean)))
cat(sprintf("  Trust均值 = %.2f ± %.2f\n", mean(acc$Trust_mean), sd(acc$Trust_mean)))
cat(sprintf("  Int均值 = %.2f ± %.2f\n", mean(acc$Int_mean), sd(acc$Int_mean)))

# ============================================================================
# C. 综合接受度图
# ============================================================================
cat("绑制接受度图...\n")

# SUS个人得分条形图
p_sus <- sus |>
  ggplot(aes(x = reorder(ID, SUS_recalc), y = SUS_recalc, fill = SUS_recalc >= 70)) +
  geom_col(width = 0.7) +
  geom_hline(yintercept = 70, color = "red", linetype = "dashed", linewidth = 1) +
  geom_hline(yintercept = mean(sus$SUS_recalc),
             color = "blue", linetype = "dotdash", linewidth = 0.8) +
  scale_fill_manual(values = c("TRUE" = COL_AI, "FALSE" = "gray70"),
                    guide = "none") +
  coord_flip() +
  labs(x = "受试者", y = "SUS 评分",
       title = sprintf("SUS个人得分（均值=%.1f，红色虚线=70分阈值）",
                       mean(sus$SUS_recalc))) +
  theme_thesis +
  theme(legend.position = "none")

# TAM三维度箱线图
p_tam <- acc |>
  select(ID, PU_mean, Trust_mean, Int_mean) |>
  pivot_longer(-ID, names_to = "Dimension", values_to = "Score") |>
  mutate(
    Dimension = dplyr::recode(Dimension,
      "PU_mean"    = "感知有用性",
      "Trust_mean" = "系统信任度",
      "Int_mean"   = "使用意愿"
    )
  ) |>
  ggplot(aes(x = Dimension, y = Score, fill = Dimension)) +
  geom_boxplot(alpha = 0.7) +
  stat_summary(fun = mean, geom = "point", color = "red", shape = 8, size = 3) +
  scale_fill_brewer(palette = "Set2") +
  labs(x = "", y = "Likert 5点均值", title = "TAM三维度得分") +
  theme_thesis +
  theme(legend.position = "none", axis.text.x = element_text(angle = 15))

p_combined <- p_sus + p_tam
save_fig(p_combined, "fig_sus_tam", w = 16, h = 8)

cat("✓ SUS 与接受度分析完成\n")
