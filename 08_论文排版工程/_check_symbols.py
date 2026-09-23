#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Check LaTeX special characters in main.tex"""

with open('main.tex', 'r', encoding='utf-8') as f:
    content = f.read()

lines = content.split('\n')
bare_pct = []
bare_amp = []
bare_hash = []

for i, line in enumerate(lines, 1):
    stripped = line.lstrip()
    if stripped.startswith('%'):
        continue

    # bare %: % not preceded by backslash
    j = 0
    while j < len(line):
        if j > 0 and line[j] == '%' and line[j-1] != '\\':
            bare_pct.append((i, line.rstrip()[:80]))
            break
        j += 1

    # bare &: & not preceded by backslash
    j = 0
    while j < len(line):
        if j > 0 and line[j] == '&' and line[j-1] != '\\':
            bare_amp.append((i, line.rstrip()[:80]))
            break
        j += 1

    # bare #: # not preceded by backslash (outside comments)
    j = 0
    while j < len(line):
        if j > 0 and line[j] == '#' and line[j-1] != '\\':
            bare_hash.append((i, line.rstrip()[:80]))
            break
        j += 1

print(f'Bare % (non-comment lines): {len(bare_pct)}')
for ln, text in bare_pct[:5]:
    print(f'  L{ln}: {text}')

print(f'Bare & (non-comment lines): {len(bare_amp)}')
for ln, text in bare_amp[:5]:
    print(f'  L{ln}: {text}')

print(f'Bare # (non-comment lines): {len(bare_hash)}')
for ln, text in bare_hash[:3]:
    print(f'  L{ln}: {text}')
