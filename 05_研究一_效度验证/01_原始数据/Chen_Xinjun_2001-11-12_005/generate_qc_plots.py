from pathlib import Path

import ezc3d
import matplotlib.pyplot as plt
import numpy as np
from scipy.signal import butter, filtfilt

DATA_DIR = Path("2025-12-03/2025-12-03_unspecified")
QC_DIR = Path("docs/qc_plots")
QC_DIR.mkdir(parents=True, exist_ok=True)

TRIALS = [4, 5, 6, 7, 9, 11, 12, 13, 15, 16, 17, 18, 19, 20, 22, 23, 24]
GROUP_LABELS = {
    11: "0kg CMJ",
    15: "0kg CMJ (longest FT)",
    18: "20kg (shortest FT)",
    20: "20kg",
    22: "40kg",
    24: "40kg",
    9: "0kg CMJ (boundary)",
    5: "0kg CMJ",
}


def lowpass(x, fs, fc=50, order=4):
    b, a = butter(order, fc / (fs / 2), btype="low")
    return filtfilt(b, a, x)


def interpolate_nan(y):
    nans = np.isnan(y)
    if not nans.any():
        return y
    x = np.arange(len(y))
    y[nans] = np.interp(x[nans], x[~nans], y[~nans])
    return y


def estimate_bw_histogram(Fz, fs):
    valid = Fz[np.abs(Fz) > 50]
    if len(valid) < 100:
        return np.nan
    hist, bin_edges = np.histogram(valid, bins=100)
    mode_bin = np.argmax(hist)
    bw = (bin_edges[mode_bin] + bin_edges[mode_bin + 1]) / 2
    return bw


def detect_flight_segments(Fz, bw, fs, min_dur=0.15, max_dur=1.0):
    T = max(20.0, 0.05 * bw)
    is_flight = np.abs(Fz) < T

    runs = np.diff(np.concatenate([[0], is_flight.astype(int), [0]]))
    starts = np.where(runs == 1)[0]
    ends = np.where(runs == -1)[0]

    if len(starts) == 0:
        return None, None, None, []

    all_candidates = []
    for s, e in zip(starts, ends):
        dur = (e - s) / fs
        all_candidates.append((s, e, dur))

    valid_candidates = [
        (s, e, d) for s, e, d in all_candidates if min_dur <= d <= max_dur
    ]

    if not valid_candidates:
        return None, None, None, all_candidates

    valid_candidates.sort(key=lambda x: x[2], reverse=True)
    best = valid_candidates[0]
    return best[0], best[1], best[2], all_candidates


