from pathlib import Path

import ezc3d

TRIAL_DIR = Path("2025-12-03/2025-12-03_unspecified")

for trial_id in [4, 5, 11, 13, 16, 22]:
    for suffix in ["", "_c3d"]:
        c3d_path = TRIAL_DIR / f"Gait FB - CAST {trial_id}{suffix}.c3d"
        if c3d_path.exists():
            c3d = ezc3d.c3d(str(c3d_path))
            pts = c3d["data"]["points"]
            labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
            print(f"Trial {trial_id}{suffix}: pts={pts.shape}, labels={len(labels)}")
