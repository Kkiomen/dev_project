# Plan: Wspólne właściwości elementów (wzór Creatomate)

## Context
Creatomate oferuje bogaty zestaw właściwości wspólnych dla wszystkich typów elementów. Porównanie z naszym edytorem NLE pozwala zidentyfikować brakujące funkcje.

## Właściwości wspólne w Creatomate

### Pozycjonowanie i layout
- `track` — warstwa/porządek
- `time`, `duration` — timing na timeline
- `x`, `y` — pozycja
- `width`, `height` — wymiary
- `aspect_ratio` — proporcje
- `x_padding`, `y_padding` — padding

### Transformacje 2D + 3D
- `x_anchor`, `y_anchor` — punkt transformacji
- `x_scale`, `y_scale` — skalowanie
- `x_skew`, `y_skew` — pochylenie
- `x_rotation`, `y_rotation`, `z_rotation` — rotacja 3D
- `perspective` — perspektywa 3D
- `backface_visible` — widoczność tylnej strony

### Fill (wypełnienie)
- `fill_color` — kolor solid + gradient
- `fill_mode` — tryb wypełnienia
- `fill_x0`, `fill_y0`, `fill_x1`, `fill_y1` — punkty gradientu
- `fill_radius` — radius gradientu radialnego

### Stroke (obrys)
- `stroke_color`, `stroke_width`
- `stroke_cap` (butt/round/square), `stroke_join` (miter/round/bevel)
- `stroke_start`, `stroke_end`, `stroke_offset` — animowalne stroke drawing

### Shadow
- `shadow_color`, `shadow_blur`, `shadow_x`, `shadow_y`

### Efekty wizualne
- `opacity` — przezroczystość (0-1)
- `blend_mode` — 16 trybów: none, multiply, screen, overlay, darken, lighten, color-dodge, color-burn, hard-light, soft-light, lighter, difference, exclusion, hue, saturation, color, luminosity
- `color_filter` — 7 filtrów: none, brighten, contrast, hue, invert, grayscale, sepia
- `color_filter_value` — wartość filtra
- `color_overlay` — nakładka kolorowa
- `blur_radius` + `blur_mode` — rozmycie
- `border_radius` — zaokrąglenie rogów

### Masking i clipping
- `mask_mode` — maskowanie jednego elementu drugim
- `clip` — przycinanie zawartości do granic elementu

### Zaawansowane
- `repeat` — powtarzanie wzorca
- `warp_mode` + `warp_matrix` — warping/deformacja elementu
- `z_index` — kolejność renderowania
- `visible`, `locked` — widoczność i blokada edycji

## Co mamy w naszym edytorze
- x, y, width, height, rotation, opacity, fit — podstawy ✅
- time, duration, trim_start, trim_end — ✅
- volume, fade_in, fade_out — ✅
- effects (array) — placeholder, nie zaimplementowane

## Czego brakuje (priorytet)
1. **Skalowanie** (x_scale, y_scale) — ważne dla animacji
2. **Shadow** — bardzo popularne w social media video
3. **Blend modes** — overlay, multiply itp.
4. **Border radius** — zaokrąglone rogi na video/image
5. **Color filters** — grayscale, sepia, brighten, contrast
6. **Blur** — blur na elementach
7. **Masking** — maskowanie elementem
8. **3D rotation** — efektowne przejścia
9. **Gradient fill** — na shapes i tekście
10. **Stroke animowany** — stroke drawing effect

## Źródła
- https://creatomate.com/docs/api/render-script/element-properties