def process_trial(trial_id):
    c3d_file = DATA_DIR / f"Gait FB - CAST {trial_id}.c3d"
    c3d = ezc3d.c3d(str(c3d_file))

    fs = c3d["header"]["analogs"]["frame_rate"]
    labels = c3d["parameters"]["ANALOG"]["LABELS"]["value"]
    ana = c3d["data"]["analogs"]

    n_frames = ana.shape[2]
    A = ana[0, :, :].T

    fz1_idx = 0
    fz2_idx = 6
    for i, lbl in enumerate(labels):
        if "Force Z" in lbl and i < 6:
            fz1_idx = i
        elif "Force Z" in lbl and i >= 6:
            fz2_idx = i
            break

    Fz1_raw = A[:, fz1_idx].astype(float)
    Fz2_raw = A[:, fz2_idx].astype(float)

    Fz1_raw = interpolate_nan(Fz1_raw)
    Fz2_raw = interpolate_nan(Fz2_raw)

    if np.mean(Fz1_raw[:1000]) < 0:
        Fz1_raw = -Fz1_raw
        Fz2_raw = -Fz2_raw

    t = np.arange(len(Fz1_raw)) / fs

    rough_start, rough_end, _, _ = detect_flight_segments(
        Fz1_raw + Fz2_raw, 800, fs, min_dur=0.1, max_dur=5.0
    )
    if rough_start is not None:
        offset1 = np.median(Fz1_raw[rough_start:rough_end])
        offset2 = np.median(Fz2_raw[rough_start:rough_end])
        Fz1_c = Fz1_raw - offset1
        Fz2_c = Fz2_raw - offset2
    else:
        Fz1_c = Fz1_raw
        Fz2_c = Fz2_raw

    Fz_total = Fz1_c + Fz2_c

    BW = estimate_bw_histogram(Fz_total, fs)
    T_thresh = max(20.0, 0.05 * BW) if BW and not np.isnan(BW) else 50.0

    Fz_f = lowpass(Fz_total, fs, fc=50)

    t_to_frame, t_la_frame, flight_time, all_segments = detect_flight_segments(
        Fz_f, BW, fs
    )

    t_takeoff = t_to_frame / fs if t_to_frame is not None else None
    t_landing = t_la_frame / fs if t_la_frame is not None else None

    # QC flags
    has_tail_segment = False
    if all_segments:
        trial_end = len(Fz_f) / fs
        for s, e, dur in all_segments:
            if t_landing and (e / fs) > (trial_end - 0.3):
                has_tail_segment = True
                break

    # Plate share before takeoff
    plate_share = None
    if t_takeoff is not None:
        to_idx = int(t_takeoff * fs)
        window = min(150, to_idx)
        if window > 0:
            fz1_before = np.mean(Fz1_c[to_idx - window : to_idx])
            fz2_before = np.mean(Fz2_c[to_idx - window : to_idx])
            total = fz1_before + fz2_before
            if total > 0:
                plate_share = fz1_before / total

    # Landing peak ratio
    landing_peak_ratio = None
    if t_landing is not None and BW and BW > 0:
        la_idx = int(t_landing * fs)
        window = int(0.2 * fs)
        if la_idx + window < len(Fz_total):
            landing_peak = np.max(Fz_total[la_idx : la_idx + window])
            landing_peak_ratio = landing_peak / BW

    n_candidates = len(all_segments)

    return {
        "trial_id": trial_id,
        "t": t,
        "Fz1": Fz1_c,
        "Fz2": Fz2_c,
        "Fz_total": Fz_total,
        "Fz_filtered": Fz_f,
        "BW": BW,
        "T": T_thresh,
        "t_takeoff": t_takeoff,
        "t_landing": t_landing,
        "flight_time": flight_time,
        "group": GROUP_LABELS.get(trial_id, "unknown"),
        "has_tail_segment": has_tail_segment,
        "plate_share": plate_share,
        "all_segments": [(s / fs, e / fs, d) for s, e, d in all_segments]
        if all_segments
        else [],
        "n_candidates": n_candidates,
        "landing_peak_ratio": landing_peak_ratio,
    }


