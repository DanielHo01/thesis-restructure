# 广州体育学院硕士学位论文 LaTeX 模板

基于 TongjiThesis_Proto 改编，专为广州体育学院硕士学位论文设计的 LaTeX 模板。

## 📁 文件结构

```
gzsu_thesis/
├── gzsu_thesis.cls    % 模板类文件（核心）
├── gzsu_thesis.cfg    % 配置文件
├── thesis.tex         % 示例主文件
└── README.md          % 说明文档
```

## 🎯 功能特性

- ✅ 符合广州体育学院论文格式规范
- ✅ 支持 XeLaTeX 编译
- ✅ 内置 GB/T 7714-2015 参考文献格式
- ✅ 自动生成封面、目录、图目录、表目录
- ✅ 支持中文/英文摘要
- ✅ 支持多种学位类型（学术型/专业型）
- ✅ 支持数字式/作者年份引用格式

## 📋 使用方法

### 1. 安装依赖

确保已安装：
- TeX Live 2019+ 或 MiKTeX
- XeLaTeX 编译器
- 中文字体（宋体、楷体、黑体等）

### 2. 编译流程

```bash
# 第一次编译
xelatex thesis.tex

# 生成参考文献
biber thesis

# 后续编译（解析交叉引用）
xelatex thesis.tex
xelatex thesis.tex
```

### 3. 配置论文信息

在 `thesis.tex` 中修改封面信息：

```latex
\gzsusetup{
  ctitle={论文中文题目},
  etitle={论文英文题目},
  cauthor={作者姓名},
  csupervisor={导师姓名},
  cmajor={专业},
  cdate={2026年6月},
}
```

## 📐 格式规范（广体标准）

| 项目 | 规格 |
|------|------|
| 纸张 | A4 (210mm × 297mm) |
| 页边距 | 上3.8cm, 下3.8cm, 左3.2cm, 右3.2cm |
| 正文字号 | 小四 (14pt) |
| 行距 | 1.5倍 |
| 一级标题 | 黑体三号居中 |
| 二级标题 | 黑体四号左对齐 |
| 三级标题 | 黑体小四左对齐 |
| 首行缩进 | 2字符 |
| 参考文献 | GB/T 7714-2015 |

## 🔧 选项配置

```latex
\documentclass[
  degree=master,        % degree=[master|doctor]
  degreetype=profession, % degreetype=[academic|profession]
  bibtype=numeric      % bibtype=[numeric|authoryear]
]{gzsu_thesis}
```

## 📦 模板选项说明

| 选项 | 说明 | 可选值 |
|------|------|--------|
| `degree` | 学位类型 | `master` (硕士), `doctor` (博士) |
| `degreetype` | 学位类别 | `academic` (学术型), `profession` (专业型) |
| `bibtype` | 引用格式 | `numeric` (数字式), `authoryear` (作者年份) |

## 📚 参考

- 基于 [TongjiThesis_Proto](https://github.com/wyqy/TongjiThesis_Proto) 改编
- 参考文献格式符合 [GB/T 7714-2015](http://www.cssn.gov.cn/xxgk_zzxm/) 标准

## 📝 注意事项

1. **字体问题**：确保系统安装了 SimSun, SimHei, FangSong 等中文字体
2. **编译方式**：必须使用 XeLaTeX 或 LuaLaTeX 编译
3. **参考文献**：首次编译后运行 `biber thesis` 生成参考文献

## 🐛 常见问题

**Q: 编译报错 "Font ... not found"**
A: 安装中文字体包或使用 MiKTeX 包管理器安装字体

**Q: 参考文献格式不对**
A: 确保运行了 `biber thesis` 且使用正确的 `gb7714-2015` 样式

## 📄 许可证

本模板基于 TongjiThesis_Proto 改编，遵循其许可证。

---

**作者**：何天元  
**学校**：广州体育学院  
**日期**：2026年6月
