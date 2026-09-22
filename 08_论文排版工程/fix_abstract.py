# -*- coding: utf-8 -*-
"""
中英文摘要精修替换脚本
"""
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING

INPUT_FILE = r"C:\Users\30625\Desktop\广州体育学院-何天元-毕业论文.docx"
OUTPUT_FILE = r"C:\Users\30625\Desktop\广州体育学院-何天元-毕业论文_摘要精修.docx"

def set_font(run, font_name_cn='宋体', font_size=12, bold=False):
    """设置字体和字号"""
    run.font.size = Pt(font_size)
    run.font.bold = bold

# 中文摘要内容
cn_content = """目的：评估自研移动端计算机视觉系统SportSci Pro在抗阻训练中的测量效度，检验基于速度的数智化监控方案的4周干预效果，并探索训练者行为特征与系统接受度。

方法：研究一招募13名有训练经验的健康男性，在受控条件下同步采集SportSci Pro与GymAware线性位置传感器的杠铃后蹲平均向心速度数据，以及该系统与240 fps高速摄像机标的纵跳高度数据，采用组内相关系数（ICC）、Bland-Altman分析和标准测量误差（SEE）评估测量一致性。研究二采用随机对照设计，纳入36名完成随机化的健康青年男性训练者（AI辅助组11人，自我指导组13人）进行4周、每周2次的下肢抗阻训练干预；AI辅助组在深蹲主项中接受基于速度损失的自停组和速度反馈负荷调控；同步采集88对热身配对和43对Rep配对用于生态效度补充验证。T1后测完成后的第8周，12名受试者（P003/P004/P010/P012/P013/P014/P015/P016/P018/P021/P025/P027）完成半结构化深度访谈，采用三轮主题分析法进行分析。

结果：（1）受控条件下，SportSci Pro与GymAware在杠铃后蹲MCV上达到优秀一致性，ICC(2,1)=0.940（95% CI：0.854~0.975），MAE=0.047 m/s，Bias=+0.002 m/s；（2）PP样本24人中，训练完成率98.4%（189/192课次），达80%课次出勤标准者100%（24/24），热身配对ICC=0.987（95% CI：0.979~0.991），Rep配对ICC=0.991（95% CI：0.983~0.995）；（3）ANCOVA调整后，组间在深蹲1RM（Δ=+2.43 kg，95% CI：-2.01~6.87 kg，p=0.266）、CMJ高度（Δ=+1.36 cm，95% CI：-0.01~4.33 cm，p=0.051）和SJ高度上均无显著差异；（4）Hooper指数LMM显示时间主效应显著（F(7,154)=6.34，p<0.001），呈倒U型轨迹（S4-S6峰值→S8谷底），组×时间交互效应不显著（F(7,154)=0.32，p=0.945）；（5）12名受试者完成质性访谈，提炼出"反馈将训练决策从'凭感觉'转化为'有依据的调整'"等六大主题。

结论：数智化监控辅助训练方案在4周大众下肢抗阻训练场景中具有初步的实施可行性，训练自我效能出现积极变化，系统信任度和继续使用意愿整体较高。但本预试验样本量有限且缺乏盲法设计，效应量估计精确度有限，结论的外推性需谨慎解读。"""

cn_keywords = "关键词：数智化监控；抗阻训练；基于速度的训练；预试验性研究；混合方法研究；随机对照预试验"

# 英文摘要内容
en_content = """Objective: To evaluate the measurement validity of the self-developed mobile computer vision system SportSci Pro in resistance training, examine the effects of a 4-week velocity-based digital monitoring intervention, and explore trainees' behavioral characteristics and system acceptance.

Methods: Study 1 recruited 13 resistance-trained healthy males under controlled conditions to simultaneously collect barbell back squat mean concentric velocity (MCV) data using SportSci Pro and GymAware linear position transducer, as well as countermovement jump (CMJ) height data from SportSci Pro and a 240 fps high-speed camera, using intraclass correlation coefficient (ICC), Bland-Altman analysis, and standard error of estimate (SEE) to assess measurement agreement. Study 2 adopted a randomized controlled design, enrolling 36 randomized healthy young male trainees (11 in AI-assisted group, 13 in self-guided group) for a 4-week, twice-weekly lower-body resistance training intervention; the AI-assisted group received velocity loss-based auto-regulation and velocity feedback load regulation in main squat sessions; 88 warm-up pairs and 43 repetition pairs were simultaneously collected for ecological validity supplementary validation. After T1 post-test completion, 12 subjects completed semi-structured in-depth interviews in the 8th week, analyzed using a three-round thematic analysis approach.

Results: (1) Under controlled conditions, SportSci Pro achieved excellent agreement with GymAware for barbell back squat MCV, with ICC(2,1)=0.940 (95% CI: 0.854-0.975), MAE=0.047 m/s, Bias=+0.002 m/s; (2) Among the 24 per-protocol participants, training completion rate was 98.4% (189/192 sessions), 100% (24/24) met the >=80% session attendance criterion, warm-up pairs ICC=0.987 (95% CI: 0.979-0.991), repetition pairs ICC=0.991 (95% CI: 0.983-0.995); (3) After ANCOVA adjustment, no significant between-group differences were found for back squat 1RM (delta=+2.43 kg, 95% CI: -2.01-6.87 kg, p=0.266) or CMJ height (delta=+1.36 cm, 95% CI: -0.01-4.33 cm, p=0.051); (4) Hooper index LMM showed significant time main effect (F(7,154)=6.34, p<0.001), with an inverted-U trajectory (S4-S6 peak to S8 trough), while groupxtime interaction was not significant (F(7,154)=0.32, p=0.945); (5) Twelve subjects completed qualitative interviews, yielding six themes including "feedback transforms training decisions from feeling-based to evidence-based".

Conclusion: The digital monitoring-assisted training protocol demonstrated preliminary implementation feasibility in a 4-week lower-body resistance training setting, with positive changes in training self-efficacy and overall high system trust and intention to continue use. However, this pilot study had limited sample size and lacked blinding, requiring cautious interpretation of generalizability."""

