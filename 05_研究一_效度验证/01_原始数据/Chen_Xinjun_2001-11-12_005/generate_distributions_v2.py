import matplotlib

matplotlib.use("Agg")
import csv
from pathlib import Path

import matplotlib.pyplot as plt

OUT_DIR = Path("docs/distribution_plots")
OUT_DIR.mkdir(exist_ok=True)

qc, mocap = {}, {}
with open("docs/qc_plots/qc_flags.csv") as f:
    for r in csv.DictReader(f):
        qc[int(r["trial_id"])] = r
with open("mocap_metrics.csv") as f:
    for r in csv.DictReader(f):
        mocap[int(r["trial_id"])] = r

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
    t: float(mocap[t]["arm_amp_m"])
    if mocap.get(t, {}).get("arm_amp_m", "NA") != "NA"
    else None
    for t in trial_ids
}
fp_jh = {
    t: float(qc[t]["JH_m"]) if qc[t]["JH_m"] not in ("NA", "") else None
    for t in trial_ids
}


def classify_arm(amp):
    if amp is None:
        return "UNKNOWN"
    if amp > 0.45:
        return "FREE_ARM"
    elif amp > 0.35:
        return "AMBIGUOUS"
    else:
        return "NO_ARM"


arm_cls = {t: classify_arm(arm_amp_vals.get(t)) for t in trial_ids}
colors = {
    "FREE_ARM": "#2196F3",
    "AMBIGUOUS": "#FF9800",
    "NO_ARM": "#4CAF50",
    "UNKNOWN": "#BDBDBD",
}

fig, axes = plt.subplots(1, 3, figsize=(15, 5))

ax = axes[0]
x_pos = range(len(groups["0kg"]))
for i, tid in enumerate(groups["0kg"]):
    amp = arm_amp_vals.get(tid)
    if amp is not None:
        ax.bar(i, amp, color=colors[arm_cls[tid]], zorder=3)
ax.set_xticks(x_pos)
ax.set_xticklabels(groups["0kg"])
ax.set_xlabel("Trial ID (0kg)")
ax.set_ylabel("Arm Amplitude (m)")
ax.set_title("Arm Amp — 0kg CMJ")
ax.axhline(0.45, color="gray", ls="--", lw=1, alpha=0.5)
ax.axhline(0.35, color="gray", ls=":", lw=1, alpha=0.5)
ax.grid(True, alpha=0.2, axis="y")

ax2 = axes[1]
jh_by_group = {"0kg": [], "20kg": [], "40kg": []}
for g, tids in groups.items():
    for t in tids:
        v = fp_jh.get(t)
        if v is not None:
            jh_by_group[g].append(v)
bp = ax2.boxplot(
    [jh_by_group[g] for g in ["0kg", "20kg", "40kg"]],
    labels=["0kg", "20kg", "40kg"],
    patch_artist=True,
)
colors_bp = ["#90CAF9", "#CE93D8", "#A5D6A7"]
for patch, c in zip(bp["boxes"], colors_bp):
    patch.set_facecolor(c)
ax2.set_ylabel("Force Plate JH (m)")
ax2.set_title("JH by Load Condition")
ax2.grid(True, alpha=0.2, axis="y")

ax3 = axes[2]
for g, tids in groups.items():
    for t in tids:
        amp = arm_amp_vals.get(t)
        jh = fp_jh.get(t)
        if amp is not None and jh is not None:
            ax3.scatter(amp, jh, c=colors[arm_cls[t]], s=80, zorder=3)
ax3.set_xlabel("Arm Amplitude (m)")
ax3.set_ylabel("Force Plate JH (m)")
ax3.set_title("Arm Amp vs FP JH (colored by arm class)")
handles = [
    plt.Line2D(
        [0], [0], marker="o", color="w", markerfacecolor=c, label=l, markersize=8
    )
    for l, c in colors.items()
]
ax3.legend(handles=handles, fontsize=7)
ax3.grid(True, alpha=0.2)

plt.tight_layout()
plt.savefig(str(OUT_DIR / "distribution_overview.png"), dpi=150, bbox_inches="tight")
plt.close()

print("Saved:", OUT_DIR / "distribution_overview.png")
print("\nArm classification (threshold: FREE>0.45, AMBIG>0.35, NO<0.35):")
for cls in ["FREE_ARM", "AMBIGUOUS", "NO_ARM", "UNKNOWN"]:
    tids = [t for t in trial_ids if arm_cls[t] == cls]
    if tids:
        print(f"  {cls}: {tids}")
