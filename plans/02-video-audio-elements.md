# Plan: Video i Audio elementy (wzór Creatomate)

## Context
Porównanie właściwości video/audio w Creatomate z naszym edytorem NLE.

## Video element — Creatomate

| Właściwość | Opis | Default |
|-----------|------|---------|
| `source` | URL/GUID pliku lub prompt AI | null |
| `provider` | AI video generation (Stable Diffusion) | null |
| `fit` | cover / contain | cover |
| `trim_start` | Punkt startowy w sekundach | null |
| `trim_duration` | Długość wyciętego fragmentu | null |
| `loop` | Zapętlanie (nie łączy się z trim) | false |
| `volume` | Głośność 0-100% | 100% |
| `audio_fade_in` | Fade in audio (sekundy) | null |
| `audio_fade_out` | Fade out audio (sekundy) | null |
| `duration` | Czas trwania lub "media" (auto) | "media" |

## Audio element — Creatomate

| Właściwość | Opis | Default |
|-----------|------|---------|
| `source` | URL/GUID lub prompt AI (TTS) | null |
| `provider` | AI TTS: ElevenLabs, OpenAI | null |
| `trim_start` | Punkt startowy | null |
| `trim_duration` | Długość fragmentu | null |
| `loop` | Zapętlanie | false |
| `volume` | Głośność 0-100% | 100% |
| `audio_fade_in` | Fade in (sekundy) | null |
| `audio_fade_out` | Fade out (sekundy) | null |
| `duration` | Czas lub "media" (auto) | "media" |

## Co mamy w naszym edytorze
- source, time, duration, trim_start, trim_end ✅
- volume ✅
- fade_in, fade_out ✅
- fit (cover) ✅

## Czego brakuje (priorytet)
1. **Loop** — zapętlanie audio/video (ważne dla background music)
2. **fit: contain** — tryb "zmieść w ramce" bez przycinania
3. **AI video generation** — integracja z providerami AI
4. **AI TTS (text-to-speech)** — generowanie voiceover z tekstu
5. **trim_duration** — u nas trim_end, ale logika podobna
6. **duration: "media"** — auto-detection z pliku (mamy, ale inaczej)

## Źródła
- https://creatomate.com/docs/api/render-script/video-element
- https://creatomate.com/docs/api/render-script/audio-element
