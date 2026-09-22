from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")
TRIALS_WITH_MARKERS = [4, 5, 6, 7, 11, 12, 13, 16, 17, 19, 22, 23, 24]


def analyze_out_of_plane(trial_id):
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {trial_id}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    n_markers = pts.shape[1]

    if n_markers == 0:
        return None

    out_of_plane_ranges = []
    for i in range(n_markers):
        x = pts[0, i, :]
        z = pts[2, i, :]
        if not np.all(np.isnan(x)) and not np.all(np.isnan(z)):
            x_range = np.nanmax(x) - np.nanmin(x)
            z_range = np.nanmax(z) - np.nanmin(z)
            if z_range > 0.01:
                ratio = x_range / z_range
                out_of_plane_ranges.append(ratio)

    if out_of_plane_ranges:
        return np.median(out_of_plane_ranges)
    return None


print("trial_id,median_x_z_ratio,interpretation")
for tid in TRIALS_WITH_MARKERS:
    ratio = analyze_out_of_plane(tid)
    if ratio is not None:
        interp = (
            "2D OK"
            if ratio < 0.2
            else "Significant OOP"
            if ratio > 0.5
            else "Moderate OOP"
        )
        print(f"{tid},{ratio:.4f},{interp}")
    else:
        print(f"{tid},NA,No data")
