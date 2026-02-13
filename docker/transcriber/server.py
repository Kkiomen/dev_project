"""
Transcriber Flask Server

Transcribes audio/video files using faster-whisper and returns
word-level timestamps suitable for caption generation.
"""

import os
import io
import tempfile
import logging
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

# Configuration
MAX_FILE_SIZE = 500 * 1024 * 1024  # 500MB
app.config['MAX_CONTENT_LENGTH'] = MAX_FILE_SIZE

# Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Lazy-load model to save memory on startup
_model = None


def get_model():
    """Load the Whisper model on first use."""
    global _model
    if _model is None:
        from faster_whisper import WhisperModel
        model_size = os.environ.get("WHISPER_MODEL", "base")
        device = os.environ.get("WHISPER_DEVICE", "cpu")
        compute_type = os.environ.get("WHISPER_COMPUTE_TYPE", "int8")
        logger.info(f"[TRANSCRIBER] Loading model: {model_size} on {device} ({compute_type})")
        _model = WhisperModel(model_size, device=device, compute_type=compute_type)
        logger.info("[TRANSCRIBER] Model loaded successfully")
    return _model


@app.route("/health", methods=["GET"])
def health():
    """Health check endpoint."""
    return jsonify({
        "status": "ok",
        "service": "transcriber",
        "version": "1.0.0",
    })


@app.route("/transcribe", methods=["POST"])
def transcribe():
    """
    Transcribe an audio or video file.

    Accepts:
        - multipart/form-data with 'file' field
        - Optional query params:
            - language: source language code (auto-detect if omitted)
            - word_timestamps: true/false (default: true)

    Returns:
        JSON with:
            - language: detected language code
            - language_probability: confidence of language detection
            - duration: total audio duration in seconds
            - segments: array of segment objects with word-level timestamps
    """
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    if not file.filename:
        return jsonify({"error": "Empty filename"}), 400

    language = request.args.get("language", None)
    word_timestamps = request.args.get("word_timestamps", "true").lower() == "true"

    # Save to temp file (faster-whisper needs file path)
    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp = tempfile.NamedTemporaryFile(delete=False, suffix=suffix)
    try:
        file.save(tmp.name)
        tmp.close()

        logger.info(f"[TRANSCRIBER] Processing file: {file.filename} (language={language})")

        model = get_model()
        segments_iter, info = model.transcribe(
            tmp.name,
            language=language,
            word_timestamps=word_timestamps,
            vad_filter=True,
            vad_parameters=dict(
                min_silence_duration_ms=500,
                speech_pad_ms=200,
            ),
        )

        segments = []
        for segment in segments_iter:
            seg_data = {
                "id": segment.id,
                "start": round(segment.start, 3),
                "end": round(segment.end, 3),
                "text": segment.text.strip(),
            }

            if word_timestamps and segment.words:
                seg_data["words"] = [
                    {
                        "word": word.word.strip(),
                        "start": round(word.start, 3),
                        "end": round(word.end, 3),
                        "probability": round(word.probability, 3),
                    }
                    for word in segment.words
                ]

            segments.append(seg_data)

        result = {
            "language": info.language,
            "language_probability": round(info.language_probability, 3),
            "duration": round(info.duration, 3),
            "segments": segments,
        }

        logger.info(
            f"[TRANSCRIBER] Done: {len(segments)} segments, "
            f"lang={info.language} ({info.language_probability:.1%}), "
            f"duration={info.duration:.1f}s"
        )

        return jsonify(result)

    except Exception as e:
        logger.error(f"[TRANSCRIBER] Error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        os.unlink(tmp.name)


@app.route("/detect-language", methods=["POST"])
def detect_language():
    """
    Detect the language of an audio/video file without full transcription.

    Accepts:
        - multipart/form-data with 'file' field

    Returns:
        JSON with language code and probability.
    """
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp = tempfile.NamedTemporaryFile(delete=False, suffix=suffix)
    try:
        file.save(tmp.name)
        tmp.close()

        model = get_model()
        _, info = model.transcribe(tmp.name, word_timestamps=False)

        return jsonify({
            "language": info.language,
            "language_probability": round(info.language_probability, 3),
        })
    except Exception as e:
        logger.error(f"[TRANSCRIBER] Language detection error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        os.unlink(tmp.name)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=3340, debug=True)
