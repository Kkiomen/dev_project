# Templates API

API do zarzadzania szablonami graficznymi i warstwami.

## Workflow

1. **Utworz szablon** - `POST /api/v1/templates`
2. **Dodaj warstwy** - `POST /api/v1/templates/{id}/layers`
3. **Oznacz warstwy tagami semantycznymi** - `PUT /api/v1/layers/{id}` z `semantic_tag`
4. **Generuj grafike z danymi** - `POST /api/v1/library/templates/preview`

---

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/templates` | Lista szablonow |
| POST | `/api/v1/templates` | Utworz szablon |
| GET | `/api/v1/templates/{id}` | Pobierz szablon |
| PUT | `/api/v1/templates/{id}` | Aktualizuj szablon |
| DELETE | `/api/v1/templates/{id}` | Usun szablon |
| POST | `/api/v1/templates/{id}/duplicate` | Duplikuj szablon |
| POST | `/api/v1/templates/{id}/thumbnail` | Upload thumbnail |
| POST | `/api/v1/templates/{id}/background` | Upload background image |
| GET | `/api/v1/templates/{id}/layers` | Lista warstw |
| POST | `/api/v1/templates/{id}/layers` | Dodaj warstwe |
| PUT | `/api/v1/layers/{id}` | Aktualizuj warstwe |
| DELETE | `/api/v1/layers/{id}` | Usun warstwe |
| PUT | `/api/v1/templates/{id}/layers` | Bulk update warstw |
| POST | `/api/v1/layers/{id}/reorder` | Zmien kolejnosc warstwy |

---

## Typy warstw

| Typ | Wartosc | Opis |
|-----|---------|------|
| Text | `text` | Tekst jednoliniowy (point text) |
| Text Box | `textbox` | Tekst wieloliniowy z zawijaniem |
| Image | `image` | Obraz (url lub base64) |
| Rectangle | `rectangle` | Prostokat |
| Ellipse | `ellipse` | Elipsa/kolo |
| Line | `line` | Linia |
| Group | `group` | Grupa warstw |

---

## Semantic Tags

Tagi semantyczne pozwalaja oznaczyc warstwy, ktore beda wypelniane dynamicznie podczas generowania grafiki.

### Content Tags (dane)

| Tag | Wartosc | Typy warstw | Opis |
|-----|---------|-------------|------|
| Header | `header` | text, textbox | Glowny naglowek |
| Subtitle | `subtitle` | text, textbox | Podtytul |
| Paragraph | `paragraph` | text, textbox | Tekst akapitu |
| URL | `url` | text, textbox | Adres URL/link |
| Social Handle | `social_handle` | text, textbox | Uchwyt social media (@) |
| Main Image | `main_image` | image | Glowny obraz |
| Logo | `logo` | image | Logo |
| Call to Action | `cta` | text, textbox | Przycisk/wezwanie do akcji |

### Style Tags (kolory)

| Tag | Wartosc | Typy warstw | Opis |
|-----|---------|-------------|------|
| Primary Color | `primary_color` | text, textbox, rectangle, ellipse | Kolor glowny (fill) |
| Secondary Color | `secondary_color` | text, textbox, rectangle, ellipse | Kolor drugorzedny (fill) |
| Text Primary Color | `text_primary_color` | text, textbox | Kolor tekstu glowny |
| Text Secondary Color | `text_secondary_color` | text, textbox | Kolor tekstu drugorzedny |

---

## Lista szablonow

```http
GET /api/v1/templates
Authorization: Bearer {token}
```

**Query Parameters:**
- `per_page` (int, default: 20) - Liczba wynikow na strone
- `search` (string) - Szukaj po nazwie
- `in_library` (bool) - Tylko szablony z biblioteki

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ8...",
            "name": "Instagram Post",
            "description": "Post 1080x1080",
            "canvas_width": 1080,
            "canvas_height": 1080,
            "canvas_background_color": "#ffffff",
            "thumbnail_url": "https://example.com/storage/thumbnails/abc.png",
            "background_image_url": null,
            "in_library": true,
            "layers_count": 5,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

---

## Utworz szablon

```http
POST /api/v1/templates
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Instagram Post",
    "description": "Post 1080x1080 dla Instagram",
    "canvas_width": 1080,
    "canvas_height": 1080,
    "canvas_background_color": "#ffffff"
}
```

**Wymagane pola:**
- `name` (string, max: 255)

**Opcjonalne pola:**
- `description` (string)
- `canvas_width` (int, default: 1080)
- `canvas_height` (int, default: 1080)
- `canvas_background_color` (string, hex color, default: #ffffff)

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "name": "Instagram Post",
        "description": "Post 1080x1080 dla Instagram",
        "canvas_width": 1080,
        "canvas_height": 1080,
        "canvas_background_color": "#ffffff",
        "thumbnail_url": null,
        "background_image_url": null,
        "in_library": false,
        "layers": [],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

## Pobierz szablon

```http
GET /api/v1/templates/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "name": "Instagram Post",
        "canvas_width": 1080,
        "canvas_height": 1080,
        "canvas_background_color": "#ffffff",
        "layers": [
            {
                "id": "01HQ7X5GNPQ9...",
                "name": "Header",
                "type": "text",
                "properties": {
                    "x": 100,
                    "y": 200,
                    "text": "Sample Text",
                    "fontSize": 48,
                    "fontFamily": "Inter",
                    "fill": "#000000"
                },
                "semantic_tag": "header",
                "visible": true,
                "locked": false,
                "order": 0
            }
        ]
    }
}
```

---

## Aktualizuj szablon

```http
PUT /api/v1/templates/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Nowa nazwa",
    "canvas_width": 1200,
    "canvas_height": 628
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

