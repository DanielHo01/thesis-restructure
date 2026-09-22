from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

c3d = ezc3d.c3d(str(TRIAL_DIR / "Gait FB - CAST 11.c3d"))
labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
pts = c3d["data"]["points"]
n_frames = pts.shape[2]

print(
    f"Total frames: {n_frames}, point_rate: {c3d['header']['points']['frame_rate']} Hz"
)
print(f"Trial duration: {n_frames / c3d['header']['points']['frame_rate']:.2f} s")

first_valid_frame = None
for f in range(n_frames):
    if not np.isnan(pts[2, 12, f]):
        first_valid_frame = f
        break

print(f"\nFirst valid frame for L_TH4 (pelvis): {first_valid_frame}")
if first_valid_frame:
    print(
        f"L_TH4 z at frame {first_valid_frame}: {pts[2, 12, first_valid_frame]:.4f} (raw units)"
    )
    print(
        f"L_TH4 z range (full): {np.nanmax(pts[2, 12, :]) - np.nanmin(pts[2, 12, :]):.4f}"
    )

    z_at_100 = pts[2, 12, 100] if not np.isnan(pts[2, 12, 100]) else "NaN"
    z_at_200 = pts[2, 12, 200] if not np.isnan(pts[2, 12, 200]) else "NaN"
    print(f"L_TH4 z at frame 100: {z_at_100}")
    print(f"L_TH4 z at frame 200: {z_at_200}")
    print(f"If in meters: {z_at_100} m, if in mm: {z_at_100 * 1000:.1f} mm")
