"""Turn raw images (AI-generated, stock, photos) into the exact files the site expects.

Usage:
    python bin/prepare-images.py SOURCE TARGET_NAME [SOURCE TARGET_NAME ...]

TARGET_NAME is a file name from docs/images-checklist.html, e.g. "section-mains"
or "grilled-octopus" (the extension is optional). The kind of image, the output
folder, the size and the weight budget are all derived from the name:

- cut-out plates  -> themes/olive-ember/assets/images/photos/, 1000 px square,
                     transparent background (a plain light background is removed).
- photos          -> themes/olive-ember/assets/images/photos/, cropped to the
                     expected ratio, 1600 px long side, <= 250 KB.
- dish photos     -> plugins/oe-reservations/assets/seed/, 800 px square,
                     transparent background, <= 60 KB.

Requires Pillow and NumPy (pip install pillow numpy).
"""

import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parent.parent
THEME_DIR = ROOT / "themes/olive-ember/assets/images/photos"
SEED_DIR = ROOT / "plugins/oe-reservations/assets/seed"

CUTOUTS = {
    "hero-plate", "section-starters", "section-mains", "section-desserts",
    "section-wines", "cta-left", "cta-right", "chef-cutout", "menu-plate",
    "empty-plate",
}
PHOTOS = {"chef": (3, 4), "fire": (4, 5), "market": (5, 4), "room": (21, 9)}
# A tighter background threshold for subjects with large white areas touching
# the edge of the frame (a chef's white jacket would otherwise be eaten away).
TOLERANCE = {"chef-cutout": 3}


def background_mask(img, tolerance=8):
    """Return an "L" mask, 0 for the light background connected to the borders.

    The lightness threshold adapts to the border pixels, so it handles both a plain
    white background and a fake grey checkerboard. Only pixels connected to the
    edge are removed, so a white plate rim survives as long as a faint shadow
    separates it from the background.
    """
    rgb = np.asarray(img.convert("RGB")).astype(np.int16)
    low, high = rgb.min(axis=2), rgb.max(axis=2)
    border = np.concatenate((low[0], low[-1], low[:, 0], low[:, -1]))
    floor = max(200, int(np.percentile(border, 2)) - tolerance)
    candidate = (low >= floor) & (high - low <= tolerance)

    # Flood-fill the candidates reachable from the borders (value 128) in C.
    work = Image.fromarray(np.where(candidate, 255, 0).astype(np.uint8)).copy()
    h, w = candidate.shape
    seeds = [(x, y) for x in range(w) for y in (0, h - 1)]
    seeds += [(x, y) for y in range(h) for x in (0, w - 1)]
    for xy in seeds:
        if work.getpixel(xy) == 255:
            ImageDraw.floodfill(work, xy, 128)
    return Image.fromarray(np.where(np.asarray(work) == 128, 0, 255).astype(np.uint8))


def plate_circle(mask, size=256):
    """Find the round plate in a subject mask, ignoring the cast shadow around it.

    The flood fill keeps the plate's cast shadow, which sticks out on one side.
    The outer outline is sampled once per degree, then a RANSAC circle fit keeps
    the circle that most outline points lie on: the shadow covers less than half
    the outline, so it is rejected. Returns (cx, cy, r) in mask pixels, or None
    when the subject is not round (a bottle and a glass, a person).
    """
    solid = np.asarray(mask.resize((size, size), Image.BILINEAR)) > 127
    ys, xs = np.nonzero(solid)
    if len(xs) < 100:
        return None

    # Farthest subject pixel in each 1-degree sector around the centroid.
    dist = np.hypot(xs - xs.mean(), ys - ys.mean())
    sector = (np.degrees(np.arctan2(ys - ys.mean(), xs - xs.mean())) + 180).astype(int) % 360
    order = np.argsort(dist)
    px, py = np.zeros(360), np.zeros(360)
    found = np.zeros(360, bool)
    px[sector[order]] = xs[order]
    py[sector[order]] = ys[order]
    found[sector] = True
    px, py = px[found], py[found]

    def circle_through(x, y):
        a = np.column_stack((x, y, np.ones(len(x))))
        (d, e, f), *_ = np.linalg.lstsq(a, x ** 2 + y ** 2, rcond=None)
        cx, cy = d / 2, e / 2
        return cx, cy, np.sqrt(max(f + cx ** 2 + cy ** 2, 0))

    rng = np.random.default_rng(0)
    best = None
    for _ in range(400):
        pick = rng.choice(len(px), 3, replace=False)
        cx, cy, r = circle_through(px[pick], py[pick])
        if not size * 0.25 < r < size * 0.6:
            continue
        inliers = np.abs(np.hypot(px - cx, py - cy) - r) < max(1.5, r * 0.012)
        if best is None or inliers.sum() > best.sum():
            best = inliers
    if best is None or best.mean() < 0.5:
        return None
    cx, cy, r = circle_through(px[best], py[best])
    scale = mask.width / size
    return cx * scale, cy * scale, r * scale + 1


