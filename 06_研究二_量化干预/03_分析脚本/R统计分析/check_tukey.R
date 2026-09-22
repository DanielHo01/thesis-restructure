source("00_setup.R")
source("01_data_load.R")

sess_order <- paste0("S", 1:8)
df_hooper <- df_monitor %>%
  select(ID, Group, Sess, Week, Hooper_final) %>%
  mutate(
    Sess_factor = factor(Sess, levels = sess_order, ordered = FALSE),
    ID = factor(ID)
  ) %>%
  filter(!is.na(Hooper_final))

model_A <- lmerTest::lmer(
  Hooper_final ~ Group * Sess_factor + (1 | ID),
  data = df_hooper,
  REML = TRUE,
  control = lmerControl(optimizer = "bobyqa", optCtrl = list(maxfun = 100000))
)

emm_sess <- emmeans(model_A, ~Sess_factor)
sess_pairwise <- contrast(emm_sess, method = "pairwise", adjust = "tukey")
sp <- as.data.frame(sess_pairwise)

cat("=== 所有 Tukey 配对比较（双侧 FDR校正）===\n")
cat("(仅显示含 S8 的对比)\n\n")
sp8 <- sp[grep("S8", sp$contrast), ]
print(sp8)

cat("\n=== 全部显著配对 (p < 0.05) ===\n")
sig <- sp[sp$p.value < 0.05, ]
print(sig)

cat("\n=== 全部配对（28对）===\n")
print(sp[, c("contrast", "estimate", "df", "t.ratio", "p.value")])
