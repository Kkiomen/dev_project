"""
Video Editor Flask Server

Provides video processing capabilities: caption overlay,
silence removal, audio extraction, and video export.
Uses FFmpeg for all media operations.
"""

import os
import json
import subprocess
import tempfile
import shutil
import logging
from flask import Flask, request, jsonify, send_file
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

MAX_FILE_SIZE = 2 * 1024 * 1024 * 1024  # 2GB
app.config['MAX_CONTENT_LENGTH'] = MAX_FILE_SIZE

TEMP_DIR = "/tmp/video-editor"
CAPTION_STYLES_DIR = "/app/caption-styles"

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@app.route("/health", methods=["GET"])
def health():
    """Health check endpoint."""
    # Verify FFmpeg is available
    try:
        result = subprocess.run(
            ["ffmpeg", "-version"],
            capture_output=True, text=True, timeout=5
        )
        ffmpeg_ok = result.returncode == 0
    except Exception:
        ffmpeg_ok = False

    return jsonify({
        "status": "ok" if ffmpeg_ok else "degraded",
        "service": "video-editor",
        "version": "1.0.0",
        "ffmpeg": ffmpeg_ok,
    })


@app.route("/caption-styles", methods=["GET"])
def list_caption_styles():
    """
    List available caption styles.

    Returns:
        JSON array of style objects with id, name, and preview settings.
    """
    styles = []
    styles_dir = CAPTION_STYLES_DIR
    if os.path.isdir(styles_dir):
        for filename in sorted(os.listdir(styles_dir)):
            if filename.endswith(".json"):
                filepath = os.path.join(styles_dir, filename)
                with open(filepath, "r") as f:
                    style = json.load(f)
                    styles.append(style)

    return jsonify({"styles": styles})


@app.route("/extract-audio", methods=["POST"])
def extract_audio():
    """
    Extract audio track from a video file.

    Accepts:
        - multipart/form-data with 'file' field
        - Optional query params:
            - format: output format (wav, mp3, aac) default: wav
            - sample_rate: audio sample rate default: 16000

    Returns:
        Audio file binary.
    """
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    audio_format = request.args.get("format", "wav")
    sample_rate = request.args.get("sample_rate", "16000")

    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp_input = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)
    tmp_output = tempfile.NamedTemporaryFile(delete=False, suffix=f".{audio_format}", dir=TEMP_DIR)

    try:
        file.save(tmp_input.name)
        tmp_input.close()
        tmp_output.close()

        cmd = [
            "ffmpeg", "-y", "-i", tmp_input.name,
            "-vn", "-acodec", _get_audio_codec(audio_format),
            "-ar", str(sample_rate),
            "-ac", "1",
            tmp_output.name,
        ]
        _run_ffmpeg(cmd)

        return send_file(
            tmp_output.name,
            mimetype=_get_audio_mimetype(audio_format),
            as_attachment=True,
            download_name=f"audio.{audio_format}",
        )
    except subprocess.CalledProcessError as e:
        logger.error(f"[VIDEO-EDITOR] FFmpeg error: {e.stderr}")
        return jsonify({"error": "Audio extraction failed", "details": e.stderr}), 500
    finally:
        _cleanup(tmp_input.name, tmp_output.name)


