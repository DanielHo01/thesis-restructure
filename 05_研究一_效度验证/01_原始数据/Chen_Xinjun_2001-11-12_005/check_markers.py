import ezc3d

c3d = ezc3d.c3d("2025-12-03/2025-12-03_unspecified/Gait FB - CAST 11.c3d")
pts = c3d["data"]["points"]
print("points shape:", pts.shape)
labels = c3d["parameters"]["POINT"]["LABELS"]["value"]
print("num labels:", len(labels))
print("first 30 labels:", labels[:30])
