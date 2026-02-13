# Architektura Publikacji Postów

Dokumentacja dla developerów opisująca cały proces publikowania postów na platformy social media.

---

## Spis treści

1. [Przegląd](#przegląd)
2. [Wzorzec Adapter + Resolver](#wzorzec-adapter--resolver)
3. [Adaptery](#adaptery)
4. [Ścieżki publikacji](#ścieżki-publikacji)
5. [Cykl życia posta](#cykl-życia-posta)
6. [Konfiguracja per-brand](#konfiguracja-per-brand)
7. [Klucze API (BrandAiKey)](#klucze-api-brandaikey)
8. [Endpointy API](#endpointy-api)
9. [Scheduler i kolejki](#scheduler-i-kolejki)
10. [Obsługa błędów i logi](#obsługa-błędów-i-logi)
11. [Dodawanie nowego providera](#dodawanie-nowego-providera)
12. [Pliki i zależności](#pliki-i-zależności)

---

## Przegląd

System publikacji oparty jest na wzorcu **Adapter** z **Resolverem** per-brand. Pozwala to na:

- Wybór sposobu publikacji per brand (Direct API, Webhook n8n, GetLate.dev)
- Łatwe dodawanie nowych providerów (np. Blotato)
- Fallback chain gdy wybrany provider nie jest dostępny
- Unified interface — każdy adapter zwraca ten sam format odpowiedzi

```
┌─────────────────────────────────────────────────────────┐
│                    Trigger publikacji                     │
│  (Przycisk "Opublikuj" / Scheduler / Job z kolejki)     │
└─────────────┬───────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────────────┐
│                   PublisherResolver                       │
│  resolve(Brand, PlatformPost) → SocialPublisherInterface │
│                                                           │
│  1. Sprawdź brand->publishing_provider (explicit)        │
│  2. Fallback: Direct → Webhook (legacy chain)            │
└─────────────┬───────────────────────────────────────────┘
              │
     ┌────────┼────────────────┐
     ▼        ▼                ▼
┌─────────┐ ┌──────────┐ ┌──────────┐
│ Direct  │ │ Webhook  │ │ GetLate  │
│ Adapter │ │ Adapter  │ │ Adapter  │
│(FB/IG)  │ │  (n8n)   │ │(.dev API)│
└─────────┘ └──────────┘ └──────────┘
```

---

## Wzorzec Adapter + Resolver

### Interface: `SocialPublisherInterface`

**Plik:** `app/Contracts/SocialPublisherInterface.php`

```php
interface SocialPublisherInterface
{
    /**
     * @return array{success: bool, external_id?: string, external_url?: string, method: string, error?: string}
     */
    public function publish(PlatformPost $platformPost): array;
    public function supportsPlatform(string $platform): bool;
    public function isConfiguredForBrand(Brand $brand): bool;
}
```

Każdy adapter implementuje ten interface. Metoda `publish()` zwraca ustandaryzowany format:

| Klucz | Typ | Opis |
|-------|-----|------|
| `success` | `bool` | Czy publikacja się udała |
| `external_id` | `?string` | ID posta na platformie |
| `external_url` | `?string` | URL posta na platformie |
| `method` | `string` | Użyty adapter: `direct_api`, `webhook`, `getlate` |
| `error` | `?string` | Opis błędu (gdy `success = false`) |

### Resolver: `PublisherResolver`

**Plik:** `app/Services/Publishing/PublisherResolver.php`

```php
public function resolve(Brand $brand, PlatformPost $platformPost): SocialPublisherInterface
```

**Logika rozwiązywania:**

1. Jeśli `$brand->publishing_provider` jest ustawiony (nie null):
   - Pobierz adapter dla tego providera
   - Sprawdź `supportsPlatform()` + `isConfiguredForBrand()`
   - Jeśli OK → zwróć adapter
   - Jeśli nie → fallthrough do legacy chain
2. **Legacy fallback chain:**
   - `DirectPublishingAdapter` — jeśli platforma to facebook/instagram i brand ma ważne `PlatformCredential`
   - `WebhookPublishingAdapter` — jeśli webhook n8n jest skonfigurowany
3. Żaden adapter nie pasuje → `RuntimeException`

---

## Adaptery

### DirectPublishingAdapter

**Plik:** `app/Services/Publishing/Adapters/DirectPublishingAdapter.php`

Wrapper na istniejący `FacebookPublishingService`. Obsługuje natywne API Facebook Graph.

| Cecha | Wartość |
|-------|---------|
| Platformy | `facebook`, `instagram` |
| Wymagania | `PlatformCredential` z ważnym tokenem OAuth |
| Typ publikacji | Synchroniczny (czeka na odpowiedź API) |
| Logowanie | Przez `FacebookPublishingService` (trait `LogsApiUsage`) |

**Typy publikacji Facebook:** text post, single photo, multi-photo, video
**Typy publikacji Instagram:** single photo, carousel, reel

### WebhookPublishingAdapter

**Plik:** `app/Services/Publishing/Adapters/WebhookPublishingAdapter.php`

Wrapper na istniejący `PublishingService` (n8n webhook).

| Cecha | Wartość |
|-------|---------|
| Platformy | Wszystkie (webhook obsługuje dowolną) |
| Wymagania | `services.n8n.webhook_url` w `.env` |
| Typ publikacji | Asynchroniczny (HTTP 202 → callback) |
| Callback URL | `POST /api/v1/webhooks/publish-result` |

**Payload wysyłany do n8n:**
```json
{
    "post_id": "abc123",
    "platform": "instagram",
    "title": "Tytuł posta",
    "caption": "Treść posta...",
    "media_urls": ["https://..."],
    "scheduled_at": "2026-02-13T10:00:00Z",
    "brand": { "id": "brand_xyz", "name": "Moja Marka" },
    "callback_url": "https://app.example.com/api/v1/webhooks/publish-result"
}
```

### GetLatePublishingAdapter

**Plik:** `app/Services/Publishing/Adapters/GetLatePublishingAdapter.php`

Nowy adapter integrujący z API getlate.dev.

| Cecha | Wartość |
|-------|---------|
| Platformy | `instagram`, `facebook`, `tiktok`, `linkedin`, `x`, `youtube` |
| Wymagania | Klucz API GetLate w `BrandAiKey` |
| Typ publikacji | Synchroniczny |
| Base URL | `https://getlate.dev/api/v1` |
| Autoryzacja | Bearer token |

**Payload wysyłany do GetLate:**
```json
{
    "content": "Treść posta...",
    "platforms": ["instagram"],
    "mediaItems": ["https://..."],
    "publishNow": true,
    "title": "Tytuł posta",
    "hashtags": ["marketing", "socialmedia"]
}
```

Lub z zaplanowaną datą:
```json
{
    "content": "...",
    "platforms": ["facebook"],
    "scheduledFor": "2026-02-15T14:00:00Z"
}
```

---

## Ścieżki publikacji

System ma **trzy niezależne ścieżki** triggera publikacji:

### 1. Ręczna publikacja z edytora posta

```
Użytkownik klika "Opublikuj" w PostEditorPage
    ↓
postsStore.webhookPublishPost(postId)
    ↓
POST /api/v1/posts/{post}/webhook-publish
    ↓
PostAutomationController::webhookPublish()
    ↓
Dla każdego włączonego PlatformPost:
    PublisherResolver::resolve() → adapter.publish()
    ↓
Aktualizacja PlatformPost (publish_status, external_id)
Aktualizacja SocialPost (status → Published/Failed)
```

### 2. Ręczna publikacja na konkretną platformę

```
"Save & Publish to Facebook" z dropdown menu
    ↓
postsStore.publishPost(postId, 'facebook')
    ↓
POST /api/v1/posts/{post}/publish  (body: { platform: 'facebook' })
    ↓
SocialPostController::publish()
    ↓
PublisherResolver::resolve() → adapter.publish()
    ↓
Aktualizacja PlatformPost + SocialPost
```

### 3. Automatyczna publikacja (scheduler)

```
Laravel Scheduler (co minutę)
    ↓
php artisan sm:publish-due
    ↓
SmPublishOrchestratorService::publishDuePosts()
    ↓
SmScheduledPost::readyToPublish()
  (status='scheduled' AND approval_status='approved' AND scheduled_at <= now())
    ↓
Dla każdego posta:
    SmPublishOrchestratorService::publish($scheduledPost)
        ↓
    PublisherResolver::resolve() → adapter.publish()
        ↓
    scheduledPost.markAsPublished() / markAsFailed()
    SmPublishLog::create()
```

---

## Cykl życia posta

### SocialPost — statusy (`PostStatus` enum)

```
Draft ──→ PendingApproval ──→ Approved ──→ Scheduled ──→ Published
                                   │                         │
                                   └── (ręczna publikacja) ──┘
                                                             │
                                                         Failed
```

| Status | Opis | Można edytować | Można publikować |
|--------|------|:-:|:-:|
| `draft` | Szkic, w trakcie tworzenia | ✅ | ❌ |
| `pending_approval` | Czeka na akceptację klienta | ✅ | ❌ |
| `approved` | Zaakceptowany, gotowy do publikacji | ✅ | ✅ |
| `scheduled` | Zaplanowany / w trakcie publikacji | ❌ | ✅ |
| `published` | Opublikowany na platformach | ❌ | ❌ |
| `failed` | Publikacja nie powiodła się | ❌ | ❌ |

### PlatformPost — statusy (`PublishStatus` enum)

Każdy `SocialPost` ma wiele `PlatformPost` (po jednym na platformę).

| Status | Opis |
|--------|------|
| `not_started` | Domyślny, jeszcze nie próbowano publikować |
| `pending` | Wysłano do n8n webhook, czekamy na callback |
| `published` | Opublikowany na platformie |
| `failed` | Błąd publikacji |

### SmScheduledPost — statusy (SM Manager)

Równoległy model dla Social Media Managera (nie SocialPost).

| Pole | Wartości |
|------|---------|
| `status` | `scheduled`, `published`, `failed`, `cancelled` |
| `approval_status` | `pending`, `approved`, `rejected` |

Post jest gotowy do publikacji gdy: `status = scheduled` AND `approval_status = approved` AND `scheduled_at <= now()`

---

## Konfiguracja per-brand

### Kolumna `publishing_provider` na Brand

**Migracja:** `2026_02_13_000001_add_publishing_provider_to_brands_table.php`

```php
$table->string('publishing_provider')->nullable();
```

**Enum:** `PublishingProvider`

| Wartość | Opis |
|---------|------|
| `null` | Auto — legacy chain (Direct → Webhook) |
| `direct` | Wymuszony Direct API (Facebook/Instagram) |
| `webhook` | Wymuszony n8n Webhook |
| `getlate` | Wymuszony GetLate.dev |

**Ustawienie z frontu:** Dropdown w `AiKeysPanel.vue` → `PUT /api/v1/brands/{brand}` z `publishing_provider`.

Opcja GetLate w dropdown jest **widoczna tylko gdy klucz API GetLate jest skonfigurowany** dla danego brandu.

---

## Klucze API (BrandAiKey)

**Model:** `app/Models/BrandAiKey.php`

Klucze API przechowywane per-brand w tabeli `brand_ai_keys`. **Nie używamy globalnych kluczy z `.env`.**

```php
// Pobranie klucza
$apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::GetLate);
// → string (odszyfrowany) lub null
```

**Providery (`AiProvider` enum):**

| Case | Wartość | Użycie |
|------|---------|--------|
| `OpenAi` | `openai` | Generowanie treści AI |
| `Gemini` | `gemini` | Generowanie treści AI |
| `WaveSpeed` | `wavespeed` | Generowanie obrazów AI |
| `GetLate` | `getlate` | Publikacja postów przez getlate.dev |

Klucze są **szyfrowane** w bazie (`encrypt()`/`decrypt()`) automatycznie przez mutator na modelu.

**Frontend:** `AiKeysPanel.vue` — backend dynamicznie listuje `AiProvider::cases()`, więc nowe case'y pojawiają się automatycznie.

---

## Endpointy API

### Publikacja

| Metoda | Endpoint | Kontroler | Opis |
|--------|----------|-----------|------|
| `POST` | `/api/v1/posts/{post}/webhook-publish` | `PostAutomationController::webhookPublish` | Publikuj na wszystkie platformy |
| `POST` | `/api/v1/posts/{post}/publish` | `SocialPostController::publish` | Publikuj na konkretną platformę |

### Callbacki (bez auth)

| Metoda | Endpoint | Opis |
|--------|----------|------|
| `POST` | `/api/v1/webhooks/publish-result` | Callback z n8n po publikacji |
| `POST` | `/api/v1/webhooks/automation-callback` | Callback z n8n po generowaniu |

### Konfiguracja brandu

| Metoda | Endpoint | Opis |
|--------|----------|------|
| `PUT` | `/api/v1/brands/{brand}` | Update brandu (w tym `publishing_provider`) |
| `GET` | `/api/v1/brands/{brand}/ai-keys` | Lista kluczy AI |
| `POST` | `/api/v1/brands/{brand}/ai-keys` | Zapisz klucz AI |
| `DELETE` | `/api/v1/brands/{brand}/ai-keys/{provider}` | Usuń klucz AI |

---

## Scheduler i kolejki

### Komendy Artisan

```bash
# Publikuj zaplanowane posty SM Managera (scheduler co minutę)
php artisan sm:publish-due

# Publikuj zaplanowane posty (legacy, scheduler co minutę)
php artisan posts:publish-due
```

### Konfiguracja schedulera

**Plik:** `routes/console.php`

```php
Schedule::command('sm:publish-due')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('posts:publish-due')
    ->everyMinute()
    ->withoutOverlapping();
```

### Job z kolejki

**Plik:** `app/Jobs/PublishPostJob.php`

Dispatchowany do kolejki Laravel. Używa `PublisherResolver` tak samo jak kontrolery.

```php
// Dispatch do publikacji
PublishPostJob::dispatch($socialPost);

// Lub na konkretną platformę
PublishPostJob::dispatch($socialPost, 'facebook');
```

Retry: 3 próby, backoff 60s.

---

## Obsługa błędów i logi

### Logowanie API

Trait `LogsApiUsage` (`app/Services/Concerns/LogsApiUsage.php`) zapisuje każde wywołanie zewnętrznego API do tabeli `ai_operation_logs`:

- `operation`: np. `getlate_publish_instagram`, `facebook_text_post`
- `provider`: enum `ApiProvider` (FACEBOOK, GETLATE, etc.)
- `endpoint`: ścieżka API
- `http_status`: kod odpowiedzi
- `duration_ms`: czas wykonania
- `error_message`: opis błędu

### Logi publikacji SM Managera

Tabela `sm_publish_logs` (model `SmPublishLog`):

| Kolumna | Opis |
|---------|------|
| `sm_scheduled_post_id` | FK do SmScheduledPost |
| `action` | np. `direct_api_facebook`, `webhook_instagram_failed` |
| `http_status` | Kod HTTP odpowiedzi |
| `error_message` | Opis błędu |
| `duration_ms` | Czas wykonania w ms |

### Scenariusze błędów

| Scenariusz | Zachowanie |
|------------|-----------|
| Post nie approved | Zwraca błąd 400 (nie próbuje publikować) |
| Brak klucza API GetLate | Adapter zwraca `success: false` → fallback na legacy chain |
| Token Facebook expired | Direct adapter rzuca wyjątek → fallback na webhook |
| Webhook n8n niedostępny | `PlatformPost.publish_status = failed` |
| GetLate API error (4xx/5xx) | `PlatformPost.publish_status = failed`, błąd logowany |
| Żaden adapter niedostępny | `RuntimeException` → post oznaczony jako failed |
| Timeout (30s) | Exception → retry (dla jobów) lub error response |

---

## Dodawanie nowego providera

Np. dodanie **Blotato** jako nowego sposobu publikacji:

### 1. Enum `PublishingProvider`

```php
// app/Enums/PublishingProvider.php
case Blotato = 'blotato';
```

### 2. Enum `AiProvider` (jeśli przechowuje klucz API)

```php
// app/Enums/AiProvider.php
case Blotato = 'blotato';
```

### 3. Enum `ApiProvider` (dla logowania)

```php
// app/Enums/ApiProvider.php
case BLOTATO = 'blotato';
```

### 4. Nowy adapter

```php
// app/Services/Publishing/Adapters/BlotatoPublishingAdapter.php
class BlotatoPublishingAdapter implements SocialPublisherInterface
{
    use LogsApiUsage;

    public function publish(PlatformPost $platformPost): array
    {
        // 1. Pobierz klucz API
        $apiKey = BrandAiKey::getKeyForProvider($brand, AiProvider::Blotato);

        // 2. Zbuduj payload
        // 3. Wyślij HTTP request
        // 4. Zaloguj przez LogsApiUsage
        // 5. Zwróć ustandaryzowaną odpowiedź
        return [
            'success' => true,
            'external_id' => $result['id'],
            'method' => 'blotato',
        ];
    }

    public function supportsPlatform(string $platform): bool { /* ... */ }
    public function isConfiguredForBrand(Brand $brand): bool { /* ... */ }
}
```

### 5. Rejestracja w `PublisherResolver`

```php
// app/Services/Publishing/PublisherResolver.php
public function __construct(
    // ... existing adapters
    protected BlotatoPublishingAdapter $blotatoAdapter,
) {}

private function getAdapter(PublishingProvider $provider): SocialPublisherInterface
{
    return match ($provider) {
        // ... existing
        PublishingProvider::Blotato => $this->blotatoAdapter,
    };
}
```

### 6. Frontend — ikona i kolor w `AiKeysPanel.vue`

```js
providerIcons.blotato = `<svg ...>`;
providerColors.blotato = { bg: 'bg-orange-100', text: 'text-orange-600' };
```

### 7. Tłumaczenia

```json
"aiKeys.providers.blotato": "Blotato"
"publishingProvider.blotato": "Blotato"
```

Laravel automatycznie rozwiąże nowy adapter przez DI container — nie trzeba nic rejestrować w `AppServiceProvider`.

---

## Pliki i zależności

### Nowe pliki (architektura publikacji)

```
app/
├── Contracts/
│   └── SocialPublisherInterface.php        # Interface adaptera
├── Enums/
│   └── PublishingProvider.php              # Enum: direct, webhook, getlate
└── Services/
    └── Publishing/
        ├── PublisherResolver.php            # Factory/resolver per brand
        └── Adapters/
            ├── DirectPublishingAdapter.php  # Wrapper na FacebookPublishingService
            ├── WebhookPublishingAdapter.php # Wrapper na PublishingService (n8n)
            └── GetLatePublishingAdapter.php # Nowy adapter GetLate API
```

### Istniejące pliki (owinięte adapterami, nie zmienione)

```
app/Services/Publishing/
├── FacebookPublishingService.php   # Natywne API Facebook Graph
└── PublishingService.php           # n8n webhook
```

### Pliki zmodyfikowane

```
app/Enums/AiProvider.php                    # +case GetLate
app/Enums/ApiProvider.php                   # +case GETLATE
app/Models/Brand.php                        # +publishing_provider (fillable, cast)
app/Http/Resources/BrandResource.php        # +publishing_provider w JSON
app/Http/Requests/Api/UpdateBrandRequest.php # +walidacja publishing_provider
app/Jobs/PublishPostJob.php                 # Refaktor → PublisherResolver
app/Services/SmManager/SmPublishOrchestratorService.php  # Refaktor → PublisherResolver
app/Http/Controllers/Api/V1/PostAutomationController.php # webhookPublish → PublisherResolver
app/Http/Controllers/Api/V1/SocialPostController.php     # publish → PublisherResolver
resources/js/components/brand/AiKeysPanel.vue             # +GetLate icon + publishing provider dropdown
resources/js/pages/PostEditorPage.vue                     # +przycisk "Opublikuj"
resources/js/i18n/locales/en.json                         # +tłumaczenia
resources/js/i18n/locales/pl.json                         # +tłumaczenia
```

### Migracja

```
database/migrations/2026_02_13_000001_add_publishing_provider_to_brands_table.php
```
