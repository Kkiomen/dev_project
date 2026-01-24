# Edytor Graficzny (Canvas)

Edytor graficzny oparty na Konva.js do tworzenia szablonów graficznych dla social media.

## Architektura

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        GraphicsEditorPage.vue                            │
│   Wrapper strony, ładuje template z API                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                          GraphicsEditor.vue                              │
│   Główny komponent edytora                                              │
│ ┌───────────────────────────────────────────────────────────────────┐   │
│ │                       EditorToolbar.vue                            │   │
│ │   [Select] [Text] [Image] [Rect] [Ellipse] | Undo Redo | Zoom     │   │
│ ├───────────┬───────────────────────────────────────────┬───────────┤   │
│ │ Layers    │                                           │ Properties│   │
│ │ Panel     │         EditorCanvas.vue                  │ Panel     │   │
│ │           │                                           │           │   │
│ │ ┌───────┐ │    ┌─────────────────────────────┐       │ Position  │   │
│ │ │Layer 3│ │    │                             │       │ x: 100    │   │
│ │ │Layer 2│ │    │      Konva Stage            │       │ y: 200    │   │
│ │ │Layer 1│ │    │      (canvas)               │       │           │   │
│ │ └───────┘ │    │                             │       │ Size      │   │
│ │           │    └─────────────────────────────┘       │ w: 300    │   │
│ │           │                                           │ h: 150    │   │
│ ├───────────┴───────────────────────────────────────────┴───────────┤   │
│ │                        AiChatPanel.vue                             │   │
│ │   Panel czatu AI do modyfikacji warstw                            │   │
│ └───────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Model danych

### Template (Backend)

```
Template
 ├── id (integer) - wewnętrzne
 ├── public_id (ULID) - publiczne
 ├── user_id
 ├── name
 ├── description
 ├── width, height (px)
 ├── background_color
 ├── thumbnail_url
 ├── is_library (bool) - czy w bibliotece publicznej
 ├── library_template_id - powiązanie z szablonem biblioteki
 │
 └── Layer[] (hasMany, ordered by position)
      ├── type (text, image, rectangle, ellipse, textbox)
      ├── name
      ├── x, y, width, height
      ├── rotation (degrees)
      ├── position (z-index)
      ├── visible, locked
      └── properties (json)
           ├── text (dla text/textbox)
           ├── fontSize, fontFamily, fontWeight
           ├── fill, stroke, strokeWidth
           ├── opacity
           ├── cornerRadius (dla rectangle)
           ├── image_url (dla image)
           └── ...
```

### Typy warstw

| Typ | Opis | Właściwości specyficzne |
|-----|------|-------------------------|
| `text` | Tekst | fontSize, fontFamily, fontWeight, fill, textAlign |
| `textbox` | Tekst z ramką | jak text + background, padding |
| `image` | Obrazek | image_url, objectFit |
| `rectangle` | Prostokąt | fill, stroke, cornerRadius |
| `ellipse` | Elipsa/koło | fill, stroke |

---

## Store (Pinia)

### `useGraphicsStore`

```javascript
// stores/graphics.js
state: {
    templates: [],        // Lista szablonów użytkownika
    currentTemplate: null,// Aktualnie edytowany
    layers: [],           // Warstwy aktualnego szablonu
    selectedLayerId: null,
    selectedLayerIds: [], // Multi-select (Shift+click)
    fonts: [],            // Dostępne fonty
    zoom: 1,              // Poziom zoomu (0.1 - 5)
    tool: 'select',       // select, text, image, rectangle, ellipse
    history: [],          // Undo/redo stack
    historyIndex: -1,
    isDirty: false,       // Niezapisane zmiany
    clipboard: null,      // Skopiowana warstwa
    chatPanelOpen: false, // Panel AI
    lastSavedAt: null,
}

getters: {
    selectedLayer,        // Aktualnie zaznaczona warstwa
    selectedLayers,       // Zaznaczone warstwy (multi)
    hasMultipleSelection,
    sortedLayers,         // Posortowane po position
    canUndo, canRedo,
}

actions: {
    // Templates
    fetchTemplates()
    fetchTemplate(id)
    createTemplate(data)
    updateTemplate(id, data)
    deleteTemplate(id)
    duplicateTemplate(id)

    // Layers
    addLayer(type, props)
    updateLayer(id, changes)      // Z zapisem do API
    updateLayerLocally(id, changes) // Tylko lokalna aktualizacja
    deleteLayer(id)
    reorderLayer(id, newPosition)
    saveAllLayers()               // Batch save

    // Selection
    selectLayer(id, addToSelection)
    deselectLayer()
    selectAllLayers()

    // Clipboard
    copyLayer()
    pasteLayer()
    duplicateLayer()

    // Z-order
    bringToFront()
    sendToBack()
    bringForward()
    sendBackward()

    // Alignment
    alignLeft(), alignCenterH(), alignRight()
    alignTop(), alignCenterV(), alignBottom()

    // Tools & Zoom
    setTool(tool)
    setZoom(zoom)
    zoomIn(), zoomOut(), resetZoom()

    // History
    saveToHistory()
    undo(), redo()

    // AI Chat
    toggleChatPanel()
    openChatPanel(), closeChatPanel()
}
```

