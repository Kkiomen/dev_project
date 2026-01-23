# Cells API

Komórki (pojedyncze wartości) w wierszach.

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| PUT | `/api/v1/rows/{rowId}/cells/{fieldId}` | Aktualizuj komórkę |
| PUT | `/api/v1/rows/{rowId}/cells` | Aktualizuj wiele komórek |

---

## Aktualizuj komórkę

```http
PUT /api/v1/rows/{rowId}/cells/{fieldId}
Authorization: Bearer {token}
Content-Type: application/json

{
    "value": "Nowa wartość"
}
```

**Wymagane pola:**
- `value` - Wartość komórki (typ zależy od pola)

**Wartości dla różnych typów pól:**

### Text / URL
```json
{"value": "Tekst lub URL"}
```

### Number
```json
{"value": 123.45}
```

### Date
```json
{"value": "2024-01-15T10:30:00"}
```

### Checkbox
```json
{"value": true}
```

### Select
```json
{"value": "01HQ7X5GNPQA..."}
```
Wartość to `choice_id` z opcji pola.

### Multi-select
```json
{"value": ["01HQ7X5GNPQA...", "01HQ7X5GNPQB..."]}
```
Tablica `choice_id`.

### JSON
```json
{"value": {"klucz": "wartość", "liczba": 42}}
```

### Null (wyczyść wartość)
```json
{"value": null}
```

**Response 200:**
```json
{
    "data": {
        "id": 123,
        "row_id": "01HQ7X5GNPQD...",
        "field_id": "01HQ7X5GNPQA...",
        "value": "Nowa wartość",
        "raw_value": "Nowa wartość",
        "attachments": [],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:35:00.000000Z"
    }
}
```

**Błąd 422:** Nieprawidłowa wartość dla typu pola.

```json
{
    "message": "The value is not valid for this field type."
}
```

---

## Aktualizuj wiele komórek

Aktualizacja wielu komórek w jednym wierszu jednym requestem.

```http
PUT /api/v1/rows/{rowId}/cells
Authorization: Bearer {token}
Content-Type: application/json

{
    "values": {
        "01HQ7X5GNPQA...": "Jan Kowalski",
        "01HQ7X5GNPQB...": "jan@example.com",
        "01HQ7X5GNPQC...": 42,
        "01HQ7X5GNPQD...": true
    }
}
```

**Wymagane pola:**
- `values` (object) - Klucz to `field_id`, wartość to nowa wartość komórki

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQD...",
        "table_id": "01HQ7X5GNPQ9...",
        "position": 0,
        "values": {
            "01HQ7X5GNPQA...": "Jan Kowalski",
            "01HQ7X5GNPQB...": "jan@example.com",
            "01HQ7X5GNPQC...": 42,
            "01HQ7X5GNPQD...": true
        },
        "cells": [...],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:35:00.000000Z"
    }
}
```

---

## Uwagi

### Struktura wartości w odpowiedziach

Dla pól `select` i `multi_select` wartość w odpowiedzi zawiera pełny obiekt choice:

**Select:**
```json
{
    "value": {
        "id": "01HQ7X5GNPQA...",
        "name": "Aktywny",
        "color": "#22C55E"
    }
}
```

**Multi-select:**
```json
{
    "value": [
        {"id": "01HQ...", "name": "Tag 1", "color": "#3B82F6"},
        {"id": "01HQ...", "name": "Tag 2", "color": "#22C55E"}
    ]
}
```

### Różnica między `value` a `raw_value`

- `value` - Sformatowana wartość do wyświetlenia (np. obiekt choice dla select)
- `raw_value` - Surowa wartość zapisana w bazie (np. choice_id dla select)

### Załączniki

Dla pól typu `attachment` wartości są zarządzane przez [Attachments API](./attachments.md).
Upload/usuwanie plików zmienia automatycznie wartość komórki.
