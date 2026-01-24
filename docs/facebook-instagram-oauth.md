# Facebook + Instagram OAuth Integration

## Opis

Integracja OAuth umożliwia użytkownikom połączenie kont Facebook i Instagram z poziomu aplikacji, aby mogli publikować posty bezpośrednio przez Graph API bez potrzeby korzystania z zewnętrznych webhooków (n8n).

## Status implementacji

### Zrobione

| Element | Plik | Opis |
|---------|------|------|
| Migracja | `database/migrations/2026_01_24_195217_create_platform_credentials_table.php` | Tabela na zaszyfrowane tokeny OAuth |
| Model | `app/Models/PlatformCredential.php` | Model z automatycznym szyfrowaniem tokenów |
| OAuth Service | `app/Services/OAuth/FacebookOAuthService.php` | Obsługa flow OAuth (autoryzacja, wymiana tokenów) |
| Publishing Service | `app/Services/Publishing/FacebookPublishingService.php` | Bezpośrednia publikacja przez Graph API |
| Controller | `app/Http/Controllers/Api/V1/PlatformCredentialController.php` | Endpointy API dla OAuth |
| Routes | `routes/api.php`, `routes/web.php` | Routing dla OAuth callback i API |
| Frontend | `resources/js/components/brand/ConnectedPlatformsPanel.vue` | Panel zarządzania połączeniami |
| Konfiguracja | `config/services.php`, `.env.example` | Zmienne środowiskowe |
| Tłumaczenia | `resources/js/i18n/locales/pl.json`, `en.json` | Teksty UI |
| Job | `app/Jobs/PublishPostJob.php` | Zaktualizowany o bezpośrednią publikację |

### Do zrobienia (konfiguracja)

1. **Utworzenie aplikacji Meta** - https://developers.facebook.com/
2. **Dodanie kluczy do `.env`**
3. **Konfiguracja uprawnień w Meta**
4. **App Review** (przed produkcją)

---

## Architektura

```
┌─────────────────────────────────────────────────────────────────┐
│                        Brand Settings                            │
│                    "Połączenia" Tab                              │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                 ConnectedPlatformsPanel.vue                      │
│  - Wyświetla status połączeń Facebook/Instagram                  │
│  - Przycisk "Połącz z Facebook"                                  │
│  - Modal wyboru strony Facebook                                  │
└─────────────────────┬───────────────────────────────────────────┘
                      │
         ┌────────────┴────────────┐
         ▼                         ▼
┌─────────────────┐    ┌──────────────────────────┐
│  GET /auth-url  │    │  OAuth Callback          │
│                 │    │  /auth/facebook/callback │
└────────┬────────┘    └────────────┬─────────────┘
         │                          │
         ▼                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                   FacebookOAuthService                           │
│  - getAuthUrl()          - Generuje URL autoryzacji              │
│  - exchangeCodeForToken() - Wymienia code na token               │
│  - getLongLivedToken()   - Pobiera token 60-dniowy               │
│  - getUserPages()        - Lista stron Facebook                  │
│  - getPageAccessToken()  - Token strony (nie wygasa)             │
└─────────────────────────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                   PlatformCredential (Model)                     │
│  - Automatyczne szyfrowanie tokenów (encrypt/decrypt)            │
│  - Relacja z Brand                                               │
│  - Helpery: isExpired(), isExpiringSoon()                        │
└─────────────────────────────────────────────────────────────────┘
```

### Flow publikacji

```
┌─────────────────────────────────────────────────────────────────┐
│                      PublishPostJob                              │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
              ┌───────────────┐
              │ Czy są tokeny │
              │ dla platformy?│
              └───────┬───────┘
                      │
         ┌────────────┴────────────┐
         │ TAK                     │ NIE
         ▼                         ▼
┌─────────────────┐    ┌──────────────────────────┐
│ Direct API      │    │ Fallback do n8n webhook  │
│ Publishing      │    │ (PublishingService)      │
└─────────────────┘    └──────────────────────────┘
```

---

## Konfiguracja krok po kroku

### 1. Utwórz aplikację Meta

1. Wejdź na https://developers.facebook.com/
2. Kliknij "Create App"
3. Wybierz typ "Business"
4. Podaj nazwę aplikacji

### 2. Skonfiguruj Facebook Login

