# =============================================================================
# 09_spearman.R
# 探索性 Spearman 相关矩阵（28对）
# 7个训练过程指标 × 4个结局变化量
# =============================================================================

# 自动定位脚本目录，加载全局配置
if (!exists("ROOT")) {
  script_dir <- if (!is.null(sys.frame(1)$ofile)) dirname(normalizePath(sys.frame(1)$ofile)) else getwd()
  ROOT <- normalizePath(file.path(script_dir, ".."))
}
source(file.path(ROOT, "scripts", "01_import_clean.R"), encoding = "UTF-8")
cat("\n=== 09 探索性 Spearman 相关 ===\n")

main <- readRDS(file.path(PATH_CLEAN, "main.rds"))
mon  <- readRDS(file.path(PATH_CLEAN, "monitor.rds"))

# 派生训练过程指标
hooper_mean <- mon |>
  group_by(ID) |>
  summarise(Hooper_mean = mean(Hooper_tot, na.rm = TRUE), .groups = "drop")

main <- main |>
  left_join(hooper_mean, by = "ID") |>
  mutate(
    Load_per_rep = SquatLoad / Reps,
    Load_per_set = SquatLoad / Sets
  )

# ============================================================================
# 7个训练过程指标 × 4个结局变化量
# ============================================================================
cat_vars <- c(
  "TotalLoad",     # 1. 总外部负荷
  "Reps",         # 2. 总重复次数
  "Sets",         # 3. 总组数
  "Load_per_rep", # 4. 每重复平均负荷
  "sRPE",         # 5. 平均sRPE
  "Hooper_mean",  # 6. 平均Hooper
  "Duration"      # 7. 总训练时长
)

delta_vars <- c(
  "d1RM",   # Δ绝对1RM
  "dCMJ",   # ΔCMJ
  "dSJ",    # ΔSJ
  "dSE"     # Δ自我效能
)

cat_labels <- c(
  "总外部负荷（kg）",
  "总重复次数",
  "总组数",
  "每重复平均负荷（kg）",
  "平均sRPE",
  "平均Hooper",
  "总训练时长（min）"
)

delta_labels <- c("Δ绝对1RM", "ΔCMJ", "ΔSJ", "Δ自我效能")

spearman_all <- map_dfr(cat_vars, function(cv) {
  map_dfr(delta_vars, function(dv) {
    d <- main |> select(all_of(cv), all_of(dv)) |> drop_na()
    n <- nrow(d)
    if (n < 6) return(tibble(n = n, rho = NA, p = NA))

    res <- cor.test(d[[cv]], d[[dv]], method = "spearman")
    tibble(n = n, rho = res$estimate, p = res$p.value)
  }) |>
    mutate(预测变量 = cv, 结局变量 = dv)
}) |>
  mutate(
    预测变量 = factor(预测变量, levels = cat_vars, labels = cat_labels),
    结局变量 = factor(结局变量, levels = delta_vars, labels = delta_labels)
  ) |>
  arrange(预测变量, 结局变量) |>
  mutate(p_fdr = p.adjust(p, method = "BH"))

# 显著性筛选（p < 0.05）
sig_pairs <- spearman_all |>
  filter(p < 0.05) |>
  mutate(
    rho      = sprintf("%.3f", rho),
    p_raw    = fmt_p(p),
    p_fdr_s  = fmt_p(p_fdr)
  ) |>
  select(预测变量, 结局变量, n, rho, p_raw, p_fdr_s)

save_tbl(sig_pairs, "table_spearman_sig")

# 完整矩阵（附录）
spearman_matrix <- spearman_all |>
  pivot_wider(names_from = 结局变量, values_from = c(rho, p, p_fdr)) |>
  arrange(预测变量)

save_tbl(spearman_matrix, "table_spearman_full")

# ============================================================================
# 相关热图
# ============================================================================
cat("绑制相关热图...\n")

rho_matrix <- spearman_all |>
  select(预测变量, 结局变量, rho) |>
  pivot_wider(names_from = 结局变量, values_from = rho) |>
  column_to_rownames("预测变量") |>
  as.matrix()

p_matrix <- spearman_all |>
  select(预测变量, 结局变量, p) |>
  pivot_wider(names_from = 结局变量, values_from = p) |>
  column_to_rownames("预测变量") |>
  as.matrix()

# 简单热图
rho_long <- spearman_all |>
  select(预测变量, 结局变量, rho)

p_heatmap <- rho_long |>
  ggplot(aes(x = 结局变量, y = 预测变量, fill = rho, label = sprintf("%.2f", rho))) +
  geom_tile(color = "white") +
  geom_text(size = 3) +
  scale_fill_gradient2(low = "#2166AC", mid = "white", high = "#B2182B",
                       midpoint = 0, limits = c(-1, 1)) +
  labs(x = "", y = "", fill = "Spearman ρ",
       title = "训练过程指标与结局变化量相关矩阵") +
  theme_thesis +
  theme(axis.text.x = element_text(angle = 20, hjust = 1),
        legend.position = "right")

save_fig(p_heatmap, "fig_correlation_heatmap", w = 14, h = 10)

cat(sprintf(
  "  28对中，原始p<0.05：%d对\n  FDR校正后p<0.05：%d对\n",
  sum(spearman_all$p < 0.05),
  sum(spearman_all$p_fdr < 0.05)
))

cat("✓ Spearman 相关分析完成\n")
