from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")
TRIALS = [4, 5, 6, 7, 9, 11, 12, 13, 15, 16, 17, 18, 19, 20, 22, 23, 24]


def get_marker_z(pts, labels, marker_name, frame_start=0, frame_end=None):
    """Get z trajectory for a marker"""
    if frame_end is None:
        frame_end = pts.shape[2]
    try:
        idx = labels.index(marker_name)
    except ValueError:
        return None
    z = pts[2, idx, frame_start:frame_end]
    if np.all(np.isnan(z)):
        return None
    return z


def extract_mocap_metrics(trial_id):
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {trial_id}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    n_frames = pts.shape[2]

    arm_amp = None
    cm_depth = None

    wrist_markers = ["L_HUM", "R_HUM", "L_HLE", "R_HLE"]
    shoulder_markers = ["L_SAE", "R_SAE", "L_HM2", "R_HM2"]

    best_arm_amp = 0
    for wm in wrist_markers:
        for sm in shoulder_markers:
            wz = get_marker_z(pts, labels, wm)
            sz = get_marker_z(pts, labels, sm)
            if wz is not None and sz is not None:
                rel_z = wz - sz
                valid = rel_z[~np.isnan(rel_z)]
                if len(valid) > 0:
                    amp = np.nanmax(rel_z) - np.nanmin(rel_z)
                    if amp > best_arm_amp:
                        best_arm_amp = amp
    arm_amp = best_arm_amp if best_arm_amp > 0 else None

    pelvis_markers = ["L_TH4", "R_TH4"]
    for pm in pelvis_markers:
        pz = get_marker_z(pts, labels, pm)
        if pz is not None:
            valid = pz[~np.isnan(pz)]
            if len(valid) > 0:
                cm_depth = np.nanmax(pz) - np.nanmin(pz)
                break

    return arm_amp, cm_depth


print("trial_id,arm_amp_m,cm_depth_m")
for tid in TRIALS:
    arm_amp, cm_depth = extract_mocap_metrics(tid)
    arm_str = f"{arm_amp:.4f}" if arm_amp is not None else "NA"
    cm_str = f"{cm_depth:.4f}" if cm_depth is not None else "NA"
    print(f"{tid},{arm_str},{cm_str}")
