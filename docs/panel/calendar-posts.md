# Kalendarz i Posty Social Media

System planowania i zarządzania postami na platformy społecznościowe (Facebook, Instagram, YouTube).

## Architektura

```
┌─────────────────────────────────────────────────────────────────────────┐
│                            CalendarPage.vue                              │
│   Główna strona kalendarza z widokiem miesięcznym/tygodniowym           │
├─────────────────────────────────────────────────────────────────────────┤
│  CalendarToolbar     │        CalendarView.vue                          │
│  (nawigacja, widok)  │   ┌─────────────────────────────────────────────┐│
│                      │   │ CalendarDayCell × 42 (6 tygodni)            ││
│                      │   │   └── CalendarPostCard × N                  ││
│                      │   └─────────────────────────────────────────────┘│
├─────────────────────────────────────────────────────────────────────────┤
│                         PostEditorPage.vue                               │
│   ┌──────────────────────────┬─────────────────────────────────────────┐│
│   │ Formularz posta          │ PreviewPanel.vue                        ││
│   │ - RichTextEditor         │ - FacebookPreview                       ││
│   │ - StagedMediaGallery     │ - InstagramPreview                      ││
│   │ - PlatformSettings       │ - YouTubePreview                        ││
│   └──────────────────────────┴─────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Model danych

### SocialPost (Backend)

```
SocialPost
 ├── id (integer) - wewnętrzne
 ├── public_id (ULID) - publiczne, używane w URL
 ├── user_id
 ├── title
 ├── main_caption
 ├── status (enum: draft, pending_approval, approved, scheduled, published, failed)
 ├── scheduled_at (datetime)
 ├── published_at (datetime)
 ├── settings (json)
 │
 ├── PlatformPost[] (hasMany)
 │    ├── platform (facebook, instagram, youtube)
 │    ├── enabled (boolean)
 │    ├── platform_caption
 │    ├── hashtags (json)
 │    ├── video_title, video_description (dla YT)
 │    └── publish_status
 │
 ├── PostMedia[] (hasMany)
 │    ├── type (image, video)
 │    ├── path, url
 │    ├── position (kolejność)
 │    └── metadata (json)
 │
 └── PostApproval[] (hasMany)
      ├── approval_token_id
      ├── status (pending, approved, rejected)
      └── feedback
```

### Statusy postów

| Status | Opis | Kolor |
|--------|------|-------|
| `draft` | Szkic, w trakcie edycji | Szary |
| `pending_approval` | Oczekuje na akceptację klienta | Żółty |
| `approved` | Zaakceptowany przez klienta | Niebieski |
| `scheduled` | Zaplanowany do publikacji | Indygo |
| `published` | Opublikowany | Zielony |
| `failed` | Błąd publikacji | Czerwony |

---

## Store'y (Pinia)

### `usePostsStore`

```javascript
// stores/posts.js
state: {
    posts: [],           // Lista postów
    currentPost: null,   // Aktualnie edytowany
    calendarPosts: {},   // Pogrupowane po dacie { '2024-01-15': [...] }
    loading: false,
    saving: false,
    generatingAi: false,
}

actions: {
    fetchPosts(params)              // GET /api/v1/posts
    fetchCalendarPosts(start, end)  // GET /api/v1/posts/calendar
    fetchPost(id)                   // GET /api/v1/posts/{id}
    createPost(data)                // POST /api/v1/posts
    updatePost(id, data)            // PUT /api/v1/posts/{id}
    deletePost(id)                  // DELETE /api/v1/posts/{id}
    reschedulePost(id, scheduledAt) // POST /api/v1/posts/{id}/reschedule
    duplicatePost(id)               // POST /api/v1/posts/{id}/duplicate
    requestApproval(id, tokenId)    // POST /api/v1/posts/{id}/request-approval
    uploadMedia(postId, file)       // POST /api/v1/posts/{id}/media
    deleteMedia(mediaId)            // DELETE /api/v1/media/{id}
    generateWithAi(config)          // POST /api/v1/posts/ai/generate
}
```

### `useCalendarStore`

```javascript
// stores/calendar.js
state: {
    currentDate: new Date(),
    view: 'month',        // 'month' | 'week'
    selectedDate: null,
    draggedPost: null,    // Post przeciągany (D&D)
    filters: {
        status: null,
        platforms: [],
    },
}

