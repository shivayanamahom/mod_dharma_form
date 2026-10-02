#!/usr/bin/env python3
"""Собирает media/readme.html из README.md (без внешних зависимостей).

Поддерживает то, что используется в README: заголовки, абзацы, списки
(в том числе вложенные), таблицы, блоки кода, цитаты, `код`, **жирный**,
[ссылки](url) и <автоссылки>.

Запуск из корня модуля:  python3 tools/readme2html.py
"""
import html
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / 'README.md'
DST = ROOT / 'media' / 'readme.html'


def inline(text: str) -> str:
    """Строчная разметка. Код вырезается заранее, чтобы внутри него ничего не менялось."""
    codes = []

    def keep_code(m):
        codes.append('<code>' + html.escape(m.group(1)) + '</code>')
        return f'\x00{len(codes) - 1}\x00'

    text = re.sub(r'`([^`]+)`', keep_code, text)
    text = html.escape(text, quote=False)
    text = re.sub(r'&lt;(https?://[^\s&]+?)&gt;', r'<a href="\1" target="_blank" rel="noopener">\1</a>', text)
    text = re.sub(r'\[([^\]]+)\]\(([^)\s]+)\)', r'<a href="\2" target="_blank" rel="noopener">\1</a>', text)
    text = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', text)
    text = re.sub(r'(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])', r'<em>\1</em>', text)
    return re.sub(r'\x00(\d+)\x00', lambda m: codes[int(m.group(1))], text)


def slug(text: str) -> str:
    return re.sub(r'[^\w]+', '-', re.sub(r'<[^>]+>', '', text).lower()).strip('-')


def parse_table(lines):
    def cells(line):
        line = line.strip().strip('|')
        return [c.strip().replace('\\|', '|') for c in re.split(r'(?<!\\)\|', line)]

    head = cells(lines[0])
    out = ['<div class="table"><table><thead><tr>' + ''.join(f'<th>{inline(c)}</th>' for c in head) + '</tr></thead><tbody>']

    for row in lines[2:]:
        out.append('<tr>' + ''.join(f'<td>{inline(c)}</td>' for c in cells(row)) + '</tr>')

    out.append('</tbody></table></div>')
    return '\n'.join(out)


def parse_list(lines, i):
    """Список с вложенностью по отступу. Возвращает (html, новый индекс)."""
    def indent(line):
        return len(line) - len(line.lstrip(' '))

    def marker(line):
        m = re.match(r'^(\s*)(?:([-*])|(\d+)\.)\s+(.*)$', line)
        return m

    base = indent(lines[i])
    ordered = bool(marker(lines[i]).group(3))
    tag = 'ol' if ordered else 'ul'
    items = []

    while i < len(lines):
        line = lines[i]

        if not line.strip():
            # пустая строка внутри списка: продолжаем, если дальше снова пункт или отступ
            j = i + 1

            while j < len(lines) and not lines[j].strip():
                j += 1

            if j < len(lines) and (marker(lines[j]) and indent(lines[j]) >= base or indent(lines[j]) > base):
                i = j
                continue

            break

        m = marker(line)

        if m and indent(line) == base:
            items.append({'text': [m.group(4)], 'children': ''})
            i += 1
            continue

        if m and indent(line) > base and items:
            sub, i = parse_list(lines, i)
            items[-1]['children'] += sub
            continue

        if indent(line) > base and items:
            # продолжение текста пункта (или вложенный блок кода)
            if line.strip().startswith('```'):
                code = []
                i += 1

                while i < len(lines) and not lines[i].strip().startswith('```'):
                    code.append(lines[i][base + 3:] if lines[i].startswith(' ' * (base + 3)) else lines[i].strip())
                    i += 1

                i += 1
                items[-1]['children'] += '<pre><code>' + html.escape('\n'.join(code)) + '</code></pre>'
            else:
                items[-1]['text'].append(line.strip())
                i += 1

            continue

        break

    out = [f'<{tag}>']

    for item in items:
        out.append('<li>' + inline(' '.join(item['text'])) + item['children'] + '</li>')

    out.append(f'</{tag}>')
    return '\n'.join(out), i


