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


def find_best_marker_in_window(pts, labels, patterns, frame_start, frame_end):
    for p in patterns:
        for i, l in enumerate(labels):
            if p.upper() in l.upper():
                z = pts[2, i, frame_start:frame_end]
                valid = z[~np.isnan(z)]
                if len(valid) > 20:
                    return i
    return None


def compute_arm_amp_and_depth(pts, labels, frame_start, frame_end, frame_takeoff):
    w_idx = find_best_marker_in_window(
        pts, labels, Marker_Config["wrist"], frame_start, frame_end
    )
    s_idx = (
        find_best_marker_in_window(
            pts, labels, Marker_Config["shoulder"], frame_start, frame_end
        )
        if w_idx is not None
        else None
    )

    arm_amp = None
    if w_idx is not None and s_idx is not None:
        wz = pts[2, w_idx, frame_start:frame_end]
        sz = pts[2, s_idx, frame_start:frame_end]
        rel_z = wz - sz
        valid = rel_z[~np.isnan(rel_z)]
        if len(valid) > 10:
            arm_amp = float(np.nanmax(rel_z) - np.nanmin(rel_z))

    p_idx = find_best_marker_in_window(
        pts, labels, Marker_Config["pelvis"], frame_start, frame_end
    )
    cm_depth = None
    if p_idx is not None:
        baseline_frames = min(60, frame_start) if frame_start > 0 else 30
        if frame_start <= 0:
            baseline_z = np.nanmedian(pts[2, p_idx, 0:30])
        else:
            baseline_z = np.nanmedian(
                pts[2, p_idx, frame_start : frame_start + baseline_frames]
            )
        squat_window = pts[2, p_idx, frame_start:frame_takeoff]
        valid_sq = squat_window[~np.isnan(squat_window)]
        if len(valid_sq) > 10:
            min_z = np.nanmin(squat_window)
            cm_depth = float(baseline_z - min_z) if (baseline_z - min_z) > 0 else 0.0

    return arm_amp, cm_depth


def load_qc_events():
    qc_events = {}
    try:
        with open("docs/qc_plots/qc_flags.csv") as f:
            reader = csv.DictReader(f)
            for row in reader:
                qc_events[int(row["trial_id"])] = {
                    "t_takeoff": float(row["t_takeoff"]),
                    "BW_kg": float(row["BW_kg"]),
                }
    except:
        pass
    return qc_events


qc_events = load_qc_events()
print("trial_id,arm_amp_m,cm_depth_m,valid_markers")
for tid in TRIALS:
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {tid}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    point_rate = c3d["header"]["points"]["frame_rate"]
    n_frames = pts.shape[2]
    n_markers = pts.shape[1]

    if n_markers == 0:
        print(f"{tid},NA,NA,False")
        continue

    qc = qc_events.get(tid, {"t_takeoff": 0.5})
    t_takeoff = qc["t_takeoff"]
    fs = point_rate
    frame_takeoff = int(t_takeoff * fs)
    frame_start = max(0, int((t_takeoff - 1.0) * fs))
    frame_end = min(n_frames, int((t_takeoff + 0.2) * fs))

    arm_amp, cm_depth = compute_arm_amp_and_depth(
        pts, labels, frame_start, frame_end, frame_takeoff
    )
    arm_str = f"{arm_amp:.4f}" if arm_amp is not None else "NA"
    cm_str = f"{cm_depth:.4f}" if cm_depth is not None else "NA"
    print(f"{tid},{arm_str},{cm_str},True")
