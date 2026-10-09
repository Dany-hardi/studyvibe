"""StudyVibe brand build (v3: rounded wordmark, check-mark V, spark dot).

Traces the designer's artwork (the high-resolution images inside "StudyVibe Logo.pdf") into vector paths,
recolours the V ("vibe") from the original violet to the terracotta accent, and writes:
  assets/img/*.svg  lib/BrandData.php  (PNGs are rendered afterwards by scripts/brand/render.js)

Usage: python3 scripts/brand/build.py <dir containing im-000.png (wordmark) and im-002.png (icon + dark lockup)>
       (extract them with: pdfimages -png "StudyVibe Logo.pdf" <dir>/im)
"""
import sys, os, json
import cv2
import numpy as np

SRC = sys.argv[1]
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
IMG = os.path.join(ROOT, 'assets', 'img') + '/'

INK, INK_DARK = '#14151C', '#F4F1EA'
CLAY, CLAY_DARK = '#B5482A', '#E27B57'      # terracotta: replaces the designer's violet
SPARK, SPARK_ICON = '#FF6A3D', '#FFD27A'    # i-dot (the icon dot is lighter so it reads on terracotta)
SCALE = 0.25                                 # source px -> path units (wordmark ends up ~740 units wide)
CSIGMA = 9.0                                 # contour smoothing in source px
STEP = 22.0                                  # resampling distance in source px

REF = {'ink': (20, 21, 28), 'vibe': (91, 61, 245), 'spark': (255, 106, 61)}   # RGB of the artwork


def membership(img_bgr, rgb):
    """0..1 coverage of a colour over white, per pixel (anti-aliased edges give fractional values)."""
    p = img_bgr[..., ::-1].astype(np.float32)
    d = 255.0 - p
    c = 255.0 - np.array(rgb, np.float32)
    s = (d @ c) / float(c @ c)
    # reject pixels that point in another colour direction
    perp = np.linalg.norm(d - s[..., None] * c, axis=-1)
    return np.clip(s, 0, 1) * (perp < 70)


def class_masks(img):
    p = img[..., ::-1].astype(np.float32)
    d = 255.0 - p
    out = {}
    score = {}
    for k, rgb in REF.items():
        c = 255.0 - np.array(rgb, np.float32)
        score[k] = (d @ c) / (np.linalg.norm(d, axis=-1) * np.linalg.norm(c) + 1e-6)
    stack = np.stack([score[k] for k in REF], -1)
    best = stack.argmax(-1)
    cov = np.linalg.norm(d, axis=-1) / 255.0
    for i, k in enumerate(REF):
        out[k] = ((best == i) & (cov > 0.5 * np.linalg.norm(255.0 - np.array(REF[k], np.float32)) / 255.0))
    return out


def smooth(mask):
    m = cv2.GaussianBlur(mask.astype(np.float32), (0, 0), 5.0)
    return (m > 0.5).astype(np.uint8)


def smooth_contour(pts, sigma):
    """Circular Gaussian filter along a closed contour: removes pixel noise, keeps the shape."""
    n = len(pts)
    k = int(sigma * 3)
    w = np.exp(-0.5 * (np.arange(-k, k + 1) / sigma) ** 2)
    w /= w.sum()
    ext = np.r_[pts[-k:], pts, pts[:k]]
    return np.stack([np.convolve(ext[:, i], w, mode='valid') for i in range(2)], 1)


def resample(cnt, step):
    pts = smooth_contour(cnt[:, 0, :].astype(np.float64), CSIGMA)
    seg = np.r_[pts[1:] - pts[:-1], (pts[0] - pts[-1])[None]]
    ln = np.hypot(seg[:, 0], seg[:, 1])
    total = ln.sum()
    n = max(8, int(round(total / step)))
    cum = np.r_[0, np.cumsum(ln)]
    t = np.linspace(0, total, n, endpoint=False)
    closed = np.r_[pts, pts[:1]]
    x = np.interp(t, cum, closed[:, 0])
    y = np.interp(t, cum, closed[:, 1])
    return np.stack([x, y], 1)


