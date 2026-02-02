# Social Posts API

API do zarzadzania postami w mediach spolecznosciowych z obsluga automatyzacji n8n.

## Workflow z n8n

1. **n8n generuje posty** - `POST /api/v1/posts`
2. **Posty widoczne w kalendarzu** - `GET /api/v1/posts/calendar`
3. **Weryfikacja postow** - `POST /api/v1/posts/{id}/approve`
4. **n8n pobiera zatwierdzone** - `GET /api/v1/posts/verified`
5. **n8n oznacza jako opublikowane** - `POST /api/v1/posts/{id}/mark-published`

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/posts` | Lista wszystkich postow |
| GET | `/api/v1/posts/calendar` | Posty w widoku kalendarza |
| GET | `/api/v1/posts/verified` | **Posty zatwierdzone do publikacji (n8n)** |
| GET | `/api/v1/posts/pending-approval` | Posty oczekujace na zatwierdzenie |
| POST | `/api/v1/posts` | Utworz nowy post |
| GET | `/api/v1/posts/{id}` | Pobierz post |
| PUT | `/api/v1/posts/{id}` | Aktualizuj post |
| DELETE | `/api/v1/posts/{id}` | Usun post |
| POST | `/api/v1/posts/{id}/approve` | Zatwierdz post |
| POST | `/api/v1/posts/{id}/reject` | Odrzuc post |
| POST | `/api/v1/posts/{id}/mark-published` | **Oznacz jako opublikowany (n8n)** |
| POST | `/api/v1/posts/{id}/mark-failed` | **Oznacz jako nieudany (n8n)** |
| POST | `/api/v1/posts/{id}/reschedule` | Zmien date publikacji |
| POST | `/api/v1/posts/{id}/duplicate` | Duplikuj post |

---

## Statusy postow

| Status | Wartosc | Opis |
|--------|---------|------|
| Draft | `draft` | Szkic - post w trakcie edycji |
| Pending Approval | `pending_approval` | Oczekuje na zatwierdzenie |
| Approved | `approved` | **Zatwierdzony - gotowy do publikacji** |
| Scheduled | `scheduled` | Zaplanowany do publikacji |
| Published | `published` | Opublikowany |
| Failed | `failed` | Publikacja nie powiodla sie |

---

## Lista postow

```http
GET /api/v1/posts
Authorization: Bearer {token}
```

**Query Parameters:**
- `per_page` (int, default: 20) - Liczba wynikow na strone
- `status` (string) - Filtr po statusie (draft, approved, published, etc.)
- `start` (date) - Data poczatkowa (dla scheduled_at)
- `end` (date) - Data koncowa (dla scheduled_at)

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ8...",
            "title": "Moj post",
            "main_caption": "Tresc posta do social media...",
            "status": "draft",
            "scheduled_at": "2024-01-20T15:00:00.000000Z",
            "published_at": null,
            "platform_posts": [
                {
                    "platform": "facebook",
                    "enabled": true,
                    "platform_caption": null,
                    "publish_status": "pending"
                },
                {
                    "platform": "instagram",
                    "enabled": true,
                    "platform_caption": null,
                    "publish_status": "pending"
                }
            ],
            "media": [],
            "media_count": 0,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

---

## Posty zatwierdzone (dla n8n)

Endpoint do pobierania postow zatwierdzonych, gotowych do publikacji.
**Uzywany przez n8n co godzine** do pobierania postow do automatycznej publikacji.

```http
GET /api/v1/posts/verified
Authorization: Bearer {token}
```

**Query Parameters:**
- `per_page` (int, default: 50) - Liczba wynikow na strone
- `ready_to_publish` (bool) - Tylko posty z scheduled_at <= teraz lub null
- `brand_id` (string) - Filtr po marce
- `start` (date) - Data poczatkowa
- `end` (date) - Data koncowa

**Przyklad dla n8n (posty gotowe do publikacji teraz):**
```http
GET /api/v1/posts/verified?ready_to_publish=true&per_page=10
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ8...",
            "title": "Zatwierdzony post",
            "main_caption": "Tresc gotowa do publikacji...",
            "status": "approved",
            "scheduled_at": "2024-01-15T12:00:00.000000Z",
            "platform_posts": [
                {
                    "platform": "facebook",
                    "enabled": true,
                    "platform_caption": "Customowa tresc dla FB",
                    "publish_status": "pending"
                }
            ],
            "media": [
                {
                    "id": "01HQ7X5GNPQ9...",
                    "type": "image",
                    "url": "https://example.com/storage/posts/image.jpg",
                    "thumbnail_url": "https://example.com/storage/posts/thumb_image.jpg"
                }
            ]
        }
    ]
}
```

---

## Utworz post

```http
POST /api/v1/posts
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Nowy post",
    "main_caption": "Tresc posta...",
    "scheduled_at": "2024-01-20T15:00:00Z",
    "platforms": ["facebook", "instagram"],
    "settings": {
        "auto_hashtags": true
    }
}
```

**Wymagane pola:**
- `title` (string, max: 255)

**Opcjonalne pola:**
- `main_caption` (string) - Glowna tresc posta
- `scheduled_at` (datetime) - Data zaplanowanej publikacji
- `platforms` (array) - Lista platform do publikacji: facebook, instagram, youtube
- `settings` (object) - Dodatkowe ustawienia

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "title": "Nowy post",
        "main_caption": "Tresc posta...",
        "status": "draft",
        "scheduled_at": "2024-01-20T15:00:00.000000Z",
        "platform_posts": [...],
        "media": []
    }
}
```

