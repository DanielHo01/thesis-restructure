# 研究一：SportSci Pro 移动端系统效度验证

## 研究概述

**研究目的**：验证自研移动端计算机视觉系统（SportSci Pro）与金标准 GymAware LPT 测速的一致性

**方法**：受控实验室研究，13人配对测试

**核心指标**：
- ICC = 0.987（热身级）
- MAE = 0.021 m/s
- 误差 ≤0.05 m/s 占比 100%

---

## 目录结构

```
05_Study1_Validity/
├── README.md                    # 本文件
├── 01_Raw_Data/                 # 原始运动学数据
│   ├── 01_Qualisys/            # Qualisys运动捕捉系统数据
│   │   └── Qualisys-HTY/       # 受试者HTY的完整动捕数据
│   │       ├── Data/           # 原始运动学数据
│   │       ├── Calibrations/   # 标定文件
│   │       └── Documentation/  # 技术文档
│   ├── Chen_Xinjun_2001-11-12_005/   # 受试者CXL数据
│   └── He_Tianyuan_2025-10-21_001/  # 受试者HTY数据
│
├── 02_Video_Data/               # 手机拍摄视频数据
│   └── 20251121/               # 2024年8月21日采集
│       └── *.mp4               # 18个测试视频
│
└── 03_Analysis_Results/        # 效度分析结果（待补充）
```

---

## 关键文件

| 文件 | 说明 |
|------|------|
| `01_Raw_Data/01_Qualisys/Qualisys-HTY/Data/` | Qualisys原始运动学数据 |
| `02_Video_Data/20251121/*.mp4` | 手机侧面拍摄视频 |
| `03_Analysis_Results/...` | ICC、Bland-Altman分析结果 |

---

## 数据说明

- **Qualisys数据**：包含受试者的三维运动学原始数据（.qtm格式需用Qualisys软件打开）
- **视频数据**：用于计算机视觉算法输入，需与Qualisys时间戳同步

---

## 效度结论

研究一结果表明，SportSci Pro移动端系统在测速精度上与金标准GymAware具有**极高一致性**，可作为抗阻训练现场监控的便携式工具。
