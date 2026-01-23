# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.


## About project

Jest to panel / api do automatyzacji.
Projekt słuzy do tego aby zbudować rozwiązania, które pozwolą zautomatyzować w 100% proces tworzenia marki osobistej.
Chcemy stworzy narzedzie gdzie bedzie wszystko zautomatyzowane. Posiadać funkcjonalności, które posiada konkurencja ale w jednym miejscu.


## Text

Nie mozesz umieszczać tekstu w kodzie bezpośrednio. Wszystko ma byc obslugiwane przez translacje bo aplikacja ma być multilanguage


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

**Key Directories:**
- `app/Services/` - Business logic services (e.g., CodeImageService for rayso integration)
- `docker/rayso/` - Node.js service for generating code screenshots via ray.so
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
