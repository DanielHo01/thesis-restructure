from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")
TRIALS = [4, 5, 6, 7, 9, 11, 12, 13, 15, 16, 17, 18, 19, 20, 22, 23, 24]


def get_marker_z(pts, labels, marker_name):
    """Get z trajectory for a marker"""
    try:
        idx = labels.index(marker_name)
    except ValueError:
        return None
    z = pts[2, idx, :]
    if np.all(np.isnan(z)):
        return None
    return z


def find_marker_pattern(pts, labels, patterns):
    """Find first marker matching any pattern"""
    for p in patterns:
        for i, l in enumerate(labels):
            if p.lower() in l.lower():
                return i
    return None


def extract_mocap_metrics(trial_id):
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {trial_id}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    n_markers = pts.shape[1]

    if n_markers == 0:
        return None, None

    arm_amp = None
    cm_depth = None

    wrist_patterns = ["HUM", "HLE", "WR", "WRI"]
    shoulder_patterns = ["SAE", "HM2", "CLAV", "STER"]

    best_arm_amp = 0
    w_idx = find_marker_pattern(pts, labels, wrist_patterns)
    s_idx = find_marker_pattern(pts, labels, shoulder_patterns)

    if w_idx is not None and s_idx is not None:
        wz = pts[2, w_idx, :]
        sz = pts[2, s_idx, :]
        rel_z = wz - sz
        valid = rel_z[~np.isnan(rel_z)]
        if len(valid) > 0:
            arm_amp = np.nanmax(rel_z) - np.nanmin(rel_z)

    pelvis_patterns = ["TH4", "TH1", "PEL", "HIP", "SAC"]
    p_idx = find_marker_pattern(pts, labels, pelvis_patterns)

    if p_idx is not None:
        pz = pts[2, p_idx, :]
        valid = pz[~np.isnan(pz)]
        if len(valid) > 0:
            cm_depth = np.nanmax(pz) - np.nanmin(pz)

    return arm_amp, cm_depth


results = []
for tid in TRIALS:
    arm_amp, cm_depth = extract_mocap_metrics(tid)
    results.append(
        {
            "trial_id": tid,
            "arm_amp_m": arm_amp,
            "cm_depth_m": cm_depth,
            "has_markers": arm_amp is not None or cm_depth is not None,
        }
    )

print("trial_id,arm_amp_m,cm_depth_m,has_markers")
for r in results:
    arm_str = f"{r['arm_amp_m']:.4f}" if r["arm_amp_m"] is not None else "NA"
    cm_str = f"{r['cm_depth_m']:.4f}" if r["cm_depth_m"] is not None else "NA"
    print(f"{r['trial_id']},{arm_str},{cm_str},{r['has_markers']}")
