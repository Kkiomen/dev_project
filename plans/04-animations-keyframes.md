# Plan: Animacje i keyframe'y (wzór Creatomate)

## Context
Creatomate pozwala animować prawie każdą właściwość elementu za pomocą keyframe'ów. Oferuje też predefiniowane animacje.

## Keyframe'y w Creatomate

### Zasada
- **Prawie każda właściwość** może być animowana keyframe'ami
- Keyframe'y dodaje się klikając "+" przy właściwości
- Keyframe może być powiązany z **początkiem lub końcem** elementu
- Parametr `reversed` — zamienia animację wejścia na wyjścia
- Wspierane **easing functions** (linear, ease-in, ease-out, ease-in-out, etc.)

### Co można animować (przykłady)
- position (x, y)
- scale (x_scale, y_scale)
- rotation (x/y/z_rotation)
- opacity
- fill_color (kolor)
- stroke_start / stroke_end (rysowanie linii)
- blur_radius
- volume (głośność audio)
- width, height
- border_radius
- shadow properties
- letter_spacing

## Predefiniowane animacje

### Ogólne
- **fade** — pojawianie/znikanie (opacity)
- **slide** — wjazd/wyjazd z krawędzi
- **scale** — powiększanie/zmniejszanie
- **bounce** — efekt odbicia

### Tekstowe
- **text-slide** — animacja per litera/słowo (slide)
- **text-fly** — animacja per litera/słowo (fly)

### Łączenie
- Animacje można **łączyć** — np. fade + slide + scale jednocześnie
- Dziesiątki efektów do kombinacji

## Co mamy w naszym edytorze
- fade_in, fade_out na audio — ✅ (ale tylko audio volume)
- effects array — placeholder, nie zaimplementowane
- Brak systemu keyframe'ów
- Brak predefiniowanych animacji

## Czego brakuje (priorytet)
1. **System keyframe'ów** — fundamentalna funkcja edytora
   - UI: dodawanie keyframe'ów na timeline per-property
   - Engine: interpolacja wartości między keyframe'ami
   - Easing functions
2. **Predefiniowane animacje** — fade, slide, scale, bounce
   - Animacje wejścia (in) i wyjścia (out)
   - Text-specific animations
3. **Opacity animation** — fade na wizualnych elementach (nie tylko audio)
4. **Position animation** — ruch elementów po canvas
5. **Scale animation** — zoom in/out
6. **Rotation animation** — obrót elementów
7. **Color animation** — zmiana koloru w czasie

## Sugerowana implementacja

### Etap 1: Keyframe engine
- Dodać `keyframes` array do elementu w composition JSON
- Keyframe: `{ time, property, value, easing }`
- W renderFrame(): interpolacja wartości na podstawie keyframe'ów

### Etap 2: UI keyframe'ów
- Panel properties: "+" button przy animowalnych właściwościach
- Timeline: diamond markers dla keyframe'ów
- Edycja keyframe'ów: click na marker → zmień wartość

### Etap 3: Preset animations
- Biblioteka predefiniowanych animacji
- Drag & drop na element
- Customizowalne parametry (duration, delay, easing)

## Źródła
- https://creatomate.com/docs/template-editor/animations
- https://creatomate.com/docs/json/using-keyframes
