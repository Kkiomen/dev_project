# Video Manager — Architektura i dokumentacja

System do automatycznego tworzenia filmów z napisami (captionami) — upload, transkrypcja AI, edycja, renderowanie napisów, usuwanie ciszy, eksport.

---

## Spis treści

1. [Inspiracja i cel](#inspiracja-i-cel)
2. [Architektura](#architektura)
3. [Mikroserwisy Docker](#mikroserwisy-docker)
4. [Style napisów (Caption Styles)](#style-napisów-caption-styles)
5. [Backend Laravel](#backend-laravel)
6. [Frontend Vue](#frontend-vue)
7. [Przepływy (Workflows)](#przepływy-workflows)
8. [Konfiguracja](#konfiguracja)
9. [API Reference](#api-reference)
10. [Testowanie i debug](#testowanie-i-debug)
11. [Struktura plików](#struktura-plików)

---

## Inspiracja i cel

Funkcja inspirowana jest narzędziami takimi jak **CapCut**, **Opus Clip**, **Submagic** i **Vizard** — automatyczne dodawanie napisów do krótkich filmów (reels, shorts, TikTok). Kluczowe cechy:

- **Styl Hormozi** — gruby biały tekst z czarnym outline, wielkie litery, 3 słowa na linię (popularny na YouTube Shorts)
- **Styl MrBeast** — duży żółty tekst z czerwonym akcentem, 2 słowa na linię, styl miniaturek MrBeast
- **Styl Clean** — minimalistyczny biały tekst z cieniem, profesjonalny wygląd
- **Styl Bold** — ekstra gruby outline, wysoki kontrast
- **Styl Neon** — neonowy cyan z fioletowym outline, futurystyczny

Celem jest **zero-effort workflow**: upload → automatyczna transkrypcja → wybór stylu → render → download. Wszystko w jednym narzędziu, bez wychodzenia z panelu.

---

## Architektura

```
┌─────────────────────────────────────────────────────────────┐
│                      Frontend (Vue 3)                        │
│  VideoManagerPage → Dashboard / Library / Upload / Editor    │
│  Store: useVideoManagerStore (Pinia)                         │
│  WebSocket: Reverb (real-time status updates)                │
└──────────────────────┬──────────────────────────────────────┘
                       │ REST API
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    Laravel Backend                            │
│  VideoProjectController → Jobs → Services                    │
│                                                               │
│  ┌──────────────────┐  ┌───────────────────┐                │
│  │ TranscribeVideoJob│  │ RenderCaptionsJob │                │
│  │ (queue: default)  │  │ (queue: default)  │                │
│  └────────┬─────────┘  └────────┬──────────┘                │
│           │                      │                            │
│  ┌────────▼─────────┐  ┌────────▼──────────┐                │
│  │TranscriberService │  │VideoEditorService │                │
│  └────────┬─────────┘  └────────┬──────────┘                │
└───────────┼──────────────────────┼──────────────────────────┘
            │ HTTP                  │ HTTP
            ▼                      ▼
┌─────────────────┐      ┌──────────────────┐
│   Transcriber    │      │   Video Editor    │
│  (port 3340)     │      │   (port 3341)     │
│  faster-whisper  │      │   FFmpeg + ASS    │
│  Python/Flask    │      │   Python/Flask    │
└─────────────────┘      └──────────────────┘
```

### Komunikacja między komponentami

| Źródło | Cel | Protokół | Opis |
|--------|-----|----------|------|
| Frontend → Laravel | REST API | HTTP/JSON | CRUD, upload, trigger akcji |
| Laravel → Frontend | WebSocket | Reverb | Aktualizacje statusu w real-time |
| Laravel → Transcriber | HTTP | Multipart/JSON | Wysyłanie pliku audio do transkrypcji |
| Laravel → Video Editor | HTTP | Multipart/JSON | Wysyłanie wideo + konfiguracja do renderowania |

---

## Mikroserwisy Docker

### Transcriber (port 3340)

**Lokalizacja:** `docker/transcriber/`

Mikroserwis Python/Flask oparty na **faster-whisper** — zoptymalizowana wersja OpenAI Whisper do transkrypcji mowy.

**Endpointy:**

| Endpoint | Metoda | Opis |
|----------|--------|------|
| `/health` | GET | Healthcheck |
| `/transcribe` | POST | Transkrypcja z timestampami per-słowo |
| `/detect-language` | GET | Detekcja języka bez pełnej transkrypcji |

**Konfiguracja modelu Whisper:**

| Zmienna env | Domyślna | Opis |
|-------------|----------|------|
| `WHISPER_MODEL` | `base` | Rozmiar modelu: `tiny`, `base`, `small`, `medium`, `large` |
| `WHISPER_DEVICE` | `cpu` | Urządzenie: `cpu` lub `cuda` (GPU) |
| `WHISPER_COMPUTE_TYPE` | `int8` | Precyzja: `int8`, `float16`, `float32` |

**Odpowiedź transkrypcji:**

```json
{
    "language": "pl",
    "language_probability": 0.95,
    "duration": 120.5,
    "segments": [
        {
            "id": 0,
            "start": 0.0,
            "end": 2.5,
            "text": "Cześć wszystkim",
            "words": [
                { "word": "Cześć", "start": 0.0, "end": 0.5, "probability": 0.98 },
                { "word": "wszystkim", "start": 0.6, "end": 1.2, "probability": 0.99 }
            ]
        }
    ]
}
```

Timestampy per-słowo (`words`) są kluczowe — dzięki nim napisy synchronizują się z mową na poziomie pojedynczych słów, a nie całych zdań. To pozwala na efekt **karaoke** (podświetlanie aktualnie wypowiadanego słowa).

**Cechy:**
- VAD (Voice Activity Detection) — lepsza obsługa ciszy
- Lazy loading modelu — oszczędność pamięci
- Gunicorn z 2 workerami, timeout 300s

---

### Video Editor (port 3341)

**Lokalizacja:** `docker/video-editor/`

Mikroserwis Python/Flask oparty na **FFmpeg** do obróbki wideo. Napisy renderowane w formacie **ASS** (Advanced SubStation Alpha).

**Endpointy:**

| Endpoint | Metoda | Opis |
|----------|--------|------|
| `/health` | GET | Healthcheck (weryfikuje dostępność FFmpeg) |
| `/caption-styles` | GET | Lista dostępnych stylów napisów |
| `/add-captions` | POST | Wypalanie napisów w wideo (ASS + FFmpeg) |
| `/remove-silence` | POST | Usuwanie cichych fragmentów |
| `/extract-audio` | POST | Ekstrakcja audio z wideo |
| `/probe` | POST | Metadane wideo (wymiary, czas, kodeki, FPS) |

**Proces renderowania napisów:**

1. Otrzymuje wideo + segmenty transkrypcji + styl
2. Generuje plik `.ass` z odpowiednim formatowaniem
3. Wypala napisy w wideo przez FFmpeg: `ffmpeg -i input.mp4 -vf "ass=captions.ass" output.mp4`
4. Zwraca wyrenderowane wideo

**Cechy:**
- Fonty wbudowane w kontener: DejaVu Sans, Liberation Sans
- Katalog stylów: `/app/caption-styles/*.json`
- Gunicorn z 2 workerami, timeout 600s (10 min)
- Katalog tymczasowy: `/tmp/video-editor/`

---

## Style napisów (Caption Styles)

**Lokalizacja:** `docker/video-editor/caption_styles/`

Każdy styl to plik JSON z konfiguracją ASS (Advanced SubStation Alpha):

### Clean
```
Minimalistyczny biały tekst z subtelnym cieniem
Font: DejaVu Sans 44px | Outline: 2 | Shadow: 1
5 słów na linię | Małe litery
Highlight: żółty (#FFFF00)
```

### Hormozi
```
Inspirowany stylem Alexa Hormozi (YouTube Shorts)
Font: Liberation Sans 56px | Outline: 4 | Shadow: 0
3 słowa na linię | WIELKIE LITERY
Highlight: żółty (#FFFF00)
```

### MrBeast
```
Inspirowany miniaturkami MrBeast
Font: Liberation Sans 64px | Outline: 5 | Shadow: 2
2 słowa na linię | WIELKIE LITERY
Kolor główny: żółty | Highlight: czerwony
```

### Bold
```
Ekstra gruby tekst z mocnym kontrastem
Font: Liberation Sans 52px | Outline: 6 | Shadow: 3
4 słowa na linię | Małe litery
Highlight: zielony
```

### Neon
```
Świecący neonowy styl futurystyczny
Font: DejaVu Sans 48px | Outline: 3 | Shadow: 4
3 słowa na linię | Małe litery
Kolor główny: cyan | Outline: fioletowy | Highlight: magenta
```

**Format kolorów ASS:** `&HAABBGGRR` (alfa, niebieski, zielony, czerwony) — odwrotna kolejność niż standardowy hex.

**Dodawanie nowego stylu:** Wystarczy dodać nowy plik `.json` do `docker/video-editor/caption_styles/` i przebudować kontener. Endpoint `/caption-styles` automatycznie go wykryje.

---

## Backend Laravel

### Model: VideoProject

**Plik:** `app/Models/VideoProject.php`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `public_id` | ULID | Publiczny identyfikator |
| `user_id` | FK | Właściciel |
| `brand_id` | FK nullable | Powiązany brand |
| `title` | string | Tytuł projektu |
| `status` | enum | Status (patrz niżej) |
| `original_filename` | string | Oryginalna nazwa pliku |
| `video_path` | string | Ścieżka do pliku źródłowego |
| `output_path` | string | Ścieżka do wyrenderowanego pliku |
| `language` | string | Wykryty/ustawiony język |
| `language_probability` | float | Pewność detekcji języka |
| `duration` | float | Czas trwania (sekundy) |
| `width` / `height` | int | Wymiary wideo |
| `caption_style` | string | Wybrany styl napisów |
| `caption_settings` | JSON | Dodatkowe ustawienia napisów |
| `transcription` | JSON | Pełna transkrypcja z timestampami |
| `video_metadata` | JSON | Metadane wideo (kodeki, FPS, bitrate) |
| `error_message` | text | Opis błędu |
| `completed_at` | datetime | Data zakończenia renderowania |

### Enum: VideoProjectStatus

```
Pending ──→ Transcribing ──→ Transcribed ──→ Rendering ──→ Completed
   │              │                │              │
   └──────────────┴────────────────┴──────────────┴──→ Failed
                                   │
                                   ├──→ Editing (silence removal) ──→ Transcribed
                                   │
                                   └──→ (re-render) ──→ Rendering ──→ Completed
```

| Status | Opis | `isProcessing` | `canEdit` | `canExport` |
|--------|------|:-:|:-:|:-:|
| `pending` | Po uploadzie, przed transkrypcją | ❌ | ❌ | ❌ |
| `uploading` | Upload w toku | ✅ | ❌ | ❌ |
| `transcribing` | Transkrypcja AI w toku | ✅ | ❌ | ❌ |
| `transcribed` | Transkrypcja gotowa, można edytować | ❌ | ✅ | ✅ |
| `editing` | Edycja w toku (np. usuwanie ciszy) | ✅ | ❌ | ❌ |
| `rendering` | Renderowanie napisów w toku | ✅ | ❌ | ❌ |
| `completed` | Gotowy do pobrania | ❌ | ✅ | ✅ |
| `failed` | Błąd na dowolnym etapie | ❌ | ❌ | ❌ |

### Serwisy

#### TranscriberService

**Plik:** `app/Services/TranscriberService.php`

Komunikacja z kontenerem `transcriber` (port 3340).

```php
// Transkrypcja z timestampami per-słowo
$result = $transcriberService->transcribe($filePath, $language);
// → ['language', 'language_probability', 'duration', 'segments' => [...]]

// Detekcja języka
$result = $transcriberService->detectLanguage($filePath);

// Healthcheck
$healthy = $transcriberService->isHealthy();
```

Timeout: 300s (konfigurowalny przez `TRANSCRIBER_TIMEOUT`).

#### VideoEditorService

**Plik:** `app/Services/VideoEditorService.php`

Komunikacja z kontenerem `video-editor` (port 3341).

```php
// Wypalenie napisów
$outputPath = $videoEditorService->addCaptions($videoPath, $captions, $outputPath);

// Usunięcie ciszy
$outputPath = $videoEditorService->removeSilence($videoPath, $segments, $outputPath, $padding);

// Metadane wideo
$metadata = $videoEditorService->probe($videoPath);

// Dostępne style
$styles = $videoEditorService->getCaptionStyles();

// Ekstrakcja audio
$audioPath = $videoEditorService->extractAudio($videoPath, $outputPath, 'wav');
```

Timeout: 600s (konfigurowalny przez `VIDEO_EDITOR_TIMEOUT`).

### Joby (Queue)

Wszystkie joby dispatchowane na kolejkę Laravel. Każdy broadcastuje eventy WebSocket (`TaskStarted`, `TaskCompleted`) do aktualizacji frontendu w real-time.

#### TranscribeVideoJob

**Plik:** `app/Jobs/TranscribeVideoJob.php`

| Parametr | Wartość |
|----------|---------|
| Timeout | 600s (10 min) |
| Retry | 2 próby |
| Backoff | 30s |

**Proces:**
1. `TaskStarted` event → frontend pokazuje spinner
2. Status → `transcribing`
3. `VideoEditorService::probe()` → pobranie metadanych (wymiary, czas)
4. `TranscriberService::transcribe()` → transkrypcja AI
5. Zapis: `transcription`, `language`, `duration`, `width`, `height`
6. Status → `transcribed`
7. `TaskCompleted` event → frontend odświeża dane

#### RenderCaptionsJob

**Plik:** `app/Jobs/RenderCaptionsJob.php`

| Parametr | Wartość |
|----------|---------|
| Timeout | 900s (15 min) |
| Retry | 2 próby |
| Backoff | 30s |

**Proces:**
1. `TaskStarted` event
2. Status → `rendering`
3. Budowa danych napisów z ustawień projektu
4. `VideoEditorService::addCaptions()` → renderowanie FFmpeg
5. Zapis `output_path`
6. Status → `completed`, `completed_at` = now
7. `TaskCompleted` event

**Struktura danych napisów wysyłana do serwisu:**
```php
[
    'style' => 'hormozi',
    'segments' => [...],           // segmenty z transkrypcji
    'highlight_keywords' => false, // podświetlanie słów kluczowych
    'font_size' => 48,             // nadpisanie rozmiaru fontu
    'position' => 'bottom',        // top, center, bottom
]
```

#### RemoveSilenceJob

**Plik:** `app/Jobs/RemoveSilenceJob.php`

| Parametr | Wartość |
|----------|---------|
| Timeout | 900s (15 min) |
| Retry | 2 próby |
| Backoff | 30s |

**Proces:**
1. `TaskStarted` event
2. Status → `editing`
3. Ekstrakcja segmentów mowy z transkrypcji
4. `VideoEditorService::removeSilence()` → cięcie i złączenie
5. Aktualizacja `video_path` na nowy plik (bez ciszy)
6. Status → `transcribed` (gotowy do ponownej edycji/renderowania)
7. `TaskCompleted` event

**Parametry:**
- `minSilence` — minimalna długość ciszy do usunięcia (domyślnie 0.5s)
- `padding` — margines wokół segmentów mowy (domyślnie 0.1s)

---

## Frontend Vue

### Store: useVideoManagerStore

**Plik:** `resources/js/stores/videoManager.js`

Pinia store zarządzający całym stanem Video Managera.

**Kluczowe akcje:**

| Akcja | Opis |
|-------|------|
| `fetchStats(params)` | Statystyki dashboardu |
| `fetchProjects(params)` | Lista projektów (paginacja, filtrowanie) |
| `fetchProject(publicId)` | Szczegóły projektu |
| `uploadVideo(formData)` | Upload wideo |
| `addToUploadQueue(files)` | Dodanie do kolejki uploadów |
| `processUploadQueue(brandId)` | Przetworzenie kolejki |
| `updateProject(publicId, data)` | Aktualizacja ustawień |
| `renderProject(publicId)` | Trigger renderowania |
| `removeSilence(publicId, options)` | Trigger usuwania ciszy |
| `fetchCaptionStyles()` | Pobranie stylów napisów |
| `getDownloadUrl(publicId)` | URL do pobrania |
| `bulkDelete(ids)` | Bulk usuwanie |
| `bulkRender(ids)` | Bulk renderowanie |

### Routing

```
/app/video                          → VideoManagerPage (layout)
/app/video/                         → Dashboard
/app/video/library                  → Biblioteka projektów
/app/video/upload                   → Upload wideo
/app/video/editor/:projectId        → Edytor projektu
/app/video/settings                 → Ustawienia
```

### Strony

#### Dashboard (`VideoManagerDashboardPage.vue`)
- Siatka statystyk (łączna liczba, przetwarzane, ukończone dziś, łączny czas)
- Wskaźniki zdrowia serwisów (transcriber, video-editor)
- Kolejka przetwarzania — aktualnie przetwarzane wideo
- Ostatnie projekty
- Auto-refresh co 15 sekund

#### Biblioteka (`VideoManagerLibraryPage.vue`)
- Wyszukiwanie po tytule
- Filtrowanie po statusie
- Sortowanie (najnowsze, najstarsze, tytuł)
- Widok siatki / listy
- Bulk akcje (usuń, renderuj)
- Paginacja

#### Upload (`VideoManagerUploadPage.vue`)
- Drag & drop + file picker
- Kolejka uploadów z paskami postępu
- Ustawienia per-plik: tytuł, język, styl napisów
- Akceptowane formaty: MP4, MOV, AVI, WebM, MKV
- Max rozmiar: 500 MB

#### Edytor (`VideoManagerEditorPage.vue`)
- Podgląd wideo
- Zakładki:
  - **Transkrypcja** — edycja segmentów, łączenie, usuwanie
  - **Napisy** — wybór stylu, pozycja, rozmiar, podświetlanie słów
  - **Cisza** — timeline segmentów, próg ciszy, padding, przycisk usuwania
  - **Eksport** — renderowanie i pobieranie
- Auto-refresh co 5s podczas przetwarzania

### Komponenty

```
components/
├── videoManager/
│   ├── VideoManagerLayout.vue      # Layout z sidebarem
│   ├── VideoManagerSidebar.vue     # Nawigacja, brand switcher
│   ├── VideoStatsGrid.vue          # Karty statystyk
│   ├── ProcessingQueue.vue         # Lista przetwarzanych wideo
│   ├── VideoProjectCard.vue        # Karta projektu (widok siatki)
│   ├── VideoProjectRow.vue         # Wiersz projektu (widok listy)
│   ├── UploadQueue.vue             # Kolejka uploadów z postępem
│   └── SilenceRemovalPanel.vue     # Panel usuwania ciszy
└── video/
    ├── TranscriptEditor.vue        # Edytor transkrypcji
    ├── CaptionStylePicker.vue      # Picker stylu napisów
    ├── VideoPreview.vue            # Player wideo
    └── VideoUploadModal.vue        # Modal uploadu
```

---

## Przepływy (Workflows)

### 1. Upload → Transkrypcja (automatyczny)

```
Użytkownik przeciąga plik wideo na stronę Upload
    ↓
Frontend: addToUploadQueue() → processUploadQueue()
    ↓
POST /api/v1/video-projects  (multipart: video + title + language + caption_style)
    ↓
VideoProjectController::store()
  1. Walidacja (format, max 500MB)
  2. Zapis pliku: storage/app/video-projects/{user_id}/{filename}
  3. Tworzenie rekordu VideoProject (status: pending)
  4. Dispatch TranscribeVideoJob
    ↓
TranscribeVideoJob (kolejka)
  1. probe() → metadane wideo
  2. transcribe() → transkrypcja AI (faster-whisper)
  3. Zapis wyników do bazy
  4. Status: transcribed
  5. WebSocket broadcast → frontend odświeża
    ↓
Użytkownik widzi transkrypcję i może edytować
```

### 2. Edycja transkrypcji

```
Użytkownik otwiera edytor → zakładka "Transkrypcja"
    ↓
TranscriptEditor.vue:
  - Edycja tekstu segmentów inline
  - Łączenie sąsiednich segmentów
  - Usuwanie niepotrzebnych segmentów
    ↓
PUT /api/v1/video-projects/{id}  (body: { transcription: [...] })
    ↓
VideoProjectController::update()
  → Aktualizacja pola transcription w bazie
```

### 3. Usuwanie ciszy

```
Użytkownik otwiera zakładka "Cisza"
    ↓
SilenceRemovalPanel.vue:
  - Wizualizacja segmentów na timeline
  - Suwak: min. czas ciszy (0.1-5s)
  - Suwak: padding (0-1s)
  - Statystyki: ilość przerw, łączna cisza, procent
    ↓
POST /api/v1/video-projects/{id}/remove-silence
  (body: { min_silence: 0.5, padding: 0.1 })
    ↓
Dispatch RemoveSilenceJob
  1. Status: editing
  2. Ekstrakcja segmentów mowy z transkrypcji
  3. FFmpeg: wycięcie cichych fragmentów + złączenie
  4. Nowy plik wideo (bez ciszy)
  5. Status: transcribed (gotowy do renderowania)
```

### 4. Renderowanie napisów

```
Użytkownik wybiera styl (Hormozi/MrBeast/...) i klika "Renderuj"
    ↓
CaptionStylePicker.vue:
  - Styl: clean, hormozi, mrbeast, bold, neon
  - Pozycja: top, center, bottom
  - Rozmiar fontu: 16-128px
  - Podświetlanie słów kluczowych: on/off
    ↓
POST /api/v1/video-projects/{id}/render
    ↓
Dispatch RenderCaptionsJob
  1. Status: rendering
  2. Budowa danych napisów z ustawień
  3. Video Editor: generacja pliku .ass + FFmpeg render
  4. Zapis output do: video-projects/{id}/output_{timestamp}.mp4
  5. Status: completed
    ↓
Użytkownik może pobrać gotowy plik
```

### 5. Pobieranie

```
Użytkownik klika "Pobierz" w zakładce Eksport
    ↓
GET /api/v1/video-projects/{id}/download
    ↓
VideoProjectController::download()
  → Storage::download($project->output_path)
```

### 6. Re-render (ponowne renderowanie)

Użytkownik może zmienić styl/ustawienia i ponownie kliknąć "Renderuj". System nadpisze `output_path` nowym plikiem. Status wraca do `rendering` → `completed`.

---

## Konfiguracja

### Zmienne środowiskowe (.env)

```env
# Transcriber
FORWARD_TRANSCRIBER_PORT=3340
TRANSCRIBER_URL=http://transcriber:3340
TRANSCRIBER_TIMEOUT=300

# Whisper model
WHISPER_MODEL=base          # tiny | base | small | medium | large
WHISPER_DEVICE=cpu          # cpu | cuda (GPU)
WHISPER_COMPUTE_TYPE=int8   # int8 | float16 | float32

# Video Editor
FORWARD_VIDEO_EDITOR_PORT=3341
VIDEO_EDITOR_URL=http://video-editor:3341
VIDEO_EDITOR_TIMEOUT=600
```

### Konfiguracja Laravel (`config/services.php`)

```php
'transcriber' => [
    'url' => env('TRANSCRIBER_URL', 'http://transcriber:3340'),
    'timeout' => env('TRANSCRIBER_TIMEOUT', 300),
],
'video_editor' => [
    'url' => env('VIDEO_EDITOR_URL', 'http://video-editor:3341'),
    'timeout' => env('VIDEO_EDITOR_TIMEOUT', 600),
],
```

### Dobór modelu Whisper

| Model | Rozmiar | VRAM | Szybkość | Jakość | Użycie |
|-------|---------|------|----------|--------|--------|
| `tiny` | 75 MB | ~1 GB | Najszybszy | Niska | Testy, development |
| `base` | 142 MB | ~1 GB | Szybki | Dobra | **Domyślny (produkcja)** |
| `small` | 466 MB | ~2 GB | Średni | Bardzo dobra | Lepsze języki |
| `medium` | 1.5 GB | ~5 GB | Wolny | Świetna | Złożone nagrania |
| `large` | 3.1 GB | ~10 GB | Najwolniejszy | Najlepsza | Maksymalna dokładność |

Na CPU (`WHISPER_DEVICE=cpu`) zalecamy `base` lub `small`. Z GPU (`cuda`) można używać `medium` lub `large`.

---

## API Reference

### Endpointy Video Projects

Wszystkie pod prefixem `/api/v1/video-projects`, wymagają autoryzacji (Sanctum).

| Metoda | Endpoint | Opis |
|--------|----------|------|
| `GET` | `/` | Lista projektów (paginacja, filtrowanie po statusie) |
| `POST` | `/` | Upload nowego wideo |
| `GET` | `/stats` | Statystyki dashboardu |
| `GET` | `/caption-styles` | Dostępne style napisów |
| `GET` | `/health` | Health check serwisów |
| `POST` | `/bulk-delete` | Bulk usuwanie |
| `POST` | `/bulk-render` | Bulk renderowanie |
| `GET` | `/{publicId}` | Szczegóły projektu |
| `PUT` | `/{publicId}` | Aktualizacja ustawień |
| `DELETE` | `/{publicId}` | Usunięcie projektu |
| `POST` | `/{publicId}/render` | Trigger renderowania napisów |
| `POST` | `/{publicId}/remove-silence` | Trigger usuwania ciszy |
| `GET` | `/{publicId}/download` | Pobranie wyrenderowanego wideo |

### Walidacja uploadu

```
video:          required | file | mimetypes: video/mp4, video/quicktime,
                video/x-msvideo, video/webm, video/x-matroska | max: 512000 (500MB)
title:          nullable | string | max: 255
language:       nullable | string | max: 10
brand_id:       nullable | exists:brands,id
caption_style:  nullable | string | in: hormozi, mrbeast, clean, bold, neon
```

### Format odpowiedzi (VideoProjectResource)

```json
{
    "id": "01HQ...",
    "title": "Mój film",
    "status": "transcribed",
    "status_label": "Transcribed",
    "status_color": "purple",
    "original_filename": "film.mp4",
    "language": "pl",
    "duration": 120.5,
    "width": 1080,
    "height": 1920,
    "caption_style": "hormozi",
    "caption_settings": {
        "highlight_keywords": false,
        "position": "bottom",
        "font_size": 48
    },
    "transcription": {
        "segments": [...]
    },
    "is_processing": false,
    "can_edit": true,
    "can_export": true,
    "has_transcription": true,
    "error_message": null,
    "completed_at": "2026-02-13T10:00:00Z",
    "created_at": "2026-02-13T09:00:00Z"
}
```

---

## Testowanie i debug

### Health check serwisów

```bash
# Transcriber
curl http://localhost:3340/health
# → {"status": "ok", "model": "base", "device": "cpu"}

# Video Editor
curl http://localhost:3341/health
# → {"status": "ok", "ffmpeg": true}
```

### Manualne testowanie pipeline'u

```bash
# 1. Upload wideo
curl -X POST http://localhost/api/v1/video-projects \
  -H "Authorization: Bearer {token}" \
  -F "video=@test.mp4" \
  -F "title=Test" \
  -F "caption_style=hormozi"

# 2. Sprawdzenie statusu (powtarzaj aż status = transcribed)
curl http://localhost/api/v1/video-projects/{publicId} \
  -H "Authorization: Bearer {token}"

# 3. Renderowanie
curl -X POST http://localhost/api/v1/video-projects/{publicId}/render \
  -H "Authorization: Bearer {token}"

# 4. Download (po status = completed)
curl http://localhost/api/v1/video-projects/{publicId}/download \
  -H "Authorization: Bearer {token}" -o output.mp4
```

### Testowanie mikroserwisów bezpośrednio

```bash
# Transkrypcja
curl -X POST http://localhost:3340/transcribe \
  -F "file=@test.mp4" \
  -F "language=pl" | jq '.segments[:2]'

# Metadane wideo
curl -X POST http://localhost:3341/probe \
  -F "file=@test.mp4" | jq

# Lista stylów
curl http://localhost:3341/caption-styles | jq
```

### Logi

```bash
# Logi kontenera transcriber
docker compose logs transcriber --tail=50

# Logi kontenera video-editor
docker compose logs video-editor --tail=50

# Logi kolejki Laravel (joby)
docker compose logs laravel.test --tail=100 | grep -E "(TranscribeVideo|RenderCaptions|RemoveSilence)"
```

### Przebudowa po zmianach

```bash
# Po zmianie kodu mikroserwisu
docker compose build transcriber && docker compose up -d transcriber
docker compose build video-editor && docker compose up -d video-editor

# Po dodaniu nowego stylu napisów
docker compose build video-editor && docker compose up -d video-editor
```

---

## Struktura plików

```
docker/
├── transcriber/
│   ├── Dockerfile              # Python 3.12 + ffmpeg + faster-whisper
│   ├── requirements.txt        # flask, faster-whisper, gunicorn
│   └── server.py               # Endpointy Flask
└── video-editor/
    ├── Dockerfile              # Python 3.12 + ffmpeg + fonty
    ├── requirements.txt        # flask, gunicorn
    ├── server.py               # Endpointy Flask
    └── caption_styles/         # Definicje stylów
        ├── clean.json
        ├── hormozi.json
        ├── mrbeast.json
        ├── bold.json
        └── neon.json

app/
├── Enums/
│   └── VideoProjectStatus.php
├── Models/
│   └── VideoProject.php
├── Services/
│   ├── TranscriberService.php
│   └── VideoEditorService.php
├── Jobs/
│   ├── TranscribeVideoJob.php
│   ├── RenderCaptionsJob.php
│   └── RemoveSilenceJob.php
└── Http/
    ├── Controllers/Api/V1/
    │   └── VideoProjectController.php
    └── Resources/
        └── VideoProjectResource.php

database/migrations/
└── 2026_02_13_100000_create_video_projects_table.php

resources/js/
├── stores/
│   └── videoManager.js
├── pages/
│   ├── VideoManagerPage.vue
│   └── videoManager/
│       ├── VideoManagerDashboardPage.vue
│       ├── VideoManagerLibraryPage.vue
│       ├── VideoManagerUploadPage.vue
│       ├── VideoManagerEditorPage.vue
│       └── VideoManagerSettingsPage.vue
└── components/
    ├── videoManager/
    │   ├── VideoManagerLayout.vue
    │   ├── VideoManagerSidebar.vue
    │   ├── VideoStatsGrid.vue
    │   ├── ProcessingQueue.vue
    │   ├── VideoProjectCard.vue
    │   ├── VideoProjectRow.vue
    │   ├── UploadQueue.vue
    │   └── SilenceRemovalPanel.vue
    └── video/
        ├── TranscriptEditor.vue
        ├── CaptionStylePicker.vue
        ├── VideoPreview.vue
        └── VideoUploadModal.vue

Storage (runtime):
  storage/app/video-projects/{user_id}/
    ├── {original_filename}                 # Plik źródłowy
    ├── {id}_no_silence_{timestamp}.mp4     # Po usunięciu ciszy
    └── output_{timestamp}.mp4              # Wyrenderowany z napisami
```
