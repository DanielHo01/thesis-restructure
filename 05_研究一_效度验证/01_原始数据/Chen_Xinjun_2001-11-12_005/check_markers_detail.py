from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

c3d = ezc3d.c3d(str(TRIAL_DIR / "Gait FB - CAST 11.c3d"))
labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
pts = c3d["data"]["points"]

print("=== All Markers ===")
for i, l in enumerate(labels):
    z_sample = pts[2, i, :100]
    valid_z = z_sample[~np.isnan(z_sample)]
    if len(valid_z) > 0:
        z_range = np.nanmax(pts[2, i, :]) - np.nanmin(pts[2, i, :])
        print(f"{i:2d}: {l:15s} | z_range={z_range:.4f} (raw units)")
    else:
        print(f"{i:2d}: {l:15s} | ALL NaN")

print("\n=== Unit Check (first 5 frames of marker 0) ===")
for f in range(5):
    print(
        f"Frame {f}: x={pts[0, 0, f]:.2f}, y={pts[1, 0, f]:.2f}, z={pts[2, 0, f]:.2f}"
    )

z_val = pts[2, 0, 100]
print(f"\nTypical z value: {z_val:.4f} (if ~1000-2000 range -> mm, if ~1-2 -> m)")
