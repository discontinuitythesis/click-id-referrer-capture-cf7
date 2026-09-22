import os, re, collections
root = os.getcwd()
dom = 'click-id-referrer-capture-cf7'
fn = r'(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|esc_html_x|esc_attr_x|__|_e|_x|_ex)'
pat = re.compile(fn + r"\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'" + re.escape(dom) + r"'\s*\)")
entries = collections.OrderedDict()
files = []
for dp, dn, files_ in os.walk(root):
    dn[:] = [d for d in dn if d not in ('test','dist','node_modules')]
    for f in sorted(files_):
        if f.endswith('.php'):
            files.append(os.path.join(dp, f))
for path in sorted(files):
    rel = os.path.relpath(path, root)
    lines = open(path, encoding='utf-8').read().split('\n')
    for i, line in enumerate(lines):
        for m in pat.finditer(line):
            msgid = m.group(1)
            comment = ''
            prev = lines[i-1].strip() if i > 0 else ''
            cm = re.search(r'/\*\s*(translators:.*?)\s*\*/', prev, re.I)
            if not cm:
                cm = re.search(r'/\*\s*(translators:.*?)\s*\*/', line, re.I)
            if cm:
                comment = cm.group(1)
            e = entries.setdefault(msgid, {'refs': [], 'comment': ''})
            e['refs'].append('%s:%d' % (rel.replace(os.sep,'/'), i+1))
            if comment and not e['comment']:
                e['comment'] = comment

def esc(s):
    return s.replace('\\\'', "'").replace('\\', '\\\\').replace('"', '\\"')

out = []
out.append('# Copyright (C) 2026 Fire Pixel (Ben Luong)')
out.append('# This file is distributed under the GPLv2 or later.')
out.append('msgid ""')
out.append('msgstr ""')
out.append('"Project-Id-Version: Click ID & Referrer Capture for Contact Form 7 1.0.0\\n"')
out.append('"Report-Msgid-Bugs-To: https://firepixel.co.uk/wordpress-click-id-capture\\n"')
out.append('"POT-Creation-Date: 2026-09-22T00:00:00+00:00\\n"')
out.append('"MIME-Version: 1.0\\n"')
out.append('"Content-Type: text/plain; charset=UTF-8\\n"')
out.append('"Content-Transfer-Encoding: 8bit\\n"')
out.append('"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"')
out.append('"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"')
out.append('"Language-Team: LANGUAGE <LL@li.org>\\n"')
out.append('"Plural-Forms: nplurals=2; plural=(n != 1);\\n"')
out.append('"X-Domain: %s\\n"' % dom)
out.append('')
for msgid, e in entries.items():
    for r in e['refs']:
        out.append('#: %s' % r)
    if e['comment']:
        out.append('#. %s' % e['comment'])
    out.append('msgid "%s"' % esc(msgid))
    out.append('msgstr ""')
    out.append('')
open(os.path.join(root, 'languages', dom + '.pot'), 'w', encoding='utf-8').write('\n'.join(out))
print('entries:', len(entries))