getters: {
    currentYear, currentMonth, currentMonthKey,
    weekStartsOn,         // 0 (niedziela) lub 1 (poniedziałek)
    orderedDayKeys,       // ['mon', 'tue', ...] lub ['sun', 'mon', ...]
    monthStart, monthEnd, // Zakresy dat dla API
    calendarDays,         // Array dni do wyświetlenia (42 dla miesiąca)
}

actions: {
    nextMonth(), prevMonth(),
    goToToday(), goToMonth(year, month),
    selectDate(date), clearSelection(),
    startDragging(post), stopDragging(),
    setView(view),
    setFilter(key, value), clearFilters(),
}
```

---

## Komponenty

### CalendarPage.vue

Główna strona kalendarza.

```vue
<CalendarToolbar />  <!-- Nawigacja: miesiąc/tydzień, prev/next -->
<CalendarView
    :posts="postsStore.calendarPosts"
    @edit="handleEditPost"        <!-- Klik na post → edycja -->
    @reschedule="handleReschedule" <!-- Drag & drop → zmiana daty -->
    @create="handleCreatePost"    <!-- Klik na dzień → nowy post -->
/>
```

**Funkcje:**
- Widok miesiąca (42 dni, 6 tygodni)
- Widok tygodnia (7 dni, większe komórki)
- Drag & drop do zmiany daty
- Dwuklik na dzień → nowy post

### PostEditorPage.vue

Edytor posta z podglądem.

**Props:**
- `postId` (String, optional) - ID posta do edycji, null dla nowego

**Sekcje:**
1. **Content** - tytuł, treść, harmonogram
2. **Media** - galeria zdjęć/video

**Flow dla nowego posta:**
1. Modal wyboru platform (PlatformSelectModal)
2. Opcjonalnie: przywrócenie draftu z localStorage
3. Edycja treści dla każdej platformy
4. Podgląd na żywo

**Flow dla istniejącego posta:**
1. Pobranie danych z API
2. Załadowanie treści platform
3. Edycja i zapis

### PreviewPanel.vue

Podgląd posta w stylu platformy.

```vue
<PreviewPanel
    :title="sharedData.title"
    :caption="getEffectiveCaption(activePlatformTab)"
    :media="allMedia"
    :active-platform="activePlatformTab"
    :hashtags="platformContent[activePlatformTab].hashtags"
    :video-title="platformContent[activePlatformTab].videoTitle"
/>
```

**Podglądy platform:**
- `FacebookPreview` - grid zdjęć (1, 2, 3, 4, 5+ z "+X")
- `InstagramPreview` - karuzela ze swipe/strzałkami
- `YouTubePreview` - miniaturka + tytuł + opis

---

## Composables

### `usePostDraft`

Zarządzanie draftem w localStorage.

```javascript
// composables/usePostDraft.js
const draft = usePostDraft(postId);

// Draft posta
draft.loadDraft()      // Wczytaj draft
draft.saveDraft(data)  // Zapisz draft
draft.autoSave(data)   // Auto-save z debounce (2s)
draft.clearDraft()     // Usuń draft

// Staged media (dla nowych postów)
draft.stagedMedia         // ref<Array>
draft.loadStagedMedia()
draft.stageMediaFile(file)  // Dodaj plik (jako base64)
draft.removeStagedMedia(id)
draft.reorderStagedMedia(from, to)
draft.getStagedFilesForUpload()  // Konwersja base64 → File

