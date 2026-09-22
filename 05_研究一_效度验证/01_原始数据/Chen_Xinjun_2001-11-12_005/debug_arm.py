from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

c3d = ezc3d.c3d(str(TRIAL_DIR / "Gait FB - CAST 11.c3d"))
labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
pts = c3d["data"]["points"]
point_rate = c3d["header"]["points"]["frame_rate"]

t_takeoff = 0.442
fs = point_rate
frame_takeoff = int(t_takeoff * fs)
frame_start = max(0, int((t_takeoff - 1.0) * fs))
frame_end = min(pts.shape[2], int((t_takeoff + 0.2) * fs))

print(f"Window: frame_start={frame_start}, frame_end={frame_end}")

wrist_patterns = [
        "L_HUM",
        "R_HUM",
        "L_HLE",
        "R_HLE",
        "L_RSP",
        "R_RSP",
        "L_USP",
        "R_USP",
]
shoulder_patterns = ["L_SAE", "R_SAE", "L_HM2", "R_HM2", "L_RSP", "R_RSP"]

w_idx = None
for p in wrist_patterns:
        for i, l in enumerate(labels):
                if p.upper() in l.upper():
                        z = pts[2, i, frame_start:frame_end]
                        valid = z[~np.isnan(z)]
                        print(
                                f"Wrist {l} (idx={i}): valid in window = {len(valid)}/{frame_end - frame_start}"
                        )
                        if len(valid) > 20 and w_idx is None:
                                w_idx = i

s_idx = None
for p in shoulder_patterns:
        for i, l in enumerate(labels):
                if p.upper() in l.upper():
                        z = pts[2, i, frame_start:frame_end]
                        valid = z[~np.isnan(z)]
                        print(
                                f"Shoulder {l} (idx={i}): valid in window = {len(valid)}/{frame_end - frame_start}"
                        )
                        if len(valid) > 20 and s_idx is None:
                                s_idx = i

print(f"\nw_idx={w_idx}, s_idx={s_idx}")

if w_idx is not None and s_idx is not None:
        wz = pts[2, w_idx, frame_start:frame_end]
        sz = pts[2, s_idx, frame_start:frame_end]
        rel_z = wz - sz
        valid = rel_z[~np.isnan(rel_z)]
        print(f"rel_z valid: {len(valid)}/{len(rel_z)}")
        print(f"rel_z sample: {rel_z[0]:.4f}, {rel_z[50]:.4f}, {rel_z[100]:.4f}")
        if len(valid) > 10:
                print(f"arm_amp = {np.nanmax(rel_z) - np.nanmin(rel_z):.4f}")
