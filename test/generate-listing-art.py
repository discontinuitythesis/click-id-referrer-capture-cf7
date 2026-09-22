from PIL import Image, ImageDraw, ImageFont
import os

OUT = os.getcwd() + '/assets'
ORANGE = (228, 87, 46)
ORANGE_DARK = (196, 68, 32)
WHITE = (255, 255, 255)
INK = (26, 26, 26)
S = 4  # supersample factor

SANS_B = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
SANS = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'

def font(path, size):
    return ImageFont.truetype(path, size)

ARROW = [
    (0.30, 0.10), (0.30, 0.82), (0.455, 0.665), (0.565, 0.90),
    (0.685, 0.845), (0.575, 0.615), (0.78, 0.585),
]

def draw_mark(d, x, y, size, colour=WHITE, trail=True):
    """Original mark: a pointer arrow with three motion dashes, meaning a tracked click."""
    pts = [(x + px * size, y + py * size) for px, py in ARROW]
    d.polygon(pts, fill=colour)
    if not trail:
        return
    w = max(2, int(size * 0.045))
    for i, (ax, ay, bx, by) in enumerate([
        (0.02, 0.30, 0.15, 0.30),
        (0.05, 0.15, 0.16, 0.22),
        (0.17, 0.03, 0.22, 0.14),
    ]):
        d.line([(x + ax * size, y + ay * size), (x + bx * size, y + by * size)], fill=colour, width=w)

def new(w, h, bg):
    img = Image.new('RGB', (w * S, h * S), bg)
    return img, ImageDraw.Draw(img)

def finish(img, w, h, path):
    img.resize((w, h), Image.LANCZOS).save(path, 'PNG', optimize=True)
    print('wrote', os.path.relpath(path, os.getcwd()))

def icon(size, path):
    img, d = new(size, size, ORANGE)
    n = size * S
    # soft diagonal band for depth
    d.polygon([(0, n), (n, 0), (n, n * 0.42), (n * 0.42, n)], fill=ORANGE_DARK)
    draw_mark(d, n * 0.22, n * 0.18, n * 0.60)
    finish(img, size, size, path)

def banner(w, h, path, big):
    img, d = new(w, h, ORANGE)
    W, H = w * S, h * S
    d.polygon([(W * 0.60, 0), (W, 0), (W, H), (W * 0.82, H)], fill=ORANGE_DARK)
    mark = H * 0.52
    draw_mark(d, W * 0.045, (H - mark) / 2, mark)
    tx = W * 0.045 + mark + W * 0.035
    if big:
        f1, f2, f3 = font(SANS_B, 68 * S), font(SANS_B, 68 * S), font(SANS, 30 * S)
        d.text((tx, H * 0.24), 'Click ID & Referrer', font=f1, fill=WHITE, anchor='ls')
        d.text((tx, H * 0.50), 'Capture for Contact Form 7', font=f2, fill=WHITE, anchor='ls')
        d.text((tx, H * 0.70), 'gclid  gbraid  wbraid  msclkid  UTM  referrer', font=f3, fill=WHITE, anchor='ls')
        d.text((tx, H * 0.82), 'Free. No account. Offline conversion CSV export.', font=f3, fill=WHITE, anchor='ls')
    else:
        f1, f2 = font(SANS_B, 40 * S), font(SANS, 19 * S)
        d.text((tx, H * 0.34), 'Click ID & Referrer Capture', font=f1, fill=WHITE, anchor='ls')
        d.text((tx, H * 0.56), 'for Contact Form 7', font=f1, fill=WHITE, anchor='ls')
        d.text((tx, H * 0.76), 'gclid  gbraid  wbraid  msclkid  UTM', font=f2, fill=WHITE, anchor='ls')
    finish(img, w, h, path)

CAPTIONS = [
    'Settings: storage mode, cookie lifetime, consent cookie',
    'Offline conversion defaults and per form overrides',
    'Submissions log with date and form filters',
    'CSV exports for Google Ads and Microsoft Advertising',
    'Attribution summary inside the notification email',
]

def screenshot(i, caption, path):
    w, h = 1200, 900
    img, d = new(w, h, (245, 245, 246))
    W, H = w * S, h * S
    d.rectangle([(0, 0), (W, H * 0.12)], fill=ORANGE)
    draw_mark(d, W * 0.022, H * 0.022, H * 0.076, trail=False)
    d.text((W * 0.09, H * 0.075), 'Click ID & Referrer Capture for Contact Form 7',
           font=font(SANS_B, 26 * S), fill=WHITE, anchor='ls')
    d.rectangle([(W * 0.06, H * 0.22), (W * 0.94, H * 0.80)], fill=WHITE, outline=(200, 200, 204), width=2 * S)
    d.text((W / 2, H * 0.44), 'PLACEHOLDER', font=font(SANS_B, 76 * S), fill=ORANGE, anchor='mm')
    d.text((W / 2, H * 0.55), 'Screenshot %d' % i, font=font(SANS_B, 30 * S), fill=INK, anchor='mm')
    d.text((W / 2, H * 0.62), caption, font=font(SANS, 24 * S), fill=(90, 90, 96), anchor='mm')
    d.text((W / 2, H * 0.90), 'firepixel.co.uk', font=font(SANS, 22 * S), fill=(120, 120, 126), anchor='mm')
    finish(img, w, h, path)

icon(256, OUT + '/icon-256x256.png')
icon(128, OUT + '/icon-128x128.png')
banner(1544, 500, OUT + '/banner-1544x500.png', True)
banner(772, 250, OUT + '/banner-772x250.png', False)
for i, c in enumerate(CAPTIONS, 1):
    screenshot(i, c, OUT + '/screenshot-%d.png' % i)