def convert(md: str):
    lines = md.split('\n')
    out = []
    toc = []
    i = 0

    while i < len(lines):
        line = lines[i]

        if not line.strip():
            i += 1
            continue

        if line.startswith('```'):
            code = []
            i += 1

            while i < len(lines) and not lines[i].startswith('```'):
                code.append(lines[i])
                i += 1

            i += 1
            out.append('<pre><code>' + html.escape('\n'.join(code)) + '</code></pre>')
            continue

        m = re.match(r'^(#{1,4})\s+(.*)$', line)

        if m:
            level = len(m.group(1))
            title = inline(m.group(2))
            anchor = slug(m.group(2))

            if level == 2:
                toc.append((anchor, re.sub(r'<[^>]+>', '', title)))

            out.append(f'<h{level} id="{anchor}">{title}</h{level}>')
            i += 1
            continue

        if line.startswith('|') and i + 1 < len(lines) and re.match(r'^\|[\s:|-]+\|?$', lines[i + 1].strip()):
            block = []

            while i < len(lines) and lines[i].startswith('|'):
                block.append(lines[i])
                i += 1

            out.append(parse_table(block))
            continue

        if line.startswith('>'):
            block = []

            while i < len(lines) and lines[i].startswith('>'):
                block.append(lines[i].lstrip('>').strip())
                i += 1

            out.append('<blockquote>' + inline(' '.join(block)) + '</blockquote>')
            continue

        if re.match(r'^\s*(?:[-*]|\d+\.)\s+', line):
            block, i = parse_list(lines, i)
            out.append(block)
            continue

        para = []

        while i < len(lines) and lines[i].strip() and not re.match(r'^(#{1,4}\s|```|\||>|\s*(?:[-*]|\d+\.)\s+)', lines[i]):
            para.append(lines[i].strip())
            i += 1

        out.append('<p>' + inline(' '.join(para)) + '</p>')

    return '\n'.join(out), toc


PAGE = '''<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dharma Form (mod_dharma_form) — инструкция</title>
<style>
:root{--bg:#fff;--ink:#1c2433;--muted:#5b6678;--line:#e3e7ee;--soft:#f5f7fa;--accent:#2a66a6;--code:#eef1f6}
@media (prefers-color-scheme:dark){:root{--bg:#141922;--ink:#e6eaf2;--muted:#9aa5b8;--line:#2a3243;--soft:#1b2230;--accent:#7fb2ee;--code:#222b3b}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.wrap{display:grid;grid-template-columns:240px minmax(0,1fr);gap:48px;max-width:1160px;margin:0 auto;padding:32px 24px 96px}
nav{position:sticky;top:24px;align-self:start;font-size:14px}
nav b{display:block;margin-bottom:8px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-size:12px}
nav a{display:block;padding:4px 0;color:var(--muted);text-decoration:none}
nav a:hover{color:var(--accent)}
main{min-width:0;max-width:820px}
h1{font-size:2rem;line-height:1.2;margin:0 0 1rem}
h2{margin:2.5rem 0 .75rem;padding-top:1.5rem;border-top:1px solid var(--line);font-size:1.5rem;line-height:1.25}
h3{margin:1.75rem 0 .5rem;font-size:1.15rem}
a{color:var(--accent)}
code{padding:.1em .35em;border-radius:5px;background:var(--code);font:.88em ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
pre{overflow:auto;margin:1rem 0;padding:14px 16px;border-radius:10px;background:var(--code);line-height:1.5}
pre code{padding:0;background:none}
blockquote{margin:1rem 0;padding:.75rem 1rem;border-left:4px solid var(--accent);border-radius:0 8px 8px 0;background:var(--soft)}
.table{overflow-x:auto;margin:1rem 0}
table{width:100%;border-collapse:collapse;font-size:.95rem}
th,td{padding:8px 12px;border:1px solid var(--line);text-align:left;vertical-align:top}
th{background:var(--soft)}
li{margin:.25rem 0}
@media (max-width:860px){.wrap{display:block;padding:20px 16px 64px}nav{display:none}}
</style>
</head>
<body>
<div class="wrap">
<nav><b>Содержание</b>
%NAV%
</nav>
<main>
%BODY%
</main>
</div>
</body>
</html>
'''


def main():
    body, toc = convert(SRC.read_text(encoding='utf-8'))
    nav = '\n'.join(f'<a href="#{a}">{t}</a>' for a, t in toc)
    DST.parent.mkdir(parents=True, exist_ok=True)
    DST.write_text(PAGE.replace('%NAV%', nav).replace('%BODY%', body), encoding='utf-8')
    print(f'{DST.relative_to(ROOT)}: {DST.stat().st_size} bytes, {len(toc)} разделов')


if __name__ == '__main__':
    sys.exit(main())