1. W panelu aplikacji dodaj produkt "Facebook Login"
2. Przejdź do **Facebook Login → Settings**
3. Dodaj **Valid OAuth Redirect URIs**:
   ```
   # Development
   http://localhost/auth/facebook/callback

   # Production
   https://twoja-domena.pl/auth/facebook/callback
   ```

### 3. Włącz wymagane uprawnienia

W **App Review → Permissions and Features** włącz:

| Uprawnienie | Opis | Wymagany Review |
|-------------|------|-----------------|
| `pages_show_list` | Lista stron użytkownika | Nie (basic) |
| `pages_read_engagement` | Odczyt statystyk | Tak |
| `pages_manage_posts` | Publikacja na FB | Tak |
| `instagram_basic` | Podstawowe info IG | Tak |
| `instagram_content_publish` | Publikacja na IG | Tak |

> **Uwaga:** W trybie Development możesz testować z kontami administratorów aplikacji bez App Review.

### 4. Dodaj klucze do `.env`

```env
FACEBOOK_APP_ID=123456789012345
FACEBOOK_APP_SECRET=abcdef1234567890abcdef1234567890
FACEBOOK_OAUTH_REDIRECT_URL="${APP_URL}/auth/facebook/callback"
```

### 5. Uruchom migrację

```bash
php artisan migrate
```

### 6. (Produkcja) Przejdź App Review

Przed wdrożeniem na produkcję:

1. Przygotuj **Privacy Policy URL** i **Terms of Service URL**
2. Nagraj screencast pokazujący jak używasz uprawnień
3. Wyślij do review w panelu Meta

---

## API Endpoints

### Lista połączonych platform

```http
GET /api/v1/brands/{brand}/platforms
Authorization: Bearer {token}
```

**Response:**
```json
{
  "platforms": {
    "facebook": {
      "connected": true,
      "account_name": "Moja Strona",
      "account_id": "123456789",
      "connected_at": "2024-01-15T10:30:00Z",
      "is_expired": false,
      "is_expiring_soon": false
    },
    "instagram": {
      "connected": true,
      "account_name": "moj_instagram",
      "account_id": "987654321",
      "connected_at": "2024-01-15T10:30:00Z",
      "is_expired": false,
      "is_expiring_soon": false
    }
  }
}
```

### Pobierz URL autoryzacji

```http
GET /api/v1/brands/{brand}/platforms/facebook/auth-url
Authorization: Bearer {token}
```

**Response:**
```json
{
  "auth_url": "https://www.facebook.com/v18.0/dialog/oauth?client_id=..."
}
```

### Lista dostępnych stron (po OAuth)

```http
GET /api/v1/brands/{brand}/platforms/facebook/pages
Authorization: Bearer {token}
```

**Response:**
```json
{
  "pages": [
    {
      "id": "123456789",
      "name": "Moja Strona",
      "has_instagram": true,
      "instagram": {
        "id": "987654321",
        "username": "moj_instagram"
      }
    }
  ]
}
```

### Wybierz stronę do połączenia

```http
POST /api/v1/brands/{brand}/platforms/facebook/select-page
Authorization: Bearer {token}
Content-Type: application/json

{
  "page_id": "123456789"
}
```

### Rozłącz platformę

```http
DELETE /api/v1/brands/{brand}/platforms/{platform}
Authorization: Bearer {token}
```

### Zweryfikuj token

```http
POST /api/v1/brands/{brand}/platforms/{platform}/verify
Authorization: Bearer {token}
```

---

## Baza danych

### Tabela `platform_credentials`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | bigint | PK |
| `brand_id` | bigint | FK do brands |
| `platform` | string | 'facebook' lub 'instagram' |
| `platform_user_id` | string | ID strony/konta |
| `platform_user_name` | string | Nazwa strony/konta |
| `access_token` | text | **Zaszyfrowany** token |
| `token_expires_at` | timestamp | Data wygaśnięcia (null dla page tokens) |
| `refresh_token` | text | **Zaszyfrowany** refresh token |
| `metadata` | json | Dodatkowe dane (page_id, instagram_business_id) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indeksy:**
- `UNIQUE (brand_id, platform)` - jeden token per platforma per brand
- `INDEX (platform)`

---

## Bezpieczeństwo

### Szyfrowanie tokenów

Tokeny są automatycznie szyfrowane przed zapisem do bazy:

