#!/usr/bin/env python3
"""Validate relative Markdown file/directory targets; standard library, no network.

Default scope: root README.md, docs/**/*.md, app/docs/**/*.md.
Explicit Markdown paths may include an independent documentation repository.
Anchors and remote URLs are intentionally outside this check.
"""
import argparse
import re
import sys
from pathlib import Path
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parent.parent
INLINE = re.compile(r'!?\[[^\]\n]*\]\(\s*(<[^>\n]+>|[^\s)]+)(?:\s+["\'][^\n]*?["\'])?\s*\)')
REFERENCE = re.compile(r'^\s{0,3}\[[^\]\n]+\]:\s*(<[^>\n]+>|\S+)', re.MULTILINE)


def without_code(text):
    """Preserve line numbers while skipping fenced and inline code."""
    lines = []
    fence = None
    for line in text.splitlines(keepends=True):
        marker = re.match(r'^\s{0,3}(`{3,}|~{3,})', line)
        if fence:
            lines.append('\n' if line.endswith('\n') else '')
            if marker and marker[1][0] == fence[0] and len(marker[1]) >= len(fence):
                fence = None
        elif marker:
            fence = marker[1]
            lines.append('\n' if line.endswith('\n') else '')
        else:
            lines.append(re.sub(r'(`+).*?\1', '', line))
    return ''.join(lines)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('files', nargs='*', type=Path)
    args = parser.parse_args()
    files = args.files or [ROOT / 'README.md', *sorted((ROOT / 'docs').rglob('*.md')),
                          *sorted((ROOT / 'app/docs').rglob('*.md'))]
    broken = []
    checked = 0
    for path in files:
        if not path.is_file():
            broken.append(f'{path}: missing Markdown input')
            continue
        content = without_code(path.read_text(encoding='utf-8'))
        for pattern in (INLINE, REFERENCE):
            for match in pattern.finditer(content):
                target = match[1].strip('<>')
                parsed = urlsplit(target)
                if parsed.scheme or parsed.netloc or not parsed.path:
                    continue
                checked += 1
                destination = path.parent / unquote(parsed.path)
                if not destination.exists():
                    line = content.count('\n', 0, match.start()) + 1
                    broken.append(f'{path}:{line}: missing relative target {target}')
    for problem in broken:
        print(problem, file=sys.stderr)
    print(f'Markdown files: {len(files)}; relative targets checked: {checked}; broken: {len(broken)}')
    return 1 if broken else 0


if __name__ == '__main__':
    sys.exit(main())
