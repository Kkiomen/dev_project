# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.


## About project

Jest to panel / api do automatyzacji.
Projekt słuzy do tego aby zbudować rozwiązania, które pozwolą zautomatyzować w 100% proces tworzenia marki osobistej.
Chcemy stworzy narzedzie gdzie bedzie wszystko zautomatyzowane. Posiadać funkcjonalności, które posiada konkurencja ale w jednym miejscu.


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

Uses **Pest PHP** with Laravel plugin.

```bash
# Run all tests
composer test

# Run single test file
./vendor/bin/pest tests/Feature/ExampleTest.php

# Run with filter
./vendor/bin/pest --filter="test name"
```

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
