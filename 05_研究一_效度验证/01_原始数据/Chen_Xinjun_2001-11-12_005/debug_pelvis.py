from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

c3d = ezc3d.c3d(str(TRIAL_DIR / "Gait FB - CAST 11.c3d"))
labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
pts = c3d["data"]["points"]
point_rate = c3d["header"]["points"]["frame_rate"]

pelvis_idx = labels.index("L_TH4")
pelvis_z = pts[2, pelvis_idx, :]

print("Trial 11 L_TH4 (pelvis) z trajectory (every 50 frames):")
for f in range(0, 1023, 50):
    z = pelvis_z[f]
    t = f / point_rate
    print(f"  frame {f:4d} (t={t:.2f}s): z={z:.4f} m ({z * 1000:.1f} mm)")

print(f"\nPelvis z range: {np.nanmin(pelvis_z):.4f} to {np.nanmax(pelvis_z):.4f} m")
print(
    f"Full trial range: {(np.nanmax(pelvis_z) - np.nanmin(pelvis_z)):.4f} m = {(np.nanmax(pelvis_z) - np.nanmin(pelvis_z)) * 1000:.1f} mm"
)