```php
// Model PlatformCredential
public function setAccessTokenAttribute(?string $value): void
{
    $this->attributes['access_token'] = $value ? encrypt($value) : null;
}

public function getAccessTokenAttribute(?string $value): ?string
{
    return $value ? decrypt($value) : null;
}
```

### CSRF Protection

State parameter w OAuth zawiera zaszyfrowane dane z timestampem:

```php
$state = encrypt([
    'brand_id' => $brand->id,
    'timestamp' => now()->timestamp,
]);
```

Walidacja sprawdza czy state nie jest starszy niż 10 minut.

### Autoryzacja

Wszystkie endpointy wymagają:
1. Uwierzytelnienia (`auth:sanctum`)
2. Autoryzacji do edycji brandu (`$this->authorize('update', $brand)`)

---

## Typy tokenów Facebook

| Typ | Ważność | Użycie |
|-----|---------|--------|
| User Access Token (short-lived) | ~1-2 godziny | Zwracany po OAuth |
| User Access Token (long-lived) | 60 dni | Po wymianie short-lived |
| Page Access Token | **Nigdy nie wygasa** | Do publikacji - zapisujemy ten! |

> **Ważne:** Zapisujemy Page Access Token, który nie wygasa (jeśli został uzyskany z long-lived user token).

---

## Obsługiwane typy publikacji

### Facebook

| Typ | Metoda | Opis |
|-----|--------|------|
| Tekst | `publishTextPost()` | Post bez mediów |
| Zdjęcie | `publishPhotoPost()` | Pojedyncze zdjęcie |
| Galeria | `publishMultiPhotoPost()` | Wiele zdjęć |
| Wideo | `publishVideoPost()` | Film |

### Instagram

| Typ | Metoda | Opis |
|-----|--------|------|
| Zdjęcie | `publishInstagramPhoto()` | Pojedyncze zdjęcie |
| Karuzela | `publishInstagramCarousel()` | Do 10 mediów |
| Reel | `publishInstagramReel()` | Krótki film |

> **Uwaga:** Instagram wymaga mediów - nie można publikować samego tekstu.

---

## Troubleshooting

### "Facebook OAuth is not configured"

Brak kluczy w `.env`. Dodaj:
```env
FACEBOOK_APP_ID=...
FACEBOOK_APP_SECRET=...
```

### "No Facebook Pages found"

Użytkownik nie ma uprawnień administratora do żadnej strony Facebook.

### "Instagram not connected"

Instagram Business Account musi być połączony ze stroną Facebook. Sprawdź:
1. Profil Instagram jest typu Business/Creator
2. Jest połączony ze stroną Facebook w ustawieniach IG

### "Token has expired"

Page Access Token nie powinien wygasać, ale jeśli tak się stanie:
1. Użytkownik musi ponownie połączyć konto
2. Kliknij "Połącz ponownie" w panelu

### Instagram container processing timeout

Przetwarzanie mediów na Instagramie trwa zbyt długo. Możliwe przyczyny:
- Zbyt duży plik wideo
- Problemy z formatem mediów
- Przeciążenie API Instagram

---

## Pliki źródłowe

```
app/
├── Http/
│   └── Controllers/
│       └── Api/V1/
│           └── PlatformCredentialController.php
├── Jobs/
│   └── PublishPostJob.php (zmodyfikowany)
├── Models/
│   ├── Brand.php (zmodyfikowany - dodana relacja)
│   └── PlatformCredential.php
└── Services/
    ├── OAuth/
    │   └── FacebookOAuthService.php
    └── Publishing/
        ├── FacebookPublishingService.php
        └── PublishingService.php (bez zmian - fallback)

config/
└── services.php (zmodyfikowany)

database/migrations/
└── 2026_01_24_195217_create_platform_credentials_table.php

resources/js/
├── components/brand/
│   └── ConnectedPlatformsPanel.vue
├── i18n/locales/
│   ├── en.json (zmodyfikowany)
│   └── pl.json (zmodyfikowany)
└── pages/
    └── BrandEditPage.vue (zmodyfikowany)

routes/
├── api.php (zmodyfikowany)
└── web.php (zmodyfikowany)
```

---

## Changelog

### 2024-01-24

- Implementacja OAuth flow dla Facebook/Instagram
- Bezpośrednia publikacja przez Graph API
- Panel zarządzania połączeniami w ustawieniach brandu
- Fallback do n8n webhook gdy brak tokenów
