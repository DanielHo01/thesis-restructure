import csv
from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")
TRIALS = [4, 5, 6, 7, 9, 11, 12, 13, 15, 16, 17, 18, 19, 20, 22, 23, 24]

Marker_Config = {
    "wrist": ["L_HUM", "R_HUM", "L_HLE", "R_HLE"],
    "shoulder": ["L_SAE", "R_SAE", "L_HM2", "R_HM2"],
    "pelvis": ["L_TH4", "R_TH4", "L_TH1", "R_TH1"],
}


def find_marker_idx(labels, patterns):
    for p in patterns:
        for i, l in enumerate(labels):
            if p.upper() in l.upper():
                return i
    return None


def compute_relative_arm_amp(pts, labels, frame_start, frame_end):
    w_idx = find_marker_idx(labels, Marker_Config["wrist"])
    s_idx = find_marker_idx(labels, Marker_Config["shoulder"])
    if w_idx is None or s_idx is None:
        return None
    wz = pts[2, w_idx, frame_start:frame_end]
    sz = pts[2, s_idx, frame_start:frame_end]
    rel_z = wz - sz
    valid = rel_z[~np.isnan(rel_z)]
    if len(valid) < 10:
        return None
    return float(np.nanmax(rel_z) - np.nanmin(rel_z))


def compute_cm_depth(pts, labels, frame_start, frame_end, frame_takeoff):
    p_idx = find_marker_idx(labels, Marker_Config["pelvis"])
    if p_idx is None:
        return None
    baseline_frames = min(60, frame_start) if frame_start > 0 else 30
    if frame_start <= 0:
        baseline_z = np.nanmedian(pts[2, p_idx, 0:30])
    else:
        baseline_z = np.nanmedian(
            pts[2, p_idx, frame_start : frame_start + baseline_frames]
        )
    squat_window = pts[2, p_idx, frame_start:frame_takeoff]
    valid = squat_window[~np.isnan(squat_window)]
    if len(valid) < 10:
        return None
    min_z = np.nanmin(squat_window)
    depth = baseline_z - min_z
    return float(depth) if depth > 0 else 0.0


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


def process_trial_v2(trial_id, qc_events):
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {trial_id}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    point_rate = c3d["header"]["points"]["frame_rate"]
    n_markers = pts.shape[1]
    n_frames = pts.shape[2]

    if n_markers == 0:
        return {
            "trial_id": trial_id,
            "arm_amp_m": None,
            "cm_depth_m": None,
            "valid_markers": False,
        }

    qc = qc_events.get(trial_id, {})
    t_takeoff = qc.get("t_takeoff", 0.5)
    fs = point_rate
    frame_takeoff = int(t_takeoff * fs)
    frame_start = max(0, int((t_takeoff - 1.0) * fs))
    frame_end = min(n_frames, int((t_takeoff + 0.2) * fs))

    arm_amp = compute_relative_arm_amp(pts, labels, frame_start, frame_end)
    cm_depth = compute_cm_depth(pts, labels, frame_start, frame_end, frame_takeoff)

    return {
        "trial_id": trial_id,
        "arm_amp_m": arm_amp,
        "cm_depth_m": cm_depth,
        "valid_markers": True,
    }


qc_events = load_qc_events()
print("trial_id,arm_amp_m,cm_depth_m,valid_markers")
for tid in TRIALS:
    r = process_trial_v2(tid, qc_events)
    arm_str = f"{r['arm_amp_m']:.4f}" if r["arm_amp_m"] is not None else "NA"
    cm_str = f"{r['cm_depth_m']:.4f}" if r["cm_depth_m"] is not None else "NA"
    print(f"{r['trial_id']},{arm_str},{cm_str},{r['valid_markers']}")
