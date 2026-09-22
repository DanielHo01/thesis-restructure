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


def find_marker_data(pts, labels, patterns, axis=2):
    for p in patterns:
        for i, l in enumerate(labels):
            if p.upper() in l.upper():
                z = pts[axis, i, :]
                valid = z[~np.isnan(z)]
                if len(valid) > 100:
                    return i, l, z
    return None, None, None


def detect_vertical_axis(pts, fz_total, t_takeoff, t_landing, point_rate):
    fs = point_rate
    n = pts.shape[2]
    idx_start = max(0, int((t_takeoff - 1.0) * fs))
    idx_end = min(n, int((t_landing + 0.2) * fs))
    if idx_end - idx_start < 50:
        return 2, 1

    fz_window = fz_total[idx_start:idx_end]
    a_fp = np.gradient(np.gradient(fz_window))

    best_corr = -1
    best_axis = 2
    best_sign = 1

    for axis in range(3):
        for i in range(pts.shape[1]):
            z = pts[axis, i, idx_start:idx_end]
            valid = z[~np.isnan(z)]
            if len(valid) < 50:
                continue
            clean = np.interp(np.arange(len(z)), np.arange(len(z))[~np.isnan(z)], valid)
            smooth = np.convolve(clean, np.ones(5) / 5, mode="same")
            a_axis = np.gradient(np.gradient(smooth))
            min_len = min(len(a_fp), len(a_axis))
            corr = np.corrcoef(a_fp[:min_len], a_axis[:min_len])[0, 1]
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

    idx_l4, _, z_l4 = find_marker_data(pts, labels, ["L_TH4"], vertical_axis)
    idx_r4, _, z_r4 = find_marker_data(pts, labels, ["R_TH4"], vertical_axis)

    if idx_l4 is not None and idx_r4 is not None:
        valid_mask = ~(np.isnan(z_l4) | np.isnan(z_r4))
        mid_thor = np.where(valid_mask, (z_l4 + z_r4) / 2, np.nan)
    elif idx_l4 is not None:
        mid_thor = z_l4.copy()
    elif idx_r4 is not None:
        mid_thor = z_r4.copy()
    else:
        mid_thor = None

    baseline_start = max(0, frame_takeoff - int(0.5 * fs))
    baseline_end = frame_takeoff
    baseline_vals = (
        mid_thor[baseline_start:baseline_end] if mid_thor is not None else None
    )
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

    thoracolumbar_rise = None
    if baseline is not None and peak is not None:
        raw_rise = (peak - baseline) * vertical_sign
        if raw_rise > 0:
            thoracolumbar_rise = float(raw_rise)

    arm_amp = None
    for p in Marker_Config["hand"]:
        idx_h, _, z_h = find_marker_data(pts, labels, [p], vertical_axis)
        if idx_h is not None and mid_thor is not None:
            window_start = max(0, frame_takeoff - int(1.0 * fs))
            window_end = frame_takeoff
            h_window = z_h[window_start:window_end] * vertical_sign
            th_window = mid_thor[window_start:window_end] * vertical_sign
            h_clean = (
                np.interp(
                    np.arange(len(h_window)),
                    np.arange(len(h_window))[~np.isnan(h_window)],
                    h_window[~np.isnan(h_window)],
                )
                if np.any(~np.isnan(h_window))
                else h_window
            )
            th_clean = (
                np.interp(
                    np.arange(len(th_window)),
                    np.arange(len(th_window))[~np.isnan(th_window)],
                    th_window[~np.isnan(th_window)],
                )
                if np.any(~np.isnan(th_window))
                else th_window
            )
            rel_z = h_clean - th_clean
            valid_rel = rel_z[~np.isnan(rel_z)]
            if len(valid_rel) > 20:
                amp = float(np.nanmax(rel_z) - np.nanmin(rel_z))
                if arm_amp is None or amp > arm_amp:
                    arm_amp = amp

    return thoracolumbar_rise, arm_amp


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
        print(f"{tid},NA,NA,NA,False")
        continue

    qc = qc_events.get(tid, {"t_takeoff": 0.5, "t_landing": 1.0})
    t_takeoff = qc["t_takeoff"]
    t_landing = qc["t_landing"]
    frame_takeoff = int(t_takeoff * point_rate)
    frame_landing = int(t_landing * point_rate)

    fz_total = np.zeros(n_frames)
    for i, l in enumerate(labels):
        if "Force Z" in l:
            fz_raw = pts[2, i, :].copy()
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
    rise_str = f"{rise:.4f}" if rise is not None else "NA"
    arm_str = f"{arm_amp:.4f}" if arm_amp is not None else "NA"
    print(f"{tid},{vertical_axis},{rise_str},{arm_str},True")
