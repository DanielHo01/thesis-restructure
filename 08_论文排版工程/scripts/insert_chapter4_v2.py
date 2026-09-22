"""
第4章 4.1-4.4 精修内容批量插入脚本
策略：读取LaTeX源文件 → 生成Word兼容内容 → 追加到文档
使用 python-docx 直接操作
"""
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import copy

DOC = r"C:\Users\30625\Desktop\广州体育学院-何天元-毕业论文.docx"

# ========================
# 格式辅助函数
# ========================

def set_run_font(run, cn_font="宋体", en_font="Times New Roman", size_pt=12, bold=False):
    """设置 run 的中英文字体和字号"""
    run.font.size = Pt(size_pt)
    run.font.bold = bold
    # 中文字体
    r = run._element
    r_rPr = r.get_or_add_rPr()
    # 设置字体
    rFonts = OxmlElement('w:rFonts')
    rFonts.set(qn('w:eastAsia'), cn_font)
    rFonts.set(qn('w:ascii'), en_font)
    rFonts.set(qn('w:hAnsi'), en_font)
    rFonts.set(qn('w:cs'), en_font)
    r_rPr.append(rFonts)

def add_heading(doc, text, level=2):
    """添加标题"""
    p = doc.add_paragraph()
    p.style = doc.styles[f'Heading {level}']
    run = p.add_run(text)
    return p

def add_normal(doc, text, indent=False, bold_parts=None):
    """添加正文段落"""
    p = doc.add_paragraph()
    if indent:
        p.paragraph_format.first_line_indent = Cm(0.74)  # 2字符
    
    if bold_parts is None:
        run = p.add_run(text)
        set_run_font(run)
    else:
        # bold_parts: list of (text, is_bold)
        for part_text, is_bold in bold_parts:
            run = p.add_run(part_text)
            set_run_font(run, bold=is_bold)
    return p

def add_table(doc, headers, rows, col_widths=None):
    """添加三线表"""
    table = doc.add_table(rows=1+len(rows), cols=len(headers))
    table.style = 'Table Grid'
    
    # 表头
    hdr = table.rows[0].cells
    for i, h in enumerate(headers):
        hdr[i].text = h
        run = hdr[i].paragraphs[0].runs[0]
        set_run_font(run, bold=True)
    
    # 数据行
    for ri, row in enumerate(rows):
        cells = table.rows[ri+1].cells
        for ci, cell in enumerate(row):
            cells[ci].text = str(cell)
            set_run_font(cells[ci].paragraphs[0].runs[0])
    
    return table

# ========================
# 第4章内容定义
# ========================

