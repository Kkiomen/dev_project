# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.


## About project

Jest to panel / api do automatyzacji.
Projekt słuzy do tego aby zbudować rozwiązania, które pozwolą zautomatyzować w 100% proces tworzenia marki osobistej.
Chcemy stworzy narzedzie gdzie bedzie wszystko zautomatyzowane. Posiadać funkcjonalności, które posiada konkurencja ale w jednym miejscu.

## Code - SOLID, KISS, DRY

Kod ma być pisany zgodnie z najlepszymi praktykami.
Ma być zgodny z SOLID, KISS, DRY, YAGNI.
Ma korzystać ze wzorców projektowych.


## Text

Nie mozesz umieszczać tekstu w kodzie bezpośrednio. Wszystko ma byc obslugiwane przez translacje bo aplikacja ma być multilanguage


## Responsywnosc
Każda podstrona musi być w pełni responsywna

## Development Commands

```bash
# Full development stack (PHP server, queue, logs, Vite)
composer dev

# Or with Docker/Sail
./vendor/bin/sail up -d

# Run tests
composer test

# Build frontend assets
npm run build

# Development with hot reload
npm run dev

# Database migrations
php artisan migrate

# Interactive shell
php artisan tinker
```

## Project Architecture

**Stack:** Laravel 12 + PHP 8.5 + MySQL 8.4 + Redis + Tailwind CSS 4 + Vite

**Docker Services (compose.yaml):**
- `laravel.test` - Main PHP application (port 80)
- `mysql` - Database (port 3306)
- `redis` - Cache/sessions (port 6379)
- `rayso` - Code image generation service (port 3333)
- `psd-parser` - PSD file parsing service (port 3335)
- `template-renderer` - Template preview rendering service (port 3336)

**Key Directories:**
- `app/Services/` - Business logic services (e.g., CodeImageService for rayso integration)
- `docker/rayso/` - Node.js service for generating code screenshots via ray.so
- `docker/psd-parser/` - Python/Flask service for parsing PSD files (see README.md)
- `docker/template-renderer/` - Node.js/Puppeteer service for rendering templates
- `docs/` - Project documentation

## Testing

### Backend (Pest PHP)

Uses **Pest PHP** with Laravel plugin.

```bash
# Run all tests
composer test

# Run single test file
./vendor/bin/pest tests/Feature/ExampleTest.php

# Run with filter
./vendor/bin/pest --filter="test name"
```

### Backend manual testing (Tinker)

For quick API/service testing without curl, use `sail artisan tinker`:

```php
# Load a model and call service
$project = App\Models\VideoProject::where('public_id', 'xxx')->first();
$service = new App\Services\CompositionService();
$result = $service->buildDefaultComposition($project);

# Simulate controller logic
$project->update(['composition' => $result]);
$resource = new App\Http\Resources\VideoProjectResource($project);
$resource->toArray(request());
```

**Why tinker instead of curl:** The app uses Sanctum session auth (cookies). curl requires
a complex dance of CSRF tokens + cookie jars + XSRF headers. Tinker bypasses all of that
and lets you test services, models, and business logic directly.

### Frontend (Puppeteer browser tests)

Browser tests live in `tests/browser/`. They use **puppeteer-core** with system Chrome.

```bash
# Run a browser test
node tests/browser/test-nle-editor.mjs [optionalProjectId]

# Screenshots are saved to /tmp/<test>-screenshots/
```

**Tools available:**
- `puppeteer-core` (installed via npm) — no bundled Chromium, uses system Chrome
- Chrome path: `/usr/bin/google-chrome-stable`
- Headless mode (`headless: 'new'`), viewport 1920x1080

**Authentication in browser tests (IMPORTANT):**

The SPA uses **Laravel Sanctum stateful session auth** (cookie-based, NOT bearer tokens).

To authenticate in Puppeteer:
1. Navigate to `/login` — this is a **Blade-rendered page** (server-side HTML), not Vue SPA
2. Fill `input[name="email"]` + `input[name="password"]`
3. Click `button[type="submit"]`
4. `waitForNavigation()` — browser receives `laravel-session` + `XSRF-TOKEN` cookies
5. All subsequent `page.goto()` and Axios calls are now authenticated

```javascript
// Login pattern for Puppeteer tests
await page.goto('http://localhost/login', { waitUntil: 'networkidle0' });
await page.type('input[name="email"]', 'test@example.com');
await page.type('input[name="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForNavigation({ waitUntil: 'networkidle0' });
// Now authenticated — navigate to any SPA page
```

Test credentials: `test@example.com` / `password` (from DatabaseSeeder).

**API URL rewriting:**