// Template session
draft.templateInProgress
draft.loadTemplateInProgress()
draft.saveTemplateInProgress(data)
draft.clearTemplateInProgress()
```

**Klucze localStorage:**
- `post_draft_{postId}` lub `post_draft_new`
- `post_staged_media_{postId}` lub `post_staged_media_new`
- `post_template_{postId}` lub `post_template_new`

---

## API Endpoints

### Social Posts

| Method | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/posts` | Lista postów (paginacja) |
| GET | `/api/v1/posts/calendar?start=&end=` | Posty dla kalendarza |
| POST | `/api/v1/posts` | Utwórz post |
| GET | `/api/v1/posts/{id}` | Pobierz post |
| PUT | `/api/v1/posts/{id}` | Aktualizuj post |
| DELETE | `/api/v1/posts/{id}` | Usuń post |
| POST | `/api/v1/posts/{id}/reschedule` | Zmień datę |
| POST | `/api/v1/posts/{id}/duplicate` | Duplikuj |
| POST | `/api/v1/posts/{id}/request-approval` | Wyślij do akceptacji |
| POST | `/api/v1/posts/{id}/publish` | Opublikuj |

### Platform Posts

| Method | Endpoint | Opis |
|--------|----------|------|
| PUT | `/api/v1/posts/{id}/platforms/{platform}` | Aktualizuj platformę |
| POST | `/api/v1/posts/{id}/platforms/{platform}/sync` | Sync z główną |
| POST | `/api/v1/posts/{id}/platforms/{platform}/toggle` | Włącz/wyłącz |

### Media

| Method | Endpoint | Opis |
|--------|----------|------|
| POST | `/api/v1/posts/{id}/media` | Upload pliku |
| DELETE | `/api/v1/media/{id}` | Usuń media |
| POST | `/api/v1/posts/{id}/media/reorder` | Zmień kolejność |

### AI Generation

| Method | Endpoint | Opis |
|--------|----------|------|
| POST | `/api/v1/posts/ai/generate` | Generuj treść AI |

---

## Dziedziczenie treści platform

System dziedziczenia treści między platformami:

```javascript
// Pierwsza włączona platforma to "source"
const firstPlatform = computed(() => {
    const order = ['facebook', 'instagram', 'youtube'];
    return order.find(p => selectedPlatforms.value.includes(p));
});

// Pobierz efektywną treść (własna lub dziedziczona)
const getEffectiveCaption = (platform) => {
    if (platformContent[platform].captionModified) {
        return platformContent[platform].caption;
    }
    return platformContent[firstPlatform.value].caption;
};
```

**Reguły:**
1. Pierwsza platforma (FB → IG → YT) jest źródłem
2. Pozostałe platformy dziedziczą treść do momentu modyfikacji
3. `captionModified: true` = platforma ma własną treść
4. Przycisk "Reset to inherit" przywraca dziedziczenie

---

## System akceptacji

### ApprovalToken

Token dla klienta do akceptacji postów.

```
ApprovalToken
 ├── public_id (ULID) - używane w URL
 ├── user_id
 ├── client_name
 ├── expires_at
 └── is_active

URL akceptacji: /approve/{token}
```

### Flow akceptacji

1. Użytkownik tworzy token dla klienta
2. Wysyła post do akceptacji (wybiera token)
3. System tworzy `PostApproval` (status: pending)
4. Klient wchodzi na `/approve/{token}`
5. Widzi listę postów do akceptacji (ClientApprovalPage)
6. Akceptuje lub odrzuca (może dodać feedback)
7. Status posta zmienia się na `approved` lub pozostaje `pending_approval`

---

## Walidacja

### StoreSocialPostRequest

```php
'title' => ['required', 'string', 'max:255'],
'main_caption' => ['required', 'string', 'max:5000'],
'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
'platforms' => ['required', 'array', 'min:1'],
'platforms.*' => ['in:facebook,instagram,youtube'],
```

### RescheduleSocialPostRequest

```php
'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
```

**Custom validation messages:**
```php
// resources/lang/pl/validation.php
'custom' => [
    'scheduled_at' => [
        'future' => 'Data publikacji musi być w przyszłości.',
    ],
],
```