CHAPTER4_14_CONTENT = [
    # (type, content)
    # type: 'h2'=二级标题, 'h3'=三级标题, 'p'=正文, 'table'=表格, 'blank'=空行
    
    ('h2', '4.1 研究一：实验室受控环境下移动端计算机视觉系统的测量效度'),
    
    ('h3', '4.1.1 研究背景与目的'),
    
    ('p', '研究一旨在验证自研移动端应用程序SportSci Pro与传统金标准线性位置传感器GymAware在杠铃后蹲平均向心速度测量上的一致性，为后续真实训练场景应用提供测量学基础。当前移动端速度监测工具日益普及，但其相对于专业设备的测量精度尚缺乏系统性验证。'),
    
    ('p', '研究一在受控实验室条件下开展，采用递增负荷实验设计。13名具有抗阻训练经验的健康青年男性受试者在30%至接近100% 1RM的负荷范围内完成测试，每名受试者在每个负荷水平完成3次有效动作并取平均值。测试过程中SportSci Pro与GymAware同步记录数据，后期由独立研究员完成手动配对标注。'),
    
    ('h3', '4.1.2 效度分析结果'),
    
    ('p', '共纳入60对有效配对样本（13名受试者×多负荷水平）。分析结果显示，组内相关系数ICC(2,1)为0.940（95% CI：0.854, 0.975），根据Koo与Li（2016）的解释标准达到"优秀"一致性水平。Bland-Altman分析显示，两种设备间的平均偏差Bias为+0.002 m/s，接近零线，表明系统无系统性高估或低估。95%一致性界限为（-0.098, +0.102）m/s，对应误差范围约为±10 cm/s。平均绝对误差MAE为0.047 m/s。Pearson相关系数r=0.982（95% CI：0.957, 0.992），决定系数R²=0.965，表明两种设备测量值间存在极强的线性关系。'),
    
    ('p', '上述结果支持以下结论：SportSci Pro在受控实验条件下具备替代GymAware进行杠铃后蹲速度测量的测量学基础，可用于后续真实训练场景中的移动端速度监测应用。'),
    
    ('blank', None),
    
    ('h2', '4.2 研究二真实训练场景生态效度补充分析'),
    
    ('h3', '4.2.1 Rep级配对效度分析'),
    
    ('p', '研究二在干预期间以静默模式对AI辅助组受试者的训练过程进行持续录像，后期由两名研究员独立完成Rep级App-GymAware手动配对标注。共纳入3名受试者（AI辅助组P006，P016，P025）的43对Rep级配对数据。'),
    
    ('p', 'Bland-Altman分析显示，Rep级配对的平均偏差Bias为+0.0019 m/s，接近零线。95%一致性界限为（-0.034, +0.038）m/s，对应误差范围约为±3.5 cm/s。组内相关系数ICC(2,1)为0.991（95% CI: 0.983, 0.995），表明该系统在Rep级精度的动作中也具有优秀的一致性。'),
    
    ('h3', '4.2.2 热身级配对效度分析'),
    
    ('p', '在热身组次层面，共纳入11名AI辅助组受试者的88对热身级配对数据。分析结果显示Bias为+0.0212 m/s（系统偏高约2 cm/s），95%一致性界限为（+0.013, +0.030）m/s。该偏差方向与热身阶段动作速度偏低、铃片边缘检测受衣物干扰的物理机制一致。所有88对配对均呈现正偏，系统不存在低估现象。ICC(2,1)为0.987（95% CI: 0.979, 0.991）。'),
    
    ('p', '综合研究一和研究二的效度证据，SportSci Pro在Rep级（ICC=0.991）和热身级（ICC=0.987）均展现出接近优秀的一致性水平。该系统在真实训练场景中具有替代线性位置传感器的测量学基础，但在热身低速区间存在系统性正偏，需通过工程校正弥补。'),
    
    ('blank', None),
    
    ('h2', '4.3 研究二干预可行性分析'),
    
    ('h3', '4.3.1 样本招募与保留'),
    
    ('p', '研究二从45名报名者中，36名完成随机化（随机化率80.0%），29名进入干预（进入干预率80.6%），24名完成主要后测（主要后测完成率66.7%）。12名脱落者中，7名（58.3%）发生于随机化后至正式干预开始前的T0基线测试过渡阶段，5名（41.7%）发生于4周干预期间。脱落原因主要包括个人事务（8名）、出勤率不足（3名）及腰部轻度肌肉拉伤（1名）。'),
    
    ('h3', '4.3.2 训练执行与系统操作'),
    
    ('p', 'PP样本24名受试者在4周干预期间共执行192个训练课次，其中189个课次完成了完整的过程记录（训练完成率98.4%）。3个课次因关键字段缺失未达到完整记录标准。AI辅助组在深蹲主项中的速度命中率（实际速度落在目标速度区间的比例）为80.2%（目标≥60%）。不良事件方面，AI辅助组报告2例轻度肌肉酸痛，自我指导组无不良事件报告。'),
    
    ('blank', None),
    
    ('h2', '4.4 两组训练执行特征比较'),
    
    ('p', '两组在4周共8次训练中累积的外部训练负荷、内部负荷反应与派生结构指标的组间比较结果如下。'),
    
    # 表4-4
    ('table', {
        'caption': '表4-4 两组训练执行指标比较',
        'headers': ['指标', 'AI辅助组（n=11）', '自我指导组（n=13）', 'p值'],
        'rows': [
            ['全期总负荷（kg）', '17651.8±2198.6', '17461.5±2792.1', '0.854'],
            ['深蹲主项总负荷（kg）', '13654.5±1819.3', '13642.3±2226.5', '0.988'],
            ['平均每课次负荷（kg）', '2297.7±370.7', '2182.7±349.0', '0.445'],
            ['总组数', '28.2±1.6', '29.1±1.1', '0.136'],
            ['总完成重复次数', '100.3±6.5', '105.5±4.1', '0.035*'],
            ['每组平均重复次数', '3.56±0.27', '3.63±0.16', '0.492'],
            ['平均每组重量（kg）', '62.14±6.21', '60.07±6.88', '0.466'],
            ['组间RPE', '7.2±0.6', '7.4±0.5', '0.381'],
            ['会话RPE（sRPE）', '6.8±1.0', '7.1±0.9', '0.462'],
        ]
    }),
    
    ('p', '注：*p<0.05，组间差异具有统计学意义。数据以均值±标准差表示。'),
    
    ('p', '在总负荷相当的前提下，AI辅助组的总完成重复次数显著少于自我指导组（100.3±6.5 vs. 105.5±4.1次，p=0.035，Hedges\' g=-0.95），效应量为中等偏大。这表明AI辅助组在保持总训练量的同时，通过提高每次重复的重量实现了等量负荷，体现了"高重量、少次数"的负荷策略差异。'),
    
    ('blank', None),
]

