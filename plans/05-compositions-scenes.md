# Plan: Compositions / Sceny (wzór Creatomate)

## Context
Creatomate pozwala grupować elementy w kompozycje (sceny), które mogą być zagnieżdżone.

## Compositions w Creatomate

### Czym jest composition
- Grupa elementów działająca jako **jeden element**
- Ma własny timeline, width, height
- Może być umieszczona na timeline innej kompozycji (**nesting**)
- Umożliwia tworzenie **wieloscenowych** filmów

### Zastosowania
1. **Sceny** — film podzielony na intro → main → outro, każda scena to osobna composition
2. **Reusable components** — logo animation, lower third, call-to-action jako composition wielokrotnego użytku
3. **Transitions** — przejścia między scenami
4. **Responsive** — composition dostosowuje się do różnych aspect ratio

### Właściwości composition
- Wszystkie wspólne właściwości elementów (position, opacity, rotation, etc.)
- `elements` — tablica elementów wewnątrz
- Własny `duration`
- Mogą mieć własne `animations`

## Co mamy w naszym edytorze
- Flat structure: tracks → elements ✅
- Brak nested compositions
- Brak koncepcji "scen"

## Czego brakuje (priorytet)
1. **Scenowy workflow** — podział timeline na sceny/segmenty
2. **Nested compositions** — composition element na timeline
3. **Transitions między scenami** — fade, slide, wipe itp.
4. **Reusable components** — zapisywanie grup elementów jako template

## Źródła
- https://creatomate.com/docs/json/elements/composition-element
- https://creatomate.com/docs/template-editor/composition