@app.route("/add-captions", methods=["POST"])
def add_captions():
    """
    Burn captions into a video file using ASS subtitle format.

    Accepts:
        - multipart/form-data with 'file' field (video)
        - JSON body 'captions' field with caption data:
            {
                "style": "hormozi|mrbeast|clean|bold|neon",
                "segments": [
                    {"start": 0.0, "end": 1.5, "text": "Hello", "words": [...]},
                    ...
                ],
                "highlight_keywords": true,
                "font_size": 48,
                "position": "bottom"
            }

    Returns:
        Video file with burned-in captions (MP4).
    """
    if "file" not in request.files:
        return jsonify({"error": "No video file provided"}), 400

    file = request.files["file"]
    captions_raw = request.form.get("captions")
    if not captions_raw:
        return jsonify({"error": "No captions data provided"}), 400

    try:
        captions = json.loads(captions_raw)
    except json.JSONDecodeError:
        return jsonify({"error": "Invalid captions JSON"}), 400

    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp_input = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)
    tmp_ass = tempfile.NamedTemporaryFile(delete=False, suffix=".ass", dir=TEMP_DIR, mode="w")
    tmp_output = tempfile.NamedTemporaryFile(delete=False, suffix=".mp4", dir=TEMP_DIR)

    try:
        file.save(tmp_input.name)
        tmp_input.close()
        tmp_output.close()

        # Get video dimensions for caption positioning
        video_info = _get_video_info(tmp_input.name)
        width = video_info.get("width", 1080)
        height = video_info.get("height", 1920)

        # Generate ASS subtitle file
        style_name = captions.get("style", "clean")
        style_config = _load_style(style_name)
        ass_content = _generate_ass(
            segments=captions.get("segments", []),
            style=style_config,
            width=width,
            height=height,
            font_size=captions.get("font_size", style_config.get("font_size", 48)),
            position=captions.get("position", "bottom"),
            highlight_keywords=captions.get("highlight_keywords", False),
        )
        tmp_ass.write(ass_content)
        tmp_ass.close()

        logger.info(f"[VIDEO-EDITOR] Burning captions: style={style_name}, segments={len(captions.get('segments', []))}")

        # Burn subtitles into video
        cmd = [
            "ffmpeg", "-y", "-i", tmp_input.name,
            "-vf", f"ass={tmp_ass.name}",
            "-c:v", "libx264", "-preset", "medium", "-crf", "23",
            "-c:a", "copy",
            "-movflags", "+faststart",
            tmp_output.name,
        ]
        _run_ffmpeg(cmd)

        return send_file(
            tmp_output.name,
            mimetype="video/mp4",
            as_attachment=True,
            download_name="captioned_video.mp4",
        )
    except subprocess.CalledProcessError as e:
        logger.error(f"[VIDEO-EDITOR] FFmpeg error: {e.stderr}")
        return jsonify({"error": "Caption rendering failed", "details": e.stderr}), 500
    except Exception as e:
        logger.error(f"[VIDEO-EDITOR] Error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        _cleanup(tmp_input.name, tmp_ass.name, tmp_output.name)


@app.route("/remove-silence", methods=["POST"])
def remove_silence():
    """
    Remove silent segments from a video based on transcription timestamps.

    Accepts:
        - multipart/form-data with 'file' field (video)
        - JSON body 'segments' field: array of {start, end} objects to KEEP
        - Optional: min_silence_duration (seconds, default 0.5)
        - Optional: padding (seconds added around each segment, default 0.1)

    Returns:
        Video file with silence removed (MP4).
    """
    if "file" not in request.files:
        return jsonify({"error": "No video file provided"}), 400

    segments_raw = request.form.get("segments")
    if not segments_raw:
        return jsonify({"error": "No segments data provided"}), 400

    try:
        segments = json.loads(segments_raw)
    except json.JSONDecodeError:
        return jsonify({"error": "Invalid segments JSON"}), 400

    file = request.files["file"]
    padding = float(request.form.get("padding", "0.1"))

    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp_input = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)
    tmp_output = tempfile.NamedTemporaryFile(delete=False, suffix=".mp4", dir=TEMP_DIR)
    tmp_list = tempfile.NamedTemporaryFile(delete=False, suffix=".txt", dir=TEMP_DIR, mode="w")
    segment_files = []

    try:
        file.save(tmp_input.name)
        tmp_input.close()
        tmp_output.close()

        # Cut each segment
        for i, seg in enumerate(segments):
            start = max(0, float(seg["start"]) - padding)
            end = float(seg["end"]) + padding
            seg_file = os.path.join(TEMP_DIR, f"seg_{os.getpid()}_{i}.mp4")
            segment_files.append(seg_file)

            cmd = [
                "ffmpeg", "-y", "-i", tmp_input.name,
                "-ss", str(start), "-to", str(end),
                "-c:v", "libx264", "-preset", "ultrafast", "-crf", "18",
                "-c:a", "aac",
                seg_file,
            ]
            _run_ffmpeg(cmd)
            tmp_list.write(f"file '{seg_file}'\n")

        tmp_list.close()

        # Concatenate segments
        cmd = [
            "ffmpeg", "-y", "-f", "concat", "-safe", "0",
            "-i", tmp_list.name,
            "-c:v", "libx264", "-preset", "medium", "-crf", "23",
            "-c:a", "aac",
            "-movflags", "+faststart",
            tmp_output.name,
        ]
        _run_ffmpeg(cmd)

        logger.info(f"[VIDEO-EDITOR] Silence removed: {len(segments)} segments kept")

        return send_file(
            tmp_output.name,
            mimetype="video/mp4",
            as_attachment=True,
            download_name="trimmed_video.mp4",
        )
    except subprocess.CalledProcessError as e:
        logger.error(f"[VIDEO-EDITOR] FFmpeg error: {e.stderr}")
        return jsonify({"error": "Silence removal failed", "details": e.stderr}), 500
    finally:
        _cleanup(tmp_input.name, tmp_output.name, tmp_list.name, *segment_files)


