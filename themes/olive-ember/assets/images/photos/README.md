# Photo slots

Drop images here with these exact names (`.webp` preferred, `.png` or `.jpg` also work).
The theme picks them up automatically through `olive_ember_image()`; until a file exists,
a neutral placeholder is shown instead.

## Cut-out plates (transparent background, square, ~1000 × 1000 px, seen from above)

| Slot               | Where                                   |
|--------------------|-----------------------------------------|
| `hero-plate`       | Home hero, inside the dashed ring        |
| `section-starters` | Home, "menu sections" strip              |
| `section-mains`    | Home, "menu sections" strip              |
| `section-desserts` | Home, "menu sections" strip              |
| `section-wines`    | Home, "menu sections" strip (bottle/glass) |
| `cta-left`         | Booking call to action, left edge        |
| `cta-right`        | Booking call to action, right edge       |
| `chef-cutout`      | Guest book, chef inside the saffron circle (portrait, transparent) |
| `menu-plate`       | Menu page header                         |
| `empty-plate`      | 404 page                                 |

## Photos (~1600 px on the long side, compressed WebP ≤ 250 KB)

| Slot     | Ratio | Where                                  |
|----------|-------|----------------------------------------|
| `chef`   | 3:4   | Home, "The kitchen"                    |
| `fire`   | 4:5   | Home, "The kitchen" (offset photo)     |
| `market` | 4:5   | Home, "Produce & suppliers"            |
| `room`   | 21:9  | Contact page, top                      |

Menu dishes are not here: see `plugins/oe-reservations/assets/seed/README.md`.
