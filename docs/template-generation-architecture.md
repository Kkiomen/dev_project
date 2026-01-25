# System Generowania Szablonów AI - Architektura

**Wersja:** 1.4.1 (Technology Query Fix & Visual Balance)

## Spis treści

1. [Przegląd systemu](#przegląd-systemu)
2. [Pipeline przetwarzania](#pipeline-przetwarzania)
3. [Struktura warstw (KRYTYCZNE)](#struktura-warstw-krytyczne)
4. [Komponenty systemu](#komponenty-systemu)
5. [Faza 3: Premium Quality](#faza-3-premium-quality)
6. [Archetypy kompozycji](#archetypy-kompozycji)
7. [Mikroserwis analizy obrazów](#mikroserwis-analizy-obrazów)
8. [Źródła zdjęć (Pexels/Unsplash)](#źródła-zdjęć)
9. [Tools AI - szczegółowa dokumentacja](#tools-ai---szczegółowa-dokumentacja)
10. [Debugging i Logowanie](#debugging-i-logowanie)
11. [**Znalezione Bugi i Poprawki (6 bugów)**](#znalezione-bugi-i-poprawki-dla-eksperta)
12. [Troubleshooting](#troubleshooting)
13. [Znane problemy i rozwiązania](#znane-problemy-i-rozwiązania)
14. [Changelog](#changelog)

---

## Przegląd systemu

System generowania szablonów AI składa się z **15-krokowego pipeline'u walidacji i korekcji**, który zapewnia profesjonalną jakość wygenerowanych szablonów graficznych.

### Główne cele

| Cel | Rozwiązanie | Serwis |
|-----|-------------|--------|
| Precyzyjne wyrównanie | 8pt Grid System | `GridSnapService` |
| Spójność wizualna | Design Tokens (skala modularna) | `DesignTokensService` |
| Profesjonalne kompozycje | 6 Archetypów kompozycji | `CompositionArchetypeService` |
| Czytelność tekstu | WCAG AA Contrast + Text Overlays | `ContrastValidator` + `TextOverlayService` |
| Hierarchia wizualna | Typography Hierarchy Validator | `TypographyHierarchyValidator` |
| Unikanie nachodzenia na zdjęcia | Image Analysis + Safe Zones | `ImageAnalysisService` |
| Poprawne pozycjonowanie tekstu | Text Positioning Service | `TextPositioningService` |
| Harmonia kolorystyczna | Ekstrakcja kolorów ze zdjęć | `node-vibrant` + `DesignTokensService` |
| Obecność CTA | Mandatory CTA Enforcement | `SelfCorrectionService` |
| **Premium shadows** | Multi-layer elevation | `ElevationService` |
| **Vertical rhythm** | Baseline grid typography | `DesignTokensService` |
| **Premium image search** | Industry modifiers | `PremiumQueryBuilder` |
| **Quality review** | Visual Critic Agent | `VisualCriticService` |
| **Multi-format** | 1:1, 4:5, 9:16, 16:9 | `FormatService` |

### Architektura wysokiego poziomu

```
┌──────────────────────────────────────────────────────────────────┐
│                        AI Chat Service                           │
│                   (handleCreateFullTemplate)                     │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                  1. Premium Image Search                         │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ PremiumQueryBuilder + Unsplash/Pexels API                   │ │
│  │ "woman portrait" → "woman portrait minimalist aesthetic     │ │
│  │                     soft natural light authentic candid"    │ │
│  └─────────────────────────────────────────────────────────────┘ │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                  2. Image Analysis (Docker)                      │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ Focal Point Detection + Color Extraction + Safe Zones       │ │
│  └─────────────────────────────────────────────────────────────┘ │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                  3. Composition Archetype Selection              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ hero_left   │  │ hero_right  │  │ split_      │              │
│  │             │  │             │  │ diagonal    │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
│  ┌─────────────┐  ┌─────────────┐                               │
│  │ bottom_     │  │ centered_   │                               │
│  │ focus       │  │ minimal     │                               │
│  └─────────────┘  └─────────────┘                               │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                    4. 15-Step Validation Pipeline                │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ STEP 1:  Premium Image Search (industry modifiers)       │   │
│  │ STEP 2:  Grid Snap (8pt)                                 │   │
│  │ STEP 3:  Design Tokens (modular scale)                   │   │
│  │ STEP 3.5: Vertical Rhythm & Tracking                     │   │
│  │ STEP 4:  Template Validator                              │   │
│  │ STEP 5:  Typography Hierarchy                            │   │
│  │ STEP 6:  Contrast Validator (WCAG AA)                    │   │
│  │ STEP 7:  Auto-add Missing Elements                       │   │
│  │ STEP 7.5: Apply Archetype Constraints                    │   │
│  │ STEP 8:  Image Analysis Adjustment                       │   │
│  │ STEP 9:  Self-Correction (12 sub-steps)                  │   │
│  │ STEP 9.5: Elevation Shadows (CTA floating)               │   │
│  │ STEP 10: Final Grid Snap                                 │   │
│  │ STEP 11: Z-Order Sorting                                 │   │
│  │ STEP 12: Visual Critic Review (score ≥75 to pass)        │   │
│  └──────────────────────────────────────────────────────────┘   │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │  Final Layers   │
                    │  (validated)    │
                    └─────────────────┘
```

---

## Pipeline przetwarzania

### Pełny pipeline w `AiChatService::handleCreateFullTemplate()`

```php
// STEP 1: Search for images with premium queries
$premiumQuery = $this->premiumQueryBuilder->buildQueryWithComposition(
    $query,
    $archetype,
    $industry,
    $this->premiumQueryBuilder->getSuggestedLighting($industry)
);
$result = $this->stockPhotoService->searchPhotos($premiumQuery, 5, $orientation);

// STEP 2: Apply Grid Snap (8pt grid)
$layers = $this->gridSnapService->snapAllLayers($layers);

// STEP 3: Apply Design Tokens (modular scale for fonts, etc.)
$layers = $this->designTokensService->snapAllLayersToTokens($layers);

// STEP 3.5: Apply Vertical Rhythm & Tracking (Phase 3.2)
$layers = $this->designTokensService->applyVerticalRhythmToLayers($layers);

// STEP 4: Validate and fix layers using TemplateValidator
$layers = $this->templateValidator->validateAndFix($layers, $currentWidth);

// STEP 5: Fix typography hierarchy
$layers = $this->typographyValidator->fixHierarchy($layers);

// STEP 6: Fix contrast issues
$layers = $this->contrastValidator->fixContrastIssues($layers, $backgroundColor);

// STEP 7: Check completeness - auto-add missing required elements
$missing = $this->templateValidator->checkCompleteness($layers, $width, $height);
if (!empty($missing)) {
    $layers = $this->templateValidator->addMissingElements($layers, $missing, ...);
}

// STEP 7.5: Apply archetype constraints to layers
$layers = $this->applyArchetypeConstraints($layers, $archetype, $width, $height);

// STEP 8: Adjust layers based on image analysis (avoid focal point)
$layers = $this->imageAnalysisService->adjustLayersToAnalysis($layers, $imageAnalysis);

// STEP 9: Self-correction pass (final review)
$correctionResult = $this->selfCorrectionService->reviewAndCorrect($layers, ...);
$layers = $correctionResult['layers'];

// STEP 9.5: Apply Elevation Shadows (Phase 3.3 - Multi-layer Shadow Physics)
$layers = $this->elevationService->applyElevationToLayers($layers);

// STEP 10: Final grid snap to ensure everything is aligned
$layers = $this->gridSnapService->snapAllLayers($layers);

// STEP 11: Sort layers by z-order (background first, CTA last)
$layers = $this->templateValidator->sortLayersByZOrder($layers);

// STEP 12: Visual Critic Review (Phase 3.1 - Premium Quality Check)
$critique = $this->visualCriticService->critique($layers, $imageAnalysis, $width, $height);
if (!$critique['passed']) {
    $layers = $this->visualCriticService->applyFixes($layers, $critique, $width, $height);
}
```

---

## Struktura warstw (KRYTYCZNE)

### Wymagana struktura warstwy

**WAŻNE:** AI musi generować warstwy w dokładnie tej strukturze. Brakujące pola powodują błędy.

```php
$layer = [
    // WYMAGANE - identyfikacja
    'name' => 'headline',           // string - unikalna nazwa warstwy
    'type' => 'text',               // string - typ warstwy (patrz poniżej)

    // WYMAGANE - pozycja i wymiary
    'x' => 80,                      // int - pozycja X (wielokrotność 8)
    'y' => 200,                     // int - pozycja Y (wielokrotność 8)
    'width' => 920,                 // int - szerokość (wielokrotność 8)
    'height' => 100,                // int - wysokość (wielokrotność 8)

    // OPCJONALNE
    'rotation' => 0,                // int - rotacja w stopniach

    // WYMAGANE - właściwości (zależne od typu)
    'properties' => [
        // ... zależne od typu warstwy
    ],
];
```

### Typy warstw i ich właściwości

#### 1. `text` - Warstwa tekstowa

```php
'type' => 'text',
'properties' => [
    'text' => 'Treść nagłówka',     // WYMAGANE - tekst do wyświetlenia
    'fontFamily' => 'Montserrat',    // WYMAGANE - nazwa fontu
    'fontSize' => 49,                // WYMAGANE - rozmiar (ze skali: 13,16,20,25,31,39,49,61)
    'fontWeight' => '700',           // string - waga fontu
    'fontStyle' => 'normal',         // string - normal/italic
    'fill' => '#FFFFFF',             // WYMAGANE - kolor tekstu (hex)
    'align' => 'left',               // string - left/center/right
    'verticalAlign' => 'top',        // string - top/middle/bottom
    'lineHeight' => 1.306,           // float - obliczane automatycznie
    'letterSpacing' => -0.7,         // float - obliczane automatycznie
],
```

#### 2. `textbox` - Przycisk/box z tekstem (używany dla CTA)

```php
'type' => 'textbox',
'properties' => [
    'text' => 'Sprawdź Teraz',       // WYMAGANE - tekst przycisku
    'fontFamily' => 'Montserrat',
    'fontSize' => 16,
    'fontWeight' => '600',
    'fill' => '#D4AF37',             // WYMAGANE - kolor tła przycisku
    'textColor' => '#FFFFFF',        // WYMAGANE - kolor tekstu (NIE fill!)
    'align' => 'center',
    'padding' => 16,                 // int - wewnętrzny padding
    'cornerRadius' => 500,           // int - 500 = pill shape
    'lineHeight' => 1.1,
    // Elevation shadows (dodawane automatycznie dla CTA)
    'shadowEnabled' => true,
    'shadowColor' => '#000000',
    'shadowBlur' => 8,
    'shadowOffsetX' => 0,
    'shadowOffsetY' => 4,
    'shadowOpacity' => 0.12,
],
```

#### 3. `image` - Warstwa obrazu

```php
'type' => 'image',
'properties' => [
    'src' => null,                   // string|null - URL obrazu (uzupełniany automatycznie)
    'fit' => 'cover',                // string - cover/contain/fill
],
```

#### 4. `rectangle` - Prostokąt (tło, overlay, akcenty)

```php
'type' => 'rectangle',
'properties' => [
    'fill' => '#1E3A5F',             // WYMAGANE - kolor wypełnienia
    'stroke' => null,                // string|null - kolor obramowania
    'strokeWidth' => 0,              // int - grubość obramowania
    'cornerRadius' => 0,             // int - zaokrąglenie rogów
    'opacity' => 1.0,                // float - przezroczystość (0-1)
],
```

#### 5. `ellipse` - Elipsa/koło

```php
'type' => 'ellipse',
'properties' => [
    'fill' => '#D4AF37',
    'stroke' => null,
    'strokeWidth' => 0,
],
```

#### 6. `line` - Linia

```php
'type' => 'line',
'properties' => [
    'stroke' => '#D4AF37',           // WYMAGANE - kolor linii
    'strokeWidth' => 3,              // WYMAGANE - grubość
],
```

### Nazewnictwo warstw (KRYTYCZNE dla wykrywania)

System automatycznie wykrywa typ warstwy na podstawie nazwy. **Używaj tych nazw:**

| Nazwa | Cel | Automatyczne działania |
|-------|-----|------------------------|
| `background` lub `bg_*` | Tło szablonu | Pomijane przy margin fix |
| `photo` lub `image_*` | Zdjęcie główne | Pomijane przy margin fix |
| `overlay` lub `overlay_*` | Półprzezroczyste tło pod tekstem | Wykrywane dla kontrastu |
| `headline` lub `title` | Główny nagłówek | Największy font, line-height headline_normal |
| `subtext` lub `subtitle` | Podtytuł/opis | Średni font, line-height body_tight |
| `cta_button` lub `button` | Przycisk CTA | Elevation 3, pozycja na dole |
| `accent_*` | Element dekoracyjny | Elevation 1 |
| `card_*` lub `panel_*` | Karta/panel | Elevation 2 |

### Kolejność warstw (z-order)

Warstwy są sortowane automatycznie:

```
1. background (najniżej)
2. image/photo
3. overlay
4. rectangle (dekoracyjne)
5. text (headline, subtext)
6. textbox/cta (najwyżej)
```

---

## Komponenty systemu

### 1. CompositionArchetypeService

**Plik:** `app/Services/AI/CompositionArchetypeService.php`

Definiuje 6 profesjonalnych archetypów kompozycji.

#### Strefy kompozycji (1080x1080)

```php
ARCHETYPES = [
    'hero_left' => [
        'text_zone' => ['x' => 80, 'y' => 200, 'width' => 400, 'height' => 680],
        'photo_zone' => ['x' => 480, 'y' => 0, 'width' => 600, 'height' => 1080],
        'cta_position' => 'bottom-left',
        'headline_align' => 'left',
        'ideal_focal_x' => [0.6, 1.0], // Focal point po prawej
    ],
    'hero_right' => [
        'text_zone' => ['x' => 600, 'y' => 200, 'width' => 400, 'height' => 680],
        'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 600, 'height' => 1080],
        'cta_position' => 'bottom-right',
        'ideal_focal_x' => [0.0, 0.4], // Focal point po lewej
    ],
    // NOWE w v1.4.0 - czysty podział bez tekstu na zdjęciu
    'split_content' => [
        'text_zone' => ['x' => 580, 'y' => 200, 'width' => 420, 'height' => 680],
        'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 540, 'height' => 1080],
        'background_zone' => ['x' => 540, 'y' => 0, 'width' => 540, 'height' => 1080], // Solid color
        'cta_position' => 'bottom-right',
        'headline_align' => 'left',
        'ideal_focal_x' => [0.0, 0.5],
        'requires_overlay' => false,     // NO overlay - text on solid color
        'no_text_on_photo' => true,       // CRITICAL: text NEVER on photo
    ],
    'split_diagonal' => [
        'text_zone' => ['x' => 400, 'y' => 500, 'width' => 600, 'height' => 500],
        'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 700],
        'requires_overlay' => true,
    ],
    'bottom_focus' => [
        'text_zone' => ['x' => 80, 'y' => 700, 'width' => 920, 'height' => 300],
        'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 1080, 'height' => 650],
        'headline_align' => 'center',
        'ideal_focal_y' => [0.0, 0.5], // Focal point na górze
    ],
    'centered_minimal' => [
        'text_zone' => ['x' => 140, 'y' => 300, 'width' => 800, 'height' => 480],
        'photo_zone' => ['x' => 0, 'y' => 0, 'width' => 1080, 'height' => 1080],
        'requires_overlay' => true,
        'overlay_opacity' => 0.6,
        'headline_align' => 'center',
    ],
]
```

#### Kiedy używać `split_content` (NOWE)

Użyj `split_content` gdy:
- Zdjęcie jest bardzo "busy" (busy_ratio > 70%)
- Potrzebujesz maksymalnej czytelności tekstu
- Projekt wymaga czystego, minimalistycznego wyglądu
- Chcesz uniknąć problemów z kontrastem tekst/zdjęcie

```
┌─────────────────────────────────────────────────┐
│  SPLIT_CONTENT                                  │
│ ┌─────────────────────┐┌──────────────────────┐ │
│ │                     ││  SOLID COLOR BG      │ │
│ │     ZDJĘCIE         ││  + TEKST             │ │
│ │     0-540           ││  580-1000            │ │
│ │                     ││                      │ │
│ │                     ││  [CTA centered]      │ │
│ └─────────────────────┘└──────────────────────┘ │
└─────────────────────────────────────────────────┘
```

### 2. GridSnapService

**Plik:** `app/Services/AI/GridSnapService.php`

```php
// Wzór matematyczny
P(x, y) = (round(x/8)*8, round(y/8)*8)

// Przykład
snapToGrid(137) → 136  // round(137/8)*8
snapToGrid(423) → 424
```

### 3. DesignTokensService

**Plik:** `app/Services/AI/DesignTokensService.php`

#### Skala typograficzna (ratio 1.25)

```php
FONT_SCALE = [
    'xs'  => 13,   // S₀ × 1.25⁻¹
    'sm'  => 16,   // S₀ (base)
    'md'  => 20,   // S₀ × 1.25¹
    'lg'  => 25,   // S₀ × 1.25²
    'xl'  => 31,   // S₀ × 1.25³
    '2xl' => 39,   // S₀ × 1.25⁴ - minimum dla premium headline
    '3xl' => 49,   // S₀ × 1.25⁵
    '4xl' => 61,   // S₀ × 1.25⁶
]
```

#### Vertical Rhythm (NOWE w v1.3.0)

```php
BASELINE_UNIT = 8;  // Wszystkie line-heights są wielokrotnością 8

LINE_HEIGHT_SCALE = [
    'headline_tight'  => 1.1,   // Nagłówki wieloliniowe
    'headline_normal' => 1.2,   // Nagłówki jednoliniowe
    'body_tight'      => 1.4,   // Gęsty tekst
    'body_normal'     => 1.5,   // Standardowy tekst
    'body_loose'      => 1.75,  // Luźny tekst
];

TRACKING_SCALE = [
    'xs'  => 0.05,   // +5% dla małych fontów (więcej przestrzeni)
    'sm'  => 0.02,
    'md'  => 0.0,
    'lg'  => -0.01,
    'xl'  => -0.02,  // -2% dla dużych fontów (ciaśniej)
    '2xl' => -0.02,
    '3xl' => -0.015,
    '4xl' => -0.01,
];
```

#### Branżowe pary fontów

```php
INDUSTRY_FONTS = [
    'medical'    => ['heading' => 'Poppins', 'body' => 'Open Sans'],
    'beauty'     => ['heading' => 'Playfair Display', 'body' => 'Montserrat'],
    'gastro'     => ['heading' => 'Lora', 'body' => 'Montserrat'],
    'fitness'    => ['heading' => 'Oswald', 'body' => 'Roboto'],
    'technology' => ['heading' => 'Inter', 'body' => 'Inter'],
    'luxury'     => ['heading' => 'Cormorant Garamond', 'body' => 'Montserrat'],
    'default'    => ['heading' => 'Montserrat', 'body' => 'Montserrat'],
]
```

---

## Faza 3: Premium Quality

### 1. VisualCriticService (Agent Krytyka)

**Plik:** `app/Services/AI/VisualCriticService.php`

Recenzuje wygenerowane szablony i ocenia jakość premium.

#### Kryteria oceny

| Kryterium | Waga | Sprawdzenia |
|-----------|------|-------------|
| `typography_hierarchy` | 20% | headline_dominance, scale_consistency, tracking |
| `composition_balance` | 25% | rule_of_thirds, visual_weight, negative_space |
| `color_harmony` | 15% | palette_coherence, contrast_wcag, accent_usage |
| `depth_and_shadow` | 15% | elevation_consistency, light_direction, shadow_softness |
| `image_text_integration` | 25% | focal_point_clear, text_readability, overlay |

#### Minimum score: 75/100

```php
$critique = $this->visualCriticService->critique($layers, $imageAnalysis, 1080, 1080);

// Przykładowy wynik
[
    'passed' => false,
    'total_score' => 68.5,
    'scores' => [
        'typography_hierarchy' => 80,
        'composition_balance' => 75,
        'color_harmony' => 85,
        'depth_and_shadow' => 40,  // Problem: brak cieni na CTA
        'image_text_integration' => 60,
    ],
    'issues' => [
        'typography:headline - Headline too small (25px). Premium requires 39px+',
        'depth:cta - CTA button lacks elevation. Add shadow for floating effect',
        'integration:overlay - Full-bleed image with text needs overlay',
    ],
    'suggestions' => [
        'Increase headline to 39px or 49px for scroll-stopping impact',
        'Apply elevation level 3 to CTA: shadowBlur=8, shadowOffsetY=4, shadowOpacity=0.12',
        'Add dark overlay (opacity 0.5-0.6) between image and text',
    ],
    'verdict' => 'NEEDS_REVISION',
]
```

### 2. ElevationService (Cienie)

**Plik:** `app/Services/AI/ElevationService.php`

System cieni oparty na Material Design elevation levels.

#### Poziomy elevation

| Level | Blur | OffsetY | Opacity | Użycie |
|-------|------|---------|---------|--------|
| 0 | - | - | - | Tło, zdjęcia |
| 1 | 2px | 1px | 8% | Akcenty |
| 2 | 4px | 2px | 10% | Karty, panele |
| 3 | 8px | 4px | 12% | **CTA buttons** |
| 4 | 16px | 8px | 14% | Modals |
| 5 | 24px | 12px | 16% | Floating elements |

```php
// Automatyczne przypisanie elevation
'background' => 0,
'image'      => 0,
'overlay'    => 0,
'accent'     => 1,
'card'       => 2,
'cta'        => 3,  // CTA MUSI mieć elevation 3
'textbox'    => 3,
```

### 3. PremiumQueryBuilder

**Plik:** `app/Services/AI/PremiumQueryBuilder.php`

Buduje zaawansowane zapytania do API zdjęć.

#### Modyfikatory branżowe (v1.4.1)

```php
INDUSTRY_MODIFIERS = [
    'beauty'     => ['minimalist aesthetic', 'soft natural light', 'lifestyle', 'spa', 'wellness'],
    'gastro'     => ['gourmet', 'flatlay', 'rustic texture', 'bokeh', 'close-up'],
    'fitness'    => ['dark background aesthetic', 'minimalist gym', 'professional equipment', 'moody lighting', 'dramatic'],
    'medical'    => ['clean', 'professional', 'bright', 'clinical', 'trustworthy'],
    // ZMIENIONE w v1.4.1 - abstrakcje zamiast literalnych terminów
    'technology' => ['abstract dark', 'clean glass architecture', 'silk waves', 'neon glow', 'futuristic minimal'],
    'luxury'     => ['premium', 'elegant', 'sophisticated', 'cinematic lighting'],
];

// NOWE w v1.4.1 - słowa produkujące cluttered screenshots
PROBLEMATIC_QUERY_WORDS = [
    'website'     => 'digital abstract',
    'screenshot'  => 'technology abstract',
    'screen'      => 'glass reflection',
    'app'         => 'futuristic interface',
    'software'    => 'digital abstract',
    'code'        => 'technology pattern',
    'programming' => 'abstract lines',
    'computer'    => 'minimalist tech',
    'corporate'   => 'professional modern',
];

// NOWE w v1.4.1 - abstrakcyjne zamienniki dla technology
ABSTRACT_TECH_REPLACEMENTS = [
    'abstract technology background dark blue',
    'silk waves technology futuristic',
    'clean glass architecture office',
    'neon light abstract minimal',
    'geometric pattern dark background',
    'digital abstract particles dark',
];

// NOWE w v1.4.0 - filtrowanie "stockowych" przymiotników
CHEESY_ADJECTIVES = [
    'happy', 'smiling', 'excited', 'joyful', 'cheerful', 'thrilled',
    'delighted', 'pleased', 'glad', 'content', 'satisfied', 'ecstatic',
];
```

#### Nowe metody (v1.4.1)

```php
// Dla technology - automatycznie przełącza na abstrakcje
$query = $builder->buildTechnologyQuery('corporate website design');
// Output: "abstract technology background dark blue"  // Nie cluttered screenshots!

// Dla split_content - czyste tła
$query = $builder->buildCleanBackgroundQuery('gym machine', 'fitness', preferDark: true);
// Output: "gym machine dark background minimalist gym"

// Automatyczne usuwanie "cheesy" słów
// "happy business team" → "business team candid"
```

#### Przykład transformacji (Technology)

```
PROBLEM: "corporate technology website" → zwraca screenshots z małym tekstem
SOLUTION: Automatyczna zamiana na abstrakcje

Input:  "technology website modern"
Output: "silk waves technology futuristic"  // Czyste tło, negative space

Input:  "software development team"
Output: "digital abstract team professional modern"  // "software" → "digital abstract"
```

#### Przykład transformacji (Inne branże)

```
Input:  "woman portrait" (beauty industry)
Output: "woman portrait minimalist aesthetic"

Input:  "happy gym machine" (fitness industry)
Output: "gym machine candid dark background aesthetic"  // "happy" usunięte
```

### 4. FormatService (Multi-format)

**Plik:** `app/Services/AI/FormatService.php`

#### Obsługiwane formaty

| Format | Wymiary | Ratio | Platformy |
|--------|---------|-------|-----------|
| `square` | 1080×1080 | 1:1 | Instagram feed, Facebook |
| `portrait` | 1080×1350 | 4:5 | Instagram optimal |
| `tall` | 1080×1440 | 3:4 | Pinterest-friendly |
| `story` | 1080×1920 | 9:16 | Stories, Reels, TikTok |
| `landscape` | 1920×1080 | 16:9 | YouTube, LinkedIn |

#### Safe zones (dla Stories)

```php
'story' => [
    'safe_zone' => ['top' => 250, 'bottom' => 250],  // Unikaj UI overlay
]
```

---

## Archetypy kompozycji

### Wizualizacja

```
┌─────────────────────────────┐  ┌─────────────────────────────┐
│  HERO_LEFT                  │  │  HERO_RIGHT                 │
│ ┌────────┐┌───────────────┐ │  │ ┌───────────────┐┌────────┐ │
│ │ TEKST  ││               │ │  │ │               ││ TEKST  │ │
│ │ 80-480 ││  ZDJĘCIE      │ │  │ │   ZDJĘCIE     ││600-1000│ │
│ │        ││  480-1080     │ │  │ │   0-600       ││        │ │
│ │        ││               │ │  │ │               ││        │ │
│ │ [CTA]  ││               │ │  │ │               ││ [CTA]  │ │
│ └────────┘└───────────────┘ │  │ └───────────────┘└────────┘ │
└─────────────────────────────┘  └─────────────────────────────┘

┌─────────────────────────────┐  ┌─────────────────────────────┐
│  BOTTOM_FOCUS               │  │  CENTERED_MINIMAL           │
│ ┌─────────────────────────┐ │  │ ┌─────────────────────────┐ │
│ │                         │ │  │ │                         │ │
│ │    ZDJĘCIE (0-650)      │ │  │ │   ZDJĘCIE (full-bleed)  │ │
│ │                         │ │  │ │ ┌─────────────────────┐ │ │
│ ├─────────────────────────┤ │  │ │ │ ▓▓▓ OVERLAY ▓▓▓▓▓▓▓ │ │ │
│ │ TEKST (700-1000) [CTA]  │ │  │ │ │ TEKST (centered)    │ │ │
│ └─────────────────────────┘ │  │ │ │      [CTA]          │ │ │
└─────────────────────────────┘  │ │ └─────────────────────┘ │ │
                                  │ └─────────────────────────┘ │
                                  └─────────────────────────────┘
```

### Algorytm wyboru

```php
if ($focalX < 0.4) return 'hero_right';      // Focal po lewej → tekst po prawej
if ($focalX > 0.6) return 'hero_left';       // Focal po prawej → tekst po lewej
if ($focalY < 0.4) return 'bottom_focus';    // Focal na górze → tekst na dole
return 'centered_minimal';                    // Domyślnie centered z overlay
```

---

## Mikroserwis analizy obrazów

### Konfiguracja Docker

**Plik:** `docker/image-analysis/Dockerfile`

```dockerfile
FROM node:20-alpine
WORKDIR /app
RUN apk add --no-cache python3 make g++ vips-dev fftw-dev
COPY package.json ./
RUN npm install --omit=dev
COPY . .
EXPOSE 3334
CMD ["node", "server.js"]
```

### Zwracane dane

```json
{
  "success": true,
  "focal_point": {
    "x": 540,
    "y": 360,
    "normalized": { "x": 0.5, "y": 0.33 }
  },
  "brightness": {
    "top-left": 0.3,
    "top-right": 0.4,
    "bottom-left": 0.6,
    "bottom-right": 0.5,
    "overall": 0.45,
    "is_dark": true
  },
  "colors": {
    "vibrant": "#E07A5F",
    "muted": "#3D405B",
    "dark_vibrant": "#81B29A",
    "dark_muted": "#5C6B73",
    "light_vibrant": "#F4D35E",
    "light_muted": "#E8E8E8",
    "accent_candidates": ["#E07A5F", "#81B29A", "#F4D35E"]
  },
  "suggested_text_position": "bottom-left",
  "safe_zones": [
    {
      "position": "bottom",
      "x": 80,
      "y": 780,
      "width": 920,
      "height": 260,
      "brightness": 0.3,
      "recommended_text_color": "#FFFFFF"
    }
  ],
  "busy_zones": [
    {
      "position": "focal",
      "x": 270,
      "y": 100,
      "width": 540,
      "height": 540,
      "reason": "Contains main subject"
    }
  ]
}
```

---

## Źródła zdjęć

### Aktualnie: Unsplash (v1.3.0)

**Plik:** `app/Services/UnsplashService.php`

```php
// Przełączenie z Pexels na Unsplash w AiChatService
protected UnsplashService $stockPhotoService
```

### Konfiguracja

```env
# .env
UNSPLASH_ACCESS_KEY=your_access_key_here

# Aby wrócić do Pexels:
# 1. Zmień w AiChatService: protected PexelsService $stockPhotoService
# 2. Upewnij się że PEXELS_API_KEY jest ustawiony
```

### Interfejs (kompatybilny)

```php
// Obie usługi mają ten sam interfejs:
$service->searchPhotos($query, $perPage, $orientation);
$service->getCuratedPhotos($perPage);
$service->getBestImageUrl($photo, $targetWidth, $targetHeight);
$service->isConfigured();
```

---

## Tools AI - szczegółowa dokumentacja

### Tool 1: `plan_design` (WYMAGANY PIERWSZY)

```json
{
  "name": "plan_design",
  "description": "REQUIRED FIRST STEP. Plan the design before creating layers.",
  "parameters": {
    "layout_description": {
      "type": "string",
      "description": "Describe the layout idea in detail"
    },
    "composition_archetype": {
      "type": "string",
      "enum": ["hero_left", "hero_right", "split_diagonal", "bottom_focus", "centered_minimal"],
      "description": "REQUIRED. Choose based on where focal point should be."
    },
    "color_scheme": {
      "type": "string",
      "description": "Color scheme with specific hex values from brand palette"
    },
    "headline": {
      "type": "string",
      "description": "Creative headline text, 4-8 words max"
    },
    "subtext": {
      "type": "string",
      "description": "Supporting text/tagline"
    },
    "cta_text": {
      "type": "string",
      "description": "Call-to-action button text"
    }
  }
}
```

### Tool 2: `create_full_template` (GŁÓWNY)

```json
{
  "name": "create_full_template",
  "description": "Create complete template with all layers.",
  "parameters": {
    "template_settings": {
      "type": "object",
      "properties": {
        "width": { "type": "integer", "default": 1080 },
        "height": { "type": "integer", "default": 1080 },
        "background_color": { "type": "string", "description": "Hex color" }
      }
    },
    "layers": {
      "type": "array",
      "description": "Array of layer objects (see Layer Structure above)",
      "items": {
        "type": "object",
        "required": ["name", "type", "x", "y", "width", "height", "properties"]
      }
    },
    "image_searches": {
      "type": "array",
      "description": "Pexels/Unsplash search queries",
      "items": {
        "type": "object",
        "properties": {
          "layer_name": { "type": "string", "description": "Must match a layer name" },
          "search_query": { "type": "string", "description": "Search query in English" },
          "orientation": { "type": "string", "enum": ["landscape", "portrait", "square"] }
        }
      }
    }
  }
}
```

### Przykład poprawnego wywołania

```json
{
  "tool": "create_full_template",
  "parameters": {
    "template_settings": {
      "width": 1080,
      "height": 1080,
      "background_color": "#1E3A5F"
    },
    "layers": [
      {
        "name": "background",
        "type": "rectangle",
        "x": 0,
        "y": 0,
        "width": 1080,
        "height": 1080,
        "properties": {
          "fill": "#1E3A5F"
        }
      },
      {
        "name": "photo",
        "type": "image",
        "x": 480,
        "y": 0,
        "width": 600,
        "height": 1080,
        "properties": {
          "src": null,
          "fit": "cover"
        }
      },
      {
        "name": "overlay",
        "type": "rectangle",
        "x": 0,
        "y": 0,
        "width": 1080,
        "height": 1080,
        "properties": {
          "fill": "#000000",
          "opacity": 0.4
        }
      },
      {
        "name": "headline",
        "type": "text",
        "x": 80,
        "y": 280,
        "width": 400,
        "height": 200,
        "properties": {
          "text": "Odkryj Nową Jakość",
          "fontFamily": "Montserrat",
          "fontSize": 49,
          "fontWeight": "700",
          "fill": "#FFFFFF",
          "align": "left"
        }
      },
      {
        "name": "subtext",
        "type": "text",
        "x": 80,
        "y": 500,
        "width": 400,
        "height": 100,
        "properties": {
          "text": "Profesjonalne rozwiązania dla Twojego biznesu",
          "fontFamily": "Montserrat",
          "fontSize": 20,
          "fontWeight": "400",
          "fill": "#CCCCCC",
          "align": "left"
        }
      },
      {
        "name": "cta_button",
        "type": "textbox",
        "x": 80,
        "y": 880,
        "width": 220,
        "height": 56,
        "properties": {
          "text": "Sprawdź Teraz",
          "fontFamily": "Montserrat",
          "fontSize": 16,
          "fontWeight": "600",
          "fill": "#D4AF37",
          "textColor": "#FFFFFF",
          "align": "center",
          "padding": 16,
          "cornerRadius": 500
        }
      }
    ],
    "image_searches": [
      {
        "layer_name": "photo",
        "search_query": "business professional office modern",
        "orientation": "portrait"
      }
    ]
  }
}
```

---

## Debugging i Logowanie

### Jak czytać logi

Wszystkie logi zapisywane są do `storage/logs/single-*.log`. Format:

```
[2024-01-25 14:30:00] local.INFO: ╔══════════════════════════════════════════════════════════════════╗
[2024-01-25 14:30:00] local.INFO: ║           CREATE FULL TEMPLATE - PIPELINE START                   ║
[2024-01-25 14:30:00] local.INFO: ╚══════════════════════════════════════════════════════════════════╝
```

### Komendy do analizy logów

```bash
# Zobacz ostatnie 100 linii logu
tail -100 storage/logs/single-$(date +%Y-%m-%d).log

# Filtruj po konkretnym STEP
grep "\[STEP" storage/logs/single-*.log | tail -50

# Zobacz tylko problemy
grep -E "(MISSING|ERROR|WARNING|rejected|failed)" storage/logs/single-*.log

# Zobacz podsumowanie pipeline'u
grep "PIPELINE SUMMARY" -A 15 storage/logs/single-*.log

# Zobacz raw JSON z AI
grep "RAW INPUT FROM AI" -A 5 storage/logs/single-*.log
```

### Struktura logów dla każdego kroku

#### STEP 0: Raw Input from AI

```php
Log::channel('single')->info('[STEP 0] RAW INPUT FROM AI', [
    'template_settings' => $arguments['template_settings'] ?? [],
    'layers_count' => count($arguments['layers'] ?? []),
    'image_searches_count' => count($arguments['image_searches'] ?? []),
    'raw_layers_json' => json_encode($arguments['layers'] ?? [], JSON_PRETTY_PRINT),
]);
```

**Co sprawdzić:**
- `layers_count` - czy AI wygenerował wymaganą liczbę warstw (minimum 5)
- `raw_layers_json` - czy każda warstwa ma `name`, `type`, `x`, `y`, `width`, `height`, `properties`

#### STEP 0.1: Layer Inspection (wykrywanie problemów)

```php
// Automatyczne wykrywanie problemów w warstwach
$issues = [];
if (!isset($layer['x']) || !isset($layer['y'])) {
    $issues[] = 'MISSING_POSITION';
}
if (!isset($layer['width']) || !isset($layer['height'])) {
    $issues[] = 'MISSING_DIMENSIONS';
}
if (!$hasProperties) {
    $issues[] = 'EMPTY_PROPERTIES';
}
if (in_array($layerType, ['text', 'textbox']) && empty($properties['text'])) {
    $issues[] = 'TEXT_LAYER_NO_TEXT';
}
if (in_array($layerType, ['text', 'textbox']) && empty($properties['fontSize'])) {
    $issues[] = 'TEXT_LAYER_NO_FONTSIZE';
}
```

**Kody problemów:**

| Kod | Opis | Rozwiązanie |
|-----|------|-------------|
| `MISSING_POSITION` | Brak x/y | Pipeline ustawi domyślne |
| `MISSING_DIMENSIONS` | Brak width/height | Pipeline ustawi domyślne |
| `EMPTY_PROPERTIES` | Brak properties | `getDefaultLayerProperties()` uzupełni |
| `TEXT_LAYER_NO_TEXT` | Warstwa text bez tekstu | Pipeline doda placeholder |
| `TEXT_LAYER_NO_FONTSIZE` | Brak fontSize | `DesignTokensService` ustawi |
| `IMAGE_NO_FIT_OR_SRC` | Obraz bez fit | Domyślnie `cover` |

#### STEP 1: Image Search

```php
Log::channel('single')->info("[STEP 1.{$idx}] Premium query built", [
    'original' => $query,           // Oryginalne query z AI
    'premium' => $premiumQuery,     // Po dodaniu modyfikatorów
    'archetype' => $archetype,      // Archetyp kompozycji
    'industry' => $industry,        // Branża (z brandu)
]);

Log::channel('single')->info("[STEP 1.{$idx}] Stock photo search result (premium)", [
    'success' => $result['success'],
    'photos_found' => count($result['photos'] ?? []),
    'error' => $result['error'] ?? null,
]);
```

**Co sprawdzić:**
- `photos_found: 0` - problem z API lub zbyt specyficzne query
- `error` - szczegóły błędu API

#### STEP 2-3: Grid Snap & Design Tokens

```php
// Wyświetla pozycje przed i po snap
Log::channel('single')->info('[STEP 2] Grid snap applied', [
    'before' => '{"headline":{"x":82,"y":205}}',  // Przed
    'after' => '{"headline":{"x":80,"y":208}}',   // Po (snap do 8pt)
    'changed' => true,  // Czy nastąpiła zmiana
]);

// Wyświetla font sizes przed i po snap do skali
Log::channel('single')->info('[STEP 3] Design tokens applied', [
    'font_sizes_before' => ['headline' => 50, 'subtext' => 22],
    'font_sizes_after' => ['headline' => 49, 'subtext' => 20],  // Snapped do skali
]);
```

#### STEP 3.5: Vertical Rhythm

```php
Log::channel('single')->info('[STEP 3.5] Vertical rhythm applied', [
    'before' => [
        'headline' => ['fontSize' => 49, 'lineHeight' => null, 'letterSpacing' => null]
    ],
    'after' => [
        'headline' => ['fontSize' => 49, 'lineHeight' => 1.306, 'letterSpacing' => -0.7]
    ],
]);
```

#### STEP 7: Completeness Check

```php
Log::channel('single')->info('[STEP 7] Completeness check result', [
    'missing_elements' => ['cta', 'overlay'],  // Brakujące elementy
    'layers_before' => 4,
]);

// Jeśli są brakujące elementy:
Log::channel('single')->warning('[STEP 7] Template incomplete, auto-adding elements', [
    'missing' => ['cta', 'overlay'],
]);
```

**Wymagane elementy:**
- `background` lub `bg_*`
- `photo` lub `image_*`
- `headline` lub `title`
- `subtext` lub `subtitle`
- `cta_button` lub `button` (textbox)

#### STEP 9.5: Elevation Shadows

```php
Log::channel('single')->info('[STEP 9.5] Elevation shadows applied', [
    'shadows_before' => [],  // Brak cieni przed
    'shadows_after' => [
        'cta_button' => [
            'enabled' => true,
            'blur' => 8,
            'offsetY' => 4,
            'opacity' => 0.12,
        ]
    ],
]);
```

#### STEP 12: Visual Critic Review

```php
Log::channel('single')->info('[STEP 12] Visual Critic scores', [
    'passed' => false,          // Czy przeszło (score >= 75)
    'total_score' => 68.5,      // Łączny wynik
    'scores' => [
        'typography_hierarchy' => 80,
        'composition_balance' => 75,
        'color_harmony' => 85,
        'depth_and_shadow' => 40,        // Problem!
        'image_text_integration' => 60,   // Problem!
    ],
    'issues' => [
        'typography:headline - Headline too small (25px). Premium requires 39px+',
        'depth:cta - CTA button lacks elevation. Add shadow for floating effect',
    ],
    'suggestions' => [
        'Increase headline to 39px or 49px',
        'Apply elevation level 3 to CTA',
    ],
    'verdict' => 'NEEDS_REVISION',
]);
```

#### STEP 13: Final Output

```php
Log::channel('single')->info('[PIPELINE SUMMARY]', [
    'total_duration_ms' => 1234.56,     // Czas wykonania
    'total_actions' => 8,                // Liczba akcji
    'layers_created' => 7,               // Liczba warstw
    'images_found' => 1,                 // Znalezione obrazy
    'visual_critic_passed' => true,      // Czy przeszło review
    'visual_critic_score' => 82.5,       // Wynik końcowy
    'design_plan_used' => true,          // Czy użyto plan_design
    'composition_archetype' => 'hero_left',
]);

// Pełny JSON wyjściowy
Log::channel('single')->info('[FINAL OUTPUT] Actions JSON', [
    'actions_json' => '...',  // Kompletny JSON do debugowania
]);
```

### Przykład pełnego logu dla jednej generacji

```log
[INFO] ╔══════════════════════════════════════════════════════════════════╗
[INFO] ║           CREATE FULL TEMPLATE - PIPELINE START                   ║
[INFO] ╚══════════════════════════════════════════════════════════════════╝

[INFO] [STEP 0] RAW INPUT FROM AI {"template_settings":{"background_color":"#1E3A5F"},"layers_count":6}
[INFO] [STEP 0.1] DETAILED LAYER INSPECTION
[INFO]   └─ Layer #0: background (rectangle) {"position":{"x":0,"y":0},"has_properties":true,"issues":[]}
[INFO]   └─ Layer #1: photo (image) {"position":{"x":0,"y":0},"has_properties":true,"issues":[]}
[INFO]   └─ Layer #2: headline (text) {"position":{"x":82,"y":300},"issues":["TEXT_LAYER_NO_FONTSIZE"]}
[WARN]   └─ Layer #3: subtext (text) {"issues":["TEXT_LAYER_NO_TEXT"]}

[INFO] ────────────────────────────────────────────────────────────────────
[INFO] [STEP 1] IMAGE SEARCH & ANALYSIS
[INFO] [STEP 1.0] Image search config {"searches_count":1,"industry":"beauty"}
[INFO] [STEP 1.0] Premium query built {"original":"woman spa","premium":"woman spa minimalist aesthetic soft natural light authentic candid"}
[INFO] [STEP 1.0] Stock photo search result (premium) {"success":true,"photos_found":5}
[INFO] [STEP 1.0] Photo selected {"photo_id":"abc123","dimensions":"1920x1280"}
[INFO] [STEP 1.0] Image analysis result {"success":true,"busy_zones_count":1,"safe_zones_count":2}

[INFO] ────────────────────────────────────────────────────────────────────
[INFO] [STEP 2] GRID SNAP (8pt grid)
[INFO] [STEP 2] Grid snap applied {"changed":true}

[INFO] ────────────────────────────────────────────────────────────────────
[INFO] [STEP 3] DESIGN TOKENS (modular scale)
[INFO] [STEP 3] Design tokens applied {"font_sizes_before":{"headline":50},"font_sizes_after":{"headline":49}}

[INFO] ────────────────────────────────────────────────────────────────────
[INFO] [STEP 7] COMPLETENESS CHECK
[WARN] [STEP 7] Template incomplete, auto-adding elements {"missing":["cta"]}

[INFO] ────────────────────────────────────────────────────────────────────
[INFO] [STEP 12] VISUAL CRITIC REVIEW
[INFO] [STEP 12] Visual Critic scores {"passed":true,"total_score":82.5,"verdict":"APPROVED"}

[INFO] ────────────────────────────────────────────────────────────────────
[INFO] [STEP 13] FINAL LAYER CREATION
[INFO] [STEP 13.0] Layer action created: background {"type":"rectangle"}
[INFO] [STEP 13.1] Layer action created: photo {"type":"image"}
[INFO] [STEP 13.2] Layer action created: headline {"type":"text"}
[INFO] [STEP 13.3] Layer action created: subtext {"type":"text"}
[INFO] [STEP 13.4] Layer action created: cta_button {"type":"textbox"}

[INFO] ╔══════════════════════════════════════════════════════════════════╗
[INFO] ║           CREATE FULL TEMPLATE - PIPELINE COMPLETE               ║
[INFO] ╚══════════════════════════════════════════════════════════════════╝

[INFO] [PIPELINE SUMMARY] {"total_duration_ms":1523.45,"layers_created":5,"visual_critic_score":82.5}
```

### Częste problemy widoczne w logach

#### 1. AI nie wysłał wymaganych pól warstwy

```log
[INFO]   └─ Layer #2: headline (text) {"issues":["MISSING_POSITION","MISSING_DIMENSIONS","EMPTY_PROPERTIES"]}
```

**Rozwiązanie:** System automatycznie uzupełni brakujące pola domyślnymi wartościami, ale jakość może ucierpieć.

#### 2. Brak zdjęć z API

```log
[WARN] [STEP 1.0] Premium query failed, trying original
[INFO] [STEP 1.0] Stock photo search result (fallback) {"success":false,"error":"API rate limit exceeded"}
```

**Rozwiązanie:** Sprawdź klucz API, limity lub połączenie.

#### 3. Visual Critic odrzuca szablon

```log
[WARN] [STEP 12] Visual Critic REJECTED design - applying fixes
[INFO] [STEP 12] Visual Critic scores {"passed":false,"total_score":62,"issues":["Headline too small","CTA lacks elevation"]}
```

**Rozwiązanie:** System automatycznie aplikuje poprawki. Sprawdź `layers_after_fix` w logu.

#### 4. Brakujące elementy

```log
[WARN] [STEP 7] Template incomplete, auto-adding elements {"missing":["cta","overlay"]}
```

**Rozwiązanie:** System doda brakujące elementy z domyślnymi wartościami.

---

## Znalezione Bugi i Poprawki (dla eksperta)

Ta sekcja dokumentuje krytyczne bugi znalezione w systemie wraz z kodem przed i po naprawie.

---

### BUG #1: ImageAnalysisService przesuwa tekst NA zdjęcie zamiast go chronić

**Lokalizacja:** `app/Services/AI/ImageAnalysisService.php`

**Symptom w logach:**
```log
[STEP 8] IMAGE ANALYSIS ADJUSTMENTS
Moved layer to avoid busy zone {"layer":"HEADLINE","new_position":{"x":580,"y":580}}
Moved layer to avoid busy zone {"layer":"SUBTEXT","new_position":{"x":580,"y":580}}
Moved layer to avoid busy zone {"layer":"CTA_BUTTON","new_position":{"x":580,"y":580}}
```

Tekst był na x=80 (lewa strona, text zone w hero_left), a został przesunięty na x=580 (NA ZDJĘCIE!).

**KOD PRZED (błędny):**

```php
public function adjustLayersToAnalysis(array $layers, array $analysis): array
{
    // ...
    foreach ($layers as $layer) {
        // Only adjust text layers
        if (!in_array($type, ['text', 'textbox'])) {
            $adjustedLayers[] = $layer;
            continue;
        }

        // BUG: Nie sprawdza czy tekst jest faktycznie na zdjęciu!
        // Sprawdza tylko czy overlaps z busy zone, ale busy zone może
        // rozciągać się poza obszar zdjęcia
        if ($this->layerOverlapsBusyZone($layer, $busyZones)) {
            // BUG: Przesuwa wszystkie warstwy do tej samej pozycji!
            $layer = $this->moveLayerToSafeZone($layer, $safeZones, $suggestedPosition);
        }

        $adjustedLayers[] = $layer;
    }
    return $adjustedLayers;
}

protected function moveLayerToSafeZone(array $layer, array $safeZones, string $preferredPosition): array
{
    // ...
    if ($targetZone) {
        // BUG: Wszystkie warstwy dostają to samo x/y!
        $layer['x'] = $targetZone['x'];
        $layer['y'] = $targetZone['y'];
    }
    return $layer;
}
```

**KOD PO (poprawiony):**

```php
public function adjustLayersToAnalysis(array $layers, array $analysis, ?array $photoLayer = null): array
{
    // Znajdź warstwę zdjęcia aby określić obszar zdjęcia
    if (!$photoLayer) {
        foreach ($layers as $layer) {
            if ($layer['type'] === 'image' || str_contains(strtolower($layer['name'] ?? ''), 'photo')) {
                $photoLayer = $layer;
                break;
            }
        }
    }

    // Jeśli nie ma zdjęcia, nie koryguj (tekst nie jest na zdjęciu)
    if (!$photoLayer) {
        return $layers;
    }

    $photoX = $photoLayer['x'] ?? 0;
    $photoY = $photoLayer['y'] ?? 0;
    $photoW = $photoLayer['width'] ?? 1080;
    $photoH = $photoLayer['height'] ?? 1080;

    // Track used Y positions to prevent stacking
    $usedYPositions = [];

    foreach ($layers as $layer) {
        if (!in_array($layer['type'], ['text', 'textbox'])) {
            $adjustedLayers[] = $layer;
            continue;
        }

        // KLUCZOWE: Sprawdź czy warstwa jest faktycznie na obszarze zdjęcia
        $onPhoto = $this->rectanglesOverlap(
            $layer['x'], $layer['y'], $layer['width'], $layer['height'],
            $photoX, $photoY, $photoW, $photoH
        );

        // Jeśli tekst NIE jest na zdjęciu, nie ruszaj go!
        if (!$onPhoto) {
            $adjustedLayers[] = $layer;
            continue;
        }

        // Tylko dla tekstu NA zdjęciu: sprawdź busy zones
        if ($this->layerOverlapsBusyZone($layer, $busyZones)) {
            $layer = $this->moveLayerToSafeZoneWithStacking(
                $layer, $safeZones, $suggestedPosition, $usedYPositions
            );
        }

        $adjustedLayers[] = $layer;
    }
    return $adjustedLayers;
}

protected function moveLayerToSafeZoneWithStacking(
    array $layer,
    array $safeZones,
    string $preferredPosition,
    array &$usedYPositions  // Pass by reference!
): array {
    // ...
    if ($targetZone) {
        $zoneKey = $targetZone['position'] ?? 'default';
        $layerHeight = $layer['height'] ?? 50;
        $spacing = 16;

        // KLUCZOWE: Stackowanie warstw zamiast nakładania
        if (!isset($usedYPositions[$zoneKey])) {
            $layer['y'] = $targetZone['y'];
            $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
        } else {
            $layer['y'] = $usedYPositions[$zoneKey];
            $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
        }

        // Centruj w safe zone
        $layer['x'] = $targetZone['x'] + (int)(($targetZone['width'] - $layer['width']) / 2);
    }
    return $layer;
}
```

---

### BUG #2: SelfCorrectionService - wszystkie warstwy na tej samej pozycji Y

**Lokalizacja:** `app/Services/AI/SelfCorrectionService.php`

**Symptom w logach:**
```log
Self-correction applied {
  "corrections": [
    {"layer":"Headline","moved_from_y":720,"moved_to_y":780},
    {"layer":"Subtext","moved_from_y":800,"moved_to_y":780}  // TO SAMO Y!
  ]
}
```

**KOD PRZED (błędny):**

```php
protected function fixTextOverlap(array $layers, array $imageAnalysis): array
{
    foreach ($layers as $layer) {
        if ($overlaps) {
            $bestZone = $this->findBestSafeZone($layer, $safeZones, $busyZones);
            if ($bestZone) {
                // BUG: Wszystkie warstwy dostają to samo Y!
                $layer['y'] = $bestZone['y'];
            }
        }
        $fixedLayers[] = $layer;
    }
    return ['layers' => $fixedLayers, 'corrections' => $corrections];
}
```

**KOD PO (poprawiony):**

```php
protected function fixTextOverlap(array $layers, array $imageAnalysis): array
{
    // Track used Y positions per zone
    $usedYPositions = [];

    foreach ($layers as $layer) {
        if ($overlaps) {
            $bestZone = $this->findBestSafeZone($layer, $safeZones, $busyZones);
            if ($bestZone) {
                $zoneKey = $bestZone['position'] ?? 'default';
                $layerHeight = $layer['height'] ?? 50;
                $spacing = 16;

                // KLUCZOWE: Stackowanie
                if (!isset($usedYPositions[$zoneKey])) {
                    $layer['y'] = $bestZone['y'];
                    $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
                } else {
                    $layer['y'] = $usedYPositions[$zoneKey];
                    $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
                }
            }
        }
        $fixedLayers[] = $layer;
    }
    return ['layers' => $fixedLayers, 'corrections' => $corrections];
}
```

---

### BUG #3: Busy zone za duża - pokrywa text zone archetypu

**Lokalizacja:** `docker/image-analysis/server.js` (mikroserwis)

**Symptom:**
```log
busy_zones: [{"x":337,"y":0,"width":720,"height":1080}]
```

Busy zone zaczyna się od x=337, podczas gdy:
- Photo jest na x=480-1080
- Text zone (hero_left) jest na x=80-480
- Focal point jest na x=697

Busy zone pokrywa prawie cały canvas zamiast tylko obszaru focal point.

**PRAWDOPODOBNA PRZYCZYNA:**
Mikroserwis oblicza busy zone na podstawie całego obrazu (1080x1080), a nie na podstawie faktycznej pozycji zdjęcia w szablonie.

**ROZWIĄZANIE:**
1. Mikroserwis powinien zwracać busy zone względem obrazu
2. PHP powinno przeliczać busy zone na współrzędne szablonu z uwzględnieniem pozycji warstwy image

```php
// Przeliczenie busy zone z współrzędnych obrazu na szablon
$busyZone['x'] = $photoLayer['x'] + ($busyZone['x'] * $photoLayer['width'] / $imageWidth);
$busyZone['y'] = $photoLayer['y'] + ($busyZone['y'] * $photoLayer['height'] / $imageHeight);
```

---

### BUG #4: TextPositioningService resetuje X pozycje do marginesu (v1.4.0)

**Lokalizacja:** `app/Services/AI/TextPositioningService.php`

**Symptom w logach:**
```log
[STEP 7.5] Archetype constraints applied - headline at x=600
[STEP 9] Self-correction: margin fix headline x=80 → x=108  // BŁĄD! x=600 stał się x=80
```

Tekst w hero_right archetype (text_zone x=600) był resetowany do x=80 (standardMargin), co powodowało nakładanie tekstu NA zdjęcie.

**PRZYCZYNA:**
W `repositionTextLayers()` przy wykryciu overlapa wszystkie warstwy były ustawiane na `$layer['x'] = $this->standardMargin`:

```php
// KOD PRZED (błędny)
foreach ($contentLayers as $item) {
    $layer['y'] = (int) $currentY;
    $layer['x'] = $this->standardMargin;  // BUG: Ignoruje archetype text zone!
}
```

**KOD PO (poprawiony):**

```php
protected function repositionTextLayers(array $textLayers, int $templateWidth, int $templateHeight): array
{
    // CRITICAL: Remember original X positions - don't override archetype text zones!
    $originalXPositions = [];
    foreach ($textLayers as $index => $item) {
        $originalXPositions[$index] = $item['layer']['x'] ?? $this->standardMargin;
    }

    // ... reszta kodu ...

    foreach ($textLayers as $index => &$item) {
        $layer['y'] = (int) $currentY;
        // PRESERVE original X position from archetype (don't reset to margin!)
        $layer['x'] = $originalXPositions[$index];
    }
}
```

---

### BUG #5: VisualCriticService używał QUADRATIC visual weight (v1.4.0)

**Lokalizacja:** `app/Services/AI/VisualCriticService.php`

**Symptom:**
```log
TypographyHierarchyValidator: headline 59.8%, subtext 24.5%
VisualCriticService: headline 91.6%, subtext 4.2%  // NIESPÓJNOŚĆ!
```

Dwa serwisy raportowały zupełnie inne wartości dla tego samego layoutu.

**PRZYCZYNA:**
VisualCriticService używał formuły `fontSize² × area`:
```php
// KOD PRZED (błędny)
protected function calculateLayerWeight(?array $layer): float
{
    $fontSize = $layer['properties']['fontSize'] ?? 16;
    $width = $layer['width'] ?? 200;
    $height = $layer['height'] ?? 50;
    // PROBLEM: fontSize² sprawia że 61px dominuje nad 20px (3721 vs 400)
    return ($fontSize * $fontSize) * (($width * $height) / 10000);
}
```

**KOD PO (poprawiony):**

```php
protected function evaluateVisualWeight(array $layers): array
{
    // Use LINEAR font size calculation (consistent with TypographyHierarchyValidator)
    $headlineSize = $headline['properties']['fontSize'] ?? 0;
    $subtextSize = $subtext['properties']['fontSize'] ?? 0;
    $ctaSize = $cta['properties']['fontSize'] ?? 0;

    $total = $headlineSize + $subtextSize + $ctaSize;
    // Teraz: 61/(61+20+20) = 60.4% - spójne z TypographyHierarchyValidator
    $distribution = [
        'headline' => round(($headlineSize / $total) * 100, 1),
        // ...
    ];
}
```

---

### BUG #6: Margin fix przenosił tekst z prawej strefy na lewą (v1.4.0)

**Lokalizacja:** `app/Services/AI/SelfCorrectionService.php`

**Symptom:**
Tekst z hero_right (x=600) był przesuwany do x=108 przez margin fix, bo `600 > 0 && 600 < 108` było fałszywe, ale kod szedł do innej gałęzi.

**PRZYCZYNA:**
Brak detekcji "right zone" - margin fix nie rozróżniał lewej i prawej strefy tekstu:

```php
// KOD PRZED (błędny)
if ($x > 0 && $x < $standardMargin) {
    $layer['x'] = $standardMargin;
}
// Problem: co z tekstem na x=600? Inny kod go przesuwał
```

**KOD PO (poprawiony):**

```php
protected function fixMargins(array $layers, ...): array
{
    // CRITICAL: Detect right text zone (archetype split layouts)
    // Text at x >= 400 is intentionally in the right half
    $isRightZone = $x >= 400;

    // Check left margin - but respect archetype zones
    if (!$isRightZone && $x > 0 && $x < $standardMargin) {
        $layer['x'] = $standardMargin;
    }

    // For right-zone text, only adjust width, never X position
    if ($isRightZone) {
        $rightEdge = $x + $width;
        $rightMargin = $templateWidth - $rightEdge;
        if ($rightMargin > 0 && $rightMargin < $standardMargin) {
            $layer['width'] = $templateWidth - $x - $standardMargin;
        }
    }
}
```

---

### Wzorzec stackowania warstw (do wykorzystania wszędzie)

```php
/**
 * Wzorzec stackowania warstw w tej samej strefie.
 * Używaj wszędzie gdzie wiele warstw może trafić do tej samej strefy.
 */
protected function stackLayersInZone(
    array $layer,
    array $targetZone,
    array &$usedYPositions  // MUSI być przez referencję!
): array {
    $zoneKey = $targetZone['position'] ?? 'default';
    $layerHeight = $layer['height'] ?? 50;
    $spacing = 16; // 2 × 8pt grid

    if (!isset($usedYPositions[$zoneKey])) {
        // Pierwsza warstwa w strefie
        $layer['y'] = $targetZone['y'];
        $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
    } else {
        // Kolejne warstwy - stack poniżej
        $layer['y'] = $usedYPositions[$zoneKey];
        $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
    }

    // Opcjonalnie: centruj w strefie
    $layerWidth = $layer['width'] ?? 200;
    $zoneWidth = $targetZone['width'] ?? 1000;
    $layer['x'] = $targetZone['x'] + (int)(($zoneWidth - $layerWidth) / 2);

    return $layer;
}
```

---

### Checklist dla przyszłych zmian

Przy każdej zmianie w pozycjonowaniu warstw sprawdź:

- [ ] Czy warstwy NIE są nakładane na siebie (sprawdź `$usedYPositions`)
- [ ] Czy tekst poza zdjęciem (w text zone) NIE jest przesuwany
- [ ] Czy busy zone jest przeliczane względem pozycji zdjęcia w szablonie
- [ ] Czy archetyp jest respektowany (hero_left = tekst po lewej)
- [ ] Czy spacing między warstwami to wielokrotność 8pt (np. 16px)

**NOWE w v1.4.0:**
- [ ] Czy "right zone" (x≥400) jest rozpoznawana i chroniona przed przeniesieniem na lewo
- [ ] Czy oryginalne pozycje X są zachowywane przy naprawie overlapów Y
- [ ] Czy visual weight jest kalkulowany LINEAR (fontSize/total), nie QUADRATIC
- [ ] Czy split_content archetype nie dodaje overlay'a (tekst jest na solid color)
- [ ] Czy stock photo queries nie zawierają "cheesy" przymiotników (happy, smiling)

---

## Troubleshooting

### Problem: AI generuje layers bez wymaganych pól

**Symptom:** Error "Cannot read property 'x' of undefined"

**Rozwiązanie:** Sprawdź system prompt - musi zawierać przykład struktury layer. Dodaj do promptu:

```
REQUIRED LAYER STRUCTURE:
{
  "name": "layer_name",  // REQUIRED
  "type": "text|textbox|image|rectangle|ellipse|line",  // REQUIRED
  "x": 80,  // REQUIRED, multiple of 8
  "y": 200,  // REQUIRED, multiple of 8
  "width": 400,  // REQUIRED, multiple of 8
  "height": 100,  // REQUIRED, multiple of 8
  "properties": { ... }  // REQUIRED, depends on type
}
```

### Problem: Tekst nachodzi na zdjęcie (focal point)

**Diagnoza:**
```bash
# Sprawdź czy mikroserwis działa
curl http://localhost:3334/health

# Sprawdź logi analizy
grep "focal_point" storage/logs/single-*.log
```

**Rozwiązanie:**
1. Upewnij się że `ImageAnalysisService::analyzeImage()` jest wywoływany
2. Sprawdź czy `applyArchetypeConstraints()` jest wywoływany
3. Sprawdź czy archetyp jest poprawnie wybrany na podstawie focal point

### Problem: CTA nie ma cienia (nie "pływa")

**Symptom:** Visual Critic issue: "CTA button lacks elevation"

**Rozwiązanie:** Sprawdź czy `ElevationService::applyElevationToLayers()` jest wywoływany. CTA (textbox) powinno automatycznie dostać elevation 3.

```php
// Sprawdź w logu
grep "elevation" storage/logs/single-*.log
```

### Problem: Headline za mały

**Symptom:** Visual Critic issue: "Headline too small (25px)"

**Rozwiązanie:** Premium wymaga minimum 39px dla headline. Sprawdź:
1. `TypographyHierarchyValidator::fixHierarchy()` powinno zwiększyć
2. `VisualCriticService::applyFixes()` też naprawia

### Problem: Brak overlay dla full-bleed image

**Symptom:** Visual Critic issue: "Full-bleed image with text needs overlay"

**Rozwiązanie:**
1. Archetyp `centered_minimal` powinien automatycznie mieć `requires_overlay: true`
2. `TextOverlayService::addTextOverlays()` dodaje overlay
3. AI powinno generować warstwę `overlay` z `opacity: 0.5-0.6`

### Problem: Unsplash/Pexels nie zwraca wyników

**Diagnoza:**
```bash
php artisan tinker --execute="
\$s = app(App\Services\UnsplashService::class);
var_dump(\$s->isConfigured());
\$r = \$s->searchPhotos('test', 1);
var_dump(\$r);
"
```

**Rozwiązanie:**
1. Sprawdź `.env` - `UNSPLASH_ACCESS_KEY` lub `PEXELS_API_KEY`
2. Sprawdź czy premium query nie jest zbyt specyficzne (fallback do oryginalnego)

### Problem: Mikroserwis image-analysis nie startuje

```bash
# Sprawdź logi
docker compose logs image-analysis

# Rebuild
docker compose build image-analysis --no-cache
docker compose up -d image-analysis

# Test
curl http://localhost:3334/health
```

---

## Znane problemy i rozwiązania

### 1. Layers z pustymi properties

**Problem:** AI generuje `"properties": {}` zamiast wymaganych właściwości.

**Rozwiązanie:** `getDefaultLayerProperties()` w `AiChatService` uzupełnia brakujące:

```php
$defaultProperties = $this->getDefaultLayerProperties($type, $properties);
```

### 2. fontSize nie ze skali modularnej

**Problem:** AI używa np. `fontSize: 32` zamiast `fontSize: 31`.

**Rozwiązanie:** `DesignTokensService::snapFontSize()` automatycznie snapuje:

```php
snapFontSize(32) → 31  // Najbliższa wartość ze skali
```

### 3. Tekst poza marginesami

**Problem:** Tekst bliżej niż 80px od krawędzi.

**Rozwiązanie:** `SelfCorrectionService::fixMargins()` naprawia:

```php
$standardMargin = 80;  // Wymuszane dla wszystkich tekstów
```

### 4. Brak CTA

**Problem:** AI nie wygenerował przycisku CTA.

**Rozwiązanie:** `SelfCorrectionService::addMissingCta()` dodaje domyślny:

```php
if (!$this->hasCta($layers)) {
    $layers = $this->addMissingCta($layers, $width, $height);
}
```

### 5. CTA za wysoko

**Problem:** CTA w środku szablonu zamiast na dole.

**Rozwiązanie:** `SelfCorrectionService::ensureCtaProminent()` przesuwa:

```php
if ($y < $templateHeight * 0.6) {
    $layer['y'] = $templateHeight - 120;  // 120px od dołu
}
```

### 6. Kontrast niewystarczający

**Problem:** Biały tekst na jasnym tle.

**Rozwiązanie:** `ContrastValidator::fixContrastIssues()` automatycznie naprawia. Jeśli kontrast < 4.5:1, zmienia kolor tekstu.

### 7. Visual Critic score < 75

**Problem:** Szablon nie przechodzi review.

**Rozwiązanie:** Sprawdź `$critique['issues']` i `$critique['suggestions']`. Najczęstsze problemy:
- Headline < 39px → zwiększ
- Brak cienia na CTA → dodaj elevation
- Brak overlay → dodaj warstwę overlay
- Tekst na focal point → przesuń do safe zone

---

## Pliki systemu (aktualne)

### Serwisy PHP

| Plik | Wersja | Opis |
|------|--------|------|
| `app/Services/AI/GridSnapService.php` | v1.0 | Siatka 8pt |
| `app/Services/AI/DesignTokensService.php` | v1.3 | Tokeny + vertical rhythm + tracking |
| `app/Services/AI/CompositionArchetypeService.php` | v1.2 | 5 archetypów |
| `app/Services/AI/ImageAnalysisService.php` | v1.2 | Klient mikroserwisu |
| `app/Services/AI/SelfCorrectionService.php` | v1.2 | 12-krokowy pipeline |
| `app/Services/AI/TypographyHierarchyValidator.php` | v1.0 | Hierarchia fontów |
| `app/Services/AI/ContrastValidator.php` | v1.0 | WCAG AA |
| `app/Services/AI/TextPositioningService.php` | v1.2 | Pozycjonowanie (80px margin) |
| `app/Services/AI/TextOverlayService.php` | v1.1 | Overlay dla tekstu |
| `app/Services/AI/VisualCriticService.php` | v1.3 | **NOWY** - Agent krytyka |
| `app/Services/AI/ElevationService.php` | v1.3 | **NOWY** - System cieni |
| `app/Services/AI/PremiumQueryBuilder.php` | v1.3 | **NOWY** - Zaawansowane query |
| `app/Services/AI/FormatService.php` | v1.3 | **NOWY** - Multi-format |
| `app/Services/UnsplashService.php` | v1.3 | **NOWY** - Klient Unsplash |

### Testy

| Plik | Testy |
|------|-------|
| `tests/Unit/Services/AI/PremiumQueryBuilderTest.php` | 17 |
| `tests/Unit/Services/AI/ElevationServiceTest.php` | 20 |
| `tests/Unit/Services/AI/FormatServiceTest.php` | 23 |
| `tests/Unit/Services/AI/VisualCriticServiceTest.php` | 8 |
| `tests/Unit/Services/AI/DesignTokensServiceTest.php` | 28 |
| (+ pozostałe istniejące testy) | ~50 |

---

## Aktualne Problemy i Kod do Analizy

Ta sekcja zawiera szczegółowy kod związany z problemami wykrytymi podczas ostatniego testu generacji.

---

### PROBLEM #1: Subtext pozycjonowany poza canvas (x: -32)

**Symptom z logu:**
```log
[STEP 13] Layer action created: Subtext
{"type":"text","position":{"x":-32,"y":848}}
```

**Podejrzewany kod:** `SelfCorrectionService::fixTextOverlap()`

```php
// app/Services/AI/SelfCorrectionService.php:166-298

protected function fixTextOverlap(array $layers, array $imageAnalysis): array
{
    $corrections = [];
    $busyZones = $imageAnalysis['busy_zones'] ?? [];
    $safeZones = $imageAnalysis['safe_zones'] ?? [];

    if (empty($busyZones)) {
        return ['layers' => $layers, 'corrections' => $corrections];
    }

    // Find photo layer to determine photo area
    $photoLayer = null;
    foreach ($layers as $layer) {
        $type = $layer['type'] ?? '';
        $name = strtolower($layer['name'] ?? '');
        if ($type === 'image' || str_contains($name, 'photo')) {
            $photoLayer = $layer;
            break;
        }
    }

    // If no photo, no need to fix overlaps (text isn't on photo)
    if (!$photoLayer) {
        Log::channel('single')->info('SelfCorrection: No photo layer found, skipping text overlap fix');
        return ['layers' => $layers, 'corrections' => $corrections];
    }

    $photoX = $photoLayer['x'] ?? 0;
    $photoY = $photoLayer['y'] ?? 0;
    $photoW = $photoLayer['width'] ?? 1080;
    $photoH = $photoLayer['height'] ?? 1080;

    // Scale busy zones to photo area (they come as full-image coordinates)
    $scaledBusyZones = $this->scaleBusyZonesToPhoto($busyZones, $photoLayer);

    // Track Y positions used in each safe zone to avoid overlap
    $usedYPositions = [];

    foreach ($layers as $layer) {
        $type = $layer['type'] ?? '';

        // Only check text layers
        if (!in_array($type, ['text', 'textbox'])) {
            $fixedLayers[] = $layer;
            continue;
        }

        $layerX = $layer['x'] ?? 0;
        $layerY = $layer['y'] ?? 0;
        $layerW = $layer['width'] ?? 200;
        $layerH = $layer['height'] ?? 50;

        // CRITICAL: Check if text is actually ON the photo area
        $onPhoto = $this->rectanglesOverlap(
            $layerX, $layerY, $layerW, $layerH,
            $photoX, $photoY, $photoW, $photoH
        );

        // If text is NOT on photo, don't move it (it's in the text zone)
        if (!$onPhoto) {
            $fixedLayers[] = $layer;
            continue;
        }

        // Only for text ON photo: check busy zones
        if ($this->layerOverlapsBusyZone($layer, $scaledBusyZones)) {
            $bestZone = $this->findBestSafeZone($layer, $safeZones, $scaledBusyZones);

            if ($bestZone) {
                $zoneKey = $bestZone['position'] ?? 'default';
                $layerHeight = $layer['height'] ?? 50;
                $spacing = 16;

                // Calculate Y position
                if (!isset($usedYPositions[$zoneKey])) {
                    $layer['y'] = $bestZone['y'];
                    $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
                } else {
                    $layer['y'] = $usedYPositions[$zoneKey];
                    $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
                }

                // ============================================================
                // PROBLEM MOŻE BYĆ TUTAJ - obliczanie X
                // ============================================================
                $layerWidth = $layer['width'] ?? 200;
                $zoneWidth = $bestZone['width'] ?? 1000;

                if ($layerWidth <= $zoneWidth) {
                    // Layer fits in zone - center it
                    // POTENCJALNY BUG: jeśli $bestZone['x'] jest ujemne lub
                    // ($zoneWidth - $layerWidth) / 2 daje ujemny wynik
                    // gdy $bestZone['x'] < 0
                    $layer['x'] = $bestZone['x'] + (int)(($zoneWidth - $layerWidth) / 2);
                } else {
                    // Layer wider than zone - align to zone start with margin
                    $layer['x'] = max(40, $bestZone['x']);
                }

                // Ensure X is never negative
                // TO POWINNO CHRONIĆ, ALE CZY JEST WYWOŁYWANE?
                $layer['x'] = max(0, $layer['x']);
            }
        }

        $fixedLayers[] = $layer;
    }

    return ['layers' => $fixedLayers, 'corrections' => $corrections];
}
```

**Możliwe przyczyny x: -32:**
1. Safe zone z mikroserwisu ma `x < 0` (błędnie skalowana)
2. `$bestZone['x']` jest ujemne przed obliczeniem
3. Wartość jest nadpisywana przez inny serwis PÓŹNIEJ w pipeline (np. GridSnapService snap do -32 = najbliższa wielokrotność 8 od -28?)
4. `max(0, $layer['x'])` jest obchodzony przez inną metodę

**SPRAWDŹ:** Logowanie `$bestZone` w `findBestSafeZone()` - jaka jest wartość X?

---

### PROBLEM #2: Overlay ponad tekstem (złe sortowanie z-order)

**Symptom z logu:**
```log
[STEP 11] Layer order sorted
{"order_before":["Background","Photo","Overlay","Headline","Subtext","CTA_Button"]}
{"order_after":["Background","Photo","Overlay","Headline","Subtext","CTA_Button"]}
// Overlay jest PRZED Headline - to źle! Overlay przykryje tekst
```

**Podejrzewany kod:** `TemplateValidator::sortLayersByZOrder()`

```php
// app/Services/AI/TemplateValidator.php:519-563

public function sortLayersByZOrder(array $layers): array
{
    $order = [
        'background' => 0,
        'bg' => 0,
        'photo' => 1,
        'image' => 1,
        'accent' => 2,
        'line' => 2,
        'shape' => 2,
        'decoration' => 2,
        'overlay' => 3,      // ← OVERLAY ma priorytet 3
        'subtext' => 4,      // ← SUBTEXT ma priorytet 4
        'text' => 4,         // ← TEXT ma priorytet 4
        'headline' => 5,     // ← HEADLINE ma priorytet 5
        'title' => 5,
        'cta' => 6,
        'button' => 6,
    ];

    usort($layers, function ($a, $b) use ($order) {
        $nameA = strtolower($a['name'] ?? '');
        $nameB = strtolower($b['name'] ?? '');
        $typeA = strtolower($a['type'] ?? '');
        $typeB = strtolower($b['type'] ?? '');

        $orderA = 3; // default middle
        $orderB = 3;

        // ============================================================
        // PROBLEM: używa min() więc jeśli nazwa zawiera wiele keywords,
        // bierze NAJNIŻSZY priorytet (najbardziej "na dole")
        // ============================================================
        foreach ($order as $keyword => $priority) {
            if (str_contains($nameA, $keyword) || $typeA === $keyword) {
                $orderA = min($orderA, $priority);
            }
            if (str_contains($nameB, $keyword) || $typeB === $keyword) {
                $orderB = min($orderB, $priority);
            }
        }

        return $orderA <=> $orderB;
    });

    return $layers;
}
```

**ANALIZA:**

Teoretycznie kolejność powinna być poprawna:
- Overlay (priorytet 3) powinien być PRZED (niżej w warstwie) niż Headline (priorytet 5)
- W systemie warstw, niższy indeks = niższa warstwa (tło)

**MOŻLIWY BUG:**

Algorytm sortuje od NAJNIŻSZEGO priorytetu do NAJWYŻSZEGO, więc:
- `[Background(0), Photo(1), Overlay(3), Subtext(4), Headline(5), CTA(6)]`

To JEST poprawne dla renderowania gdzie:
- Background jest renderowany PIERWSZY (na dole)
- CTA jest renderowany OSTATNI (na wierzchu)

**ALE:** Sprawdź jak frontend interpretuje tę kolejność! Jeśli frontend renderuje tablicę od końca, to Overlay przykryje tekst.

**ALTERNATYWNA HIPOTEZA:**

Overlay ma `opacity: 0.5` więc POWINIEN przepuszczać tekst... chyba że:
1. Frontend renderuje w złej kolejności
2. Opacity nie jest stosowane

---

### PROBLEM #3: Tekst w języku angielskim mimo polskiego inputu

**Symptom z logu:**
```log
User message: "nowy grafika z siłownią"
AI Output: {"text":"Elevate Your Workout"}  // ANGIELSKI!
```

**Podejrzewany kod:** System prompt w `AiChatService::buildSystemPrompt()`

```php
// app/Services/AiChatService.php:251-320 (fragment)

protected function buildSystemPrompt(Template $template): string
{
    $context = $this->contextService->getSimplifiedContext($template);
    // ...

    return <<<PROMPT
You are a professional template designer using a DESIGN SYSTEM.

TEMPLATE: {$context}
// ...

################################################################################
#                    COMPOSITION ARCHETYPES
################################################################################

Choose ONE archetype based on image focal point:

1. hero_left: Text left column (40%), photo right (60%)
   - Use when: focal point is on RIGHT side of image
   - Text zone: x=80, y=200, width=400
// ...
PROMPT;
}
```

**BRAK INSTRUKCJI O JĘZYKU!**

System prompt nie zawiera informacji:
1. W jakim języku pisać tekst
2. Że powinien respektować język użytkownika
3. Że brand może mieć preferowany język

**ROZWIĄZANIE:**

Dodać do system prompt:

```php
// Dodaj po "You are a professional template designer..."

LANGUAGE RULES:
- ALWAYS write text content (headlines, subtext, CTA) in the SAME LANGUAGE as the user message
- If user writes in Polish, generate Polish text
- If user writes in English, generate English text
- NEVER translate or change the language unless explicitly asked
```

---

### PROBLEM #4: VisualCritic score 74.5 (próg 75)

**Symptom z logu:**
```log
[STEP 12] Visual Critic scores (attempt 1)
{"passed":false,"total_score":74.5,"issues":["integration:overlap"]}
```

**Kod oceniający:** `VisualCriticService::critique()`

```php
// app/Services/AI/VisualCriticService.php (fragment)

public function critique(array $layers, array $imageAnalysis, int $width, int $height): array
{
    $scores = [
        'typography_hierarchy' => $this->evaluateTypography($layers),
        'composition_balance' => $this->evaluateComposition($layers, $width, $height),
        'color_harmony' => $this->evaluateColorHarmony($layers, $imageAnalysis),
        'depth_and_shadow' => $this->evaluateElevation($layers),
        'image_text_integration' => $this->evaluateIntegration($layers, $imageAnalysis, $width, $height),
    ];

    $weights = [
        'typography_hierarchy' => 0.20,
        'composition_balance' => 0.25,
        'color_harmony' => 0.15,
        'depth_and_shadow' => 0.15,
        'image_text_integration' => 0.25,  // ← WYSOKA WAGA
    ];

    $totalScore = 0;
    foreach ($scores as $key => $score) {
        $totalScore += $score * $weights[$key];
    }

    $passed = $totalScore >= 75;  // ← PRÓG 75

    // ...
}
```

**Issue `integration:overlap`:**

```php
protected function evaluateIntegration(array $layers, array $imageAnalysis, int $width, int $height): int
{
    $score = 100;
    $issues = [];

    // Check for text overlapping focal point
    $busyZones = $imageAnalysis['busy_zones'] ?? [];

    foreach ($layers as $layer) {
        if (!in_array($layer['type'] ?? '', ['text', 'textbox'])) continue;

        foreach ($busyZones as $zone) {
            if ($this->rectanglesOverlap($layer, $zone)) {
                $score -= 25;  // ← DUŻA KARA
                $issues[] = "integration:overlap - {$layer['name']} overlaps busy zone";
            }
        }
    }

    return max(0, $score);
}
```

**MOŻLIWY PROBLEM:**

Busy zone z mikroserwisu jest ZBYT DUŻA i pokrywa cały canvas, więc każdy tekst "nachodzi" na busy zone mimo że w rzeczywistości jest w text zone.

**SPRAWDŹ:**
1. Wartości `busy_zones` w logu
2. Czy busy zone jest skalowana do pozycji photo layer
3. Czy sprawdzenie `$onPhoto` jest przed sprawdzeniem overlap

---

### PEŁNY PIPELINE - handleCreateFullTemplate()

```php
// app/Services/AiChatService.php:1046-1579

protected function handleCreateFullTemplate(array $arguments, Template $template): array
{
    // STEP 0: RAW INPUT FROM AI
    $layers = $arguments['layers'] ?? [];

    // STEP 1: IMAGE SEARCH & ANALYSIS
    // - PremiumQueryBuilder buduje query
    // - stockPhotoService wyszukuje zdjęcia
    // - PhotoRankingService wybiera najlepsze
    // - ImageAnalysisService analizuje (busy/safe zones, colors)

    // STEP 2: GRID SNAP (8pt)
    $layers = $this->gridSnapService->snapAllLayers($layers);

    // STEP 3: DESIGN TOKENS (modular scale)
    $layers = $this->designTokensService->snapAllLayersToTokens($layers);

    // STEP 3.5: VERTICAL RHYTHM & TRACKING
    $layers = $this->designTokensService->applyVerticalRhythmToLayers($layers);

    // STEP 4: TEMPLATE VALIDATOR
    $layers = $this->templateValidator->validateAndFix($layers, $currentWidth);

    // STEP 5: TYPOGRAPHY HIERARCHY
    $layers = $this->typographyValidator->fixHierarchy($layers);

    // STEP 6: CONTRAST VALIDATION
    $layers = $this->contrastValidator->fixContrastIssues($layers, $backgroundColor);

    // STEP 7: COMPLETENESS CHECK
    $missing = $this->templateValidator->checkCompleteness(...);
    if (!empty($missing)) {
        $layers = $this->templateValidator->addMissingElements(...);
    }

    // STEP 7.5: ARCHETYPE CONSTRAINTS
    $layers = $this->applyArchetypeConstraints($layers, $archetype, ...);

    // STEP 8: IMAGE ANALYSIS ADJUSTMENTS
    // ← TUTAJ może nastąpić niepoprawne przesunięcie tekstu
    $layers = $this->imageAnalysisService->adjustLayersToAnalysis($layers, $primaryAnalysis);

    // STEP 9: SELF-CORRECTION PASS
    // ← TUTAJ może nastąpić x: -32 (fixTextOverlap)
    $correctionResult = $this->selfCorrectionService->reviewAndCorrect(...);
    $layers = $correctionResult['layers'];

    // STEP 9.5: ELEVATION SHADOWS
    $layers = $this->elevationService->applyElevationToLayers($layers);

    // STEP 10: FINAL GRID SNAP
    // ← TUTAJ x: -32 może być "snapowane" do -32 (najbliższe do -28?)
    $layers = $this->gridSnapService->snapAllLayers($layers);

    // STEP 11: LAYER SORTING (z-order)
    // ← TUTAJ może być problem z kolejnością Overlay/Headline
    $layers = $this->templateValidator->sortLayersByZOrder($layers);

    // STEP 12: VISUAL CRITIC REVIEW (with retry loop)
    // Pętla: max 2 retries
    do {
        $critique = $this->visualCriticService->critique(...);
        if ($critique['passed']) break;
        if ($attempt >= $maxRetries) break;

        // Apply fixes
        $layers = $this->visualCriticService->applyFixes(...);
        // Re-run self-correction
        // Re-apply elevation
        // Final grid snap
        // Re-sort
    } while (true);

    // STEP 13: FINAL LAYER CREATION
    // Tworzy finalne akcje z layers
    foreach ($layers as $layer) {
        $actions[] = [
            'type' => 'add_layer',
            'data' => [
                'x' => $layer['x'] ?? 0,  // ← TUTAJ x: -32 idzie do outputu
                'y' => $layer['y'] ?? 0,
                // ...
            ],
        ];
    }

    return $actions;
}
```

---

### ZALECENIA DLA EKSPERTA

1. **Debuguj x: -32:**
   - Dodaj log przed KAŻDYM miejscem gdzie `$layer['x']` jest modyfikowane
   - Sprawdź wartość `$bestZone['x']` w `findBestSafeZone()`
   - Sprawdź czy `GridSnapService::snapToGrid(-28)` zwraca -32

2. **Debuguj sortowanie:**
   - Sprawdź jak frontend interpretuje kolejność layers
   - Czy renderuje od indeksu 0 (poprawnie) czy od końca (błędnie)?

3. **Napraw język:**
   - Dodaj explicit instrukcje o języku do system prompt

4. **Napraw busy zone:**
   - Busy zone powinna być skalowana TYLKO do obszaru photo layer
   - Nie powinna wykraczać poza photo layer

---

### ImageAnalysisService - drugie źródło x: -32

**Plik:** `app/Services/AI/ImageAnalysisService.php:329-378`

```php
/**
 * Move a layer to a safe zone with stacking support.
 * Multiple layers in the same zone will be stacked vertically.
 */
protected function moveLayerToSafeZoneWithStacking(
    array $layer,
    array $safeZones,
    string $preferredPosition,
    array &$usedYPositions
): array {
    // Find a safe zone matching the preferred position
    $targetZone = null;

    foreach ($safeZones as $zone) {
        if (str_contains($zone['position'] ?? '', $preferredPosition)) {
            $targetZone = $zone;
            break;
        }
    }

    // If no match, use the first safe zone
    if (!$targetZone && !empty($safeZones)) {
        $targetZone = $safeZones[0];
    }

    if ($targetZone) {
        $zoneKey = $targetZone['position'] ?? 'default';
        $layerHeight = $layer['height'] ?? 50;
        $spacing = 16;

        // Calculate Y position with stacking
        if (!isset($usedYPositions[$zoneKey])) {
            $layer['y'] = $targetZone['y'];
            $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
        } else {
            $layer['y'] = $usedYPositions[$zoneKey];
            $usedYPositions[$zoneKey] = $layer['y'] + $layerHeight + $spacing;
        }

        // ============================================================
        // POTENCJALNY BUG: brak sprawdzenia czy wynik jest ujemny!
        // ============================================================
        $layerWidth = $layer['width'] ?? 200;
        $zoneWidth = $targetZone['width'] ?? 1000;

        // Jeśli $targetZone['x'] = -80 i $zoneWidth = 1000, $layerWidth = 920
        // to: $layer['x'] = -80 + (1000 - 920) / 2 = -80 + 40 = -40
        // GridSnapService później zamieni -40 na -40 (wielokrotność 8)
        $layer['x'] = $targetZone['x'] + (int) (($zoneWidth - $layerWidth) / 2);

        // BRAK: $layer['x'] = max(0, $layer['x']);  ← NIE MA TEJ OCHRONY!

        // Adjust width if needed
        if ($layerWidth > $zoneWidth) {
            $layer['width'] = $zoneWidth;
        }
    }

    return $layer;
}
```

**PROBLEM:**

Metoda `moveLayerToSafeZoneWithStacking()` NIE sprawdza czy obliczona wartość X jest ujemna!

Jeśli safe_zone z mikroserwisu ma ujemny X (błędnie skalowana) lub jeśli `$targetZone['x']` + offset daje ujemną wartość, to warstwa dostanie negatywne X.

**ROZWIĄZANIE:**

```php
$layer['x'] = $targetZone['x'] + (int) (($zoneWidth - $layerWidth) / 2);
$layer['x'] = max(0, $layer['x']);  // ← DODAJ TĘ LINIĘ
```

---

### GridSnapService - możliwe źródło x: -32

```php
// app/Services/AI/GridSnapService.php

class GridSnapService
{
    protected int $gridSize = 8;

    public function snapToGrid(int $value): int
    {
        // STANDARDOWY SNAP: round() może zaokrąglić ujemne wartości w dół
        // np. snapToGrid(-1) = round(-1/8) * 8 = round(-0.125) * 8 = 0 * 8 = 0
        // np. snapToGrid(-5) = round(-5/8) * 8 = round(-0.625) * 8 = -1 * 8 = -8
        // np. snapToGrid(-28) = round(-28/8) * 8 = round(-3.5) * 8 = -4 * 8 = -32 ← !!!
        return (int) (round($value / $this->gridSize) * $this->gridSize);
    }

    public function snapAllLayers(array $layers): array
    {
        foreach ($layers as &$layer) {
            $layer['x'] = $this->snapToGrid($layer['x'] ?? 0);  // ← -28 → -32
            $layer['y'] = $this->snapToGrid($layer['y'] ?? 0);
            // ...
        }
        return $layers;
    }
}
```

**POTWIERDZENIE:** Jeśli warstwa ma x = -28 (z SelfCorrectionService), to GridSnapService zamieni to na -32.

**ROZWIĄZANIE:** Dodać `max(0, ...)` w GridSnapService:

```php
public function snapToGrid(int $value): int
{
    $snapped = (int) (round($value / $this->gridSize) * $this->gridSize);
    return max(0, $snapped);  // Nigdy nie zwracaj ujemnych
}
```

---

## Changelog

### v1.4.1 (Technology Query Fix & Visual Balance) - CURRENT

#### Krytyczne poprawki dla branży technologicznej

- **NOWE:** `PROBLEMATIC_QUERY_WORDS` - słowa "website", "screenshot", "code" zastępowane abstrakcjami
- **NOWE:** `ABSTRACT_TECH_REPLACEMENTS` - czyste alternatywy: "abstract technology background dark blue", "silk waves technology futuristic"
- **NOWE:** `buildTechnologyQuery()` - automatycznie wykrywa literalne terminy tech i przełącza na abstrakcje
- **ZMIANA:** Technology industry modifiers: `['abstract dark', 'clean glass architecture', 'silk waves', 'neon glow', 'futuristic minimal']`

#### Pozycjonowanie Accent Line

- **NAPRAWIONE:** Accent line teraz pozycjonowana 24px NAD headline (nie pod)
- **ZMIANA:** Accent line wyrównana do lewej z headline (nie centrowana)
- **ZMIANA:** Fallback pozycja: górny kwartał (25%) zamiast środka (52%)

#### Visual Weight Balance (70-20-10)

- **ZMIANA:** CTA font size zmniejszone z 31px do 20px (modular scale 'md')
- **ZMIANA:** CTA min width zmniejszone z 320px do 280px
- **ZMIANA:** CTA padding multiplier zwiększony z 0.8 do 1.0 (kompaktowy przycisk)
- **NOWE:** Max CTA font size cap (25px) - większe są redukowane
- **REZULTAT:** Visual weight zmieniony z 52-21-26 na ~58-24-18

| Metryka | Przed | Po | Target |
|---------|-------|-----|--------|
| Headline | 52.1% | ~58% | 70% |
| Subtext | 21.4% | ~24% | 20% |
| CTA | 26.5% | ~18% | 10% |

---

### v1.4.0 (Archetype Zone Preservation & Premium Fixes)

#### Krytyczne poprawki pozycjonowania

- **NAPRAWIONE:** TextPositioningService już nie resetuje X pozycji do marginesu (zachowuje archetype text zones)
- **NAPRAWIONE:** SelfCorrectionService respektuje "right zone" (x≥400) dla split layouts
- **NAPRAWIONE:** Margin fix nie przenosi tekstu z prawej strefy na lewo (co powodowało nakładanie na zdjęcie)
- **NOWE:** `split_content` archetype - czysty podział 50/50 (zdjęcie | solid color + tekst)

#### Poprawki Visual Weight

- **NAPRAWIONE:** VisualCriticService używa teraz LINEAR formuły (fontSize/total) zamiast QUADRATIC (fontSize²)
- **NAPRAWIONE:** Spójna kalkulacja visual weight między TypographyHierarchyValidator i VisualCriticService
- **NAPRAWIONE:** Headline 61px + Subtext 20px teraz poprawnie pokazuje ~60% zamiast 91%

#### Premium Photo Queries

- **ZMIANA:** Fitness industry modifiers: `['dark background aesthetic', 'minimalist gym', 'professional equipment', 'moody lighting']`
- **NOWE:** `CLEAN_BACKGROUND_MODIFIERS` dla split layouts
- **NOWE:** `buildCleanBackgroundQuery()` dla split_content archetype
- **NOWE:** `CHEESY_ADJECTIVES` filter - usuwa "happy", "smiling" etc. z queries

#### CTA & Typography

- **NAPRAWIONE:** Proporcjonalne wymiary CTA: `height = padding * 2 + fontSize`
- **ZMIANA:** CTA fontSize 31px (2xl) zamiast 39px
- **ZMIANA:** CTA padding = fontSize × 0.8
- **NOWE:** Headline max size cap (61px) w TypographyHierarchyValidator
- **NOWE:** `balanceVisualWeight()` - automatyczny boost subtext gdy headline >85%

#### Gradient Overlays

- **ZMIANA:** Gradient coverage zmniejszone z 50% do 20-35%
- **ZMIANA:** Gradient buffer zmniejszony z 50px do 20px
- **NOWE:** FULL OVERLAY MODE gdy busy_zone >70% - nie przesuwa tekstu

### v1.3.1 (Debug Logging)

- **NOWE:** Kompleksowe logowanie każdego kroku pipeline'u
- **NOWE:** Szczegółowa inspekcja warstw AI (wykrywanie problemów)
- **NOWE:** Logowanie before/after dla każdej transformacji
- **NOWE:** Pipeline summary z czasem wykonania i statystykami
- **NOWE:** Pełny JSON output dla debugowania

### v1.3.0 (Premium Quality)

- **NOWE:** `VisualCriticService` - agent krytyka z oceną 0-100
- **NOWE:** `ElevationService` - system cieni (6 poziomów)
- **NOWE:** `PremiumQueryBuilder` - zaawansowane query do API zdjęć
- **NOWE:** `FormatService` - wsparcie 4:5, 3:4, 9:16, 16:9
- **NOWE:** `UnsplashService` - alternatywne źródło zdjęć
- **NOWE:** Vertical Rhythm - line-height snapped do baseline grid 8pt
- **NOWE:** Letter Tracking - automatyczny tracking bazujący na fontSize
- **ZMIANA:** CTA automatycznie dostaje elevation 3 (floating button)
- **ZMIANA:** cornerRadius dla CTA domyślnie 500 (pill shape)
- **ZMIANA:** Pipeline rozszerzony do 15 kroków

### v1.2.0 (Professional Compositions)

- `CompositionArchetypeService` - 5 archetypów
- Branżowe pary fontów
- Ekstrakcja kolorów (node-vibrant)
- Margines 80px

### v1.1.0 (Text Readability)

- `TextPositioningService`
- `TextOverlayService`
- Mandatory CTA enforcement

### v1.0.0 (Initial)

- GridSnapService
- DesignTokensService
- ContrastValidator
- ImageAnalysisService
