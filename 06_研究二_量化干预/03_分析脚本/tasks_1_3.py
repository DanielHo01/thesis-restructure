import csv, os

base = 'D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预'
raw_csv = os.path.join(base, '01_原始数据', '01_主分析数据_24人.csv')
cln_csv = os.path.join(base, '02_清洗后数据', '01_main_PP_24.csv')

raw  = list(csv.DictReader(open(raw_csv, encoding='utf-8-sig')))
clean = list(csv.DictReader(open(cln_csv, encoding='utf-8')))

# =============================================
# 任务2: 原始问卷选项核实
# =============================================
print('='*60)
print('任务2: 原始数据字段值分布')
print('='*60)
freq_vals  = sorted(set(r['Freq_wk'] for r in raw))
years_vals = sorted(set(r['ResistYears'] for r in raw))
print('Freq_wk 唯一值:', freq_vals)
print('ResistYears 唯一值:', years_vals)
print()

# =============================================
# 任务1: 16格交叉表
# =============================================
print('='*60)
print('任务1: 计划频率 x 组别 x 实际出勤 16格对账表')
print('='*60)
plan_order = freq_vals   # ['≤1次/周', '2次/周', '3次/周', '≥4次/周']

header = '%-12s | %5s %5s %5s %5s | %5s'
print(header % ('计划频率', 'AI7', 'AI8', 'Slf7', 'Slf8', '小计'))
print('-'*60)
for p in plan_order:
    a7  = sum(1 for r in clean if r['Freq_wk']==p and r['Group']=='AI组'   and r['Attend_int']=='7')
    a8  = sum(1 for r in clean if r['Freq_wk']==p and r['Group']=='AI组'   and r['Attend_int']=='8')
    s7  = sum(1 for r in clean if r['Freq_wk']==p and r['Group']=='Self组' and r['Attend_int']=='7')
    s8  = sum(1 for r in clean if r['Freq_wk']==p and r['Group']=='Self组' and r['Attend_int']=='8')
    tot = a7 + a8 + s7 + s8
    print('%-12s | %5d %5d %5d %5d | %5d' % (p, a7, a8, s7, s8, tot))

t7 = sum(1 for r in clean if r['Attend_int']=='7')
t8 = sum(1 for r in clean if r['Attend_int']=='8')
print('-'*60)
print('%-12s | %5d %5d %5d %5d | %5d' % ('合计', t7, t8, 0, 0, 24))
print()
print('说明: AI7=AI组实际7次, AI8=AI组实际8次, Slf7=Self组实际7次, Slf8=Self组实际8次')
print()

# 计划频率x组别 Fisher用
print('='*60)
print('计划频率 x 组别 (用于Fisher精确检验)')
print('='*60)
print('%-12s | %5s %5s | %5s' % ('计划频率', 'AI组', 'Self组', '合计'))
print('-'*38)
row_totals = {}
for p in plan_order:
    ai_n  = sum(1 for r in clean if r['Freq_wk']==p and r['Group']=='AI组')
    slf_n = sum(1 for r in clean if r['Freq_wk']==p and r['Group']=='Self组')
    row_totals[p] = (ai_n, slf_n)
    print('%-12s | %5d %5d | %5d' % (p, ai_n, slf_n, ai_n+slf_n))
ai_col = sum(v[0] for v in row_totals.values())
slf_col = sum(v[1] for v in row_totals.values())
print('-'*38)
print('%-12s | %5d %5d | %5d' % ('合计', ai_col, slf_col, ai_col+slf_col))
print()

# =============================================
# 任务3: ResistYears x 组别 Fisher表
# =============================================
print('='*60)
print('任务3: 抗阻训练年限 x 组别 (用于Fisher精确检验)')
print('='*60)
years_order = sorted(years_vals, key=lambda x: x)
print('%-12s | %5s %5s | %5s' % ('训练年限', 'AI组', 'Self组', '合计'))
print('-'*38)
for y in years_order:
    ai_n  = sum(1 for r in clean if r['ResistYears']==y and r['Group']=='AI组')
    slf_n = sum(1 for r in clean if r['ResistYears']==y and r['Group']=='Self组')
    print('%-12s | %5d %5d | %5d' % (y, ai_n, slf_n, ai_n+slf_n))
print('-'*38)
print('%-12s | %5d %5d | %5d' % ('合计',
    sum(1 for r in clean if r['Group']=='AI组'),
    sum(1 for r in clean if r['Group']=='Self组'), 24))
print()

# =============================================
# 任务4: 查找表4-7
# =============================================
print('='*60)
print('任务4: 表4-7相关文件内容')
print('='*60)
t4_7_path = os.path.join(base, '03_分析脚本', 'R统计分析', '08_psych_acceptance.R')
with open(t4_7_path, encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()
print('--- 08_psych_acceptance.R 全文 ---')
for i, line in enumerate(lines):
    if any(kw in line for kw in ['4-7', 'T4-7', 'psych', 'SUS', '接受']):
        print('L%-3d: %s' % (i+1, line.rstrip()))
print()

# 也检查对应输出csv
csv_path = os.path.join(base, '..', '..', 'outputs', 'tables', 'T4-7_psych_acceptance.csv')
if os.path.exists(csv_path):
    rows = list(csv.DictReader(open(csv_path, encoding='utf-8')))
    print('T4-7_psych_acceptance.csv 内容:')
    print('列名:', list(rows[0].keys()))
    for r in rows:
        print(dict(r))
else:
    print('T4-7 输出文件不存在，需运行 08_psych_acceptance.R')