---

## Komponenty

### GraphicsEditor.vue

Główny komponent edytora.

```vue
<template>
    <EditorToolbar />
    <div class="editor-main">
        <LayersPanel v-if="showLayersPanel" />
        <EditorCanvas :template="template" />
        <PropertiesPanel
            v-if="showPropertiesPanel"
            :style="{ width: propertiesPanelWidth + 'px' }"
        />
    </div>
    <AiChatPanel v-if="graphicsStore.chatPanelOpen" />

    <!-- Modals -->
    <ExportModal v-if="showExportModal" />
    <FontUploadModal v-if="showFontModal" />
    <TemplateLibraryModal v-if="showLibraryModal" />
    <AddToLibraryModal v-if="showAddToLibraryModal" />
</template>
```

**Funkcje:**
- Auto-save co 30 sekund
- Resizable properties panel
- Integracja z biblioteką szablonów
- Export do PNG/JPEG

### EditorCanvas.vue

Canvas oparty na Konva.js.

```javascript
// Główne elementy
const stageRef = ref(null);       // Konva.Stage
const transformerRef = ref(null); // Konva.Transformer
const guidesLayerRef = ref(null); // Linie pomocnicze (snapping)

// Snapping
const SNAP_THRESHOLD = 8; // px
const guides = ref({ vertical: [], horizontal: [] });

// Pan & Zoom
const panOffset = ref({ x: 0, y: 0 });
const isPanning = ref(false);
const isSpacePressed = ref(false);
```

**Funkcje:**
- Renderowanie warstw jako Konva shapes
- Transformer do skalowania/obracania
- Snapping do krawędzi i środka canvas
- Pan (Space + drag) i Zoom (scroll)
- Drag & drop obrazków z zewnątrz
- Kontekstowe menu (prawy klik)
- Edycja tekstu inline

### LayersPanel.vue

Panel warstw (z-order).

```
┌─────────────────────────────┐
│ Warstwy                  [+]│
├─────────────────────────────┤
│ 👁 🔒 📝 Text Layer 3     │ ← Najwyżej (position: 3)
│ 👁 🔒 🖼 Image Layer 2    │
│ 👁 🔒 ⬜ Rectangle 1      │ ← Najniżej (position: 1)
└─────────────────────────────┘
```

**Funkcje:**
- Drag & drop do zmiany kolejności
- Toggle widoczności (👁)
- Toggle blokady (🔒)
- Podwójne kliknięcie → zmiana nazwy
- Prawy klik → menu kontekstowe

### PropertiesPanel.vue

Panel właściwości zaznaczonej warstwy.

```
┌─────────────────────────────┐
│ Properties                  │
├─────────────────────────────┤
│ Position                    │
│ X: [100    ] Y: [200    ]  │
│                             │
│ Size                        │
│ W: [300    ] H: [150    ]  │
│ 🔗 Lock aspect ratio        │
│                             │
│ Rotation: [45°         ]   │
│ Opacity:  [100%        ]   │
│                             │
│ ─── Text ───                │
│ Font: [Inter         ▼]   │
│ Size: [24            ]     │
│ Weight: [Bold        ▼]   │
│ Color: [████ #000000 ]     │
│ Align: [⬅] [⬌] [➡]       │
│                             │
│ ─── Background ───          │
│ Fill: [████ #FFFFFF  ]     │
│ Border: [████ #000   ]     │
│ Radius: [8           ]     │
└─────────────────────────────┘
```

