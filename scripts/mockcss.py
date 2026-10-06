"""Kullanım (proje kökünde): python scripts/mockcss.py  → ardından npm run check:fix

Tasarım sayfasının (public/_tasarim.html) stil kurallarını uygulamaya taşır.

- Kurallar `.mx` sarmalayıcısının altına alınır (Tailwind sınıflarıyla çakışmasın).
- `.s-a` (Gece Zümrüdü) → [data-theme='zumrut'] .mx, `.s-c` (Şafak) → [data-theme='safak'] .mx
- Tasarım sayfasının kendi kabuğu (.mock ızgara, .fx, .side, .brand, .nav, .me, .toast, .demo, .pal ...) atlanır.
- Animasyon adları mx- önekiyle (uygulamadakilerle çakışmasın).
"""
import re

SRC = r'C:\Projeler\marhal\public\_tasarim.html'
OUT = r'C:\Projeler\marhal\resources\css\marhal-mock.css'

html = open(SRC, encoding='utf-8').read()
lines = html.split('\n')
css = '\n'.join(lines[50:602])  # 51. satır (MOCK base) .. 602 (media) dahil

# yorumları at
css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)

SKIP = re.compile(r'^(\.mock(\s|$|[:,])|\.fx|\.side|\.toast|\.demo|\.overlay)')
ANIMS = ['pulse', 'pop', 'burst', 'drift', 'spin', 'sheen', 'palin', 'laser', 'fill', 'blink', 'fade']


def parse(text):
    """Üst düzey blokları (seçici, gövde) olarak döndürür."""
    out, i, n = [], 0, len(text)
    while i < n:
        j = text.find('{', i)
        if j == -1:
            break
        head = text[i:j].strip()
        depth, k = 1, j + 1
        while k < n and depth:
            if text[k] == '{':
                depth += 1
            elif text[k] == '}':
                depth -= 1
            k += 1
        out.append((head, text[j + 1:k - 1]))
        i = k
    return out


def scope_selector(sel):
    sel = sel.strip()
    if not sel:
        return None
    theme = None
    m = re.match(r'^\.s-a\b\s*', sel)
    if m:
        theme, sel = 'zumrut', sel[m.end():]
    m = re.match(r'^\.s-c\b\s*', sel)
    if m:
        theme, sel = 'safak', sel[m.end():]
    if sel == '' or sel.startswith('.mock'):
        # tema değişken bloğu (.s-a{--m-...}) veya kabuk
        sel = sel.replace('.mock', '').strip()
        if theme and sel == '':
            return f"[data-theme='{theme}'] .mx"
        if sel == '':
            return '.mx'
    if SKIP.match(sel):
        return None
    if sel.startswith(':root') or sel.startswith('body') or sel.startswith('*'):
        return None
    prefix = f"[data-theme='{theme}'] .mx " if theme else '.mx '
    return prefix + sel


def scope(head):
    parts = [scope_selector(s) for s in head.split(',')]
    parts = [p for p in parts if p]
    return ', '.join(parts) if parts else None


def rename_anims(body):
    for a in ANIMS:
        body = re.sub(rf'(animation[^;:]*:[^;]*?)\b{a}\b', rf'\1mx-{a}', body)
    return body


result = []
for head, body in parse(css):
    if head.startswith('@keyframes'):
        name = head.split()[1]
        result.append(f'@keyframes mx-{name}{{{body}}}')
    elif head.startswith('@property'):
        result.append(f'{head}{{{body}}}')
    elif head.startswith('@container') or head.startswith('@media'):
        if 'prefers-reduced-motion' in head:
            result.append("@media (prefers-reduced-motion: reduce){ .mx *, .mx *::before, .mx *::after{animation:none !important; transition:none !important} }")
            continue
        if 'max-width:760px' in head:
            continue  # tasarım sayfasının kabuğu için
        inner = []
        for h2, b2 in parse(body):
            s = scope(h2)
            if s:
                inner.append(f'  {s}{{{rename_anims(b2.strip())}}}')
        result.append(head + '{\n' + '\n'.join(inner) + '\n}')
    else:
        s = scope(head)
        if s:
            result.append(f'{s}{{{rename_anims(body.strip())}}}')

header = """/*
 * Tasarım sayfasının (claude.ai artifact UuhFHJG8dJ3nmbiUuDS1AS) ekran stilleri, birebir.
 * Üreten: scratchpad/mockcss.py — elle değiştirmeyin, tasarım değişirse yeniden üretin.
 * Ekranlar bu sınıfları `.mx` sarmalayıcısı içinde kullanır (components/mock/*). Renkler temadan:
 * Gece Zümrüdü ve Şafak değişkenleri tasarımdakiyle aynı.
 */
"""
open(OUT, 'w', encoding='utf-8').write(header + '\n'.join(result) + '\n')
print(len(result), 'blok')
