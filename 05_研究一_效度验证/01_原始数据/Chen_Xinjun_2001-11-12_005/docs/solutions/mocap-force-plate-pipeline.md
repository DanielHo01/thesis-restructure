# Motion Capture Force Plate Pipeline

## Context
Processing Qualisys C3D motion capture data with integrated force plate signals for jump/CMJ analysis. Covers data audit, force-plate debugging, and C3D processing with ezc3d.

---

## 1. Data Audit: C3D/JSON Consistency Check

**Rule:** Always verify C3D and JSON exist as pairs before processing.

### Sampling Rate Verification

```python
import ezc3d

c3d = ezc3d.c3d("trial.c3d")
point_rate = c3d["header"]["points"]["frame_rate"]   # marker rate (typically 200 Hz)
analog_rate = c3d["header"]["analogs"]["frame_rate"] # force plate rate (1000 or 2000 Hz)
print(f"point_rate: {point_rate}, analog_rate: {analog_rate}")
```

**Expected pairs:**
| Trial Type | point_rate | analog_rate |
|------------|------------|-------------|
| Gait | 200 Hz | 1000 Hz |
| Jump/CMJ | 200 Hz | 2000 Hz |

### Analog Shape Pattern

The analog data reshape follows: `(1, 12, N)` where `N = point_frames * 5` for 1000Hz or `N = point_frames * 10` for 2000Hz.

**Reason:** analog_rate / point_rate = 5 (1000/200) or 10 (2000/200).

### Channel Identification

```python
labels = c3d["parameters"]["ANALOG"]["LABELS"]["value"]
units = c3d["parameters"]["ANALOG"]["UNITS"]["value"]
for i, (n, u) in enumerate(zip(labels, units)):
    print(f"  {i}: {n} [{u}]")
```

Look for force plate identifiers: `Fz`, `FP1_Fz`, `FP2_Fz`, etc.

### Flight Phase Detection (Simple)

```python
import numpy as np

# Flight = when Fz is near zero (body airborne)
# Detect by finding longest continuous segment where Fz < threshold
threshold = 5  # N
flight_mask = (np.abs(Fz) < threshold).astype(int)
# Find longest run of 1s using run-length encoding
```

---

## 2. Force Plate Pipeline: Critical Bug Pattern

### The NaN-Offset Bug

**Symptom:** Offset computation produces NaN after combining Fz1/Fz2.

**Root Cause:** Interpolation must happen BEFORE offset computation, not after combine.

**Correct order:**
```
Fz1 (raw) → interpolate NaN → compute offset → subtract offset
Fz2 (raw) → interpolate NaN → compute offset → subtract offset
                                          ↓
                                    combine Fz1', Fz2'
```

**Wrong order:**
```
Fz1 → combine with Fz2 → interpolate → compute offset  ← BROKEN
```

### Body Weight (BW) Estimation

**Use histogram mode, not mean/median.**

```python
def estimate_bw(Fz_series):
    """BW estimation using histogram mode - resistant to impact peaks."""
    hist, bin_edges = np.histogram(Fz_series, bins=100)
    mode_bin = np.argmax(hist)
    bw = (bin_edges[mode_bin] + bin_edges[mode_bin + 1]) / 2
    return bw
```

**Why:** Impact peaks skew mean/median, histogram mode is robust.

### Flight Detection Algorithm

```python
def detect_flight(Fz, bw, min_duration=0.15, max_duration=1.0):
    """
    Detect flight phase from Fz signal.
    
    Steps:
    1. Normalize by BW: Fz_norm = Fz / bw
    2. Find near-zero segments: |Fz_norm| < 0.1 (10% BW)
    3. Filter by duration: 0.15s < duration < 1.0s
    4. Validate: segment must be surrounded by ~1 BW contacts
    """
    threshold = 0.1 * bw
    is_flight = np.abs(Fz) < threshold
    
    # Find longest contiguous flight segment
    runs = np.diff(np.concatenate([[0], is_flight.astype(int), [0]]))
    starts = np.where(runs == 1)[0]
    ends = np.where(runs == -1)[0]
    
    candidates = []
    for s, e in zip(starts, ends):
        duration = (e - s) / analog_rate
        if min_duration <= duration <= max_duration:
            # Check surrounding contact phases
            before_contact = np.mean(Fz[s-10:s]) if s > 10 else 0
            after_contact = np.mean(Fz[e:e+10]) if e+10 < len(Fz) else 0
            if before_contact > 0.8 * bw and after_contact > 0.8 * bw:
                candidates.append((s, e, duration))
    
    return candidates  # sorted by duration descending
```

