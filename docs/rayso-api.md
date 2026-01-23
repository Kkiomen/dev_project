# Rayso API - Generowanie grafik kodu

API do automatycznego generowania estetycznych grafik z kodu źródłowego, wykorzystujące [ray.so](https://ray.so).

## Spis treści

- [Jak to działa](#jak-to-działa)
- [Uruchomienie](#uruchomienie)
- [Endpointy API](#endpointy-api)
- [Parametry](#parametry)
- [Przykłady użycia](#przykłady-użycia)
- [Integracja z Laravel](#integracja-z-laravel)
- [Rozwiązywanie problemów](#rozwiązywanie-problemów)

---

## Jak to działa

```
┌─────────────┐      ┌─────────────┐      ┌─────────────┐      ┌─────────────┐
│   Request   │ ---> │  Rayso API  │ ---> │   ray.so    │ ---> │  Screenshot │
│  (JSON)     │      │  (Express)  │      │  (Puppeteer)│      │    (PNG)    │
└─────────────┘      └─────────────┘      └─────────────┘      └─────────────┘
```

1. **Request** - Aplikacja (Laravel/Postman/curl) wysyła żądanie POST z kodem i opcjami
2. **Rayso API** - Serwer Express.js waliduje dane i uruchamia Puppeteer
3. **Puppeteer** - Headless Chrome otwiera ray.so z zakodowanym kodem w URL
4. **Screenshot** - Puppeteer robi screenshot elementu z kodem
5. **Sharp** - Opcjonalnie skaluje obraz do żądanego rozmiaru (z zachowaniem jakości)
6. **Response** - Zwraca binarny plik PNG

### Architektura Docker

```
┌─────────────────────────────────────────────────────────────┐
│                      Docker Network: sail                    │
│                                                              │
│  ┌──────────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │ laravel.test │  │  mysql   │  │  redis   │  │  rayso   │ │
│  │    :80       │  │  :3306   │  │  :6379   │  │  :3333   │ │
│  └──────────────┘  └──────────┘  └──────────┘  └──────────┘ │
│         │                                            │       │
│         └────────────── HTTP ───────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
                              │
                         Port :3333
                              │
                    ┌─────────────────┐
                    │  External Access │
                    │  (Postman, curl) │
                    └─────────────────┘
```

---

## Uruchomienie

### Build i start

```bash
# Zbuduj kontener rayso
./vendor/bin/sail build rayso

# Uruchom wszystkie serwisy
./vendor/bin/sail up -d

# Sprawdź status
./vendor/bin/sail ps
```

### Weryfikacja

```bash
# Health check
curl http://localhost:3333/health

# Oczekiwana odpowiedź:
# {"status":"ok","service":"rayso-api"}
```

---

## Endpointy API

### GET /health

Sprawdzenie stanu serwisu.

**Response:**
```json
{
    "status": "ok",
    "service": "rayso-api"
}
```

### POST /generate

Generowanie grafiki z kodu.

**Request:**
```http
POST /generate
Content-Type: application/json

{
    "code": "<?php\necho 'Hello World';",
    "title": "example.php",
    "theme": "midnight",
    "language": "php"
}
```

**Response:**
- Content-Type: `image/png`
- Body: binarny plik PNG

---

## Parametry

| Parametr | Typ | Wymagany | Domyślnie | Opis |
|----------|-----|----------|-----------|------|
| `code` | string | **Tak** | - | Kod źródłowy do wyrenderowania |
| `title` | string | Nie | "Untitled-1" | Nazwa pliku wyświetlana w pasku tytułu |
| `theme` | string | Nie | "breeze" | Motyw kolorystyczny |
| `background` | boolean | Nie | true | Czy pokazać gradient tła |
| `darkMode` | boolean | Nie | true | Ciemny (true) lub jasny (false) tryb okna |
| `padding` | number | Nie | 32 | Padding wewnętrzny okna |
| `language` | string | Nie | "auto" | Język do syntax highlighting |
| `width` | number | Nie | auto | Szerokość obrazu w pikselach |
| `height` | number | Nie | auto | Wysokość obrazu w pikselach |

### Dostępne motywy (`theme`)

| Motyw | Opis |
|-------|------|
| `breeze` | Jasny turkusowo-zielony gradient |
| `candy` | Różowo-fioletowy gradient |
| `crimson` | Czerwono-pomarańczowy gradient |
| `falcon` | Szaro-niebieski gradient |
| `meadow` | Zielony gradient |
| `midnight` | Ciemny turkusowo-granatowy gradient |
| `raindrop` | Niebieski gradient |
| `sunset` | Pomarańczowo-różowy gradient |

### Dostępne wartości padding

- `16` - mały
- `32` - średni (domyślny)
- `64` - duży
- `128` - bardzo duży

### Szerokość i wysokość

- `width`: 100 - 4000 px (skaluje proporcjonalnie)
- `height`: 100 - 4000 px (wymusza dokładną wysokość)

**Uwaga:** Przy dużych rozmiarach (>600px) API automatycznie renderuje w wyższej rozdzielczości (3x) dla zachowania jakości.

---

## Przykłady użycia

### curl

**Podstawowe użycie:**
```bash
curl -X POST http://localhost:3333/generate \
  -H "Content-Type: application/json" \
  -d '{"code": "console.log(\"Hello\");", "theme": "midnight"}' \
  --output code.png
```

**Z wszystkimi opcjami:**
```bash
curl -X POST http://localhost:3333/generate \
  -H "Content-Type: application/json" \
  -d '{
    "code": "<?php\n\nnamespace App\\Services;\n\nclass HelloWorld\n{\n    public function greet(): string\n    {\n        return \"Hello World!\";\n    }\n}",
    "title": "HelloWorld.php",
    "theme": "candy",
    "background": true,
    "darkMode": true,
    "padding": 32,
    "language": "php",
    "width": 800
  }' \
  --output hello.png
```

**Szeroki obraz (1920px):**
```bash
curl -X POST http://localhost:3333/generate \
  -H "Content-Type: application/json" \
  -d '{"code": "SELECT * FROM users WHERE active = 1;", "theme": "falcon", "language": "sql", "width": 1920}' \
  --output wide.png
```

### Postman

1. Utwórz nowy request **POST**
2. URL: `http://localhost:3333/generate`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):
```json
{
    "code": "function hello() {\n  return 'Hello World';\n}",
    "title": "hello.js",
    "theme": "midnight",
    "language": "javascript",
    "width": 600
}
```
5. Send i zapisz response jako PNG

### JavaScript (fetch)

```javascript
async function generateCodeImage(code, options = {}) {
    const response = await fetch('http://localhost:3333/generate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            code,
            theme: 'midnight',
            ...options,
        }),
    });

    if (!response.ok) {
        throw new Error('Failed to generate image');
    }

    const blob = await response.blob();
    return URL.createObjectURL(blob);
}

// Użycie
const imageUrl = await generateCodeImage('console.log("Hello");', {
    title: 'example.js',
    width: 800
});
```

---

## Integracja z Laravel

### Konfiguracja

**config/services.php:**
```php
'rayso' => [
    'url' => env('RAYSO_URL', 'http://rayso:3333'),
    'timeout' => env('RAYSO_TIMEOUT', 30),
],
```

**.env:**
```env
RAYSO_URL=http://rayso:3333
RAYSO_TIMEOUT=30
```

### Service Class

Plik `app/Services/CodeImageService.php` jest już utworzony i gotowy do użycia:

```php
use App\Services\CodeImageService;

$codeImage = app(CodeImageService::class);

// Generuj base64
$base64 = $codeImage->generate('echo "Hello";', [
    'theme' => 'midnight',
    'language' => 'php',
    'width' => 800,
]);

// Użyj w HTML
$html = '<img src="data:image/png;base64,' . $base64 . '">';

// Lub zapisz do storage
$codeImage->generateAndStore('echo "Hello";', 'images/code.png', [
    'theme' => 'candy',
]);

// Pobierz surowy PNG
$pngBinary = $codeImage->generateRaw('echo "Hello";');

// Sprawdź health
if ($codeImage->isHealthy()) {
    // Serwis działa
}
```

### Przykład w kontrolerze

```php
<?php

namespace App\Http\Controllers;

use App\Services\CodeImageService;
use Illuminate\Http\Request;

class CodeSnippetController extends Controller
{
    public function __construct(
        private CodeImageService $codeImage
    ) {}

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10000',
            'theme' => 'nullable|in:breeze,candy,crimson,falcon,meadow,midnight,raindrop,sunset',
            'language' => 'nullable|string',
            'width' => 'nullable|integer|min:100|max:4000',
        ]);

        $base64 = $this->codeImage->generate(
            $validated['code'],
            $request->only(['theme', 'language', 'width', 'title'])
        );

        return response()->json([
            'image' => "data:image/png;base64,{$base64}",
        ]);
    }

    public function download(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $png = $this->codeImage->generateRaw($validated['code'], [
            'theme' => 'midnight',
            'width' => 1200,
        ]);

        return response($png)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="code.png"');
    }
}
```

---

## Rozwiązywanie problemów

### Serwis nie odpowiada

```bash
# Sprawdź czy kontener działa
docker ps | grep rayso

# Sprawdź logi
docker logs dev-project-rayso-1 --tail 50

# Zrestartuj
./vendor/bin/sail restart rayso
```

### Błąd "Failed to launch browser"

Problem z Chromium w Docker. Sprawdź:
- `shm_size: '1gb'` w compose.yaml
- Flagi `--no-sandbox`, `--disable-dev-shm-usage` w server.js

### Timeout przy generowaniu

Zwiększ timeout w Laravel:
```php
Http::timeout(60)->post(...)
```

### Niska jakość obrazu

Użyj parametru `width` - API automatycznie renderuje w wyższej rozdzielczości dla dużych rozmiarów.

### Połączenie z Laravel nie działa

Upewnij się, że używasz nazwy serwisu Docker (`rayso`) a nie `localhost`:
```php
// Dobrze (wewnątrz Docker)
'url' => 'http://rayso:3333'

// Źle (wewnątrz Docker)
'url' => 'http://localhost:3333'
```

---

## Dostęp do API

| Źródło | URL |
|--------|-----|
| Laravel (wewnątrz Docker) | `http://rayso:3333` |
| Host (localhost) | `http://localhost:3333` |
| Zewnętrzne | `http://<IP-SERWERA>:3333` |

---

## Pliki projektu

```
docker/rayso/
├── Dockerfile      # Obraz Docker z Node.js i Chromium
├── package.json    # Zależności (express, puppeteer, sharp)
├── server.js       # Serwer Express z logiką generowania
└── .dockerignore   # Wykluczenia dla Docker

app/Services/
└── CodeImageService.php  # Laravel service class

config/
└── services.php    # Konfiguracja rayso URL
```
