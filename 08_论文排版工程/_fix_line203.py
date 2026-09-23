#!/usr/bin/env python3
with open('main.tex', 'rb') as f:
    content = f.read()

old = b'\\newgeometry{left=2.7cm,right=2.7cm,top=2.75cm,bottom=1.92cm}\\begin{titlepage}'
new = b'\\newgeometry{left=2.7cm,right=2.7cm,top=2.75cm,bottom=1.92cm}\n\\begin{titlepage}'

if old in content:
    content = content.replace(old, new)
    with open('main.tex', 'wb') as f:
        f.write(content)
    print('Fixed!')
else:
    print('Pattern not found, checking...')
    # Find the line
    idx = content.find(b'newgeometry')
    print(f'newgeometry found at: {idx}')
    print(f'Bytes around: {repr(content[idx:idx+120])}')