def remove_background(img, tolerance=8):
    """Make the background transparent; round plates also lose their cast shadow."""
    img = img.convert("RGBA")
    if img.getextrema()[3][0] < 250:
        return img  # Already has real transparency.
    mask = background_mask(img, tolerance)
    circle = plate_circle(mask)
    if circle:
        cx, cy, r = circle
        disc = Image.new("L", img.size, 0)
        ImageDraw.Draw(disc).ellipse((cx - r, cy - r, cx + r, cy + r), fill=255)
        mask = Image.fromarray(np.minimum(np.asarray(mask), np.asarray(disc)))
    # Soften the edge by a pixel so the cut-out does not look jagged.
    mask = mask.filter(ImageFilter.MinFilter(3)).filter(ImageFilter.GaussianBlur(1))
    img.putalpha(mask)
    return img


def square_cutout(img, size, margin=0.03, tolerance=8):
    """Trim to the visible subject, centre it on a transparent square, resize."""
    img = remove_background(img, tolerance)
    bbox = img.getchannel("A").point(lambda a: 255 if a > 8 else 0).getbbox()
    if bbox:
        img = img.crop(bbox)
    side = int(max(img.size) * (1 + margin * 2))
    canvas = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    canvas.paste(img, ((side - img.width) // 2, (side - img.height) // 2), img)
    return canvas.resize((size, size), Image.LANCZOS)


def crop_ratio(img, ratio, long_side):
    """Centre-crop to the ratio, then scale so the long side is long_side px."""
    img = img.convert("RGB")
    rw, rh = ratio
    w, h = img.size
    if w / h > rw / rh:
        nw = int(h * rw / rh)
        img = img.crop(((w - nw) // 2, 0, (w - nw) // 2 + nw, h))
    else:
        nh = int(w * rh / rw)
        img = img.crop((0, (h - nh) // 2, w, (h - nh) // 2 + nh))
    scale = long_side / max(img.size)
    if scale < 1:
        img = img.resize((round(img.width * scale), round(img.height * scale)), Image.LANCZOS)
    return img


def save_webp(img, path, max_kb):
    """Save as WebP, lowering quality until the file fits the budget."""
    for quality in range(90, 30, -5):
        img.save(path, "WEBP", quality=quality, method=6)
        if path.stat().st_size <= max_kb * 1024:
            break
    return quality


def main(args):
    if not args or len(args) % 2:
        sys.exit(__doc__)
    for src, name in zip(args[::2], args[1::2]):
        name = Path(name).stem
        img = Image.open(src)
        if name in CUTOUTS:
            out, max_kb = THEME_DIR / f"{name}.webp", 200
            img = square_cutout(img, 1000, tolerance=TOLERANCE.get(name, 8))
        elif name in PHOTOS:
            out, max_kb = THEME_DIR / f"{name}.webp", 250
            img = crop_ratio(img, PHOTOS[name], 1600)
        else:
            out, max_kb = SEED_DIR / f"{name}.webp", 60
            img = square_cutout(img, 800)
        quality = save_webp(img, out, max_kb)
        size = out.stat().st_size / 1024
        print(f"{out.relative_to(ROOT)}  {img.width}x{img.height}  q{quality}  {size:.0f} KB")


if __name__ == "__main__":
    main(sys.argv[1:])