def path_from(points, ox, oy):
    P = (points - np.array([ox, oy])) * SCALE
    n = len(P)
    f = lambda v: ('%.1f' % v).rstrip('0').rstrip('.')
    d = f'M{f(P[0][0])} {f(P[0][1])}'
    for i in range(n):
        p0, p1, p2, p3 = P[(i - 1) % n], P[i], P[(i + 1) % n], P[(i + 2) % n]
        c1 = p1 + (p2 - p0) / 6.0
        c2 = p2 - (p3 - p1) / 6.0
        d += f'C{f(c1[0])} {f(c1[1])} {f(c2[0])} {f(c2[1])} {f(p2[0])} {f(p2[1])}'
    return d + 'Z'


def shapes(mask, min_area=400):
    """List of (path-points list [outer, holes...], bbox) for each connected piece, left to right."""
    m = smooth(mask)
    cnts, hier = cv2.findContours(m, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_NONE)
    out = []
    for i, c in enumerate(cnts):
        if hier[0][i][3] != -1 or cv2.contourArea(c) < min_area:
            continue
        holes = [cnts[j] for j in range(len(cnts)) if hier[0][j][3] == i and cv2.contourArea(cnts[j]) > 60]
        out.append(dict(outer=c, holes=holes, bbox=cv2.boundingRect(c)))
    out.sort(key=lambda s: s['bbox'][0])
    return out


def combine(shape, ox, oy):
    return ' '.join(path_from(resample(c, STEP), ox, oy) for c in [shape['outer']] + shape['holes'])


# ---------------------------------------------------------------- wordmark (page 1 of the PDF)
wm = cv2.imread(os.path.join(SRC, 'im-000.png'))
H = 1200   # wordmark lives above the colour legend
wm[H:] = 255
cm = class_masks(wm)
ink = shapes(cm['ink'])
vibe = shapes(cm['vibe'])
spark = shapes(cm['spark'], 200)
print('pieces: ink', len(ink), 'vibe', len(vibe), 'spark', len(spark))
assert len(ink) == 5 and len(vibe) == 4 and len(spark) == 1, 'unexpected number of letters; check the artwork'

allb = [s['bbox'] for s in ink + vibe + spark]
x0 = min(b[0] for b in allb); y0 = min(b[1] for b in allb)
x1 = max(b[0] + b[2] for b in allb); y1 = max(b[1] + b[3] for b in allb)
PAD = 40
ox, oy = x0 - PAD, y0 - PAD
VB = [0, 0, round((x1 - x0 + 2 * PAD) * SCALE), round((y1 - y0 + 2 * PAD) * SCALE)]

letters = []
for ch, s in zip('study', ink):
    letters.append(dict(ch=ch, k='i', d=combine(s, ox, oy), x0=round((s['bbox'][0] - ox) * SCALE), x1=round((s['bbox'][0] + s['bbox'][2] - ox) * SCALE)))
for ch, s in zip('vibe', vibe):
    letters.append(dict(ch=ch, k='c', d=combine(s, ox, oy), x0=round((s['bbox'][0] - ox) * SCALE), x1=round((s['bbox'][0] + s['bbox'][2] - ox) * SCALE)))
s = spark[0]
letters.append(dict(ch='.', k='s', d=combine(s, ox, oy), x0=round((s['bbox'][0] - ox) * SCALE), x1=round((s['bbox'][0] + s['bbox'][2] - ox) * SCALE)))
# order for stacking/animation: letters left to right, spark last (it sits on top of the i)


def wm_svg(ink_c, clay_c, spark_c, mono=False):
    body = '<title>StudyVibe</title>'
    for l in letters:
        c = ink_c if (mono or l['k'] == 'i') else (clay_c if l['k'] == 'c' else spark_c)
        body += f'<path d="{l["d"]}" fill="{c}" fill-rule="evenodd"/>'
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{VB[0]} {VB[1]} {VB[2]} {VB[3]}" role="img" aria-label="StudyVibe">{body}</svg>\n'


open(IMG + 'logo-wordmark.svg', 'w').write(wm_svg(INK, CLAY, SPARK))
open(IMG + 'logo-wordmark-dark.svg', 'w').write(wm_svg(INK_DARK, CLAY_DARK, SPARK))
open(IMG + 'logo-mono.svg', 'w').write(wm_svg(INK, INK, INK, mono=True))
open(IMG + 'logo-wordmark-white.svg', 'w').write(wm_svg('#fff', '#fff', '#fff', mono=True))

