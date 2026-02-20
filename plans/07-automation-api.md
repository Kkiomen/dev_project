# Plan: Automatyzacja i API (wzór Creatomate)

## Context
Creatomate stawia na automatyzację — wszystko co jest w edytorze jest też dostępne przez API.

## Automatyzacja w Creatomate

### REST API
- Pełne API do renderowania video/obrazów
- JSON-based modifications object
- Dot notation do nadpisywania właściwości szablonu (np. `"Title.fill_color": "#4980f1"`)
- Bearer token auth
- Endpoints: create render, check status, download

### RenderScript
- JSON do budowania video od zera (bez szablonu)
- Pełna kontrola nad elementami, timeline, efektami
- Programmatic video editing

### Bulk/Batch
- CSV/spreadsheet import → generowanie 100+ video
- Każdy wiersz = jeden render z innymi danymi

### No-code integracje
- **Zapier** — 5000+ app connections
- **Make.com** (Integromat)
- **n8n** — self-hosted
- **Pabbly** — budget alternative

### JavaScript Preview SDK
- Edycja video w przeglądarce
- Modular component (ten sam co w ich edytorze)
- Real-time preview bez server-side rendering

### Forms
- Shareowalne formularze
- Klient wypełnia pola → video generowane automatycznie
- Bez potrzeby logowania do edytora

## Co mamy w naszym projekcie
- REST API (Laravel) ✅
- Composition JSON ✅
- Pipeline automation ✅
- Brand-based AI keys ✅
- Docker rendering (video-editor service) ✅

## Czego brakuje (priorytet)
1. **Dot notation modifications** — proste nadpisywanie właściwości szablonu przez API
2. **Bulk rendering z CSV** — batch processing
3. **Public forms** — shareowalne formularze do generowania video
4. **Webhook notifications** — callback po zakończeniu renderingu
5. **Preview SDK** — embeddable editor component

## Źródła
- https://creatomate.com/
- https://creatomate.com/javascript-video-sdk
- https://creatomate.com/how-to/programmatic-video-editing