Frontend stores use `/api/v1/...` but `bootstrap.js` has an Axios interceptor that rewrites:
- `/api/v1/...` → `/api/panel/...` (session auth)
- `/api/admin/...` → `/api/panel/admin/...`

In Puppeteer tests this happens automatically (real app JS runs in browser).
When debugging network calls you'll see `/api/panel/` in actual requests.
**In store code always use `/api/v1/` prefix — never use `/api/panel/` directly.**

**Why NOT curl for frontend auth:**
curl doesn't work with Sanctum session auth because the login form requires CSRF token,
session cookies must be managed across requests, and the XSRF-TOKEN cookie must be echoed
back as an X-XSRF-TOKEN header. Use tinker for backend, Puppeteer for frontend.

**Writing a new browser test:**
1. Copy `tests/browser/test-nle-editor.mjs` as template
2. Change `SCREENSHOT_DIR` and constants
3. Keep `setup()` (launches Chrome + error collection) and `testLogin()` as first test
4. Add test functions, call them in `main()`
5. Run: `node tests/browser/your-test.mjs`

## External Services

**Rayso API** (code image generation):
- Internal URL: `http://rayso:3333`
- External URL: `http://localhost:3333`
- Service class: `App\Services\CodeImageService`
- Documentation: `docs/rayso-api.md`

## Database

MySQL connection via Docker. Uses `sail` user with `laravel` database.

```bash
# Inside Laravel container
php artisan migrate
php artisan db:seed

# Direct MySQL access
./vendor/bin/sail mysql
```

## PSD Parser Service

Python/Flask microservice for parsing PSD files. Full documentation in `docker/psd-parser/README.md`.

**Quick debugging workflow (direct microservice):**

```bash
# 1. Render original PSD (how Photoshop sees it)
curl -X POST -F "file=@storage/app/private/test.psd" \
  "http://localhost:3335/render-psd?scale=0.5" -o /tmp/original.png

# 2. Parse PSD to JSON
curl -X POST -F "file=@storage/app/private/test.psd" \
  http://localhost:3335/parse -o /tmp/parsed.json

# 3. Render parsed data (simple PIL render)
curl -X POST -H "Content-Type: application/json" \
  -d @/tmp/parsed.json http://localhost:3335/render -o /tmp/rendered.png

# 4. View logs for debugging
docker compose logs psd-parser --tail=50 | grep -E "(CLIP|TEXT|MASK|FONT)"

# 5. Rebuild after code changes
docker compose build psd-parser && docker compose up -d psd-parser
```

**Laravel Debug Endpoints (requires admin auth):**

```bash
# Get auth token first (or use existing session)
TOKEN="your-sanctum-token"

# Render parsed JSON using template-renderer (Konva)
curl -X POST "http://localhost/api/v1/debug/render" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d @/tmp/parsed.json -o rendered.png

# Parse PSD and render in one request
curl -X POST "http://localhost/api/v1/debug/render-psd" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@storage/app/private/test.psd" -o rendered.png

# Get original PSD composite (Photoshop render)
curl -X POST "http://localhost/api/v1/debug/psd-original?scale=0.5" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@storage/app/private/test.psd" -o original.png

# Compare original vs rendered (returns JSON with base64 images)
curl -X POST "http://localhost/api/v1/debug/compare" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@storage/app/private/test.psd" | jq '.rendered.success'
```

**Key log tags:** `[CLIP]`, `[TEXT]`, `[TEXT BOX]`, `[FONT]`, `[MASK]`, `[SMART_OBJECT]`

**Common issues:**
- Text wrapping wrong → Check `fixedWidth` (should be `false` for point text)
- Image not clipped → Check `clip_layers` detection in logs
- Colors as #CCCCCC → Shape color extraction fallback

## AI API Keys

Klucze API do serwisow AI (OpenAI, Gemini, WaveSpeed) sa przechowywane per-brand w tabeli `brand_ai_keys`. Nie uzywamy globalnych kluczy z `.env` ani `config()`. Klucz pobieramy zawsze przez `BrandAiKey::getKeyForProvider($brand, AiProvider::OpenAi)`. Jesli brak klucza — zwracamy blad z `error_code: 'no_api_key'`.

## Custom Commands

### `/fix-psd-import` - PSD Import Fixer Agent

Specialized agent for debugging and fixing PSD import issues. Use when:
- PSD imports incorrectly
- Layers don't display properly in editor
- Text wrapping or clipping is broken

Usage:
```
/fix-psd-import storage/app/private/problematic.psd
```

The agent will:
1. Render original PSD (Photoshop composite)
2. Parse and render using our pipeline
3. Compare and identify differences
4. Fix parser (`docker/psd-parser/`) or editor (`EditorCanvas.vue`) code
5. Test and verify the fix