**Sekcje (zależne od typu warstwy):**
- Position & Size (wszystkie)
- Rotation & Opacity (wszystkie)
- Text (text, textbox)
- Fill & Stroke (rectangle, ellipse, text)
- Image (image)
- Corner Radius (rectangle)

### EditorToolbar.vue

Pasek narzędzi.

```
┌────────────────────────────────────────────────────────────────────────┐
│ [←] Template Name                                                       │
│ [Select] [T Text] [🖼 Image] [□ Rect] [○ Ellipse] │ [↩] [↪] │ [50% ▼] │
└────────────────────────────────────────────────────────────────────────┘
```

**Narzędzia:**
- `select` - Zaznaczanie i przesuwanie
- `text` - Dodawanie tekstu (klik na canvas)
- `image` - Upload obrazka
- `rectangle` - Rysowanie prostokąta
- `ellipse` - Rysowanie elipsy

### AiChatPanel.vue

Panel czatu AI do modyfikacji warstw.

```
┌─────────────────────────────────────────┐
│ 🤖 AI Assistant                      [×]│
├─────────────────────────────────────────┤
│ ┌─────────────────────────────────────┐ │
│ │ User: Zmień kolor tekstu na czerwony│ │
│ └─────────────────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │
│ │ AI: Zmieniłem kolor tekstu na      │ │
│ │     #FF0000 dla warstwy "Title"    │ │
│ └─────────────────────────────────────┘ │
├─────────────────────────────────────────┤
│ [Wpisz polecenie...              ] [➤] │
└─────────────────────────────────────────┘
```

**Funkcje:**
- Modyfikacja warstw przez polecenia tekstowe
- Kontekst aktualnego szablonu i warstw
- Historia konwersacji

---

## Composables

### `useGoogleFonts`

Ładowanie fontów z Google Fonts.

```javascript
const { loadFont, loadedFonts, isLoading } = useGoogleFonts();

// Ładowanie fontu
await loadFont('Roboto', 400);
await loadFont('Open Sans', 700);
```

---

## API Endpoints

### Templates

| Method | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/templates` | Lista szablonów |
| POST | `/api/v1/templates` | Utwórz szablon |
| GET | `/api/v1/templates/{id}` | Pobierz szablon z warstwami |
| PUT | `/api/v1/templates/{id}` | Aktualizuj metadane |
| DELETE | `/api/v1/templates/{id}` | Usuń szablon |
| POST | `/api/v1/templates/{id}/duplicate` | Duplikuj |
| POST | `/api/v1/templates/{id}/export` | Export do obrazu |

### Layers

| Method | Endpoint | Opis |
|--------|----------|------|
| POST | `/api/v1/templates/{id}/layers` | Dodaj warstwę |
| PUT | `/api/v1/templates/{id}/layers` | Batch update warstw |
| PUT | `/api/v1/layers/{id}` | Aktualizuj warstwę |
| DELETE | `/api/v1/layers/{id}` | Usuń warstwę |
| POST | `/api/v1/layers/{id}/reorder` | Zmień position |

### Library

| Method | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/library/templates` | Szablony z biblioteki |
| POST | `/api/v1/templates/{id}/add-to-library` | Dodaj do biblioteki |
| POST | `/api/v1/templates/{id}/unlink-from-library` | Odłącz od biblioteki |
| POST | `/api/v1/library/templates/{id}/copy` | Kopiuj z biblioteki |

### AI Chat

| Method | Endpoint | Opis |
|--------|----------|------|
| POST | `/api/v1/templates/{id}/chat` | Wyślij polecenie AI |

---

## Renderowanie warstw (Konva)

### Mapowanie typ → Konva shape

