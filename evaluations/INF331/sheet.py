import sys, glob, os
from PIL import Image, ImageDraw
names = sys.argv[2:]
out = sys.argv[1]
W = 760
ims = []
for n in names:
    im = Image.open(f'images/{n}.png').convert('RGB')
    r = W / im.width
    im = im.resize((W, int(im.height * r)))
    d = ImageDraw.Draw(im); d.text((4,2), n, fill=(180,0,0))
    ims.append(im)
cols = 2
rows = [ims[i:i+cols] for i in range(0, len(ims), cols)]
H = sum(max(i.height for i in r) + 8 for r in rows)
sheet = Image.new('RGB', (cols*W + 8, H), 'white')
y = 0
for r in rows:
    x = 0
    for im in r:
        sheet.paste(im, (x, y)); x += W + 8
    y += max(i.height for i in r) + 8
sheet.save(out)
print(sheet.size)
