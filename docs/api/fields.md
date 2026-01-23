# Fields API

Pola (kolumny) w tabeli.

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/tables/{tableId}/fields` | Lista pól |
| POST | `/api/v1/tables/{tableId}/fields` | Utwórz pole |
| GET | `/api/v1/fields/{id}` | Pobierz pole |
| PUT | `/api/v1/fields/{id}` | Aktualizuj pole |
| DELETE | `/api/v1/fields/{id}` | Usuń pole |
| POST | `/api/v1/fields/{id}/reorder` | Zmień pozycję |
| POST | `/api/v1/fields/{id}/choices` | Dodaj opcję wyboru |

---

## Typy pól

| Typ | Wartość w komórce | Opis |
|-----|-------------------|------|
| `text` | `string` | Tekst jednoliniowy |
| `number` | `float` | Liczba całkowita lub dziesiętna |
| `date` | `datetime string` | Data i opcjonalnie czas |
| `checkbox` | `boolean` | Wartość tak/nie |
| `select` | `choice_id` | Wybór jednej opcji |
| `multi_select` | `[choice_id, ...]` | Wybór wielu opcji |
| `attachment` | `[attachment_id, ...]` | Pliki/zdjęcia |
| `url` | `string` | Link URL |
| `json` | `object/array` | Dane strukturalne JSON |

---

## Lista pól

```http
GET /api/v1/tables/{tableId}/fields
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQA...",
            "table_id": "01HQ7X5GNPQ9...",
            "name": "Name",
            "type": "text",
            "type_label": "Tekst",
            "type_icon": "type",
            "options": null,
            "is_required": false,
            "is_primary": true,
            "position": 0,
            "width": 200,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        },
        {
            "id": "01HQ7X5GNPQB...",
            "table_id": "01HQ7X5GNPQ9...",
            "name": "Status",
            "type": "select",
            "type_label": "Wybór pojedynczy",
            "type_icon": "chevron-down",
            "options": {
                "choices": [
                    {"id": "01HQ...", "name": "Nowy", "color": "#3B82F6"},
                    {"id": "01HQ...", "name": "W trakcie", "color": "#EAB308"},
                    {"id": "01HQ...", "name": "Zakończony", "color": "#22C55E"}
                ]
            },
            "is_required": false,
            "is_primary": false,
            "position": 1,
            "width": 150,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ]
}
```

---

## Utwórz pole

```http
POST /api/v1/tables/{tableId}/fields
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Status",
    "type": "select",
    "options": {
        "choices": [
            {"name": "Nowy", "color": "#3B82F6"},
            {"name": "W trakcie", "color": "#EAB308"},
            {"name": "Zakończony", "color": "#22C55E"}
        ]
    },
    "is_required": false,
    "width": 150
}
```

**Wymagane pola:**
- `name` (string, max: 255)
- `type` (string, enum: `text`, `number`, `date`, `checkbox`, `select`, `multi_select`, `attachment`, `url`, `json`)

**Opcjonalne pola:**
- `options` (object) - Ustawienia pola (np. `choices` dla select)
- `is_required` (boolean, default: false)
- `is_primary` (boolean, default: false)
- `width` (integer, min: 50, max: 1000, default: 200)

**Opcje dla select/multi_select:**
```json
{
    "options": {
        "choices": [
            {"name": "Opcja 1", "color": "#3B82F6"},
            {"name": "Opcja 2", "color": "#22C55E"}
        ]
    }
}
```

**Opcje dla attachment:**
```json
{
    "options": {
        "max_size": 10485760,
        "allowed_types": ["image/*", "application/pdf"]
    }
}
```

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQB...",
        "table_id": "01HQ7X5GNPQ9...",
        "name": "Status",
        "type": "select",
        "type_label": "Wybór pojedynczy",
        "type_icon": "chevron-down",
        "options": {
            "choices": [
                {"id": "01HQ...", "name": "Nowy", "color": "#3B82F6"},
                {"id": "01HQ...", "name": "W trakcie", "color": "#EAB308"},
                {"id": "01HQ...", "name": "Zakończony", "color": "#22C55E"}
            ]
        },
        ...
    }
}
```

**Uwaga:** Identyfikatory i kolory dla choices są generowane automatycznie jeśli nie podane.

---

## Pobierz pole

```http
GET /api/v1/fields/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQB...",
        "table_id": "01HQ7X5GNPQ9...",
        "name": "Status",
        "type": "select",
        ...
    }
}
```

---

## Aktualizuj pole

```http
PUT /api/v1/fields/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Nowa nazwa",
    "options": {
        "choices": [
            {"id": "01HQ...", "name": "Zmieniona opcja", "color": "#EF4444"}
        ]
    },
    "width": 180
}
```

Wszystkie pola są opcjonalne.

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQB...",
        "name": "Nowa nazwa",
        ...
    }
}
```

---

## Usuń pole

```http
DELETE /api/v1/fields/{id}
Authorization: Bearer {token}
```

**Response 204:** No Content

**Błąd 422:** Nie można usunąć ostatniego pola głównego.

```json
{
    "message": "Cannot delete the last primary field"
}
```

---

## Zmień pozycję pola

```http
POST /api/v1/fields/{id}/reorder
Authorization: Bearer {token}
Content-Type: application/json

{
    "position": 2
}
```

**Response 200:**
```json
{
    "success": true
}
```

---

## Dodaj opcję wyboru

Szybkie dodanie nowej opcji do pola select/multi_select.

```http
POST /api/v1/fields/{id}/choices
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Nowa opcja",
    "color": "#8B5CF6"
}
```

**Wymagane pola:**
- `name` (string, max: 255)

**Opcjonalne pola:**
- `color` (string, format: `#RRGGBB`) - Losowy kolor jeśli nie podany

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQB...",
        "name": "Status",
        "type": "select",
        "options": {
            "choices": [
                {"id": "01HQ...", "name": "Nowy", "color": "#3B82F6"},
                {"id": "01HQ...", "name": "Nowa opcja", "color": "#8B5CF6"}
            ]
        },
        ...
    }
}
```