en_keywords = "Keywords: Digital Monitoring; Resistance Training; Velocity-Based Training; Pilot Study; Mixed Methods Research; Randomized Controlled Pilot Trial"

def fix_document():
    print(f"Reading: {INPUT_FILE}")
    doc = Document(INPUT_FILE)
    
    changes = 0
    
    # 遍历所有段落
    for i, para in enumerate(doc.paragraphs):
        text = para.text.strip()
        
        if not text:
            continue
        
        # 1. 找到"摘  要"标题
        if text == '摘  要' or text == '摘要':
            for run in para.runs:
                set_font(run, '黑体', 16, True)
            para.alignment = WD_ALIGN_PARAGRAPH.CENTER
            para.paragraph_format.space_before = Pt(24)
            para.paragraph_format.space_after = Pt(18)
            print(f"[{i}] 格式化: 摘  要")
            changes += 1
            continue
        
        # 2. 找到"目的"段落并替换
        if text.startswith('目的：'):
            # 清除所有run
            for run in para.runs:
                run.text = ''
            # 设置第一个run的内容
            para.runs[0].text = cn_content
            for run in para.runs:
                set_font(run, '宋体', 12, False)
            para.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
            para.paragraph_format.first_line_indent = Pt(21)
            para.paragraph_format.line_spacing_rule = WD_LINE_SPACING.EXACTLY
            para.paragraph_format.line_spacing = Pt(20)
            print(f"[{i}] 替换中文摘要内容")
            changes += 1
            continue
        
        # 3. 找到中文关键词段落
        if text.startswith('关键词：'):
            # 清除
            for run in para.runs:
                run.text = ''
            # 添加关键词
            run1 = para.runs[0]
            run1.text = cn_keywords
            set_font(run1, '宋体', 12, False)
            para.paragraph_format.space_before = Pt(0)
            para.paragraph_format.space_after = Pt(0)
            print(f"[{i}] 替换中文关键词")
            changes += 1
            continue
        
        # 4. 找到"Abstract"标题
        if text == 'Abstract':
            for run in para.runs:
                set_font(run, 'Times New Roman', 16, True)
            para.alignment = WD_ALIGN_PARAGRAPH.CENTER
            para.paragraph_format.space_before = Pt(24)
            para.paragraph_format.space_after = Pt(18)
            print(f"[{i}] 格式化: Abstract")
            changes += 1
            continue
        
        # 5. 找到英文目的段落
        if text.startswith('Objective:'):
            for run in para.runs:
                run.text = ''
            para.runs[0].text = en_content
            for run in para.runs:
                set_font(run, '宋体', 12, False)
            para.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
            para.paragraph_format.first_line_indent = Pt(21)
            para.paragraph_format.line_spacing_rule = WD_LINE_SPACING.EXACTLY
            para.paragraph_format.line_spacing = Pt(20)
            print(f"[{i}] 替换英文摘要内容")
            changes += 1
            continue
        
        # 6. 找到英文关键词段落
        if text.startswith('Keywords:'):
            for run in para.runs:
                run.text = ''
            para.runs[0].text = en_keywords
            for run in para.runs:
                set_font(run, '宋体', 12, False)
            para.paragraph_format.space_before = Pt(0)
            para.paragraph_format.space_after = Pt(0)
            print(f"[{i}] 替换英文关键词")
            changes += 1
            continue
    
    print(f"\n总计修改: {changes}处")
    
    # 保存
    print(f"Saving to: {OUTPUT_FILE}")
    doc.save(OUTPUT_FILE)
    print("✅ 完成!")

if __name__ == '__main__':
    fix_document()
