# API Reference

REST API w wersji 1 (`/api/v1`).

## Autentykacja

API wymaga tokena Sanctum. Token przekazuj w nagłówku:

```http
Authorization: Bearer {token}
```

### Uzyskanie tokena

```http
POST /api/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "secret"
}
```

**Response:**
```json
{
    "token": "1|abc123...",
    "user": {
        "id": "01HQ...",
        "name": "Jan Kowalski",
        "email": "user@example.com"
    }
}
```

### Informacje o użytkowniku

```http
GET /api/user
Authorization: Bearer {token}
```

---

## Endpointy

| Zasób | Dokumentacja | Endpointy |
|-------|--------------|-----------|
| Bases | [bases.md](./bases.md) | CRUD baz danych |
| Tables | [tables.md](./tables.md) | CRUD tabel + reorder |
| Fields | [fields.md](./fields.md) | CRUD pól + choices |
| Rows | [rows.md](./rows.md) | CRUD wierszy + bulk |
| Cells | [cells.md](./cells.md) | Update komórek |
| Attachments | [attachments.md](./attachments.md) | Upload plików |

---

## Format odpowiedzi

Wszystkie odpowiedzi są w formacie JSON z envelope `data`:

```json
{
    "data": {
        "id": "01HQ...",
        "name": "Example"
    }
}
```

Kolekcje z paginacją:

```json
{
    "data": [...],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 20,
        "to": 20,
        "total": 100
    }
}
```

---

## Kody błędów

| Kod | Znaczenie |
|-----|-----------|
| 200 | Sukces |
| 201 | Utworzono |
| 204 | Usunięto (no content) |
| 401 | Brak autoryzacji |
| 403 | Brak uprawnień |
| 404 | Nie znaleziono |
| 422 | Błąd walidacji |
| 500 | Błąd serwera |

Błędy walidacji:

```json
{
    "message": "The name field is required.",
    "errors": {
        "name": ["The name field is required."]
    }
}
```

---

## Identyfikatory

Wszystkie zasoby używają publicznych ULID jako identyfikatorów (np. `01HQ7X5GNPQ8...`).