## Usun szablon

```http
DELETE /api/v1/templates/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "message": "Template deleted successfully"
}
```

---

## Duplikuj szablon

```http
POST /api/v1/templates/{id}/duplicate
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQA...",
        "name": "Instagram Post (kopia)",
        ...
    }
}
```

---

## Upload thumbnail

```http
POST /api/v1/templates/{id}/thumbnail
Authorization: Bearer {token}
Content-Type: multipart/form-data

thumbnail: [file]
```

**Wymagane:**
- `thumbnail` (file, image, max: 5MB)

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "thumbnail_url": "https://example.com/storage/thumbnails/abc.png",
        ...
    }
}
```

---

## Upload background image

```http
POST /api/v1/templates/{id}/background
Authorization: Bearer {token}
Content-Type: multipart/form-data

background: [file]
```

**Wymagane:**
- `background` (file, image, max: 10MB)

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "background_image_url": "https://example.com/storage/backgrounds/xyz.png",
        ...
    }
}
```

---

## Lista warstw

```http
GET /api/v1/templates/{id}/layers
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ9...",
            "name": "Header",
            "type": "text",
            "properties": {...},
            "semantic_tag": "header",
            "visible": true,
            "locked": false,
            "order": 0
        },
        {
            "id": "01HQ7X5GNPQA...",
            "name": "Logo",
            "type": "image",
            "properties": {...},
            "semantic_tag": "logo",
            "visible": true,
            "locked": false,
            "order": 1
        }
    ]
}
```

---

## Dodaj warstwe

```http
POST /api/v1/templates/{id}/layers
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Header Text",
    "type": "text",
    "properties": {
        "x": 100,
        "y": 200,
        "text": "Hello World",
        "fontSize": 48,
        "fontFamily": "Inter",
        "fontWeight": "bold",
        "fill": "#000000"
    },
    "semantic_tag": "header"
}
```

**Wymagane pola:**
- `name` (string, max: 255)
- `type` (string) - text, textbox, image, rectangle, ellipse, line, group

**Opcjonalne pola:**
- `properties` (object) - Wlasciwosci zaleznie od typu
- `semantic_tag` (string) - Tag semantyczny
- `visible` (bool, default: true)
- `locked` (bool, default: false)
- `parent_id` (string) - ID rodzica (dla grup)

### Properties wedlug typu

**text/textbox:**
```json
{
    "x": 100,
    "y": 200,
    "text": "Sample",
    "fontSize": 24,
    "fontFamily": "Inter",
    "fontWeight": "normal",
    "fontStyle": "normal",
    "fill": "#000000",
    "textAlign": "left",
    "lineHeight": 1.2,
    "letterSpacing": 0,
    "width": 400,
    "height": 100
}
```

**image:**
```json
{
    "x": 100,
    "y": 200,
    "width": 400,
    "height": 300,
    "src": "https://example.com/image.jpg"
}
```

**rectangle:**
```json
{
    "x": 100,
    "y": 200,
    "width": 400,
    "height": 300,
    "fill": "#ff0000",
    "stroke": "#000000",
    "strokeWidth": 2,
    "cornerRadius": 10
}
```

**ellipse:**
```json
{
    "x": 100,
    "y": 200,
    "radiusX": 50,
    "radiusY": 50,
    "fill": "#ff0000",
    "stroke": "#000000",
    "strokeWidth": 2
}
```

**line:**
```json
{
    "points": [0, 0, 100, 100],
    "stroke": "#000000",
    "strokeWidth": 2
}
```

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ9...",
        "name": "Header Text",
        "type": "text",
        "properties": {...},
        "semantic_tag": "header",
        "visible": true,
        "locked": false,
        "order": 0
    }
}
```

---

## Aktualizuj warstwe

```http
PUT /api/v1/layers/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Updated Header",
    "properties": {
        "text": "New Text",
        "fontSize": 56
    },
    "semantic_tag": "header"
}
```

Wszystkie pola sa opcjonalne. Properties sa mergowane (nie nadpisywane).

**Response 200:**
```json
{
    "data": {...}
}
```

---

## Usun warstwe

```http
DELETE /api/v1/layers/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "message": "Layer deleted successfully"
}
```

---

## Bulk update warstw

```http
PUT /api/v1/templates/{id}/layers
Authorization: Bearer {token}
Content-Type: application/json

{
    "layers": [
        {
            "id": "01HQ7X5GNPQ9...",
            "properties": {
                "text": "Updated text"
            }
        },
        {
            "id": "01HQ7X5GNPQA...",
            "visible": false
        }
    ]
}
```

**Response 200:**
```json
{
    "data": [...]
}
```

---

## Zmien kolejnosc warstwy

```http
POST /api/v1/layers/{id}/reorder
Authorization: Bearer {token}
Content-Type: application/json

{
    "order": 2
}
```

**Wymagane:**
- `order` (int) - Nowa pozycja (0-indexed)

**Response 200:**
```json
{
    "data": {...}
}
```

---

## Uwagi

- Wszystkie endpointy wymagaja autentykacji przez Sanctum (Bearer token)
- Szablony sa przypisane do uzytkownika - kazdy widzi tylko swoje szablony
- ID szablonow i warstw to ULID (public_id), nie numeryczne ID
- Warstwy sa sortowane wedlug pola `order` (0 = na wierzchu)