# ---------------------------------------------------------------- app icon (page 3 of the PDF, left panel)
ic = cv2.imread(os.path.join(SRC, 'im-002.png'))
ic = ic[:, :3500].copy()
icm = class_masks(ic)
sq = shapes(icm['vibe'], 200000)
assert sq, 'icon square not found'
sq = max(sq, key=lambda s: cv2.contourArea(s['outer']))
bx, by, bw, bh = sq['bbox']
# the icon is drawn on a light-grey panel; the square mask has the check and the dot as holes
holes = sorted(sq['holes'], key=cv2.contourArea, reverse=True)
check_c, dot_c = holes[0], holes[-1]
ISC = 512.0 / max(bw, bh)
sc = lambda pts: (pts - np.array([bx, by])) * ISC


def icon_path(cnt, step):
    P = sc(resample(cnt, step))
    n = len(P)
    f = lambda v: ('%.1f' % v).rstrip('0').rstrip('.')
    d = f'M{f(P[0][0])} {f(P[0][1])}'
    for i in range(n):
        p0, p1, p2, p3 = P[(i - 1) % n], P[i], P[(i + 1) % n], P[(i + 2) % n]
        c1 = p1 + (p2 - p0) / 6.0
        c2 = p2 - (p3 - p1) / 6.0
        d += f'C{f(c1[0])} {f(c1[1])} {f(c2[0])} {f(c2[1])} {f(p2[0])} {f(p2[1])}'
    return d + 'Z'


# a rounded square is simpler and cleaner than a traced one: measure the corner radius from the artwork
rad = 0.235 * 512   # squircle-ish corner measured from the artwork (~0.235 of the side)
sqd = f'M{rad:.1f} 0H{512 - rad:.1f}A{rad:.1f} {rad:.1f} 0 0 1 512 {rad:.1f}V{512 - rad:.1f}A{rad:.1f} {rad:.1f} 0 0 1 {512 - rad:.1f} 512H{rad:.1f}A{rad:.1f} {rad:.1f} 0 0 1 0 {512 - rad:.1f}V{rad:.1f}A{rad:.1f} {rad:.1f} 0 0 1 {rad:.1f} 0Z'
checkd = icon_path(check_c, 14)
dotd = icon_path(dot_c, 14)
MARK = dict(vb=[0, 0, 512, 512], sq=sqd, check=checkd, dot=dotd)


def mark_svg(bg, fg='#fff', dot=SPARK_ICON, extra=''):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">{extra}<path class="bg" d="{sqd}" fill="{bg}"/>'
            f'<path class="ck" d="{checkd}" fill="{fg}"/><path class="dt" d="{dotd}" fill="{dot}"/></svg>\n')


open(IMG + 'logo-mark.svg', 'w').write(mark_svg(CLAY))
dark_css = f'<style>@media (prefers-color-scheme:dark){{.bg{{fill:{CLAY_DARK}}}.ck{{fill:#1A0F09}}}}</style>'
open(IMG + 'favicon.svg', 'w').write(mark_svg(CLAY, extra=dark_css))

# ---------------------------------------------------------------- PHP data
def php(v):
    if isinstance(v, dict):
        return 'array(' + ','.join(f"'{k}'=>{php(x)}" for k, x in v.items()) + ')'
    if isinstance(v, (list, tuple)):
        return '[' + ','.join(php(x) for x in v) + ']'
    if isinstance(v, str):
        return "'" + v.replace('\\', '\\\\').replace("'", "\\'") + "'"
    return repr(v)


data = dict(vb=VB, letters=letters, mark=MARK)
open(os.path.join(ROOT, 'lib', 'BrandData.php'), 'w').write(
    "<?php\n// GENERATED by scripts/brand/build.py from the designer's artwork. Do not edit by hand.\nreturn " + php(data) + ";\n")
print('viewBox', VB, 'letters', [(l['ch'], l['k'], l['x0'], l['x1']) for l in letters])
print('bytes', {n: os.path.getsize(IMG + n) for n in ['logo-wordmark.svg', 'logo-mark.svg']})
