# Plan: Text element (wzór Creatomate)

## Context
Creatomate ma rozbudowany text element z auto-sizing, auto-transkrypcją i bogatymi opcjami stylizacji.

## Text element — Creatomate

### Podstawowe
- `text` — treść tekstowa
- `font_family` — tysiące Google Fonts + custom upload (WOF, OTF, TTF)
- `font_weight` — grubość (100-900), default: 400
- `font_style` — normal / italic
- `font_size` — stały lub null dla auto-sizing
- `font_size_minimum` — minimalna wielkość przy auto-sizing
- `font_size_maximum` — maksymalna wielkość przy auto-sizing

### Spacing i layout
- `letter_spacing` — spacing liter (% font size)
- `line_height` — wysokość linii (%)
- `text_wrap` — zawijanie tekstu (true/false)
- `text_clip` — przycinanie tekstu na granicy elementu
- `text_transform` — uppercase / lowercase / capitalize
- `x_alignment` — wyrównanie poziome (0%=left, 50%=center, 100%=right)
- `y_alignment` — wyrównanie pionowe (0%=top, 50%=middle, 100%=bottom)

### Tło tekstu (highlight)
- `background_color` — kolor tła za tekstem
- `background_x_padding`, `background_y_padding` — padding tła
- `background_border_radius` — zaokrąglenie tła
- `background_align_threshold` — próg wyrównania dla wieloliniowego tekstu

### Auto-transkrypcja (napisy/subtitles)
- `transcript_source` — link do video (źródło audio)
- `transcript_effect` — efekt wizualny (karaoke-style highlighting)
- `transcript_split` — podział: word / line
- `transcript_placement` — static / animated
- `transcript_maximum_length` — max znaków na raz
- `transcript_color` — kolor podświetlenia aktywnego słowa

## Co mamy w naszym edytorze
- text, font_size, font_family, color ✅
- text_align (center/left/right) ✅
- font_weight (bold) ✅
- stroke_color, stroke_width ✅

## Czego brakuje (priorytet)
1. **Auto-sizing tekstu** (font_size: null z min/max) — bardzo przydatne
2. **Letter spacing, line height** — typografia
3. **Text background/highlight** — popularne w social media
4. **Text transform** (uppercase) — proste ale przydatne
5. **Font upload** (custom fonts) — WOF/OTF/TTF
6. **Auto-transkrypcja** — automatyczne napisy z video
7. **Transcript effects** — karaoke-style word highlighting
8. **Italic / font_style** — brakuje
9. **Text wrap control** — wrap/no-wrap
10. **Vertical alignment** — top/middle/bottom w ramce

## Źródła
- https://creatomate.com/docs/api/render-script/text-element
