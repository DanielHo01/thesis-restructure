import csv, os

base = 'D:/研究生文件/研三/2026年9月/毕业论文重构版/06_研究二_量化干预/01_原始数据'
mon = list(csv.DictReader(open(os.path.join(base, '02_训练监控数据_192课次.csv'), encoding='utf-8-sig')))

# =============================================
# 问题2: 有效组数分析
# =============================================
print('=== 按组别统计 GA/App 数据记录情况 ===')
groups = {}
for r in mon:
    g = r.get('Group', '?')
    if g not in groups:
        groups[g] = {'total': 0, 'ga_ok': 0, 'app_ok': 0, 'both_ok': 0}
    groups[g]['total'] += 1
    ga_val = r.get('GA', '')
    app_val = r.get('App', '')
    if ga_val and ga_val not in ('', 'NA', 'NaN'):
        groups[g]['ga_ok'] += 1
    if app_val and app_val not in ('', 'NA', 'NaN'):
        groups[g]['app_ok'] += 1
    if ga_val and app_val and ga_val not in ('', 'NA', 'NaN') and app_val not in ('', 'NA', 'NaN'):
        groups[g]['both_ok'] += 1

for g, v in groups.items():
    print(f'{g}: 总={v["total"]} GA有={v["ga_ok"]} App有={v["app_ok"]} 两者都有={v["both_ok"]}')

print()
print('=== Self组出勤情况 ===')
self_ids = sorted(set(r['ID'] for r in mon if r.get('Group') == 'Self组'))
for pid in self_ids:
    records = [rr for rr in mon if rr['ID'] == pid]
    ga_count = sum(1 for rr in records if rr.get('GA') and str(rr.get('GA', '')).strip() not in ('', 'NA'))
    print(f'  {pid}: {len(records)}次课, GA记录={ga_count}次')

print()
print('=== AI组出勤与缺失课次 ===')
main = list(csv.DictReader(open(os.path.join(base, '01_主分析数据_24人.csv'), encoding='utf-8-sig')))
for pid in sorted(set(r['ID'] for r in mon if r.get('Group') == 'AI组')):
    row = next((r for r in main if r['ID'] == pid), None)
    records = [rr for rr in mon if rr['ID'] == pid]
    missing = row.get('MissingSessions', '?') if row else '?'
    ga_count = sum(1 for rr in records if rr.get('GA') and str(rr.get('GA', '')).strip() not in ('', 'NA'))
    print(f'  {pid}: {len(records)}次课, GA记录={ga_count}, MissingSessions={missing}')
