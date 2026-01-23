# Bases API

Bazy danych (workspaces) użytkownika.

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/v1/bases` | Lista baz |
| POST | `/api/v1/bases` | Utwórz bazę |
| GET | `/api/v1/bases/{id}` | Pobierz bazę |
| PUT | `/api/v1/bases/{id}` | Aktualizuj bazę |
| DELETE | `/api/v1/bases/{id}` | Usuń bazę |

---

## Lista baz

```http
GET /api/v1/bases
Authorization: Bearer {token}
```

**Query Parameters:**
- `per_page` (int, default: 20) - Liczba wyników na stronę

**Response 200:**
```json
{
    "data": [
        {
            "id": "01HQ7X5GNPQ8...",
            "name": "Moja baza",
            "description": "Opis bazy",
            "color": "#3B82F6",
            "icon": "database",
            "tables_count": 3,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ],
    "links": {...},
    "meta": {...}
}
```

---

## Utwórz bazę

```http
POST /api/v1/bases
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Nowa baza",
    "description": "Opcjonalny opis",
    "color": "#3B82F6",
    "icon": "folder"
}
```

**Wymagane pola:**
- `name` (string, max: 255)

**Opcjonalne pola:**
- `description` (string, max: 1000)
- `color` (string, format: `#RRGGBB`)
- `icon` (string, max: 50)

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "name": "Nowa baza",
        "description": "Opcjonalny opis",
        "color": "#3B82F6",
        "icon": "folder",
        "tables": [
            {
                "id": "01HQ7X5GNPQ9...",
                "name": "Table 1",
                "fields": [
                    {
                        "id": "01HQ7X5GNPQA...",
                        "name": "Name",
                        "type": "text",
                        "is_primary": true
                    }
                ]
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Uwaga:** Przy tworzeniu bazy automatycznie tworzona jest pierwsza tabela z polem głównym (primary).

---

## Pobierz bazę

```http
GET /api/v1/bases/{id}
Authorization: Bearer {token}
```

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "name": "Moja baza",
        "description": "Opis",
        "color": "#3B82F6",
        "icon": "database",
        "tables": [
            {
                "id": "01HQ7X5GNPQ9...",
                "name": "Tabela 1",
                "position": 0
            },
            {
                "id": "01HQ7X5GNPQB...",
                "name": "Tabela 2",
                "position": 1
            }
        ],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

## Aktualizuj bazę

```http
PUT /api/v1/bases/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Zmieniona nazwa",
    "description": "Nowy opis",
    "color": "#EF4444",
    "icon": "star"
}
```

Wszystkie pola są opcjonalne.

**Response 200:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQ8...",
        "name": "Zmieniona nazwa",
        ...
    }
}
```

---

## Usuń bazę

```http
DELETE /api/v1/bases/{id}
Authorization: Bearer {token}
```

**Response 204:** No Content

**Uwaga:** Usunięcie bazy kasuje wszystkie tabele, pola, wiersze i załączniki.
