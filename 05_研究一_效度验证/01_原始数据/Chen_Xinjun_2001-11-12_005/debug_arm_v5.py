from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")
TRIALS = [11]

Marker_Config = {
    "wrist": ["L_HUM", "R_HUM", "L_HLE", "R_HLE", "L_RSP", "R_RSP", "L_USP", "R_USP"],
    "shoulder": ["L_SAE", "R_SAE", "L_HM2", "R_HM2", "L_RSP", "R_RSP"],
    "pelvis": ["L_TH4", "R_TH4", "L_TH1", "R_TH1"],
}


def find_best_marker_in_window(pts, labels, patterns, frame_start, frame_end):
    print(
        f"  find_best_marker: patterns={patterns}, frame_start={frame_start}, frame_end={frame_end}"
    )
    for p in patterns:
        for i, l in enumerate(labels):
            if p.upper() in l.upper():
                z = pts[2, i, frame_start:frame_end]
                valid = z[~np.isnan(z)]
                print(f"    Check {l} (idx={i}): valid={len(valid)}")
                if len(valid) > 20:
                    print(f"    -> SELECTED {l}")
                    return i
    print("    -> NONE FOUND")
    return None


def compute_metrics(pts, labels, frame_takeoff, point_rate, n_frames):
    frame_start = max(0, int((frame_takeoff - 1.0) * point_rate))
    frame_end = min(n_frames, int((frame_takeoff + 0.2) * point_rate))
    print(f"Window: [{frame_start}, {frame_end})")

    w_idx = find_best_marker_in_window(
        pts, labels, Marker_Config["wrist"], frame_start, frame_end
    )
    s_idx = (
        find_best_marker_in_window(
            pts, labels, Marker_Config["shoulder"], frame_start, frame_end
        )
        if w_idx
        else None
    )
    print(f"w_idx={w_idx}, s_idx={s_idx}")

    arm_amp = None
    if w_idx and s_idx:
        wz = pts[2, w_idx, frame_start:frame_end]
        sz = pts[2, s_idx, frame_start:frame_end]
        rel_z = wz - sz
        valid = rel_z[~np.isnan(rel_z)]
        print(f"rel_z valid: {len(valid)}")
        if len(valid) > 10:
            arm_amp = float(np.nanmax(rel_z) - np.nanmin(rel_z))
            print(f"arm_amp = {arm_amp:.4f}")

    p_idx = find_best_marker_in_window(
        pts, labels, Marker_Config["pelvis"], frame_start, frame_end
    )
    jump_height = None
    if p_idx:
        baseline_window = (
            pts[2, p_idx, max(0, frame_start) : frame_start + 60]
            if frame_start > 0
            else pts[2, p_idx, 0:30]
        )
        baseline_z = float(np.nanmedian(baseline_window))
        peak_window = pts[2, p_idx, frame_takeoff : min(n_frames, frame_takeoff + 200)]
        valid_peak = peak_window[~np.isnan(peak_window)]
        if len(valid_peak) > 10:
            peak_z = float(np.nanmax(peak_window))
            jump_height = peak_z - baseline_z
            print(
                f"baseline_z={baseline_z:.4f}, peak_z={peak_z:.4f}, jump_height={jump_height:.4f}"
            )

    return arm_amp, jump_height


for tid in [11]:
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {tid}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    point_rate = c3d["header"]["points"]["frame_rate"]
    n_markers = pts.shape[1]
    n_frames = pts.shape[2]

    print(f"\n=== Trial {tid} ===")
    print(f"n_markers={n_markers}, n_frames={n_frames}, point_rate={point_rate}")

    t_takeoff = 0.442
    frame_takeoff = int(t_takeoff * point_rate)
    arm_amp, jump_height = compute_metrics(
        pts, labels, frame_takeoff, point_rate, n_frames
    )
    print(f"Result: arm_amp={arm_amp}, jump_height={jump_height}")