---

## Skróty klawiaturowe

| Klawisz | Akcja (w edytorze) |
|---------|---------------------|
| `Ctrl+S` | Zapisz post |
| `Tab` | Następna platforma |
| `Shift+Tab` | Poprzednia platforma |

---

## Stany komponentów

### CalendarPostCard

```
┌──────────────────────────────────────────┐
│ 🖼 | Tytuł posta...          10:30 FB IG │
└──────────────────────────────────────────┘
```

- Kolorowe tło według statusu
- Miniaturka pierwszego zdjęcia
- Ikony włączonych platform
- Drag & drop do zmiany daty

### PostEditorPage

**Tryb tworzenia (`postId: null`):**
1. PlatformSelectModal → wybór platform
2. Formularz z pustymi polami
3. Auto-save do localStorage

**Tryb edycji (`postId: 'xxx...'`):**
1. Fetch danych z API
2. Załadowanie treści platform
3. Auto-save zmian

---

## Pliki

### Backend

```
app/
├── Enums/
│   ├── Platform.php              # facebook, instagram, youtube
│   └── PostStatus.php            # draft, pending_approval, etc.
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── SocialPostController.php
│   │   ├── PlatformPostController.php
│   │   ├── PostMediaController.php
│   │   ├── ApprovalTokenController.php
│   │   └── ClientApprovalController.php
│   ├── Requests/Api/
│   │   ├── StoreSocialPostRequest.php
│   │   ├── UpdateSocialPostRequest.php
│   │   ├── RescheduleSocialPostRequest.php
│   │   └── ...
│   └── Resources/
│       ├── SocialPostResource.php
│       ├── CalendarPostResource.php
│       ├── PlatformPostResource.php
│       └── PostMediaResource.php
├── Models/
│   ├── SocialPost.php
│   ├── PlatformPost.php
│   ├── PostMedia.php
│   ├── ApprovalToken.php
│   └── PostApproval.php
├── Policies/
│   ├── SocialPostPolicy.php
│   └── ApprovalTokenPolicy.php
└── Services/
    ├── ApprovalService.php
    ├── ContentSyncService.php
    ├── PostMediaService.php
    └── LinkPreviewService.php
```

### Frontend

```
resources/js/
├── pages/
│   ├── CalendarPage.vue
│   ├── PostEditorPage.vue
│   ├── ApprovalTokensPage.vue
│   └── ClientApprovalPage.vue
├── components/
│   ├── calendar/
│   │   ├── CalendarView.vue
│   │   ├── CalendarToolbar.vue
│   │   ├── CalendarDayCell.vue
│   │   └── CalendarPostCard.vue
│   ├── posts/
│   │   ├── PostForm.vue
│   │   ├── PostMediaGallery.vue
│   │   ├── StagedMediaGallery.vue
│   │   ├── RichTextEditor.vue
│   │   ├── PlatformSelectModal.vue
│   │   ├── PlatformSettings.vue
│   │   ├── PostStatusBadge.vue
│   │   ├── TemplatePickerModal.vue
│   │   ├── TemplateEditorModal.vue
│   │   └── AiPlatformGenerateModal.vue
│   ├── preview/
│   │   ├── PreviewPanel.vue
│   │   ├── FacebookPreview.vue
│   │   ├── InstagramPreview.vue
│   │   └── YouTubePreview.vue
│   └── approval/
│       ├── ApprovalTokenList.vue
│       ├── ApprovalTokenForm.vue
│       └── FeedbackModal.vue
├── stores/
│   ├── posts.js
│   ├── calendar.js
│   └── approval.js
└── composables/
    └── usePostDraft.js
```

---

## Migracje

```bash
# Kolejność migracji
2026_01_24_100000_create_social_posts_table.php
2026_01_24_100001_create_platform_posts_table.php
2026_01_24_100002_create_post_media_table.php
2026_01_24_100003_create_approval_tokens_table.php
2026_01_24_100004_create_post_approvals_table.php
```
