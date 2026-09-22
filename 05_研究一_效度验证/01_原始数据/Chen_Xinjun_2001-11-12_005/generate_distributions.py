import matplotlib

matplotlib.use("Agg")
import csv
from pathlib import Path

import matplotlib.pyplot as plt

QC_FLAGS = "docs/qc_plots/qc_flags.csv"
OUT_DIR = Path("docs/distribution_plots")
OUT_DIR.mkdir(exist_ok=True)


def load_data():
    qc, mocap = {}, {}
    with open(QC_FLAGS) as f:
        for r in csv.DictReader(f):
            qc[int(r["trial_id"])] = r
    try:
        with open("mocap_metrics.csv") as f:
            for r in csv.DictReader(f):
                mocap[int(r["trial_id"])] = r
    except:
        pass
    return qc, mocap


def classify_arm(arm_amp):
    if arm_amp is None:
        return "UNKNOWN"
    if arm_amp > 0.2:
        return "FREE_ARM"
    elif arm_amp > 0.08:
        return "AMBIGUOUS"
    else:
        return "NO_ARM"


qc, mocap = load_data()
trial_ids = sorted(qc.keys())

groups = {"0kg": [], "20kg": [], "40kg": []}
for tid in trial_ids:
    bw = float(qc[tid]["BW_kg"])
    if 60 <= bw <= 100:
        groups["0kg"].append(tid)
    elif 90 <= bw <= 110:
        groups["20kg"].append(tid)
    elif 110 <= bw <= 140:
        groups["40kg"].append(tid)

arm_amp_vals = {
    tid: float(mocap[tid]["arm_amp_m"])
    if mocap.get(tid, {}).get("arm_amp_m", "NA") != "NA"
    else None
    for tid in trial_ids
}
jh_vals = {
    tid: float(qc[tid]["JH_m"]) if qc[tid]["JH_m"] not in ("NA", "") else None
    for tid in trial_ids
}

colors_arm = {
    "FREE_ARM": "#2196F3",
    "AMBIGUOUS": "#FF9800",
    "NO_ARM": "#4CAF50",
    "UNKNOWN": "#9E9E9E",
}
arm_classes = {tid: classify_arm(arm_amp_vals.get(tid)) for tid in trial_ids}

fig, axes = plt.subplots(1, 3, figsize=(15, 5))

ax = axes[0]
for tid in trial_ids:
    amp = arm_amp_vals.get(tid)
    if amp is not None:
        c = colors_arm[arm_classes[tid]]
        ax.scatter(
            groups["0kg"].index(tid) if tid in groups["0kg"] else tid,
            amp,
            c=c,
            s=80,
            zorder=3,
        )
ax.set_xticks(range(len(groups["0kg"])))
ax.set_xticklabels(groups["0kg"])
ax.set_xlabel("Trial ID (0kg group)")
ax.set_ylabel("Arm Amplitude (m)")
ax.set_title("Arm Amplitude by Trial (0kg CMJ)")
ax.axhline(0.2, color="gray", ls="--", lw=1, label="FREE vs AMBIG threshold")
ax.axhline(0.08, color="gray", ls=":", lw=1, label="AMBIG vs NO_ARM")
ax.legend(fontsize=8)
ax.grid(True, alpha=0.2)

ax2 = axes[1]
bar_colors = ["#2196F3" if jh_vals[t] else "#E0E0E0" for t in trial_ids]
jh_numeric = [jh_vals[t] if jh_vals[t] else 0 for t in trial_ids]
bars = ax2.bar([str(t) for t in trial_ids], jh_numeric, color=bar_colors)
ax2.set_xlabel("Trial ID")
ax2.set_ylabel("Jump Height (m)")
ax2.set_title("Force Plate JH by Trial")
ax2.tick_params(axis="x", rotation=45, labelsize=7)
ax2.grid(True, alpha=0.2, axis="y")

ax3 = axes[2]
for load_label, grp_tids in [
    ("0kg", groups["0kg"]),
    ("20kg", groups["20kg"]),
    ("40kg", groups["40kg"]),
]:
    for tid in grp_tids:
        amp = arm_amp_vals.get(tid)
        jh = jh_vals.get(tid)
        if amp is not None and jh is not None:
            c = colors_arm[arm_classes[tid]]
            ax3.scatter(
                amp, jh, c=c, s=80, label=f"{load_label} {arm_classes[tid]}", zorder=3
            )
ax3.set_xlabel("Arm Amplitude (m)")
ax3.set_ylabel("Force Plate JH (m)")
ax3.set_title("Arm Amp vs JH (colored by arm class)")
ax3.legend(fontsize=8)
ax3.grid(True, alpha=0.2)

plt.tight_layout()
plt.savefig(str(OUT_DIR / "distribution_overview.png"), dpi=150, bbox_inches="tight")
plt.close()

print(f"Saved: {OUT_DIR / 'distribution_overview.png'}")
print("\nArm classification summary:")
for cls in ["FREE_ARM", "AMBIGUOUS", "NO_ARM", "UNKNOWN"]:
    tids = [t for t in trial_ids if arm_classes[t] == cls]
    if tids:
        print(f"  {cls}: trials {tids}")
