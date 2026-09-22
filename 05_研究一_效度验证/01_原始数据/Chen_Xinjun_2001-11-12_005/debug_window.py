from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

c3d = ezc3d.c3d(str(TRIAL_DIR / "Gait FB - CAST 11.c3d"))
labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
pts = c3d["data"]["points"]
n_frames = pts.shape[2]
point_rate = c3d["header"]["points"]["frame_rate"]

t_takeoff = 0.442
frame_start = int((t_takeoff - 1.2) * point_rate)
frame_end = int((t_takeoff + 0.1) * point_rate)
print(
    f"Window: frames {frame_start} to {frame_end} (t={frame_start / point_rate:.2f}s to {frame_end / point_rate:.2f}s)"
)

for marker_name, patterns in [
    ("L_HUM", ["L_HUM"]),
    ("L_SAE", ["L_SAE"]),
    ("R_HUM", ["R_HUM"]),
    ("R_SAE", ["R_SAE"]),
    ("L_TH4", ["L_TH4"]),
]:
    for p in patterns:
        try:
            idx = labels.index(p)
        except ValueError:
            continue
        z_vals = pts[2, idx, :]
        window_z = z_vals[frame_start:frame_end]
        valid = window_z[~np.isnan(window_z)]
        print(
            f"{marker_name} (idx={idx}): window_valid={len(valid)}/{len(window_z)}, global_valid={len(z_vals[~np.isnan(z_vals)])}"
        )
