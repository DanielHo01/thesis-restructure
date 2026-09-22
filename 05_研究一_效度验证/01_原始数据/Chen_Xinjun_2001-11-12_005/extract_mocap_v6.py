import csv
from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")
TRIALS = [4, 5, 6, 7, 9, 11, 12, 13, 15, 16, 17, 18, 19, 20, 22, 23, 24]

Marker_Config = {
    "wrist": ["L_HUM", "R_HUM", "L_HLE", "R_HLE", "L_RSP", "R_RSP", "L_USP", "R_USP"],
    "shoulder": ["L_SAE", "R_SAE", "L_HM2", "R_HM2", "L_RSP", "R_RSP"],
    "pelvis": ["L_TH4", "R_TH4", "L_TH1", "R_TH1"],
}


def find_best_marker(pts, labels, patterns, frame_start, frame_end):
    for p in patterns:
        for i, l in enumerate(labels):
            if p.upper() in l.upper():
                z = pts[2, i, frame_start:frame_end]
                valid = z[~np.isnan(z)]
                if len(valid) > 20:
                    return i
    return None


def compute_metrics(pts, labels, frame_takeoff, point_rate, n_frames):
    baseline_before = max(0, frame_takeoff - int(1.0 * point_rate))
    window_end = min(n_frames, frame_takeoff + int(0.2 * point_rate))

    w_idx = find_best_marker(
        pts, labels, Marker_Config["wrist"], baseline_before, window_end
    )
    s_idx = (
        find_best_marker(
            pts, labels, Marker_Config["shoulder"], baseline_before, window_end
        )
        if w_idx
        else None
    )

    arm_amp = None
    if w_idx and s_idx:
        wz = pts[2, w_idx, baseline_before:window_end]
        sz = pts[2, s_idx, baseline_before:window_end]
        rel_z = wz - sz
        valid = rel_z[~np.isnan(rel_z)]
        if len(valid) > 10:
            arm_amp = float(np.nanmax(rel_z) - np.nanmin(rel_z))

    p_idx = find_best_marker(
        pts, labels, Marker_Config["pelvis"], baseline_before, window_end
    )
    jump_height = None
    if p_idx:
        baseline_z = float(
            np.nanmedian(pts[2, p_idx, baseline_before : baseline_before + 60])
        )
        peak_window = pts[2, p_idx, frame_takeoff : min(n_frames, frame_takeoff + 200)]
        valid_peak = peak_window[~np.isnan(peak_window)]
        if len(valid_peak) > 10:
            peak_z = float(np.nanmax(peak_window))
            jump_height = peak_z - baseline_z

    return arm_amp, jump_height


def load_qc_events():
    qc_events = {}
    try:
        with open("docs/qc_plots/qc_flags.csv") as f:
            reader = csv.DictReader(f)
            for row in reader:
                qc_events[int(row["trial_id"])] = {"t_takeoff": float(row["t_takeoff"])}
    except:
        pass
    return qc_events


qc_events = load_qc_events()
print("trial_id,arm_amp_m,jump_height_m,valid_markers")
for tid in TRIALS:
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {tid}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    point_rate = c3d["header"]["points"]["frame_rate"]
    n_markers = pts.shape[1]
    n_frames = pts.shape[2]

    if n_markers == 0:
        print(f"{tid},NA,NA,False")
        continue

    t_takeoff = qc_events.get(tid, {"t_takeoff": 0.5})["t_takeoff"]
    frame_takeoff = int(t_takeoff * point_rate)

    arm_amp, jump_height = compute_metrics(
        pts, labels, frame_takeoff, point_rate, n_frames
    )
    arm_str = f"{arm_amp:.4f}" if arm_amp is not None else "NA"
    jh_str = f"{jump_height:.4f}" if jump_height is not None else "NA"
    print(f"{tid},{arm_str},{jh_str},True")