def main():
    print(f"打开文档: {DOC}")
    doc = Document(DOC)
    
    # 找到第4章位置
    ch4_start = None
    for i, p in enumerate(doc.paragraphs):
        if '4\u3010' in p.text or '4\u7ae0' in p.text:
            ch4_start = i
            print(f"找到第4章位置: 段落{i}")
            break
    
    if ch4_start is None:
        print("未找到第4章，退出")
        return
    
    # 插入内容
    insert_idx = ch4_start
    for item_type, content in CHAPTER4_14_CONTENT:
        if item_type == 'h2':
            p = doc.paragraphs[insert_idx].insert_paragraph_before()
            run = p.add_run(content)
            run.bold = True
            run.font.size = Pt(14)
            set_run_font(run, cn_font="黑体")
            p.paragraph_format.space_before = Pt(24)
            p.paragraph_format.space_after = Pt(6)
            insert_idx += 1
            print(f"插入二级标题: {content[:30]}...")
        elif item_type == 'h3':
            p = doc.paragraphs[insert_idx].insert_paragraph_before()
            run = p.add_run(content)
            run.bold = True
            run.font.size = Pt(12)
            set_run_font(run, cn_font="黑体")
            p.paragraph_format.space_before = Pt(12)
            p.paragraph_format.space_after = Pt(3)
            insert_idx += 1
            print(f"插入三级标题: {content[:30]}...")
        elif item_type == 'p':
            p = doc.paragraphs[insert_idx].insert_paragraph_before()
            run = p.add_run(content)
            set_run_font(run)
            p.paragraph_format.first_line_indent = Cm(0.74)
            p.paragraph_format.space_after = Pt(0)
            insert_idx += 1
            print(f"插入正文: {content[:40]}...")
        elif item_type == 'blank':
            p = doc.paragraphs[insert_idx].insert_paragraph_before()
            insert_idx += 1
        elif item_type == 'table':
            # 在段落前插入表格
            p = doc.paragraphs[insert_idx].insert_paragraph_before()
            p.add_run(content['caption']).bold = True
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            insert_idx += 1
            
            table = doc.tables[-1] if doc.tables else None
            # 用 python-docx API 直接创建表格较复杂，改用 python-docx 操作
            tbl = doc.add_table(rows=1+len(content['rows']), cols=len(content['headers']))
            tbl.style = 'Table Grid'
            
            # 表头
            for i, h in enumerate(content['headers']):
                tbl.rows[0].cells[i].text = h
                r = tbl.rows[0].cells[i].paragraphs[0].runs
                if r:
                    r[0].bold = True
                    set_run_font(r[0])
            
            # 数据
            for ri, row in enumerate(content['rows']):
                for ci, cell in enumerate(row):
                    tbl.rows[ri+1].cells[ci].text = str(cell)
                    r = tbl.rows[ri+1].cells[ci].paragraphs[0].runs
                    if r:
                        set_run_font(r[0])
            
            insert_idx += 1
            print(f"插入表格: {content['caption']}")
    
    # 保存
    out = DOC.replace('.docx', '_updated.docx')
    doc.save(out)
    print(f"\n保存到: {out}")
    print(f"总段落数: {len(doc.paragraphs)}")
    print(f"总表格数: {len(doc.tables)}")

if __name__ == '__main__':
    main()
