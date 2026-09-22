from pathlib import Path

import ezc3d

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

for trial_id in [9, 15, 18, 20]:
    c3d_path = TRIAL_DIR / f"Gait FB - CAST {trial_id}_c3d.c3d"
    c3d = ezc3d.c3d(str(c3d_path))
    pts = c3d["data"]["points"]
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    print(f"\nTrial {trial_id} (_c3d): points shape {pts.shape}, {len(labels)} labels")
    print("All labels:", labels[:35])