### Per-Plate Offset Correction

```python
def correct_plate_offset(Fz, flight_indices, side='left'):
    """Use flight phase median to estimate and correct plate offset."""
    flight_Fz = Fz[flight_indices]
    offset = np.median(flight_Fz)  # During flight, Fz should be ~0
    return Fz - offset
```

---

## 3. C3D Processing with ezc3d

### Loading and Reshaping Analog Data

```python
# ezc3d returns analog as (frames, 12, 1) or similar - check with:
print(f"analog shape: {c3d['data']['analogs']['values'].shape}")

# Transpose to (channels, N_analog) for easier processing
analog = c3d['data']['analogs']['values'][0, :, :]  # shape: (12, N)
# If N is point_frames * decimation_factor
```

### Direction Fix (Vertical Force)

```python
def fix_force_direction(Fz, analog_rate, window_ms=1000):
    """Fix sign based on first 1s mean."""
    window_points = int(analog_rate * window_ms / 1000)
    first_mean = np.mean(Fz[:window_points])
    if first_mean < 0:
        return -Fz
    return Fz
```

### Butterworth Lowpass Filter

```python
from scipy.signal import butter, filtfilt

def lowpass_filter(data, cutoff=50, fs=1000, order=4):
    """
    Apply Butterworth lowpass with filtfilt (zero-phase filtering).
    
    Args:
        data: 1D force signal
        cutoff: cutoff frequency in Hz (use 50 for force plate data)
        fs: sampling rate
        order: filter order (4 is standard)
    """
    nyq = fs / 2
    b, a = butter(order, cutoff / nyq, btype='low')
    return filtfilt(b, a, data)
```

**Why filtfilt:** Zero-phase distortion — forward-backward filtering cancels phase shift.

---

## 4. Common Pitfalls

### Trial Naming Mismatch

**Issue:** File named `Gait FB - CAST 4.c3d` but contains CMJ/jump data.

**Lesson:** Trial name ≠ actual content. Always inspect data before assuming type.

### Body Weight Clustering (Loaded vs Unloaded)

BW clustering can reveal loaded vs unloaded jumps:
| BW Cluster | Interpretation |
|------------|----------------|
| ~79 kg | Unloaded (body weight only) |
| ~100 kg | Light load (+21 kg) |
| ~120 kg | Heavy load (+41 kg) |

### Long "Flight" at End of Trial

**Issue:** Trial 20 had 2.07s "flight" at end — person walking off.

**Fix:** Always apply time constraint `0.15s < duration < 1.0s` for flight detection.

### Trailing NaN in C3D

**Issue:** End of C3D files sometimes have NaN frames.

**Fix:** Interpolate NaN values before any offset or analysis computation.

---

## 5. Verification Checklist

Before declaring pipeline complete:

- [ ] C3D/JSON pair verified
- [ ] Sampling rate matches expected (200/1000 or 200/2000)
- [ ] Force plate channels identified (Fz, FP1_Fz, FP2_Fz)
- [ ] NaN interpolated before offset computation
- [ ] BW estimated from histogram mode
- [ ] Flight segments pass duration filter (0.15-1.0s)
- [ ] Per-plate offset corrected using flight phase median
- [ ] Filter applied with filtfilt (zero-phase)
- [ ] Direction fixed based on first 1s sign

---

## References

- ezc3d: `pip install ezc3d`
- scipy.signal Butterworth: `scipy.signal.butter`, `scipy.signal.filtfilt`
- ijson for large JSON: `pip install ijson`