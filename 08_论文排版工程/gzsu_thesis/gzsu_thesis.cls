%%
%% 广州体育学院硕士学位论文 LaTeX 模板
%% gzsu_thesis.cls
%% 版本: 3.0 (2026年)
%% 精确匹配广体官方Word模板格式
%%

\NeedsTeXFormat{LaTeX2e}[1999/12/01]
\ProvidesClass{gzsu_thesis}[2026/09/21 Guangzhou Sport University Thesis Template]

%% ========================================================================
%% 工具宏包
%% ========================================================================
\RequirePackage{ifxetex}
\RequirePackage{etoolbox}
\RequirePackage{kvoptions}

%% ========================================================================
%% 配置选项
%% ========================================================================
\SetupKeyvalOptions{
  family=gzsu,
  prefix=gzsu@,
  setkeys=\kvsetkeys}

%% 封面信息配置
\def\gzsu@def@term#1{%
  \define@key{gzsu}{#1}{\csname #1\endcsname{##1}}
  \expandafter\gdef\csname #1\endcsname##1{%
    \expandafter\gdef\csname gzsu@#1\endcsname{##1}}
  \csname #1\endcsname{}}

\gzsu@def@term{classification}
\gzsu@def@term{schoolcode}
\gzsu@def@term{secretlevel}
\gzsu@def@term{ctitle}
\gzsu@def@term{etitle}
\gzsu@def@term{cauthor}
\gzsu@def@term{eauthor}
\gzsu@def@term{studentid}
\gzsu@def@term{csupervisor}
\gzsu@def@term{esupervisor}
\gzsu@def@term{cmajor}
\gzsu@def@term{emajor}
\gzsu@def@term{cdirection}
\gzsu@def@term{cdate}
\gzsu@def@term{edate}
\gzsu@def@term{cthesis}

\def\gzsusetup{\kvsetkeys{gzsu}}

\DeclareDefaultOption{\PassOptionsToClass{\CurrentOption}{ctexbook}}
\ProcessKeyvalOptions*

%% ========================================================================
%% 字体与语言支持
%% ========================================================================
\PassOptionsToPackage{no-math}{fontspec}
\PassOptionsToPackage{AutoFakeBold=1.5,AutoFakeSlant}{xeCJK}
\LoadClass[a4paper,openany,UTF8,zihao=-4,scheme=plain]{ctexbook}

%% ========================================================================
%% 常用宏包
%% ========================================================================
\RequirePackage{amsmath}
\RequirePackage{amssymb}
\RequirePackage{graphicx}
\RequirePackage{subcaption}
\RequirePackage{array}
\RequirePackage{booktabs}
\RequirePackage{longtable}
\RequirePackage{makecell}
\RequirePackage{multirow}
\RequirePackage{threeparttable}
\RequirePackage{tabularx}
\RequirePackage{pdfpages}
\RequirePackage{fancyhdr}
\RequirePackage{float}
\RequirePackage{cleveref}
\RequirePackage{setspace}

\raggedbottom

%% ========================================================================
%% 页面设置（广体规范：精确匹配）
%% ========================================================================
\RequirePackage{geometry}
\geometry{
  a4paper,
  hcentering,
  textheight=227mm,  % 297 - 38*2 = 221mm (考虑页眉页脚)
  top=38mm,
  bottom=38mm,
  left=32mm,
  right=32mm,
  headheight=12pt,
  headsep=12pt,
  footskip=14pt}

%% ========================================================================
%% 字号定义（广体规范）
%% ========================================================================
% 正文字号：小四 (14pt)，行距固定18pt
\normalsize{%
  \@setfontsize\normalsize{14pt}{18pt}%
  \abovedisplayskip=6pt \@plus 1pt \@minus 0.5pt
  \abovedisplayshortskip=6pt \@plus 1pt \@minus 0.5pt
  \belowdisplayskip=\abovedisplayskip
  \belowdisplayshortskip=\abovedisplayshortskip}

% 自定义字号命令
\def\erhao{\@setfontsize\erhao{22pt}{22pt}}       % 二号 22pt
\def\sanhao{\@setfontsize\sanhao{16pt}{16pt}}     % 三号 16pt
\def\sihao{\@setfontsize\sihao{14pt}{14pt}}      % 四号 14pt
\def\xiaosi{\@setfontsize\xiaosi{12pt}{12pt}}    % 小四 12pt (ctex默认)
\def\banxiaosi{\@setfontsize\banxiaosi{13pt}{13pt}} % 半四 13pt
\def\wuhao{\@setfontsize\wuhao{10.5pt}{10.5pt}}  % 五号 10.5pt
\def\xiaowu{\@setfontsize\xiaowu{9pt}{9pt}}      % 小五 9pt

%% ========================================================================
%% 首行缩进设置（广体规范：2字符）
%% ========================================================================
\ctexset{
  punct=quanjiao,
  space=auto,
  autoindent=true}

% 全局首行缩进2字符
\setlength{\parindent}{2em}

% 段前段后不空行
\setlength{\parskip}{0pt}

%% ========================================================================
%% 章节标题格式（广体规范）
%% ========================================================================
\ctexset{
  chapter={%
    format=\centering\bfseries\heiti\sanhao,
    name={},
    number=\arabic{chapter},
    aftername=\quad,
    beforeskip=12pt,
    afterskip=12pt,
    fixskip=true,
    afterindent=true,
    pagestyle={gzsu@headingmain},
  },
  section={%
    format=\raggedright\bfseries\heiti\sihao,
    name={},
    number=\arabic{chapter}.\arabic{section},
    aftername=\quad,
    beforeskip=12pt,
    afterskip=6pt,
    fixskip=true,
    afterindent=true,
  },
  subsection={%
    format=\raggedright\bfseries\heiti\banxiaosi,
    name={},
    number=\arabic{chapter}.\arabic{section}.\arabic{subsection},
    aftername=\quad,
    beforeskip=6pt,
    afterskip=3pt,
    fixskip=true,
    afterindent=true,
  },
  subsubsection={%
    format=\raggedright\heiti\xiaosi,
    name={},
    aftername=\quad,
    beforeskip=3pt,
    afterskip=3pt,
    fixskip=true,
    afterindent=true,
  }}

%% ========================================================================
%% 页眉页脚（广体规范）
%% ========================================================================
\AtBeginDocument{%
  \pagestyle{gzsu@empty}
  \renewcommand{\chaptermark}[1]{%
    \@mkboth{\thechapter\hskip\ccwd #1}{}}}

\fancypagestyle{gzsu@empty}{%
  \fancyhf{}
  \renewcommand{\headrulewidth}{0pt}
  \renewcommand{\footrulewidth}{0pt}}

\fancypagestyle{gzsu@headingmain}{%
  \fancyhead{}
  \fancyhead[C]{\xiaowu\rmfamily 广州体育学院硕士论文}
  \fancyfoot{}
  \fancyfoot[C]{\xiaowu\thepage}
  \renewcommand{\headrulewidth}{0pt}
  \renewcommand{\footrulewidth}{0pt}}

\fancypagestyle{gzsu@toc}{%
  \fancyhf{}
  \fancyfoot[C]{\xiaowu\thepage}
  \renewcommand{\headrulewidth}{0pt}}

%% ========================================================================
%% 图表标题格式
%% ========================================================================
\DeclareCaptionLabelFormat{gzsu}{{\wuhao\normalfont #1~#2}}
\DeclareCaptionLabelSeparator{gzsu}{\space}
\DeclareCaptionFont{gzsu}{\wuhao}

\captionsetup{
  labelformat=gzsu,
  labelsep=gzsu,
  font=gzsu}

\captionsetup[table]{%
  position=top,
  skip=0pt,
  labelfont=bf}

\captionsetup[figure]{%
  position=bottom,
  skip=6pt,
  labelfont=bf}

%% ========================================================================
%% 引用格式
%% ========================================================================
\crefname{equation}{公式}{公式}
\crefname{figure}{图}{图}
\crefname{table}{表}{表}
\creflabelformat{equation}{#2(#1)#3}
\creflabelformat{figure}{#2#1#3}
\creflabelformat{table}{#2#1#3}

%% ========================================================================
%% 图表编号（全文连续）
%% ========================================================================
\@ifundefined{thechapter}{}{
  \@removefromreset{figure}{chapter}\def\thefigure{\arabic{figure}}
  \@removefromreset{table}{chapter}\def\thetable{\arabic{table}}
}

%% ========================================================================
%% 参考文献格式（GB/T 7714-2015）
%% ========================================================================
\RequirePackage[backend=biber,style=gb7714-2015,%
  gbnamefmt=familyahead,%
  gbpub=false,%
  isbn=false,%
  doi=false,%
  gbtitlelink=true,%
  url=false,%
  sortlocale=zh__pinyin]{biblatex}

%% ========================================================================
%% 超链接与 PDF
%% ========================================================================
\RequirePackage{hyperref}
\hypersetup{%
  CJKbookmarks=true,
  linktoc=all,
  bookmarksnumbered=true,
  bookmarksopen=true,
  bookmarksopenlevel=1,
  breaklinks=true,
  colorlinks=false,
  plainpages=false,
  pdfborder=0 0 0}
\urlstyle{same}

%% ========================================================================
%% 浮动体间距
%% ========================================================================
\setlength{\floatsep}{6pt \@plus 2pt \@minus 1pt}
\setlength{\intextsep}{6pt \@plus 2pt \@minus 1pt}
\setlength{\textfloatsep}{6pt \@plus 2pt \@minus 1pt}

%% ========================================================================
%% frontmatter / mainmatter / backmatter
%% ========================================================================
\let\gzsu@frontmatter\frontmatter
\def\frontmatter{%
  \gzsu@frontmatter
  \pagestyle{gzsu@empty}}

\let\gzsu@mainmatter\mainmatter
\def\mainmatter{%
  \cleardoublepage
  \@mainmattertrue
  \pagenumbering{arabic}
  \pagestyle{gzsu@headingmain}}

%% ========================================================================
%% 辅助命令
%% ========================================================================

% 封面（按广体官方格式）
\def\makecover{%
  \begin{titlepage}%
    \thispagestyle{empty}%
    \vspace*{1.5cm}%
    \begin{center}%
      %% 分类号和学校代码
      {\xiaosi\songti
      \begin{tabular}{p{6cm}@{}p{6cm}}%
        分类号：\underline{\hspace{2.5cm}} & 学校代码：\underline{\hspace{2cm}10585}
      \end{tabular}
      \\[0.6cm]%
      %% 密级和学号
      \begin{tabular}{p{6cm}@{}p{6cm}}%
        密~~级：\underline{\hspace{2.5cm}} & 学~~号：\underline{\hspace{3cm}}
      \end{tabular}
      }%
      \\[3cm]%
      %% 主标题
      {\erhao\heiti 硕士学位论文} \\[0.8cm]%
      {\sanhao\heiti（\gzsu@cthesis）} \\[2cm]%
      %% 论文题目
      {\sanhao\heiti\gzsu@ctitle} \\[1.5cm]%
      %% 英文题目
      {\xiaocht\selectfont\gzsu@etitle} \\[2.5cm]%
      %% 作者和导师信息
      {\xiaosi\songti
      \begin{tabular}{l}%
        Author：{\kaishu \gzsu@cauthor} \\[0.8cm]%
        Supervisor：{\kaishu \gzsu@csupervisor}
      \end{tabular}
      }%
      \\[2cm]%
      %% 日期
      {\sanhao \gzsu@cdate}%
    \end{center}%
  \end{titlepage}%
  \clearpage}

% 独创性声明
\def\makestatement{%
  \newpage
  \thispagestyle{empty}
  \begin{center}
    \sanhao\heiti\bfseries 独创性声明和版权使用授权书
  \end{center}
  \par\vskip 1em
  {
    \xiaosi\songti
    本人郑重声明：所呈交的学位论文，是本人在导师的指导下，独立进行研究工作所取得的成果。除文中已经注明引用的内容外，本论文不包含任何其他个人或集体已经发表或撰写过的作品成果。对本文的研究作出重要贡献的个人和集体，均已在文中以明确方式标明。本声明的法律结果由本人承担。\par
  }
  \par\vskip 4em
  \xiaosi\songti 学位论文作者签名：\hfill 签字日期：\quad\quad 年\quad 月\quad 日
  \par\vskip 8em
  \begin{center}
    \sanhao\heiti\bfseries 独创性声明和版权使用授权书
  \end{center}
  \par\vskip 1em
  {
    \xiaosi\songti
    本学位论文作者完全了解 \underline{\textbf{广州体育学院}} 有关保留、使用学位论文的规定。特授权 \underline{\textbf{广州体育学院}} 可以将学位论文的全部或部分内容编入有关数据库进行检索，并采用影印、缩印或扫描等复制手段保存、汇编以供查阅和借阅。同意学校向国家有关部门或机构送交论文的复印件和磁盘。（保密的学位论文在解密后适用本授权说明）\par
  }
  \par\vskip 4em
  \xiaosi\songti 学位论文作者签名：\hfill 导师签名：\underline{\hspace{4cm}}\par
  \xiaosi\songti 签字日期：\quad\quad 年\quad 月\quad 日 \hfill 签字日期：\quad\quad 年\quad 月\quad 日
  \par\vskip 4em}

% 关键词命令
\def\keywords#1{\par\vskip 1em\textbf{关键词}：#1}
\def\engkeywords#1{\par\vskip 0.5em\textbf{Key Words}：#1}

\endinput
%%
%% End of file `gzsu_thesis.cls'.
