import csv
from pathlib import Path

import ezc3d
import numpy as np

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")
TRIALS = [4, 5, 6, 7, 9, 11, 12, 13, 15, 16, 17, 18, 19, 20, 22, 23, 24]

Marker_Config = {
    "hand": ["L_HLE", "R_HLE"],
    "thoracolumbar": ["L_TH4", "R_TH4", "L_TH1", "R_TH1"],
}


def find_marker(pts, labels, patterns, frame_start, frame_end):
    for p in patterns:
        for i, l in enumerate(labels):
            if p.upper() in l.upper():
                z = pts[2, i, frame_start:frame_end]
                valid = z[~np.isnan(z)]
                if len(valid) > 20:
                    return i, l
    return None, None


def detect_vertical_axis(pts, fz_total, t_takeoff, t_landing, point_rate):
    fs = point_rate
    n = pts.shape[2]
    idx_start = max(0, int((t_takeoff - 1.0) * fs))
    idx_end = min(n, int((t_landing + 0.2) * fs))

    fz_window = fz_total[idx_start:idx_end]
    m = len(fz_window)
    if m < 50:
        return 2, 1

    a_fp = np.gradient(np.gradient(fz_window))

    best_corr = -1
    best_axis = 2
    best_sign = 1

    for axis in range(3):
        axis_data = pts[axis, :, idx_start:idx_end]
        valid_mask = ~np.isnan(axis_data)
        if valid_mask.sum() < 50:
            continue
        for col in range(axis_data.shape[0]):
            col_data = axis_data[col, :]
            col_clean = col_data.copy()
            col_clean[~valid_mask[col, :]] = np.nan
            valid = col_clean[~np.isnan(col_clean)]
            if len(valid) < 50:
                continue
            col_clean = np.interp(
                np.arange(len(col_data)),
                np.arange(len(col_data))[~np.isnan(col_clean)],
                valid,
            )
            col_smooth = np.convolve(col_clean, np.ones(5) / 5, mode="same")
            a_axis = np.gradient(np.gradient(col_smooth))
            corr = np.corrcoef(a_fp[: len(a_axis)], a_axis[: len(a_fp)])[0, 1]
            if not np.isnan(corr) and abs(corr) > abs(best_corr):
                best_corr = corr
                best_axis = axis
                best_sign = 1 if corr > 0 else -1

    return best_axis, best_sign


def load_qc_events():
    qc_events = {}
    try:
        with open("docs/qc_plots/qc_flags.csv") as f:
            reader = csv.DictReader(f)
            for row in reader:
                qc_events[int(row["trial_id"])] = {
                    "t_takeoff": float(row["t_takeoff"]),
                    "t_landing": float(row["t_landing"]),
                    "BW_kg": float(row["BW_kg"]),
                    "FT_s": float(row["FT_s"]) if row["FT_s"] != "NA" else None,
                    "JH_m": float(row["JH_m"]) if row["JH_m"] != "NA" else None,
                }
    except:
        pass
    return qc_events


