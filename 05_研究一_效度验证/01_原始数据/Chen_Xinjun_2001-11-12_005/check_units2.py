from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

c3d = ezc3d.c3d(str(TRIAL_DIR / "Gait FB - CAST 11.c3d"))
labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
pts = c3d["data"]["points"]
n_frames = pts.shape[2]

for m in [12, 6, 1]:  # L_TH4, R_HUM, L_HUM
    name = labels[m]
    z_vals = pts[2, m, :]
    valid = z_vals[~np.isnan(z_vals)]
    if len(valid) > 0:
        print(
            f"{name}: min={np.min(valid):.4f}, max={np.max(valid):.4f}, range={np.max(valid) - np.min(valid):.4f}"
        )
        print(
            f"  Sample values (frames 0,100,200,500): {z_vals[0]:.4f}, {z_vals[100]:.4f}, {z_vals[200]:.4f}, {z_vals[500]:.4f}"
        )

print("\n=== Interpretation ===")
print("If z range ~1-2: units are METERS (reasonable for human movement)")
print("If z range ~1000-2000: units are MILLIMETERS")