---

## Pobierz post

```http
GET /api/v1/posts/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "title": "Moj post",
        "main_caption": "Tresc...",
        "status": "draft",
        "scheduled_at": "2024-01-20T15:00:00.000000Z",
        "published_at": null,
        "platform_posts": [...],
        "media": [...],
        "approvals": [...]
    }
}
```

---

## Aktualizuj post

```http
PUT /api/v1/posts/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Zmieniony tytul",
    "main_caption": "Nowa tresc..."
}
```

Wszystkie pola sa opcjonalne.

**Response 200:**
```json
{
    "data": {...}
}
```

---

## Usun post

```http
DELETE /api/v1/posts/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "message": "Post deleted successfully"
}
```

---

## Zatwierdz post

Zmienia status posta na `approved`. Post jest gotowy do publikacji przez n8n.

```http
POST /api/v1/posts/{id}/approve
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "status": "approved",
        ...
    }
}
```

---

## Odrzuc post

Zmienia status posta z powrotem na `draft`.

```http
POST /api/v1/posts/{id}/reject
Authorization: Bearer {token}
Content-Type: application/json

{
    "feedback": "Prosze zmienic zdjecie na bardziej kolorowe"
}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "status": "draft",
        ...
    }
}
```

---

## Oznacz jako opublikowany (n8n)

Endpoint dla n8n po pomyslnej publikacji na platformie.

```http
POST /api/v1/posts/{id}/mark-published
Authorization: Bearer {token}
Content-Type: application/json

{
    "platform": "facebook",
    "external_id": "123456789",
    "external_url": "https://facebook.com/posts/123456789"
}
```

**Opcjonalne pola:**
- `platform` (string) - Nazwa platformy: facebook, instagram, youtube
- `external_id` (string) - ID posta na platformie
- `external_url` (url) - URL do posta na platformie

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "status": "published",
        "published_at": "2024-01-15T12:30:00.000000Z",
        "platform_posts": [
            {
                "platform": "facebook",
                "publish_status": "published",
                "published_at": "2024-01-15T12:30:00.000000Z",
                "external_id": "123456789",
                "external_url": "https://facebook.com/posts/123456789"
            }
        ]
    }
}
```

---

## Oznacz jako nieudany (n8n)

Endpoint dla n8n gdy publikacja sie nie powiodla.

```http
POST /api/v1/posts/{id}/mark-failed
Authorization: Bearer {token}
Content-Type: application/json

{
    "platform": "instagram",
    "error_message": "Media aspect ratio is not supported"
}
```

**Opcjonalne pola:**
- `platform` (string) - Nazwa platformy
- `error_message` (string) - Komunikat bledu

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "status": "failed",
        "platform_posts": [
            {
                "platform": "instagram",
                "publish_status": "failed",
                "error_message": "Media aspect ratio is not supported"
            }
        ]
    }
}
```

---

## Zmien date publikacji

```http
POST /api/v1/posts/{id}/reschedule
Authorization: Bearer {token}
Content-Type: application/json

{
    "scheduled_at": "2024-01-25T18:00:00Z"
}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "scheduled_at": "2024-01-25T18:00:00.000000Z",
        ...
    }
}
```

---

## Duplikuj post

```http
POST /api/v1/posts/{id}/duplicate
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQA...",
        "title": "Moj post (kopia)",
        "status": "draft",
        ...
    }
}
```

---

## Posty w kalendarzu

```http
GET /api/v1/posts/calendar?start=2024-01-01&end=2024-01-31
Authorization: Bearer {token}
```

**Wymagane Query Parameters:**
- `start` (date) - Data poczatkowa
- `end` (date) - Data koncowa

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ8...",
            "title": "Post 1",
            "scheduled_at": "2024-01-15T12:00:00.000000Z",
            "status": "approved",
            "platforms": ["facebook", "instagram"],
            "media_count": 2
        }
    ]
}
```

---

## Przykladowy workflow n8n

### 1. Generowanie posta

```http
POST /api/v1/posts
Authorization: Bearer {api_token}
Content-Type: application/json

{
    "title": "Post wygenerowany przez AI",
    "main_caption": "Tresc wygenerowana przez n8n...",
    "scheduled_at": "2024-01-20T15:00:00Z",
    "platforms": ["facebook", "instagram"]
}
```

### 2. Pobieranie zatwierdzonych (co godzine)

```http
GET /api/v1/posts/verified?ready_to_publish=true
Authorization: Bearer {api_token}
```

### 3. Po pomyslnej publikacji

```http
POST /api/v1/posts/{id}/mark-published
Authorization: Bearer {api_token}
Content-Type: application/json

{
    "platform": "facebook",
    "external_id": "fb_12345",
    "external_url": "https://facebook.com/page/posts/fb_12345"
}
```

### 4. Po nieudanej publikacji

```http
POST /api/v1/posts/{id}/mark-failed
Authorization: Bearer {api_token}
Content-Type: application/json

{
    "platform": "instagram",
    "error_message": "Rate limit exceeded"
}
```

---

## Uwagi

- Wszystkie endpointy wymagaja autentykacji przez Sanctum (Bearer token)
- Posty sa przypisane do uzytkownika - kazdy widzi tylko swoje posty
- ID postow to ULID (public_id), nie numeryczne ID
- Daty w formacie ISO 8601 (UTC)