def compute_metrics(
    pts,
    labels,
    fz_total,
    frame_takeoff,
    frame_landing,
    point_rate,
    n_frames,
    vertical_axis,
    vertical_sign,
):
    fs = point_rate
    idx_start = max(0, frame_takeoff - int(0.5 * fs))
    idx_end = min(n_frames, frame_takeoff)

    mid_thor = None
    for p in Marker_Config["thoracolumbar"]:
        for i, l in enumerate(labels):
            if p.upper() == l.upper():
                z = pts[vertical_axis, i, :]
                valid = z[~np.isnan(z)]
                if len(valid) > 100:
                    if mid_thor is None:
                        mid_thor = z.copy()
                    else:
                        mask = ~np.isnan(z)
                        mid_thor[mask] = np.where(
                            mask, (mid_thor[mask] + z[mask]) / 2, mid_thor
                        )
                    break

    baseline_vals = mid_thor[idx_start:idx_end] if mid_thor is not None else None
    baseline = (
        float(np.nanmedian(baseline_vals))
        if baseline_vals is not None
        and len(baseline_vals[~np.isnan(baseline_vals)]) > 10
        else None
    )

    peak_vals = mid_thor[frame_takeoff:frame_landing] if mid_thor is not None else None
    peak = (
        float(np.nanmax(peak_vals))
        if peak_vals is not None and len(peak_vals[~np.isnan(peak_vals)]) > 10
        else None
    )

    thoracolumbar_rise = (
        (peak - baseline) * vertical_sign
        if (baseline is not None and peak is not None)
        else None
    )
    if thoracolumbar_rise is not None and thoracolumbar_rise < 0:
        thoracolumbar_rise = None

    hand_amp = None
    for p in Marker_Config["hand"]:
        for i, l in enumerate(labels):
            if p.upper() == l.upper():
                h_z = pts[
                    vertical_axis,
                    i,
                    max(0, frame_takeoff - int(1.0 * fs)) : frame_takeoff,
                ]
                valid = h_z[~np.isnan(h_z)]
                if len(valid) > 20 and mid_thor is not None:
                    th_ref = mid_thor[
                        max(0, frame_takeoff - int(1.0 * fs)) : frame_takeoff
                    ]
                    rel_z = h_z * vertical_sign - th_ref * vertical_sign
                    rel_valid = rel_z[~np.isnan(rel_z)]
                    if len(rel_valid) > 10:
                        amp = float(np.nanmax(rel_z) - np.nanmin(rel_z))
                        if hand_amp is None or amp > hand_amp:
                            hand_amp = amp
                break

    return thoracolumbar_rise, hand_amp


qc_events = load_qc_events()
results = []

for tid in TRIALS:
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {tid}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    point_rate = c3d["header"]["points"]["frame_rate"]
    n_markers = pts.shape[1]
    n_frames = pts.shape[2]

    if n_markers == 0:
        results.append(
            {
                "trial_id": tid,
                "vertical_axis": None,
                "thoracolumbar_rise_m": None,
                "arm_amp_m": None,
                "valid_markers": False,
            }
        )
        continue

    qc = qc_events.get(tid, {"t_takeoff": 0.5, "t_landing": 1.0})
    t_takeoff = qc["t_takeoff"]
    t_landing = qc["t_landing"]
    frame_takeoff = int(t_takeoff * point_rate)
    frame_landing = int(t_landing * point_rate)

    fz_total = np.zeros(n_frames)
    for i, l in enumerate(labels):
        if "Force Z" in l:
            fz_idx = i
            fz_raw = pts[2, fz_idx, :].copy()
            fz_raw[np.isnan(fz_raw)] = 0
            if np.mean(fz_raw[:100]) < 0:
                fz_raw = -fz_raw
            fz_total += fz_raw

    vertical_axis, vertical_sign = detect_vertical_axis(
        pts, fz_total, t_takeoff, t_landing, point_rate
    )

    rise, arm_amp = compute_metrics(
        pts,
        labels,
        fz_total,
        frame_takeoff,
        frame_landing,
        point_rate,
        n_frames,
        vertical_axis,
        vertical_sign,
    )

    results.append(
        {
            "trial_id": tid,
            "vertical_axis": vertical_axis,
            "thoracolumbar_rise_m": rise,
            "arm_amp_m": arm_amp,
            "valid_markers": True,
        }
    )

print("trial_id,vertical_axis,thoracolumbar_rise_m,arm_amp_m,valid_markers")
for r in results:
    rise_str = (
        f"{r['thoracolumbar_rise_m']:.4f}"
        if r["thoracolumbar_rise_m"] is not None
        else "NA"
    )
    arm_str = f"{r['arm_amp_m']:.4f}" if r["arm_amp_m"] is not None else "NA"
    vaxis = r["vertical_axis"] if r["vertical_axis"] is not None else "NA"
    print(f"{r['trial_id']},{vaxis},{rise_str},{arm_str},{r['valid_markers']}")
