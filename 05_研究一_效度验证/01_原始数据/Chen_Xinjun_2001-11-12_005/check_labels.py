from pathlib import Path

import ezc3d

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

for trial_id in [9, 15, 18, 20]:
    c3d = ezc3d.c3d(str(TRIAL_DIR / f"Gait FB - CAST {trial_id}.c3d"))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    print(f"\nTrial {trial_id}: points shape {pts.shape}, {len(labels)} labels")
    print("All labels:", labels)