def plot_qc(data, out_png):
    fig, ax = plt.subplots(figsize=(12, 5))

    ax.plot(
        data["t"],
        data["Fz_filtered"],
        label="Fz_total",
        lw=1.5,
        color="black",
        zorder=3,
    )
    ax.plot(data["t"], data["Fz1"], label="Fz1", alpha=0.6, lw=1)
    ax.plot(data["t"], data["Fz2"], label="Fz2", alpha=0.6, lw=1)

    if not np.isnan(data["BW"]) and data["BW"] > 0:
        ax.axhline(
            data["BW"], color="k", ls="--", lw=1, label=f"BW={data['BW'] / 9.81:.1f}kg"
        )
    ax.axhline(
        data["T"], color="red", ls=":", lw=1, label=f"Threshold={data['T']:.1f}N"
    )

    t_to = data["t_takeoff"]
    t_la = data["t_landing"]
    ft = (t_la - t_to) if (t_to is not None and t_la is not None) else None

    # Draw excluded segments in gray
    for seg_start, seg_end, dur in data["all_segments"]:
        if t_to is None or abs(seg_start - t_to) > 0.01:
            ax.axvspan(seg_start, seg_end, color="gray", alpha=0.15)

    # Draw selected segment in orange
    if t_to is not None and t_la is not None:
        ax.axvline(t_to, color="green", lw=2, label=f"Takeoff={t_to:.3f}s")
        ax.axvline(t_la, color="magenta", lw=2, label=f"Landing={t_la:.3f}s")
        ax.axvspan(t_to, t_la, color="orange", alpha=0.2)

    bw_kg = data["BW"] / 9.81 if data["BW"] and not np.isnan(data["BW"]) else 0
    ft_str = f"{ft:.3f}s" if ft is not None else "N/A"
    jh_str = f"{9.81 * ft**2 / 8:.3f}m" if ft is not None else "N/A"
    title = f"Trial {data['trial_id']} | {data['group']} | BW={bw_kg:.1f}kg | FT={ft_str} | JH={jh_str}"
    ax.set_title(title, fontsize=11)
    ax.set_xlabel("Time (s)")
    ax.set_ylabel("Fz (N)")
    ax.legend(loc="upper right", ncol=4, fontsize=8)
    ax.grid(True, alpha=0.3)
    ax.set_xlim(data["t"][0], data["t"][-1])

    # QC text box
    ps_str = f"{data['plate_share']:.2f}" if data["plate_share"] is not None else "N/A"
    lp_str = (
        f"{data['landing_peak_ratio']:.2f}"
        if data["landing_peak_ratio"] is not None
        else "N/A"
    )
    qc_text = (
        f"FT={ft_str} | JH={jh_str}\n"
        f"tail={data['has_tail_segment']} | n_seg={data['n_candidates']}\n"
        f"plate_Fz1={ps_str} | landing_BW={lp_str}"
    )
    ax.text(
        0.98,
        0.98,
        qc_text,
        transform=ax.transAxes,
        fontsize=8,
        verticalalignment="top",
        horizontalalignment="right",
        bbox=dict(boxstyle="round", facecolor="wheat", alpha=0.8),
    )

    fig.tight_layout()
    fig.savefig(out_png, dpi=150)
    plt.close(fig)
    print(f"Saved: {out_png}")


def main():
    import csv

    print(f"Generating QC plots for {len(TRIALS)} trials...")
    all_data = []
    for trial_id in TRIALS:
        try:
            data = process_trial(trial_id)
            out_png = QC_DIR / f"Trial_{trial_id}_QC.png"
            plot_qc(data, out_png)
            all_data.append(data)
        except Exception as e:
            print(f"Trial {trial_id} failed: {e}")
            import traceback

            traceback.print_exc()

    # Write qc_flags.csv
    csv_path = QC_DIR / "qc_flags.csv"
    with open(csv_path, "w", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(
            [
                "trial_id",
                "group",
                "BW_kg",
                "FT_s",
                "JH_m",
                "has_tail_segment",
                "n_candidates",
                "plate_share_Fz1",
                "landing_peak_ratio",
                "t_takeoff",
                "t_landing",
                "threshold_N",
            ]
        )
        for d in all_data:
            ft = (
                (d["t_landing"] - d["t_takeoff"])
                if d["t_takeoff"] and d["t_landing"]
                else None
            )
            ft_str = f"{ft:.3f}" if ft else "N/A"
            jh_str = f"{9.81 * ft**2 / 8:.3f}" if ft else "N/A"
            bw_str = (
                f"{d['BW'] / 9.81:.1f}" if d["BW"] and not np.isnan(d["BW"]) else "N/A"
            )
            lp_str = (
                f"{d['landing_peak_ratio']:.2f}" if d["landing_peak_ratio"] else "N/A"
            )
            ps_str = f"{d['plate_share']:.3f}" if d["plate_share"] else "N/A"
            to_str = f"{d['t_takeoff']:.3f}" if d["t_takeoff"] else "N/A"
            la_str = f"{d['t_landing']:.3f}" if d["t_landing"] else "N/A"
            writer.writerow(
                [
                    d["trial_id"],
                    d["group"],
                    bw_str,
                    ft_str,
                    jh_str,
                    d["has_tail_segment"],
                    d["n_candidates"],
                    ps_str,
                    lp_str,
                    to_str,
                    la_str,
                    f"{d['T']:.1f}",
                ]
            )
    print(f"QC flags saved to: {csv_path}")
    print(f"\nQC plots saved to: {QC_DIR}/")


if __name__ == "__main__":
    main()
