# Graphics Generation API

API do generowania grafik z szablonow z dynamiczna trescia.

## Workflow

1. **Przygotuj szablon** z warstwami oznaczonymi tagami semantycznymi (patrz [templates.md](./templates.md))
2. **Wyslij dane** do endpointu preview z wartosciami dla kazdego tagu
3. **Otrzymaj wygenerowana grafike** jako PNG/JPEG

```
Szablon z tagami     +     Dane (header, logo, itd.)     =     Gotowa grafika
```

---

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| POST | `/api/v1/library/templates/preview` | Generuj podglad z danymi |
| GET | `/api/v1/library/templates/semantic-tags` | Lista dostepnych tagow semantycznych |
| POST | `/api/v1/templates/{id}/generate` | Generuj grafike z szablonu |
| GET | `/api/v1/templates/{id}/images` | Lista wygenerowanych obrazow |
| POST | `/api/v1/templates/{id}/images` | Zapisz wygenerowany obraz |
| GET | `/api/v1/generated-images/{id}` | Pobierz wygenerowany obraz |
| DELETE | `/api/v1/generated-images/{id}` | Usun wygenerowany obraz |
| DELETE | `/api/v1/templates/{id}/images/bulk` | Bulk delete obrazow |

---

## Semantic Tags Reference

Tagi semantyczne okreslaja jakie dane mozna wstrzyknac do szablonu podczas generowania.

### Content Tags (tresc)

| Tag | Klucz wejsciowy | Typ warstw | Zastosowanie |
|-----|-----------------|------------|--------------|
| `header` | `header` | text, textbox | Glowny naglowek grafiki |
| `subtitle` | `subtitle` | text, textbox | Podtytul, opis |
| `paragraph` | `paragraph` | text, textbox | Dluzszy tekst akapitowy |
| `url` | `url` | text, textbox | Adres strony, link |
| `social_handle` | `social_handle` | text, textbox | @username dla social media |
| `cta` | `cta` | text, textbox | Call to action (np. "Kup teraz") |
| `main_image` | `main_image` | image | Glowne zdjecie/grafika |
| `logo` | `logo` | image | Logo firmy/marki |

### Style Tags (kolorystyka)

| Tag | Klucz wejsciowy | Typ warstw | Zastosowanie |
|-----|-----------------|------------|--------------|
| `primary_color` | `primary_color` | text, textbox, rectangle, ellipse | Kolor glowny brandingu |
| `secondary_color` | `secondary_color` | text, textbox, rectangle, ellipse | Kolor drugorzedny |
| `text_primary_color` | `primary_color` | text, textbox | Kolor tekstu glowny |
| `text_secondary_color` | `secondary_color` | text, textbox | Kolor tekstu drugorzedny |

---

## Generuj podglad z danymi

Glowny endpoint do generowania grafik. Przyjmuje szablon i dane, zwraca gotowy obraz.

```http
POST /api/v1/library/templates/preview
Authorization: Bearer {token}
Content-Type: application/json

{
    "template_id": "01HQ7X5GNPQ8...",
    "data": {
        "header": "Wielka Promocja!",
        "subtitle": "Tylko do konca tygodnia",
        "paragraph": "Skorzystaj z naszej oferty specjalnej i zaoszczedz do 50%",
        "url": "www.example.com",
        "social_handle": "@firma",
        "cta": "Kup teraz",
        "main_image": "https://example.com/product.jpg",
        "logo": "https://example.com/logo.png",
        "primary_color": "#FF5733",
        "secondary_color": "#333333"
    },
    "format": "png",
    "scale": 1
}
```

**Wymagane pola:**
- `template_id` (string) - ID szablonu z biblioteki

**Opcjonalne pola:**
- `data` (object) - Dane do podstawienia w tagi semantyczne
- `format` (string) - "png" lub "jpeg" (default: png)
- `scale` (float) - Skala obrazu 0.1-2.0 (default: 1)
- `quality` (int) - Jakosc JPEG 1-100 (default: 90)

### Struktura obiektu data

Klucze odpowiadaja tagom semantycznym:

```json
{
    "data": {
        // Content tags - teksty
        "header": "Tekst naglowka",
        "subtitle": "Tekst podtytulu",
        "paragraph": "Dluzszy tekst...",
        "url": "www.example.com",
        "social_handle": "@username",
        "cta": "Kliknij tutaj",

        // Content tags - obrazy (URL lub base64)
        "main_image": "https://example.com/image.jpg",
        "logo": "data:image/png;base64,iVBORw0KGgo...",

        // Style tags - kolory (hex)
        "primary_color": "#FF5733",
        "secondary_color": "#2C3E50"
    }
}
```

**Response 200:**
```json
{
    "data": {
        "image": "data:image/png;base64,iVBORw0KGgo...",
        "width": 1080,
        "height": 1080,
        "format": "png"
    }
}
```

