# Plan: AI features (wzór Creatomate)

## Context
Creatomate integruje AI na wielu poziomach — od generowania tekstu po tworzenie video.

## AI w Creatomate

### AI Voiceover (Text-to-Speech)
- **Providery**: ElevenLabs, OpenAI
- Audio element z `provider` ustawionym na TTS
- `source` staje się promptem/tekstem do wypowiedzenia
- Automatyczne generowanie pliku audio

### AI generowanie tekstu
- **ChatGPT / GPT-4** integracja
- Generowanie treści tekstowych do szablonów
- Dynamiczne opisy, nagłówki, CTA

### AI generowanie obrazów
- **DALL-E** integracja
- Image element z `provider` ustawionym na DALL-E
- `source` jako prompt do generowania

### AI generowanie video
- **Stable Diffusion** i inne providery
- Video element z `provider`
- Text prompt → wygenerowane video

### Auto-transkrypcja
- Automatyczne rozpoznawanie mowy z video
- Generowanie napisów/subtitles
- Efekty: karaoke highlighting, word-by-word animation
- Konfiguracja: split (word/line), max length, placement

## Co mamy w naszym projekcie
- `BrandAiKey` — klucze AI per brand ✅
- OpenAI, Gemini, WaveSpeed — providery ✅
- Brak AI w edytorze NLE

## Czego brakuje (priorytet)
1. **AI Voiceover w edytorze** — generowanie VO z tekstu bezpośrednio w NLE
2. **Auto-transkrypcja** — automatyczne napisy z audio ścieżki video
   - Word-level timestamps
   - Karaoke-style highlighting
   - Konfigurowalny styl napisów
3. **AI generowanie obrazów** — tworzenie grafik w edytorze
4. **AI generowanie video** — B-roll generation z promptu

## Sugerowana kolejność implementacji
1. Auto-transkrypcja (mamy już Whisper/transcriber service w docker)
2. AI Voiceover (ElevenLabs/OpenAI TTS)
3. AI generowanie obrazów (DALL-E)
4. AI generowanie video (WaveSpeed/Stable Diffusion)

## Źródła
- https://creatomate.com/docs/api/render-script/video-element
- https://creatomate.com/docs/api/render-script/audio-element
