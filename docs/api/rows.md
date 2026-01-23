# Rows API

Wiersze (rekordy) w tabeli.

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/tables/{tableId}/rows` | Lista wierszy |
| POST | `/api/v1/tables/{tableId}/rows` | Utwórz wiersz |
| GET | `/api/v1/rows/{id}` | Pobierz wiersz |
| PUT | `/api/v1/rows/{id}` | Aktualizuj wiersz |
| DELETE | `/api/v1/rows/{id}` | Usuń wiersz |
| POST | `/api/v1/tables/{tableId}/rows/bulk` | Utwórz wiele wierszy |
| DELETE | `/api/v1/tables/{tableId}/rows/bulk` | Usuń wiele wierszy |
| POST | `/api/v1/rows/{id}/reorder` | Zmień pozycję |

---

## Lista wierszy

```http
GET /api/v1/tables/{tableId}/rows
Authorization: Bearer {token}
```

**Query Parameters:**
- `per_page` (int, default: 50) - Liczba wyników na stronę

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQD...",
            "table_id": "01HQ7X5GNPQ9...",
            "position": 0,
            "values": {
                "01HQ7X5GNPQA...": "Jan Kowalski",
                "01HQ7X5GNPQB...": "jan@example.com",
                "01HQ7X5GNPQC...": {"id": "01HQ...", "name": "Aktywny", "color": "#22C55E"}
            },
            "cells": [...],
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

**Struktura `values`:**
Obiekt gdzie kluczem jest `field_id`, a wartością:
- `string` - dla text, url
- `float` - dla number
- `datetime string` - dla date
- `boolean` - dla checkbox
- `{id, name, color}` - dla select
- `[{id, name, color}, ...]` - dla multi_select
- `[{id, filename, url, ...}, ...]` - dla attachment
- `object/array` - dla json

---

## Utwórz wiersz

```http
POST /api/v1/tables/{tableId}/rows
Authorization: Bearer {token}
Content-Type: application/json

{
    "values": {
        "01HQ7X5GNPQA...": "Jan Kowalski",
        "01HQ7X5GNPQB...": "jan@example.com",
        "01HQ7X5GNPQC...": "01HQ..."
    }
}
```

**Opcjonalne pola:**
- `values` (object) - Wartości komórek (klucz: field_id, wartość: cell value)

**Wartości dla różnych typów pól:**
- `text`, `url`: `"string value"`
- `number`: `123.45`
- `date`: `"2024-01-15T10:30:00"`
- `checkbox`: `true` lub `false`
- `select`: `"choice_id"`
- `multi_select`: `["choice_id_1", "choice_id_2"]`
- `json`: `{"any": "object"}`

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQD...",
        "table_id": "01HQ7X5GNPQ9...",
        "position": 10,
        "values": {
            "01HQ7X5GNPQA...": "Jan Kowalski",
            ...
        },
        "cells": [...],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

## Pobierz wiersz

```http
GET /api/v1/rows/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQD...",
        "table_id": "01HQ7X5GNPQ9...",
        "position": 0,
        "values": {...},
        "cells": [
            {
                "id": 123,
                "row_id": "01HQ7X5GNPQD...",
                "field_id": "01HQ7X5GNPQA...",
                "value": "Jan Kowalski",
                "raw_value": "Jan Kowalski",
                "attachments": [],
                "created_at": "2024-01-15T10:30:00.000000Z",
                "updated_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

## Aktualizuj wiersz

```http
PUT /api/v1/rows/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "values": {
        "01HQ7X5GNPQA...": "Anna Nowak"
    },
    "position": 5
}
```

**Opcjonalne pola:**
- `values` (object) - Wartości do zaktualizowania
- `position` (int) - Nowa pozycja wiersza

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQD...",
        "values": {
            "01HQ7X5GNPQA...": "Anna Nowak",
            ...
        },
        ...
    }
}
```

---

## Usuń wiersz

```http
DELETE /api/v1/rows/{id}
Authorization: Bearer {token}
```

**Response 204:** No Content

---

## Utwórz wiele wierszy (bulk)

```http
POST /api/v1/tables/{tableId}/rows/bulk
Authorization: Bearer {token}
Content-Type: application/json

{
    "rows": [
        {
            "values": {
                "01HQ7X5GNPQA...": "Jan Kowalski",
                "01HQ7X5GNPQB...": "jan@example.com"
            }
        },
        {
            "values": {
                "01HQ7X5GNPQA...": "Anna Nowak",
                "01HQ7X5GNPQB...": "anna@example.com"
            }
        }
    ]
}
```

**Wymagane pola:**
- `rows` (array, min: 1, max: 100) - Tablica wierszy do utworzenia

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQD...",
            "values": {...},
            ...
        },
        {
            "id": "01HQ7X5GNPQE...",
            "values": {...},
            ...
        }
    ]
}
```

---

## Usuń wiele wierszy (bulk)

```http
DELETE /api/v1/tables/{tableId}/rows/bulk
Authorization: Bearer {token}
Content-Type: application/json

{
    "ids": [
        "01HQ7X5GNPQD...",
        "01HQ7X5GNPQE..."
    ]
}
```

**Wymagane pola:**
- `ids` (array, min: 1) - Tablica identyfikatorów wierszy do usunięcia

**Response 204:** No Content

---

## Zmień pozycję wiersza

```http
POST /api/v1/rows/{id}/reorder
Authorization: Bearer {token}
Content-Type: application/json

{
    "position": 0
}
```

**Response 200:**
```json
{
    "success": true
}
```