**Response 422 (Validation Error):**
```json
{
    "message": "The template_id field is required.",
    "errors": {
        "template_id": ["The template_id field is required."]
    }
}
```

---

## Lista tagow semantycznych

Zwraca liste dostepnych tagow semantycznych z metadanymi.

```http
GET /api/v1/library/templates/semantic-tags
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": [
        {
            "value": "header",
            "label": "Header",
            "category": "content"
        },
        {
            "value": "subtitle",
            "label": "Subtitle",
            "category": "content"
        },
        {
            "value": "primary_color",
            "label": "Primary Color",
            "category": "style"
        }
    ]
}
```

---

## Generuj grafike z szablonu

Alternatywny endpoint dla wlasnych szablonow (nie z biblioteki).

```http
POST /api/v1/templates/{id}/generate
Authorization: Bearer {token}
Content-Type: application/json

{
    "data": {
        "header": "Moj naglowek",
        "main_image": "https://example.com/image.jpg"
    },
    "format": "png",
    "scale": 1
}
```

**Response 200:**
```json
{
    "data": {
        "image": "data:image/png;base64,..."
    }
}
```

---

## Lista wygenerowanych obrazow

```http
GET /api/v1/templates/{id}/images
Authorization: Bearer {token}
```

**Query Parameters:**
- `per_page` (int, default: 20)

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ9...",
            "template_id": "01HQ7X5GNPQ8...",
            "url": "https://example.com/storage/generated/abc.png",
            "thumbnail_url": "https://example.com/storage/generated/thumb_abc.png",
            "format": "png",
            "width": 1080,
            "height": 1080,
            "data": {
                "header": "Uzyte dane...",
                "logo": "https://..."
            },
            "created_at": "2024-01-15T10:30:00.000000Z"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

---

## Zapisz wygenerowany obraz

```http
POST /api/v1/templates/{id}/images
Authorization: Bearer {token}
Content-Type: application/json

{
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "data": {
        "header": "Uzyte dane...",
        "logo": "https://example.com/logo.png"
    }
}
```

**Wymagane pola:**
- `image` (string) - Base64-encoded obraz

**Opcjonalne pola:**
- `data` (object) - Dane uzyte do wygenerowania (dla historii)

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ9...",
        "url": "https://example.com/storage/generated/abc.png",
        "thumbnail_url": "https://example.com/storage/generated/thumb_abc.png",
        ...
    }
}
```

---

## Pobierz wygenerowany obraz

```http
GET /api/v1/generated-images/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ9...",
        "template_id": "01HQ7X5GNPQ8...",
        "url": "https://example.com/storage/generated/abc.png",
        "thumbnail_url": "https://example.com/storage/generated/thumb_abc.png",
        "format": "png",
        "width": 1080,
        "height": 1080,
        "data": {...},
        "created_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

## Usun wygenerowany obraz

```http
DELETE /api/v1/generated-images/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "message": "Generated image deleted successfully"
}
```

---

## Bulk delete obrazow

```http
DELETE /api/v1/templates/{id}/images/bulk
Authorization: Bearer {token}
Content-Type: application/json

{
    "ids": ["01HQ7X5GNPQ9...", "01HQ7X5GNPQA..."]
}
```

**Wymagane pola:**
- `ids` (array) - Lista ID obrazow do usuniecia

**Response 200:**
```json
{
    "message": "Deleted 2 images",
    "deleted_count": 2
}
```

---

## Przykladowy workflow automatyzacji

### 1. Pobierz szablon z biblioteki

```http
GET /api/v1/library/templates?has_semantic_tags=true
Authorization: Bearer {token}
```

### 2. Wygeneruj grafike z danymi z bazy

```http
POST /api/v1/library/templates/preview
Authorization: Bearer {token}
Content-Type: application/json

{
    "template_id": "01HQ7X5GNPQ8...",
    "data": {
        "header": "{{ row.title }}",
        "subtitle": "{{ row.description }}",
        "main_image": "{{ row.image_url }}",
        "primary_color": "{{ brand.primary_color }}"
    }
}
```

### 3. Zapisz wygenerowany obraz

```http
POST /api/v1/templates/{id}/images
Authorization: Bearer {token}
Content-Type: application/json

{
    "image": "data:image/png;base64,..."
}
```

### 4. Uzyj obrazu w poscie

```http
POST /api/v1/posts/{post_id}/media
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: [wygenerowany obraz]
```

---

## Uwagi

- Wszystkie endpointy wymagaja autentykacji przez Sanctum (Bearer token)
- Maksymalny rozmiar obrazu wejsciowego (main_image, logo): 10MB
- Obslugiwane formaty wyjsciowe: PNG, JPEG
- Renderowanie odbywa sie przez serwis template-renderer (Puppeteer/Konva)
- Czas renderowania zalezy od zlozonosci szablonu (typowo 1-5 sekund)
