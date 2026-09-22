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


def find_marker_z(pts, labels, patterns, axis=2):
    for p in patterns:
        for i, l in enumerate(labels):
            if p.upper() in l.upper():
                z = pts[axis, i, :]
                if np.sum(~np.isnan(z)) > 100:
                    return z
    return None


def detect_vertical_axis(pts, fz_total, t_takeoff, t_landing, point_rate):
    fs = point_rate
    n = pts.shape[2]
    idx_start = max(0, int((t_takeoff - 1.0) * fs))
    idx_end = min(n, int((t_landing + 0.2) * fs))
    if idx_end - idx_start < 50:
        return 2, 1

    fz_w = fz_total[idx_start:idx_end]
    a_fp = np.gradient(np.gradient(fz_w))
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
            for row in csv.DictReader(f):
                qc_events[int(row["trial_id"])] = {
                    "t_takeoff": float(row["t_takeoff"]),
                    "t_landing": float(row["t_landing"]),
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
    vertical_axis,
    vertical_sign,
):
    fs = point_rate
    n = pts.shape[2]

    z_l4 = find_marker_z(pts, labels, ["L_TH4"], vertical_axis)
    z_r4 = find_marker_z(pts, labels, ["R_TH4"], vertical_axis)

    b_start = max(0, frame_takeoff - int(0.5 * fs))
    b_end = frame_takeoff

    def marker_valid_in_window(z, ws, we):
        if z is None:
            return False
        vals = z[ws:we]
        return np.sum(~np.isnan(vals)) > 10

    def compute_mid(z1, z2):
        mid = np.full(n, np.nan)
        for f in range(n):
            if not (np.isnan(z1[f]) or np.isnan(z2[f])):
                mid[f] = (z1[f] + z2[f]) / 2.0
        return mid

    thor = None
    thor_source = "none"

    if z_l4 is not None and z_r4 is not None:
        mid_both = compute_mid(z_l4, z_r4)
        if np.sum(~np.isnan(mid_both[b_start:b_end])) > 10:
            thor = mid_both
            thor_source = "mid"
        elif marker_valid_in_window(z_l4, b_start, b_end):
            thor = z_l4.copy()
            thor_source = "L_TH4"
        elif marker_valid_in_window(z_r4, b_start, b_end):
            thor = z_r4.copy()
            thor_source = "R_TH4"
    elif z_l4 is not None:
        if marker_valid_in_window(z_l4, b_start, b_end):
            thor = z_l4.copy()
            thor_source = "L_TH4"
    elif z_r4 is not None:
        if marker_valid_in_window(z_r4, b_start, b_end):
            thor = z_r4.copy()
            thor_source = "R_TH4"

    baseline = None
    if thor is not None:
        b_vals = thor[b_start:b_end]
        b_clean = b_vals[~np.isnan(b_vals)]
        if len(b_clean) > 10:
            baseline = float(np.median(b_clean))

    peak = None
    if thor is not None and baseline is not None:
        p_vals = thor[frame_takeoff:frame_landing]
        p_clean = p_vals[~np.isnan(p_vals)]
        if len(p_clean) > 10:
            peak = float(np.max(p_clean))

    thoracolumbar_rise = None
    if baseline is not None and peak is not None:
        raw = (peak - baseline) * vertical_sign
        if raw > 0:
            thoracolumbar_rise = float(raw)

    arm_amp = None
    for p in Marker_Config["hand"]:
        z_h = find_marker_z(pts, labels, [p], vertical_axis)
        if z_h is not None and thor is not None:
            ws = max(0, frame_takeoff - int(1.0 * fs))
            we = frame_takeoff
            h_w = z_h[ws:we] * vertical_sign
            th_w = thor[ws:we] * vertical_sign
            valid = ~(np.isnan(h_w) | np.isnan(th_w))
            if np.sum(valid) > 20:
                rel = h_w[valid] - th_w[valid]
                amp = float(np.ptp(rel))
                if arm_amp is None or amp > arm_amp:
                    arm_amp = amp

    return thoracolumbar_rise, arm_amp, thor_source


qc_events = load_qc_events()
print("trial_id,vertical_axis,thoracolumbar_rise_m,arm_amp_m,thor_source,valid_markers")
for tid in TRIALS:
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {tid}.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    point_rate = c3d["header"]["points"]["frame_rate"]
    n_markers, n_frames = pts.shape[1], pts.shape[2]

    if n_markers == 0:
        print(f"{tid},NA,NA,NA,NA,False")
        continue

    qc = qc_events.get(tid, {"t_takeoff": 0.5, "t_landing": 1.0})
    frame_takeoff = int(qc["t_takeoff"] * point_rate)
    frame_landing = int(qc["t_landing"] * point_rate)

    fz_total = np.zeros(n_frames)
    for i, l in enumerate(labels):
        if "Force Z" in l:
            fz_raw = pts[2, i, :].copy()
            fz_raw[np.isnan(fz_raw)] = 0
            if np.mean(fz_raw[:100]) < 0:
                fz_raw = -fz_raw
            fz_total += fz_raw

    vertical_axis, vertical_sign = detect_vertical_axis(
        pts, fz_total, qc["t_takeoff"], qc["t_landing"], point_rate
    )
    rise, arm_amp, thor_src = compute_metrics(
        pts,
        labels,
        fz_total,
        frame_takeoff,
        frame_landing,
        point_rate,
        vertical_axis,
        vertical_sign,
    )

    rise_str = f"{rise:.4f}" if rise is not None else "NA"
    arm_str = f"{arm_amp:.4f}" if arm_amp is not None else "NA"
    print(f"{tid},{vertical_axis},{rise_str},{arm_str},{thor_src},True")