```javascript
// EditorCanvas.vue
const renderLayer = (layer) => {
    switch (layer.type) {
        case 'text':
        case 'textbox':
            return new Konva.Text({
                text: layer.properties.text,
                fontSize: layer.properties.fontSize,
                fontFamily: layer.properties.fontFamily,
                fill: layer.properties.fill,
                // ...
            });
        case 'image':
            return new Konva.Image({
                image: loadedImage,
                // ...
            });
        case 'rectangle':
            return new Konva.Rect({
                fill: layer.properties.fill,
                stroke: layer.properties.stroke,
                cornerRadius: layer.properties.cornerRadius,
                // ...
            });
        case 'ellipse':
            return new Konva.Ellipse({
                radiusX: layer.width / 2,
                radiusY: layer.height / 2,
                fill: layer.properties.fill,
                // ...
            });
    }
};
```

### Snapping

```javascript
// Snap lines
const getCanvasSnapLines = () => ({
    vertical: [0, width / 2, width],     // lewo, środek, prawo
    horizontal: [0, height / 2, height], // góra, środek, dół
});

// Podczas drag
const snapValue = (value, snapLines) => {
    for (const line of snapLines) {
        if (Math.abs(value - line) < SNAP_THRESHOLD) {
            return { value: line, snapped: true };
        }
    }
    return { value, snapped: false };
};
```

---

## Skróty klawiaturowe

| Klawisz | Akcja |
|---------|-------|
| `V` | Narzędzie Select |
| `T` | Narzędzie Text |
| `R` | Narzędzie Rectangle |
| `E` | Narzędzie Ellipse |
| `Delete` / `Backspace` | Usuń zaznaczoną warstwę |
| `Ctrl+C` | Kopiuj warstwę |
| `Ctrl+V` | Wklej warstwę |
| `Ctrl+D` | Duplikuj warstwę |
| `Ctrl+Z` | Cofnij (Undo) |
| `Ctrl+Shift+Z` | Ponów (Redo) |
| `Ctrl+A` | Zaznacz wszystkie warstwy |
| `Space + drag` | Pan canvas |
| `Scroll` | Zoom in/out |
| `Ctrl+0` | Reset zoom do 100% |
| `Ctrl++` | Zoom in |
| `Ctrl+-` | Zoom out |
| `[` | Przesuń warstwę w dół (z-order) |
| `]` | Przesuń warstwę w górę (z-order) |
| `Shift+[` | Przesuń na sam dół |
| `Shift+]` | Przesuń na samą górę |
| `Shift+Click` | Multi-select warstw |
| `Escape` | Anuluj edycję / Deselect |

---

## Export

### Format eksportu

```javascript
// ExportModal.vue
const exportOptions = {
    format: 'png',      // png, jpeg
    quality: 1,         // 0-1 dla jpeg
    pixelRatio: 2,      // Retina (2x)
    backgroundColor: '#ffffff',
};

const exportImage = async () => {
    const dataURL = stageRef.value.toDataURL(exportOptions);
    // Download lub zwróć jako File
};
```

### Eksport server-side

```php
// POST /api/v1/templates/{id}/export
// Renderuje template do obrazu po stronie serwera
// Używa Puppeteer lub podobnego rozwiązania
```

---

## Historia (Undo/Redo)

```javascript
// Zapisywanie stanu
saveToHistory() {
    const state = {
        layers: JSON.parse(JSON.stringify(this.layers)),
        selectedLayerId: this.selectedLayerId,
    };

    // Usuń przyszłe stany jeśli jesteśmy w środku historii
    if (this.historyIndex < this.history.length - 1) {
        this.history = this.history.slice(0, this.historyIndex + 1);
    }

    this.history.push(state);
    this.historyIndex = this.history.length - 1;

    // Limit 50 stanów
    if (this.history.length > 50) {
        this.history.shift();
        this.historyIndex--;
    }
}

// Cofanie
undo() {
    if (this.historyIndex > 0) {
        this.historyIndex--;
        const state = this.history[this.historyIndex];
        this.layers = JSON.parse(JSON.stringify(state.layers));
    }
}
```

---

## Integracja z postami

### TemplatePickerModal

Wybór szablonu do dodania do posta.

