# Attachments API

Załączniki (pliki, zdjęcia) dla pól typu `attachment`.

## Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| POST | `/api/v1/cells/{cellId}/attachments` | Upload załącznika |
| DELETE | `/api/v1/attachments/{id}` | Usuń załącznik |
| POST | `/api/v1/attachments/{id}/reorder` | Zmień pozycję |

---

## Upload załącznika

```http
POST /api/v1/cells/{cellId}/attachments
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: [binary file data]
```

**Wymagane pola:**
- `file` (file) - Plik do przesłania

**Limity (domyślne):**
- Maksymalny rozmiar: 10 MB
- Dozwolone typy: `image/*`, `application/pdf`

Limity można konfigurować w opcjach pola:
```json
{
    "options": {
        "max_size": 20971520,
        "allowed_types": ["image/*", "application/pdf", "text/*"]
    }
}
```

**Response 201:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQE...",
        "filename": "dokument.pdf",
        "url": "/storage/attachments/01HQ.../dokument.pdf",
        "thumbnail_url": null,
        "mime_type": "application/pdf",
        "size": 1048576,
        "size_formatted": "1 MB",
        "is_image": false,
        "is_pdf": true,
        "width": null,
        "height": null,
        "position": 0,
        "created_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Dla obrazów:**
```json
{
    "data": {
        "id": "01HQ7X5GNPQE...",
        "filename": "zdjecie.jpg",
        "url": "/storage/attachments/01HQ.../zdjecie.jpg",
        "thumbnail_url": "/storage/attachments/01HQ.../thumbs/zdjecie.jpg",
        "mime_type": "image/jpeg",
        "size": 524288,
        "size_formatted": "512 KB",
        "is_image": true,
        "is_pdf": false,
        "width": 1920,
        "height": 1080,
        "position": 0,
        "created_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Błędy:**

422 - Plik za duży:
```json
{
    "message": "The file field must not be greater than 10240 kilobytes.",
    "errors": {
        "file": ["The file field must not be greater than 10240 kilobytes."]
    }
}
```

422 - Nieprawidłowy typ:
```json
{
    "message": "The file field must be a file of type: jpg, jpeg, png, gif, webp, svg, pdf.",
    "errors": {
        "file": ["The file field must be a file of type: jpg, jpeg, png, gif, webp, svg, pdf."]
    }
}
```

---

## Usuń załącznik

```http
DELETE /api/v1/attachments/{id}
Authorization: Bearer {token}
```

**Response 204:** No Content

Plik jest usuwany z dysku, a jego ID jest automatycznie usuwany z wartości komórki.

---

## Zmień pozycję załącznika

Zmienia kolejność załączników w komórce.

```http
POST /api/v1/attachments/{id}/reorder
Authorization: Bearer {token}
Content-Type: application/json

{
    "position": 0
}
```

**Wymagane pola:**
- `position` (int, min: 0) - Nowa pozycja (0-indexed)

**Response 200:**
```json
{
    "success": true
}
```

---

## Uzyskanie Cell ID

Aby przesłać załącznik, potrzebujesz `cell_id`. Możesz go uzyskać:

1. **Z odpowiedzi update cell:**
```http
PUT /api/v1/rows/{rowId}/cells/{fieldId}
{"value": []}
```
Response zawiera `id` komórki.

2. **Z odpowiedzi row show:**
```http
GET /api/v1/rows/{rowId}
```
Response zawiera tablicę `cells` z identyfikatorami.

---

## Workflow uploadu załączników

```javascript
// 1. Upewnij się, że komórka istnieje
const cellResponse = await axios.put(`/api/v1/rows/${rowId}/cells/${fieldId}`, {
    value: currentAttachmentIds || []
});
const cellId = cellResponse.data.data.id;

// 2. Upload pliku
const formData = new FormData();
formData.append('file', file);

const attachmentResponse = await axios.post(
    `/api/v1/cells/${cellId}/attachments`,
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } }
);

// 3. Nowy załącznik jest automatycznie dodany do komórki
console.log(attachmentResponse.data.data);
```

---

## Właściwości załącznika

| Pole | Typ | Opis |
|------|-----|------|
| `id` | string | Unikalny identyfikator |
| `filename` | string | Oryginalna nazwa pliku |
| `url` | string | URL do pobrania pliku |
| `thumbnail_url` | string/null | URL miniatury (tylko dla obrazów) |
| `mime_type` | string | Typ MIME (np. `image/jpeg`) |
| `size` | int | Rozmiar w bajtach |
| `size_formatted` | string | Czytelny rozmiar (np. `1.5 MB`) |
| `is_image` | boolean | Czy to obraz |
| `is_pdf` | boolean | Czy to PDF |
| `width` | int/null | Szerokość obrazu w pikselach |
| `height` | int/null | Wysokość obrazu w pikselach |
| `position` | int | Pozycja w komórce |
| `created_at` | datetime | Data utworzenia |
