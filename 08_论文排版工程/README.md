# 广州体育学院硕士学位论文 LaTeX 排版工程

> **论文题目**：数智化监控下青年男性下肢抗阻训练的执行与适应  
> **作者**：何天元  
> **培养单位**：广州体育学院 运动训练学院  

---

## 快速开始

### 编译论文

```bash
# 方法1：手动编译
xelatex thesis.tex
biber thesis
xelatex thesis.tex
xelatex thesis.tex

# 方法2：使用 Makefile（如果可用）
make

# 方法3：使用 PowerShell 脚本
.\analyze_word.ps1  # 分析 Word 文档
.\fix_pdf.ps1       # 修复 PDF 输出
```

### 目录结构

```
08_Thesis_LaTeX/
├── README.md              # 本文件
├── thesis.tex            # 主文件
├── thesis.pdf            # 编译产出（查看用）
├── refs.bib              # 参考文献
│
├── chapters/             # 章节内容
│   ├── abstract-cn.tex   # 中文摘要
│   ├── abstract-en.tex   # 英文摘要
│   ├── 01_introduction.tex   # 第1章 前言
│   ├── 02_literature_review.tex  # 第2章 文献综述
│   ├── 03_methodology.tex     # 第3章 研究设计
│   ├── 04_results.tex         # 第4章 研究结果
│   ├── 05_discussion.tex     # 第5章 讨论
│   ├── 06_conclusion.tex     # 第6章 结论
│   ├── acknowledgement.tex   # 致谢
│   ├── appendix.tex          # 附录
│   ├── declarations.tex      # 原创性声明
│   └── titlepage.tex        # 封面
│
├── figures/              # 图表
│   └── gzsu-logo.png      # 广州体育学院校徽
│
├── setup/                # 格式配置
│   ├── package.tex       # 宏包加载
│   ├── format.tex        # 格式设置
│   └── command.tex       # 自定义命令
│
├── references/           # 参考文献目录（备用）
│
├── tables/               # 表格目录（备用）
│
└── scripts/              # 辅助脚本
    └── fix_tables.py     # 表格修复脚本
```

---

## 章节内容说明

| 章节 | 文件 | 主要内容 |
|------|------|---------|
| 摘要 | abstract-cn.tex | 中英文摘要，约600字 |
| 第1章 | 01_introduction.tex | 问题提出、选题目的、理论/实践意义 |
| 第2章 | 02_literature_review.tex | VBT概念、AI辅助系统、文献述评 |
| 第3章 | 03_methodology.tex | 三阶段设计、PICOS、统计模型 |
| 第4章 | 04_results.tex | CONSORT流程图、统计结果、质性结果 |
| 第5章 | 05_discussion.tex | 主要发现、机制、局限 |
| 第6章 | 06_conclusion.tex | 分层结论、建议 |

---

## 依赖环境

- **TeX 发行版**：TeX Live 2024+ 或 MiKTeX
- **编译器**：XeLaTeX
- **参考文献**：Biber
- **中文字体**：Windows 原生中文字体或 Fandol

---

## 常见问题

### Q: 编译报错 "Font ... not found"
**A**: 安装中文字体包或修改 setup/package.tex 中的字体设置

### Q: 参考文献格式不对
**A**: 确保使用 `biber thesis` 而非 `bibtex thesis`

### Q: 图片不显示
**A**: 检查 figures/ 目录下图片是否存在，路径是否正确

---

## 待填入内容

以下文件需要作者个性化填入：

- `chapters/acknowledgement.tex` — 致谢内容
- `chapters/declarations.tex` — 原创性声明（如需修改）

---

## 产出说明

编译成功后会生成：
- `thesis.pdf` — 最终论文 PDF
- `thesis.aux` — 辅助文件（目录、交叉引用）
- `thesis.log` — 编译日志（查看错误用）

---

*本模板基于广州体育学院官方学位论文格式规范定制*
*最后更新：2026-03-21*
