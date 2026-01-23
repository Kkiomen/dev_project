# Plan: Dokumentacja + Migracja na Vue.js + Docker

**Status**: Zrealizowany
**Data utworzenia**: 2026-01-23
**Data realizacji**: 2026-01-23

---

## Podsumowanie

Trzy zrealizowane cele:
1. **Dokumentacja** - Opis wszystkich endpointow API i funkcjonalnosci panelu w `/docs`
2. **Vue.js** - Przepisanie frontendu z Alpine.js na Vue 3
3. **Docker** - Automatyczne budowanie assetow przy restarcie kontenera

---

## Czesc 1: Dokumentacja - ZREALIZOWANA

### Utworzone pliki

- [x] `docs/README.md` - Przeglad projektu
- [x] `docs/api/README.md` - Przeglad API, autentykacja
- [x] `docs/api/bases.md` - Endpointy baz danych
- [x] `docs/api/tables.md` - Endpointy tabel
- [x] `docs/api/fields.md` - Endpointy pol
- [x] `docs/api/rows.md` - Endpointy wierszy
- [x] `docs/api/cells.md` - Endpointy komorek
- [x] `docs/api/attachments.md` - Endpointy zalacznikow
- [x] `docs/panel/README.md` - Przeglad panelu
- [x] `docs/panel/grid-view.md` - Funkcje widoku Grid
- [x] `docs/panel/kanban-view.md` - Funkcje widoku Kanban
- [x] `docs/development.md` - Poradnik dla developerow

---

## Czesc 2: Migracja na Vue.js - ZREALIZOWANA

### Zainstalowane pakiety

```json
{
  "dependencies": {
    "vue": "^3.5.27",
    "pinia": "^3.0.4",
    "vue-router": "^4.6.4",
    "vuedraggable": "^4.1.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^6.0.3"
  }
}
```

### Zmodyfikowane pliki

- [x] `vite.config.js` - Dodanie pluginu Vue
- [x] `resources/js/app.js` - Inicjalizacja Vue zamiast Alpine
- [x] `routes/web.php` - SPA routing

### Utworzona struktura Vue.js

```
resources/js/
├── app.js                      # Inicjalizacja Vue
├── App.vue                     # Root component
├── router/
│   └── index.js                # Konfiguracja routera
├── stores/
│   ├── auth.js                 # Stan uzytkownika
│   ├── bases.js                # CRUD baz
│   ├── tables.js               # CRUD tabel
│   ├── fields.js               # Zarzadzanie polami
│   ├── rows.js                 # Zarzadzanie wierszami
│   └── cells.js                # Cache wartosci komorek
├── composables/
│   ├── useApi.js               # Wrapper API
│   ├── useCell.js              # Logika edycji komorek
│   ├── useKeyboard.js          # Nawigacja klawiatura
│   ├── useResize.js            # Resize kolumn
│   └── useDragDrop.js          # Drag & drop
├── components/
│   ├── common/                 # Modal, Dropdown, Button, Input, LoadingSpinner
│   ├── layout/                 # AppLayout, Navigation
│   ├── grid/                   # GridTable, GridRow, GridCell, GridHeader, cells/*
│   ├── kanban/                 # KanbanBoard, KanbanColumn, KanbanCard, CardModal
│   └── base/                   # BaseCard, CreateBaseModal
└── pages/
    ├── Dashboard.vue
    ├── BaseView.vue
    ├── TableGridView.vue
    └── TableKanbanView.vue
```

### Komponenty komorek

- [x] `components/grid/cells/TextCell.vue`
- [x] `components/grid/cells/NumberCell.vue`
- [x] `components/grid/cells/DateCell.vue`
- [x] `components/grid/cells/CheckboxCell.vue`
- [x] `components/grid/cells/SelectCell.vue`
- [x] `components/grid/cells/MultiSelectCell.vue`
- [x] `components/grid/cells/AttachmentCell.vue`
- [x] `components/grid/cells/UrlCell.vue`
- [x] `components/grid/cells/JsonCell.vue`

---

## Czesc 3: Docker - ZREALIZOWANA

### Zmodyfikowane pliki

- [x] `docker/8.5/start-container` - Automatyczne budowanie assetow
- [x] `compose.yaml` - Dodanie zmiennej VITE_DEV_MODE

### Dzialanie

Przy starcie kontenera:
1. Sprawdza czy istnieje `package.json`
2. Jesli brak `node_modules` lub `package.json` jest nowszy - uruchamia `npm install`
3. Jesli NIE ustawiono `VITE_DEV_MODE` - uruchamia `npm run build`
4. Jesli ustawiono `VITE_DEV_MODE=true` - pomija build (dla development z HMR)

### Uzycie

```bash
# Produkcja (automatyczny build)
docker compose down && docker compose up -d

# Development (reczny npm run dev)
VITE_DEV_MODE=true docker compose up -d
npm run dev
```

---

## Weryfikacja

### Po dokumentacji
- [x] Wszystkie endpointy API opisane
- [x] Przyklady request/response
- [x] Funkcje panelu udokumentowane

### Po migracji Vue
- [x] Struktura komponentow utworzona
- [x] Stores Pinia zaimplementowane
- [x] Routing Vue skonfigurowany
- [x] Blade template SPA utworzony

### Po Docker
- [x] Modyfikacja start-container
- [x] Dodanie VITE_DEV_MODE do compose.yaml

---

## Nastepne kroki (opcjonalne)

1. Testy E2E dla Vue komponentow
2. Optymalizacja lazy loading
3. PWA support
4. Usuwanie legacy Blade templates
