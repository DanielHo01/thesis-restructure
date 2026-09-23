library(dplyr)
mon <- readRDS('data_clean/monitor.rds')
warmup <- mon |> filter(!is.na(GA) & !is.na(App)) |> mutate(Diff=App-GA)
n_warmup <- nrow(warmup)
n_warmup_ids <- length(unique(warmup$ID))
cat("n_warmup =", n_warmup, "\n")
cat("n_warmup_ids =", n_warmup_ids, "\n")

w1 <- as.character(n_warmup)
w2 <- as.character(n_warmup_ids)
w3 <- sprintf("%.4f", mean(warmup$Diff))
w4 <- sprintf("%.4f", sd(warmup$Diff))
w5 <- sprintf("%.4f", mean(warmup$Diff) - 1.96 * sd(warmup$Diff))
w6 <- sprintf("%.4f", mean(warmup$Diff) + 1.96 * sd(warmup$Diff))
w7 <- sprintf("%.4f", mean(abs(warmup$Diff)))
w8 <- sprintf("%.4f", sqrt(mean(warmup$Diff^2)))
w9 <- sprintf("%.4f", cor(warmup$GA, warmup$App))

icc_21 <- function(x, y) {
  z <- na.omit(data.frame(x = x, y = y))
  if (nrow(z) < 2) return(NA_real_)
  icc_res <- suppressWarnings(psych::ICC(z))
  icc_df <- icc_res$results
  subset(icc_df, type == "ICC_2")$ICC
}

icc_val <- suppressWarnings(icc_21(warmup$GA, warmup$App))
cat("icc_val =", icc_val, "\n")
w10 <- as.character(sprintf("%.4f", icc_val))
w11 <- sprintf("%.1f%%", sum(warmup$Diff > 0) / n_warmup * 100)
w12 <- sprintf("%.1f%%", sum(abs(warmup$Diff) < 0.001) / n_warmup * 100)
w13 <- sprintf("%.1f%%", sum(warmup$Diff < 0) / n_warmup * 100)

cat("w1 =", w1, "\n")
cat("w2 =", w2, "\n")
cat("w3 =", w3, "\n")
cat("w4 =", w4, "\n")
cat("w5 =", w5, "\n")
cat("w6 =", w6, "\n")
cat("w7 =", w7, "\n")
cat("w8 =", w8, "\n")
cat("w9 =", w9, "\n")
cat("w10 =", w10, "\n")
cat("w11 =", w11, "\n")
cat("w12 =", w12, "\n")
cat("w13 =", w13, "\n")

warmup_vals <- c(w1, w2, w3, w4, w5, w6, w7, w8, w9, w10, w11, w12, w13)
cat("len(warmup_vals) =", length(warmup_vals), "\n")
