# Chen_Xinjun Motion Capture Data Repository

## 数据目录结构

```
Chen_Xinjun_2001-11-12_005/
├── data.qpr                    # Qualisys项目文件(根目录)
└── 2025-12-03/
    ├── data.qpr
    └── 2025-12-03_unspecified/
        ├── data.qpr                    # 采集项目配置
        ├── session.xml                 # 会话配置
        ├── meta.xml                    # 元数据
        ├── recalc.v3s                  # Visual3D重计算脚本
        ├── Report_*.cmz/.cmx           # Visual3D分析报告
        ├── Scripts/src/Visual3D/       # 分析脚本
        ├── Gait FB - CAST [N].*       # 步态测试 (N=4,5,6,7,9,11-20,22-24)
        ├── Static FB Anterior - CAST 2.*  # 静态前测
        └── Static FB Posterior - CAST 2.* # 静态后测
```

## 文件命名约定

| 类型 | 命名模式 | 示例 |
|------|----------|------|
| 原始C3D | `Gait FB - CAST N.c3d` | `Gait FB - CAST 4.c3d` |
| 转换C3D | `Gait FB - CAST N_c3d.c3d` | `Gait FB - CAST 4_c3d.c3d` |
| QTM原生 | `Gait FB - CAST N.qtm` | `Gait FB - CAST 4.qtm` |
| JSON元数据 | `Gait FB - CAST N.json` | `Gait FB - CAST 4.json` |

## 数据格式说明

- **.qtm**: Qualisys原生采集格式，包含完整模拟数据
- **.c3d**: 第三方运动学交换格式
- **.json**: QTM导出的轨迹元数据(不含模拟数据)
- **.settings.xml**: 采集系统配置

## 受试者信息

- 姓名: Chen Xinjun
- ID: 005
- 出生日期: 2001-11-12
- 身高: 1.79m
- 体重: 79.0kg
- 采集日期: 2025-12-03
- 模型: CAST (Lower Body + Thorax + Arms)

## 数据分析环境配置

```bash
pip install ezc3d numpy pandas scipy
pip install ijson  # 流式解析大JSON
pip install pyqtgraph matplotlib  # 可视化
```

## 常用分析命令

### C3D文件检查（采样率+通道信息）

```python
import ezc3d
c3d = ezc3d.c3d("trial.c3d")
point_rate = c3d["header"]["points"]["frame_rate"]
analog_rate = c3d["header"]["analogs"]["frame_rate"]
labels = c3d["parameters"]["ANALOG"]["LABELS"]["value"]
units = c3d["parameters"]["ANALOG"]["UNITS"]["value"]
print(f"point_rate: {point_rate}, analog_rate: {analog_rate}")
print(f"channels: {len(labels)}")
for i, (n, u) in enumerate(zip(labels, units)):
    print(f"  {i}: {n} [{u}]")
```

### JSON结构检查（内存安全）

```python
import ijson
with open("trial.json", "rb") as f:
    parser = ijson.parse(f)
    for prefix, event, value in parser:
        if prefix.count('.') < 2:
            print(f"{prefix}: {value}")
```

### 完整JSON加载（小文件）

```python
import json
with open("trial.json", "r", encoding="utf-8") as f:
    d = json.load(f)
print(d.keys())
```

## Python代码规范

### 导入顺序
1. 标准库
2. 第三方库（ezc3d, numpy, pandas, scipy）
3. 本地模块

```python
import json
from pathlib import Path
import numpy as np
import pandas as pd
import ezc3d
from my_module import helper_func
```

### 命名规范
- 变量: `snake_case` (e.g., `trial_data`, `analog_rate`)
- 函数: `snake_case` (e.g., `load_c3d_file`, `extract_markers`)
- 类: `PascalCase` (e.g., `TrialData`, `ForcePlate`)
- 常量: `UPPER_SNAKE_CASE` (e.g., `DEFAULT_SAMPLE_RATE`)
- 文件: `snake_case.py` (e.g., `data_analyzer.py`)

### 类型注解
```python
def load_c3d(filepath: str) -> dict:
    """Load C3D file and return structured data."""
    ...

def process_trials(trial_paths: list[str]) -> pd.DataFrame:
    ...
```

### 错误处理
```python
try:
    c3d = ezc3d.c3d(filepath)
except FileNotFoundError:
    print(f"File not found: {filepath}")
    raise
except Exception as e:
    print(f"Error loading {filepath}: {e}")
    raise
```

### 数据验证
```python
def validate_analog_data(analog_data: np.ndarray, expected_rate: float = 2000.0) -> bool:
    assert analog_data.ndim == 2, "Analog data must be 2D"
    assert analog_data.shape[0] == expected_rate, f"Expected {expected_rate}Hz"
    return True
```

## 数据处理工作流

1. **验证C3D和JSON成对存在** — `Gait FB - CAST 4.c3d` ↔ `Gait FB - CAST 4.json`
2. **检查采样率一致性** — analog_rate应为1000或2000 Hz
3. **确认力台通道** — 查找 `Fz`、`FP1_Fz` 等力台标识
4. **提取关键数据** — marker轨迹、模拟数据、力板数据
5. **输出验证报告** — 采样率、通道数、数据范围

## 快捷操作

```bash
# 查看目录下文件数量
ls -la "2025-12-03/2025-12-03_unspecified" | wc -l

# 查找特定类型文件
Get-ChildItem -Recurse -Filter "*.c3d" | Select-Object FullName

# 查看文件大小
Get-ChildItem -Recurse -Filter "*.c3d" | ForEach-Object { $_.Name + ": " + $_.Length/1MB + " MB" }
```

## 注意事项

- JSON文件可能很大（约50MB+），避免一次性加载，用ijson流式解析
- C3D模拟数据包含力台数据（1000/2000 Hz），与marker数据（200 Hz）分开存储
- 静态测试只有2个（Anterior/Posterior），步态测试有21个
- 缺少trial 10, 14, 21的数据