@app.route("/probe", methods=["POST"])
def probe_video():
    """
    Get video file metadata (duration, resolution, codec, fps, etc.).

    Accepts:
        - multipart/form-data with 'file' field

    Returns:
        JSON with video metadata.
    """
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)

    try:
        file.save(tmp.name)
        tmp.close()
        info = _get_video_info(tmp.name)
        return jsonify(info)
    except Exception as e:
        logger.error(f"[VIDEO-EDITOR] Probe error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        _cleanup(tmp.name)


# --- Helper functions ---

def _run_ffmpeg(cmd, timeout=300):
    """Run FFmpeg command with error handling."""
    logger.info(f"[VIDEO-EDITOR] Running: {' '.join(cmd[:6])}...")
    result = subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        timeout=timeout,
    )
    if result.returncode != 0:
        raise subprocess.CalledProcessError(
            result.returncode, cmd, result.stdout, result.stderr
        )
    return result


def _get_video_info(filepath):
    """Get video metadata using ffprobe."""
    cmd = [
        "ffprobe", "-v", "quiet",
        "-print_format", "json",
        "-show_format", "-show_streams",
        filepath,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        raise RuntimeError(f"ffprobe failed: {result.stderr}")

    data = json.loads(result.stdout)
    info = {
        "duration": float(data.get("format", {}).get("duration", 0)),
        "size": int(data.get("format", {}).get("size", 0)),
        "format": data.get("format", {}).get("format_name", ""),
    }

    for stream in data.get("streams", []):
        if stream.get("codec_type") == "video":
            info["width"] = stream.get("width", 0)
            info["height"] = stream.get("height", 0)
            info["video_codec"] = stream.get("codec_name", "")
            info["fps"] = _parse_fps(stream.get("r_frame_rate", "0/1"))
        elif stream.get("codec_type") == "audio":
            info["audio_codec"] = stream.get("codec_name", "")
            info["sample_rate"] = int(stream.get("sample_rate", 0))
            info["channels"] = stream.get("channels", 0)

    return info


def _parse_fps(fps_str):
    """Parse FFmpeg frame rate string like '30/1' to float."""
    try:
        parts = fps_str.split("/")
        if len(parts) == 2 and int(parts[1]) != 0:
            return round(int(parts[0]) / int(parts[1]), 2)
        return float(parts[0])
    except (ValueError, ZeroDivisionError):
        return 0


def _load_style(style_name):
    """Load a caption style configuration."""
    style_path = os.path.join(CAPTION_STYLES_DIR, f"{style_name}.json")
    if os.path.exists(style_path):
        with open(style_path, "r") as f:
            return json.load(f)

    # Default style if not found
    return {
        "id": "clean",
        "name": "Clean",
        "font_name": "DejaVu Sans",
        "font_size": 48,
        "primary_color": "&H00FFFFFF",
        "outline_color": "&H00000000",
        "back_color": "&H80000000",
        "bold": True,
        "outline": 2,
        "shadow": 1,
        "alignment": 2,
        "margin_v": 60,
        "highlight_color": "&H0000FFFF",
    }


def _generate_ass(segments, style, width, height, font_size=48, position="bottom", highlight_keywords=False):
    """Generate ASS subtitle content from segments and style config."""
    # ASS alignment: 2 = bottom-center, 8 = top-center, 5 = middle-center
    alignment_map = {"bottom": 2, "top": 8, "center": 5}
    alignment = alignment_map.get(position, 2)

    margin_v = style.get("margin_v", 60)
    font_name = style.get("font_name", "DejaVu Sans")
    primary_color = style.get("primary_color", "&H00FFFFFF")
    outline_color = style.get("outline_color", "&H00000000")
    back_color = style.get("back_color", "&H80000000")
    bold = -1 if style.get("bold", True) else 0
    outline = style.get("outline", 2)
    shadow = style.get("shadow", 1)
    highlight_color = style.get("highlight_color", "&H0000FFFF")

    ass = f"""[Script Info]
Title: Generated Captions
ScriptType: v4.00+
PlayResX: {width}
PlayResY: {height}
WrapStyle: 0

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,{font_name},{font_size},{primary_color},&H000000FF,{outline_color},{back_color},{bold},0,0,0,100,100,0,0,1,{outline},{shadow},{alignment},40,40,{margin_v},1
Style: Highlight,{font_name},{font_size},{highlight_color},&H000000FF,{outline_color},{back_color},{bold},0,0,0,100,100,0,0,1,{outline},{shadow},{alignment},40,40,{margin_v},1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""

    for segment in segments:
        start = _seconds_to_ass_time(segment["start"])
        end = _seconds_to_ass_time(segment["end"])
        text = segment.get("text", "")

        # Handle word-level highlighting
        if highlight_keywords and "words" in segment:
            text = _generate_word_highlight_text(segment["words"], segment["start"], segment["end"])

        # Escape special ASS characters
        text = text.replace("\n", "\\N")

        ass += f"Dialogue: 0,{start},{end},Default,,0,0,0,,{text}\n"

    return ass


def _generate_word_highlight_text(words, seg_start, seg_end):
    """Generate ASS text with word-by-word highlight using karaoke tags."""
    parts = []
    for word in words:
        duration_cs = int((word["end"] - word["start"]) * 100)
        parts.append(f"{{\\kf{duration_cs}}}{word['word']}")
    return " ".join(parts)


def _seconds_to_ass_time(seconds):
    """Convert seconds to ASS time format (H:MM:SS.CC)."""
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = int(seconds % 60)
    cs = int((seconds % 1) * 100)
    return f"{h}:{m:02d}:{s:02d}.{cs:02d}"


def _get_audio_codec(fmt):
    """Map audio format to FFmpeg codec name."""
    return {"wav": "pcm_s16le", "mp3": "libmp3lame", "aac": "aac"}.get(fmt, "pcm_s16le")


def _get_audio_mimetype(fmt):
    """Map audio format to MIME type."""
    return {"wav": "audio/wav", "mp3": "audio/mpeg", "aac": "audio/aac"}.get(fmt, "audio/wav")


def _cleanup(*paths):
    """Remove temporary files, ignoring errors."""
    for path in paths:
        try:
            if path and os.path.exists(path):
                os.unlink(path)
        except OSError:
            pass


# Ensure temp directory exists
os.makedirs(TEMP_DIR, exist_ok=True)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=3341, debug=True)
