# Dokumentacja Projektu

Panel do automatyzacji procesów tworzenia marki osobistej.

## Spis treści

- [API Reference](./api/README.md) - Dokumentacja endpointów REST API
- [Panel Użytkownika](./panel/README.md) - Opis funkcjonalności panelu
- [Poradnik Developera](./development.md) - Konfiguracja środowiska i konwencje
- [Rayso API](./rayso-api.md) - Generowanie grafik z kodu

---

## Architektura

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend                              │
│   Vue 3 + Pinia + Vue Router + Tailwind CSS                 │
├─────────────────────────────────────────────────────────────┤
│                      REST API (v1)                           │
│   Laravel 12 + Sanctum Authentication                       │
├─────────────────────────────────────────────────────────────┤
│                      Baza danych                             │
│   MySQL 8.4 + Redis (cache/sesje)                           │
└─────────────────────────────────────────────────────────────┘
```

## Model danych

```
User
 └── Base (workspace)
      └── Table
           ├── Field (kolumny)
           │    └── options (choices dla select/multi_select)
           └── Row (wiersze)
                └── Cell (komórki)
                     └── Attachment (załączniki dla pól typu attachment)
```

## Typy pól

| Typ | Opis | Wartość |
|-----|------|---------|
| `text` | Tekst jednoliniowy | `string` |
| `number` | Liczba | `float` |
| `date` | Data i czas | `datetime string` |
| `checkbox` | Tak/Nie | `boolean` |
| `select` | Wybór pojedynczy | `choice_id` |
| `multi_select` | Wybór wielokrotny | `[choice_id, ...]` |
| `attachment` | Pliki/zdjęcia | `[attachment_id, ...]` |
| `url` | Link | `string` |
| `json` | Dane strukturalne | `object/array` |

## Szybki start

```bash
# Uruchomienie środowiska
./vendor/bin/sail up -d

# Migracje
./vendor/bin/sail artisan migrate

# Budowanie frontendu
npm run build

# Lub development z hot reload
npm run dev
```

## Struktura projektu

```
app/
├── Enums/           # Typy pól (FieldType)
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/  # Kontrolery API
│   │   └── Web/     # Kontrolery webowe
│   ├── Requests/    # Walidacja requestów
│   └── Resources/   # Transformacje JSON
├── Models/          # Eloquent models
├── Policies/        # Autoryzacja
└── Services/        # Logika biznesowa

resources/
├── js/              # Vue.js aplikacja
├── css/             # Style Tailwind
└── views/           # Blade templates (legacy)

docs/                # Ta dokumentacja
```
