from pathlib import Path

import ezc3d

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

for trial_id in [4, 11, 17, 22]:
    c3d = ezc3d.c3d(str(TRIAL_DIR / f"Gait FB - CAST {trial_id}.c3d"))
    labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
    pts = c3d["data"]["points"]
    n_markers = pts.shape[1]
    print(f"\nTrial {trial_id}: {n_markers} markers")
    for pattern in ["HUM", "HLE", "RSP", "USP", "HM2", "SAE", "TH4", "TH1"]:
        found = [l for l in labels if pattern.upper() in l.upper()]
        print(f"  {pattern}: {found if found else 'NOT FOUND'}")
