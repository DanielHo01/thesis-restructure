#!/usr/bin/env python3
"""修复LaTeX表格溢出问题的脚本"""

import re
import os

def fix_tabular_env(match):
    """修复tabular环境，将普通tabular转换为tabular*以适应文本宽度"""
    begin = match.group(1)
    col_spec = match.group(2)
    content = match.group(3)
    end = match.group(4)
    
    # 检查是否已经是tabular*或需要修复
    if 'tabular*' in begin:
        return match.group(0)
    
    # 计算列数和简单列规格
    # 计算列数的简单方法：统计l, c, r, p, |等
    col_count = len(re.findall(r'[lcrp|@{}]', col_spec.replace('|', '')))
    
    # 对于列数超过5个的表格，使用tabular*强制适应文本宽度
    if col_count > 5:
        # 使用tabular*，让LaTeX自动分配宽度
        new_begin = begin.replace('tabular', 'tabular*')
        # 在列规格后添加@{\extracolsep{\fill}}让列自动填充
        return f'{new_begin}{{{col_spec}@{{\\extracolsep{{\\fill}}}}}}\n% fixed column count: {col_count}'
    
    return match.group(0)

def fix_tables_in_file(filepath):
    """修复单个文件中的表格"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # 修复 tabular{...} 环境
    pattern = r'(\\begin\{(?:tabular|tabular\*)\*?)\{([^}]+)\}(.*?)(\\end\{(?:tabular|tabular\*)\*?\})'
    
    def replace_tabular(match):
        begin = match.group(1)
        col_spec = match.group(2)
        middle = match.group(3)
        end = match.group(4)
        
        # 计算列数
        plain_spec = col_spec.replace('|', '').replace('@{}', '')
        col_count = len(plain_spec)
        
        # 对于宽度敏感的表格（特别是有p{}列的），保持原样
        if 'p{' in col_spec:
            return match.group(0)
        
        # 对于超过5列的表格，转换为tabular*
        if col_count > 5 and 'tabular*' not in begin:
            # 清理列规格中的多余空白
            col_spec_clean = re.sub(r'\s+', '', col_spec)
            new_begin = begin.replace('tabular', 'tabular*')
            return f'{new_begin}{{{col_spec_clean}@{{\\extracolsep{{\\fill}}}}}}'
        
        return match.group(0)
    
    new_content = re.sub(pattern, replace_tabular, content, flags=re.DOTALL)
    
    if new_content != content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        return True
    return False

def fix_all_tables():
    """修复所有tex文件中的表格"""
    base_dir = r"D:\研究生文件\研二\研二下(2026.03-2026.08)\2026年3月\毕业论文\thesis-latex"
    chapters_dir = os.path.join(base_dir, "chapters")
    
    files_fixed = []
    
    for filename in os.listdir(chapters_dir):
        if filename.endswith('.tex'):
            filepath = os.path.join(chapters_dir, filename)
            if fix_tables_in_file(filepath):
                files_fixed.append(filename)
    
    return files_fixed

if __name__ == "__main__":
    fixed = fix_all_tables()
    print(f"Fixed tables in: {fixed}")
