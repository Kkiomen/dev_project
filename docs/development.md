# Poradnik Developera

Konfiguracja środowiska i konwencje projektu.

## Wymagania

- Docker & Docker Compose
- Node.js 20+ (dla lokalnego developmentu)
- PHP 8.5+ (opcjonalnie, dla IDE)

---

## Uruchomienie

### Z Docker (zalecane)

```bash
# Pierwszy raz
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate

# Codzienne
./vendor/bin/sail up -d

# Budowanie frontendu
npm install
npm run build
# lub development z HMR
npm run dev
```

### Pełny stack developerski

```bash
composer dev
```

Uruchamia równolegle:
- PHP server (`php artisan serve`)
- Queue worker (`php artisan queue:work`)
- Log viewer (`php artisan pail`)
- Vite dev server (`npm run dev`)

---

## Struktura kodu

### Backend

```
app/
├── Enums/
│   └── FieldType.php           # Typy pól (text, number, etc.)
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/             # REST API kontrolery
│   │   │   ├── BaseController.php
│   │   │   ├── TableController.php
│   │   │   ├── FieldController.php
│   │   │   ├── RowController.php
│   │   │   ├── CellController.php
│   │   │   └── AttachmentController.php
│   │   ├── Auth/               # Autentykacja (Breeze)
│   │   └── Web/                # Kontrolery webowe
│   ├── Requests/Api/           # Form Requests z walidacją
│   └── Resources/              # API Resources (transformacje)
├── Models/
│   ├── Base.php                # Workspace użytkownika
│   ├── Table.php               # Tabela w bazie
│   ├── Field.php               # Kolumna/pole
│   ├── Row.php                 # Wiersz/rekord
│   ├── Cell.php                # Komórka (wartość)
│   └── Attachment.php          # Załącznik
├── Policies/                   # Autoryzacja
└── Services/
    ├── AttachmentService.php   # Upload plików
    └── CodeImageService.php    # Integracja z Rayso
```

### Frontend

```
resources/
├── js/
│   ├── app.js                  # Entry point Vue
│   ├── App.vue                 # Root component
│   ├── bootstrap.js            # Axios setup
│   ├── router/                 # Vue Router
│   ├── stores/                 # Pinia stores
│   ├── composables/            # Reusable logic
│   ├── components/             # Vue components
│   └── pages/                  # Page components
├── css/
│   └── app.css                 # Tailwind entry
└── views/                      # Blade templates (legacy)
```

---

## Konwencje

### API Endpoints

- Wersjonowanie: `/api/v1/`
- REST: `GET`, `POST`, `PUT`, `DELETE`
- Nested resources: `/bases/{base}/tables`
- Shallow nesting: `/tables/{table}` (po utworzeniu)
- Akcje: `/tables/{table}/reorder`

### Identyfikatory

- Publiczne: ULID (26 znaków, np. `01HQ7X5GNPQ8...`)
- Wewnętrzne: auto-increment integers

### Nazewnictwo

- Kontrolery: `{Resource}Controller`
- Requests: `Store{Resource}Request`, `Update{Resource}Request`
- Resources: `{Resource}Resource`
- Policies: `{Resource}Policy`

### Modele

- Trait `HasPublicId` - automatyczne ULID
- Trait `HasPosition` - sortowanie z `moveToPosition()`
- Soft deletes dla `Base`, `Table`

---

## Testy

```bash
# Wszystkie testy
composer test

# Konkretny plik
./vendor/bin/pest tests/Feature/Api/BaseControllerTest.php

# Z filtrem
./vendor/bin/pest --filter="can create base"
```

### Struktura testów

```
tests/
├── Feature/
│   ├── Api/                    # Testy API endpoints
│   ├── Auth/                   # Testy autentykacji
│   └── ProfileTest.php
├── Unit/                       # Testy jednostkowe
└── Pest.php                    # Konfiguracja Pest
```

### Konwencje testów

```php
it('can create a base', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/bases', [
            'name' => 'Test Base',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test Base');
});
```

---

## Baza danych

### Migracje

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:fresh --seed
```

### Seeders

```bash
./vendor/bin/sail artisan db:seed
```

### Tinker

```bash
./vendor/bin/sail artisan tinker
```

---

## Frontend Development

### Vite

```bash
npm run dev     # Development server z HMR
npm run build   # Production build
```

### Tailwind CSS 4

```css
/* resources/css/app.css */
@import "tailwindcss";
```

### Vue 3 + Pinia

```javascript
// Store
import { defineStore } from 'pinia'

export const useBasesStore = defineStore('bases', {
    state: () => ({
        bases: [],
        loading: false,
    }),
    actions: {
        async fetchBases() { ... }
    }
})
```

---

## Debugowanie

### Laravel Telescope

Dostępne pod `/telescope` w development.

### Logi

```bash
./vendor/bin/sail artisan pail
# lub
tail -f storage/logs/laravel.log
```

### API Debugging

```bash
# Health check Rayso
curl http://localhost:3333/health
```

---

## Docker Services

| Service | Port | Opis |
|---------|------|------|
| `laravel.test` | 80 | Aplikacja PHP |
| `mysql` | 3306 | Baza danych |
| `redis` | 6379 | Cache/sesje |
| `rayso` | 3333 | Generowanie obrazów kodu |

```bash
# Restart pojedynczego serwisu
./vendor/bin/sail restart rayso

# Logi serwisu
docker logs dev-project-rayso-1 --tail 50

# Wejście do kontenera
./vendor/bin/sail shell
```

---

## Environment Variables

```env
# Baza danych
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=laravel

# Redis
REDIS_HOST=redis

# Rayso
RAYSO_URL=http://rayso:3333
RAYSO_TIMEOUT=30

# Vite (development)
VITE_DEV_MODE=true
```
