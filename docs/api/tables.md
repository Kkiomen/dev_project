# Tables API

Tabele w bazie danych.

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/bases/{baseId}/tables` | Lista tabel |
| POST | `/api/v1/bases/{baseId}/tables` | Utwórz tabelę |
| GET | `/api/v1/tables/{id}` | Pobierz tabelę |
| PUT | `/api/v1/tables/{id}` | Aktualizuj tabelę |
| DELETE | `/api/v1/tables/{id}` | Usuń tabelę |
| POST | `/api/v1/tables/{id}/reorder` | Zmień pozycję |

---

## Lista tabel

```http
GET /api/v1/bases/{baseId}/tables
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ9...",
            "base_id": "01HQ7X5GNPQ8...",
            "name": "Kontakty",
            "description": null,
            "position": 0,
            "fields_count": 5,
            "rows_count": 150,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ]
}
```

---

## Utwórz tabelę

```http
POST /api/v1/bases/{baseId}/tables
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Nowa tabela",
    "description": "Opcjonalny opis"
}
```

**Wymagane pola:**
- `name` (string, max: 255)

**Opcjonalne pola:**
- `description` (string, max: 1000)

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ9...",
        "base_id": "01HQ7X5GNPQ8...",
        "name": "Nowa tabela",
        "description": "Opcjonalny opis",
        "position": 2,
        "fields": [
            {
                "id": "01HQ7X5GNPQA...",
                "name": "Name",
                "type": "text",
                "is_primary": true,
                "position": 0,
                "width": 200
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Uwaga:** Przy tworzeniu tabeli automatycznie tworzone jest pole główne `Name` typu `text`.

---

## Pobierz tabelę

```http
GET /api/v1/tables/{id}
Authorization: Bearer {token}
```

Zwraca tabelę z polami i wierszami.

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ9...",
        "base_id": "01HQ7X5GNPQ8...",
        "name": "Kontakty",
        "description": null,
        "position": 0,
        "fields": [
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
                "width": 200
            },
            {
                "id": "01HQ7X5GNPQB...",
                "name": "Email",
                "type": "text",
                ...
            },
            {
                "id": "01HQ7X5GNPQC...",
                "name": "Status",
                "type": "select",
                "options": {
                    "choices": [
                        {"id": "01HQ...", "name": "Aktywny", "color": "#22C55E"},
                        {"id": "01HQ...", "name": "Nieaktywny", "color": "#EF4444"}
                    ]
                },
                ...
            }
        ],
        "rows": [
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
                "created_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

## Aktualizuj tabelę

```http
PUT /api/v1/tables/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Zmieniona nazwa",
    "description": "Nowy opis"
}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ9...",
        "name": "Zmieniona nazwa",
        ...
    }
}
```

---

## Usuń tabelę

```http
DELETE /api/v1/tables/{id}
Authorization: Bearer {token}
```

**Response 204:** No Content

**Uwaga:** Usunięcie tabeli kasuje wszystkie pola, wiersze i załączniki.

---

## Zmień pozycję tabeli

```http
POST /api/v1/tables/{id}/reorder
Authorization: Bearer {token}
Content-Type: application/json

{
    "position": 0
}
```

**Wymagane pola:**
- `position` (int, min: 0) - Nowa pozycja tabeli (0-indexed)

**Response 200:**
```json
{
    "success": true
}
```