```vue
<!-- W PostEditorPage.vue -->
<TemplatePickerModal
    v-if="showTemplatePickerModal"
    @select="handleTemplateSelect"
    @close="showTemplatePickerModal = false"
/>
```

### TemplateEditorModal

Edytor szablonu osadzony w modalu.

```vue
<TemplateEditorModal
    :template="selectedTemplate"
    :resume-template-id="resumeTemplateId"
    @add-to-post="handleAddTemplateToPost"
    @save-for-later="handleSaveTemplateForLater"
    @close="handleCloseTemplateEditor"
/>
```

**Flow:**
1. Użytkownik wybiera szablon z biblioteki
2. Otwiera się TemplateEditorModal z osadzonym GraphicsEditor
3. Użytkownik modyfikuje szablon
4. Klik "Dodaj do posta" → eksport do pliku → staged media
5. Lub "Zapisz na później" → sesja zapisana w localStorage

---

## Pliki

### Backend

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── TemplateController.php
│   │   ├── LayerController.php
│   │   └── LibraryController.php
│   ├── Requests/Api/
│   │   ├── StoreTemplateRequest.php
│   │   ├── UpdateTemplateRequest.php
│   │   ├── StoreLayerRequest.php
│   │   └── UpdateLayerRequest.php
│   └── Resources/
│       ├── TemplateResource.php
│       └── LayerResource.php
├── Models/
│   ├── Template.php
│   └── Layer.php
└── Services/
    ├── TemplateService.php
    └── TemplateExportService.php
```

### Frontend

```
resources/js/
├── pages/
│   ├── TemplatesPage.vue      # Lista szablonów
│   └── GraphicsEditorPage.vue # Strona edytora (wrapper)
├── components/graphics/
│   ├── GraphicsEditor.vue     # Główny komponent
│   ├── EditorCanvas.vue       # Canvas Konva.js
│   ├── EditorToolbar.vue      # Pasek narzędzi
│   ├── LayersPanel.vue        # Panel warstw
│   ├── PropertiesPanel.vue    # Panel właściwości
│   ├── FontPicker.vue         # Wybór fontu
│   ├── AiChatPanel.vue        # Panel AI
│   ├── TemplateLibraryModal.vue
│   ├── AddToLibraryModal.vue
│   ├── chat/
│   │   ├── ChatInput.vue
│   │   └── ChatMessage.vue
│   └── modals/
│       ├── ExportModal.vue
│       └── FontUploadModal.vue
├── stores/
│   └── graphics.js
└── composables/
    └── useGoogleFonts.js
```

---

## Zależności

```json
{
    "konva": "^9.x",  // Canvas rendering
    "vue-konva": "^3.x"  // Vue bindings (opcjonalne)
}
```

**Uwaga:** Projekt używa bezpośrednio Konva.js bez vue-konva dla większej kontroli.

---

## Auto-save

```javascript
// GraphicsEditor.vue
const AUTO_SAVE_INTERVAL = 30000; // 30 sekund

const startAutoSave = () => {
    autoSaveTimer = setInterval(async () => {
        if (graphicsStore.isDirty) {
            await graphicsStore.saveAllLayers();
        }
    }, AUTO_SAVE_INTERVAL);
};

onMounted(() => {
    startAutoSave();
});

onUnmounted(() => {
    clearInterval(autoSaveTimer);
    // Save on exit if dirty
    if (graphicsStore.isDirty) {
        graphicsStore.saveAllLayers();
    }
});
```

---

## Troubleshooting

### Warstwa nie wyświetla się
- Sprawdź `visible: true`
- Sprawdź `opacity > 0`
- Sprawdź czy warstwa nie jest poza canvas (x, y)
- Sprawdź z-order (position)

### Font nie ładuje się
- Sprawdź czy font jest w Google Fonts
- Sprawdź konsolę na błędy CORS
- Użyj fallback fontu (Arial, sans-serif)

### Snapping nie działa
- Sprawdź SNAP_THRESHOLD (domyślnie 8px)
- Upewnij się że drag layer nie jest zablokowany (locked: false)

### Performance issues
- Ogranicz liczbę warstw (max 50-100)
- Używaj mniejszych obrazków
- Zmniejsz pixelRatio przy eksporcie
