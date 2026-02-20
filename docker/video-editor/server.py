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
            "-pix_fmt", "yuv420p",
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
                "-pix_fmt", "yuv420p",
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
            "-pix_fmt", "yuv420p",
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


@app.route("/detect-silence", methods=["POST"])
def detect_silence():
    """
    Detect silent regions in a video/audio file using FFmpeg silencedetect.

    Accepts:
        - multipart/form-data with 'file' field
        - Optional form params:
            - noise: silence threshold in dB (default: -30)
            - duration: minimum silence duration in seconds (default: 0.5)

    Returns:
        JSON with silence_regions and speech_regions arrays of {start, end}.
    """
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    noise_db = request.form.get("noise", "-30")
    min_duration = request.form.get("duration", "0.5")

    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp_input = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)

    try:
        file.save(tmp_input.name)
        tmp_input.close()

        # Get total duration
        video_info = _get_video_info(tmp_input.name)
        total_duration = video_info.get("duration", 0)

        # Run silencedetect filter
        cmd = [
            "ffmpeg", "-i", tmp_input.name,
            "-af", f"silencedetect=noise={noise_db}dB:d={min_duration}",
            "-f", "null", "-",
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)

        # Parse silence regions from stderr
        silence_regions = _parse_silencedetect(result.stderr)

        # Invert to get speech regions
        speech_regions = _invert_regions(silence_regions, total_duration)

        logger.info(f"[VIDEO-EDITOR] Detected {len(silence_regions)} silence regions, {len(speech_regions)} speech regions")

        return jsonify({
            "silence_regions": silence_regions,
            "speech_regions": speech_regions,
            "total_duration": total_duration,
        })
    except Exception as e:
        logger.error(f"[VIDEO-EDITOR] Silence detection error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        _cleanup(tmp_input.name)


def _parse_silencedetect(stderr_output):
    """Parse FFmpeg silencedetect output into list of {start, end} regions."""
    import re
    silence_regions = []
    silence_start = None

    for line in stderr_output.split("\n"):
        # Match: [silencedetect @ ...] silence_start: 47.832
        start_match = re.search(r"silence_start:\s*([\d.]+)", line)
        if start_match:
            silence_start = float(start_match.group(1))
            continue

        # Match: [silencedetect @ ...] silence_end: 61.024 | silence_duration: 13.192
        end_match = re.search(r"silence_end:\s*([\d.]+)", line)
        if end_match and silence_start is not None:
            silence_end = float(end_match.group(1))
            silence_regions.append({
                "start": round(silence_start, 3),
                "end": round(silence_end, 3),
            })
            silence_start = None

    return silence_regions


def _invert_regions(silence_regions, total_duration):
    """Convert silence regions to speech regions (invert)."""
    if not silence_regions:
        return [{"start": 0, "end": total_duration}]

    speech_regions = []
    cursor = 0.0

    for silence in sorted(silence_regions, key=lambda r: r["start"]):
        if silence["start"] > cursor:
            speech_regions.append({
                "start": round(cursor, 3),
                "end": round(silence["start"], 3),
            })
        cursor = silence["end"]

    if cursor < total_duration:
        speech_regions.append({
            "start": round(cursor, 3),
            "end": round(total_duration, 3),
        })

    return speech_regions


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


@app.route("/waveform-peaks", methods=["POST"])
def waveform_peaks():
    """
    Extract audio waveform peak values from a video file using FFmpeg.

    Accepts:
        - multipart/form-data with 'file' field
        - Optional query params:
            - samples: number of peak samples (default: 800)

    Returns:
        JSON with peaks array of float values [-1.0, 1.0].
    """
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    num_samples = int(request.args.get("samples", "800"))

    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp_input = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)
    tmp_pcm = tempfile.NamedTemporaryFile(delete=False, suffix=".raw", dir=TEMP_DIR)

    try:
        file.save(tmp_input.name)
        tmp_input.close()
        tmp_pcm.close()

        # Extract raw PCM audio (mono, 8kHz, 16-bit signed)
        cmd = [
            "ffmpeg", "-y", "-i", tmp_input.name,
            "-vn", "-ac", "1", "-ar", "8000",
            "-f", "s16le", "-acodec", "pcm_s16le",
            tmp_pcm.name,
        ]
        _run_ffmpeg(cmd)

        # Read raw PCM and compute peaks
        import struct
        with open(tmp_pcm.name, "rb") as f:
            raw = f.read()

        total_samples = len(raw) // 2
        if total_samples == 0:
            return jsonify({"peaks": []})

        samples_per_peak = max(1, total_samples // num_samples)
        peaks = []

        for i in range(0, total_samples, samples_per_peak):
            chunk_end = min(i + samples_per_peak, total_samples)
            chunk = raw[i * 2:chunk_end * 2]
            values = struct.unpack(f"<{len(chunk) // 2}h", chunk)
            max_val = max(abs(v) for v in values) if values else 0
            peaks.append(round(max_val / 32768.0, 4))

        logger.info(f"[VIDEO-EDITOR] Waveform extracted: {len(peaks)} peaks from {total_samples} samples")

        return jsonify({"peaks": peaks})
    except Exception as e:
        logger.error(f"[VIDEO-EDITOR] Waveform extraction error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        _cleanup(tmp_input.name, tmp_pcm.name)


@app.route("/thumbnails", methods=["POST"])
def generate_thumbnails():
    """
    Generate filmstrip thumbnails from a video file.

    Accepts:
        - multipart/form-data with 'file' field
        - Optional query params:
            - count: number of thumbnails (default: 10)
            - height: thumbnail height in pixels (default: 60)

    Returns:
        JSON with thumbnails array of base64-encoded JPEG strings.
    """
    import base64

    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    count = int(request.args.get("count", "10"))
    thumb_height = int(request.args.get("height", "60"))

    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp_input = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)
    thumb_dir = tempfile.mkdtemp(dir=TEMP_DIR)

    try:
        file.save(tmp_input.name)
        tmp_input.close()

        # Get duration
        info = _get_video_info(tmp_input.name)
        duration = info.get("duration", 0)
        if duration <= 0:
            return jsonify({"thumbnails": []})

        interval = duration / count
        thumbnails = []

        for i in range(count):
            timestamp = i * interval
            thumb_path = os.path.join(thumb_dir, f"thumb_{i:04d}.jpg")

            cmd = [
                "ffmpeg", "-y", "-ss", str(timestamp),
                "-i", tmp_input.name,
                "-vframes", "1",
                "-vf", f"scale=-1:{thumb_height}",
                "-q:v", "8",
                thumb_path,
            ]
            try:
                _run_ffmpeg(cmd, timeout=15)
                if os.path.exists(thumb_path):
                    with open(thumb_path, "rb") as f:
                        b64 = base64.b64encode(f.read()).decode("utf-8")
                        thumbnails.append(f"data:image/jpeg;base64,{b64}")
            except Exception:
                thumbnails.append(None)

        logger.info(f"[VIDEO-EDITOR] Generated {len(thumbnails)} thumbnails")

        return jsonify({"thumbnails": thumbnails})
    except Exception as e:
        logger.error(f"[VIDEO-EDITOR] Thumbnail generation error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        _cleanup(tmp_input.name)
        shutil.rmtree(thumb_dir, ignore_errors=True)


@app.route("/render-composition", methods=["POST"])
def render_composition():
    """
    Render a full composition with layered video clips, images, and audio.

    Uses segment-based compositing: splits timeline at layer boundaries,
    composites each segment independently (all inputs start at PTS=0),
    then concatenates. This avoids FFmpeg PTS sync issues.

    Accepts:
        - multipart/form-data with 'video' field (main source video)
        - Optional 'image_0', 'image_1', ... fields (overlay images)
        - JSON 'render_plan' field with:
            {
                "layers": [
                    {"type": "video", "source": "video", "time": 0, "duration": 10,
                     "trim_start": 0, "x": 0, "y": 0, "width": 1080, "height": 1920,
                     "opacity": 1.0, "fit": "cover"},
                    {"type": "image", "source": "image_0", "time": 3, "duration": 4,
                     "x": 800, "y": 50, "width": 200, "height": 200,
                     "opacity": 0.9, "fit": "contain"}
                ],
                "audio": [{"source": "video", "time": 0, "duration": 17.5, "trim_start": 0}],
                "width": 1080, "height": 1920, "fps": 30, "total_duration": 17.5
            }

    Returns:
        Rendered video file (MP4).
    """
    if "video" not in request.files:
        return jsonify({"error": "No video file provided"}), 400

    render_plan_raw = request.form.get("render_plan")
    if not render_plan_raw:
        return jsonify({"error": "No render_plan data provided"}), 400

    try:
        render_plan = json.loads(render_plan_raw)
    except json.JSONDecodeError:
        return jsonify({"error": "Invalid render_plan JSON"}), 400

    layers = render_plan.get("layers", [])
    audio_segments = render_plan.get("audio", [])
    comp_width = render_plan.get("width", 1080)
    comp_height = render_plan.get("height", 1920)
    comp_fps = render_plan.get("fps", 30)
    total_duration = render_plan.get("total_duration", 0)

    if not layers and not audio_segments:
        return jsonify({"error": "No layers in render plan"}), 400

    # Save uploaded files
    video_file = request.files["video"]
    suffix = os.path.splitext(video_file.filename)[1] or ".mp4"
    tmp_video = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)
    video_file.save(tmp_video.name)
    tmp_video.close()

    image_files = {}
    for key in request.files:
        if key.startswith("image_"):
            img = request.files[key]
            img_suffix = os.path.splitext(img.filename)[1] or ".png"
            tmp_img = tempfile.NamedTemporaryFile(delete=False, suffix=img_suffix, dir=TEMP_DIR)
            img.save(tmp_img.name)
            tmp_img.close()
            image_files[key] = tmp_img.name

    tmp_output = tempfile.NamedTemporaryFile(delete=False, suffix=".mp4", dir=TEMP_DIR)
    tmp_output.close()
    all_temp = [tmp_video.name, tmp_output.name]

    try:
        pid = os.getpid()

        # 1. Collect time boundaries from all layers
        boundaries = sorted(set(
            [0.0, total_duration] +
            [l["time"] for l in layers] +
            [l["time"] + l["duration"] for l in layers]
        ))

        # 2. Render each timeline segment by compositing active layers
        seg_files = []
        for seg_idx in range(len(boundaries) - 1):
            seg_start = boundaries[seg_idx]
            seg_end = boundaries[seg_idx + 1]
            seg_dur = seg_end - seg_start
            if seg_dur <= 0.001:
                continue

            active = [l for l in layers
                      if l["time"] <= seg_start + 0.001
                      and l["time"] + l["duration"] >= seg_end - 0.001]

            seg_file = os.path.join(TEMP_DIR, f"cseg_{pid}_{seg_idx}.mp4")
            seg_files.append(seg_file)
            all_temp.append(seg_file)

            if not active:
                _make_black_segment(seg_file, comp_width, comp_height, seg_dur, comp_fps)
            else:
                _composite_segment(
                    seg_file, seg_start, seg_dur, active,
                    tmp_video.name, image_files,
                    comp_width, comp_height, comp_fps,
                )

        if not seg_files:
            return jsonify({"error": "No segments generated"}), 400

        # 3. Concat all segments
        tmp_concat = tempfile.NamedTemporaryFile(delete=False, suffix=".txt", dir=TEMP_DIR, mode="w")
        all_temp.append(tmp_concat.name)
        for sf in seg_files:
            tmp_concat.write(f"file '{sf}'\n")
        tmp_concat.close()

        tmp_video_only = os.path.join(TEMP_DIR, f"cconcat_{pid}.mp4")
        all_temp.append(tmp_video_only)

        if len(seg_files) == 1:
            shutil.copy2(seg_files[0], tmp_video_only)
        else:
            cmd = [
                "ffmpeg", "-y", "-f", "concat", "-safe", "0",
                "-i", tmp_concat.name,
                "-c:v", "libx264", "-preset", "medium", "-crf", "23",
                "-pix_fmt", "yuv420p",
                tmp_video_only,
            ]
            _run_ffmpeg(cmd)

        # 4. Add audio — cut each audio segment, concat, merge with video
        if audio_segments:
            audio_seg_files = []
            audio_concat = tempfile.NamedTemporaryFile(
                delete=False, suffix=".txt", dir=TEMP_DIR, mode="w"
            )
            all_temp.append(audio_concat.name)

            for ai, a in enumerate(audio_segments):
                a_trim = a.get("trim_start", 0)
                a_dur = a.get("duration", 0)
                if a_dur <= 0:
                    continue

                aseg = os.path.join(TEMP_DIR, f"caseg_{pid}_{ai}.aac")
                audio_seg_files.append(aseg)
                all_temp.append(aseg)

                cmd = [
                    "ffmpeg", "-y", "-i", tmp_video.name,
                    "-ss", str(a_trim), "-t", str(a_dur),
                    "-vn", "-acodec", "aac", "-b:a", "192k",
                    aseg,
                ]
                _run_ffmpeg(cmd)
                audio_concat.write(f"file '{aseg}'\n")

            audio_concat.close()

            tmp_audio = os.path.join(TEMP_DIR, f"caudio_{pid}.aac")
            all_temp.append(tmp_audio)

            if len(audio_seg_files) == 1:
                shutil.copy2(audio_seg_files[0], tmp_audio)
            elif audio_seg_files:
                cmd = [
                    "ffmpeg", "-y", "-f", "concat", "-safe", "0",
                    "-i", audio_concat.name,
                    "-acodec", "aac", "-b:a", "192k",
                    tmp_audio,
                ]
                _run_ffmpeg(cmd)

            if audio_seg_files:
                cmd = [
                    "ffmpeg", "-y",
                    "-i", tmp_video_only, "-i", tmp_audio,
                    "-c:v", "copy", "-c:a", "aac",
                    "-movflags", "+faststart",
                    tmp_output.name,
                ]
                _run_ffmpeg(cmd)
            else:
                shutil.copy2(tmp_video_only, tmp_output.name)
        else:
            cmd = [
                "ffmpeg", "-y", "-i", tmp_video_only,
                "-c:v", "copy", "-movflags", "+faststart",
                tmp_output.name,
            ]
            _run_ffmpeg(cmd)

        logger.info(f"[RENDER] Composition rendered: {len(layers)} layers, {len(seg_files)} segments")

        return send_file(
            tmp_output.name,
            mimetype="video/mp4",
            as_attachment=True,
            download_name="composition_render.mp4",
        )
    except subprocess.CalledProcessError as e:
        logger.error(f"[RENDER] FFmpeg error: {e.stderr}")
        return jsonify({"error": "Composition render failed", "details": e.stderr}), 500
    except Exception as e:
        logger.error(f"[RENDER] Error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        _cleanup(*all_temp, *image_files.values())


def _make_black_segment(output_path, width, height, duration, fps):
    """Generate a black video segment."""
    cmd = [
        "ffmpeg", "-y",
        "-f", "lavfi", "-i", f"color=black:s={width}x{height}:d={duration}:r={fps}",
        "-c:v", "libx264", "-preset", "ultrafast", "-crf", "18",
        "-pix_fmt", "yuv420p",
        output_path,
    ]
    _run_ffmpeg(cmd)


def _composite_segment(output_path, seg_start, seg_dur, active_layers,
                       video_path, image_files, comp_w, comp_h, fps):
    """
    Composite multiple layers for a single timeline segment.

    All inputs start at PTS=0, run for seg_dur seconds. This avoids
    FFmpeg timing/sync issues since every stream is time-aligned.

    Uses a complex filtergraph:
      [base] -> overlay [layer0] -> overlay [layer1] -> ... -> output
    """
    inputs = []
    filter_parts = []
    input_idx = 0

    # Input 0: black base canvas
    inputs.extend([
        "-f", "lavfi", "-i",
        f"color=black:s={comp_w}x{comp_h}:d={seg_dur}:r={fps}",
    ])
    input_idx += 1

    # Collect layer inputs and build per-layer filter chains
    layer_labels = []
    for i, layer in enumerate(active_layers):
        lt = layer["type"]
        lw = _even(layer.get("width", comp_w))
        lh = _even(layer.get("height", comp_h))
        opacity = layer.get("opacity", 1.0)
        fit = layer.get("fit", "cover")

        # Offset: how far into this layer the segment starts
        offset_in_layer = seg_start - layer["time"]
        trim_start = layer.get("trim_start", 0) + offset_in_layer

        label = f"L{i}"

        if lt == "video":
            inputs.extend([
                "-ss", str(trim_start), "-t", str(seg_dur),
                "-i", video_path,
            ])
            scale = _build_scale_filter(lw, lh, fit)
            chain = f"[{input_idx}:v]{scale},format=yuva420p"
            if opacity < 0.99:
                chain += f",colorchannelmixer=aa={opacity}"
            chain += f"[{label}]"
            filter_parts.append(chain)
            input_idx += 1

        elif lt == "image":
            source_key = layer.get("source", "")
            img_path = image_files.get(source_key)
            if not img_path:
                logger.warning(f"[RENDER] Missing image: {source_key}")
                continue

            inputs.extend(["-loop", "1", "-t", str(seg_dur), "-i", img_path])
            scale = _build_scale_filter(lw, lh, fit)
            chain = f"[{input_idx}:v]{scale},format=yuva420p"
            if opacity < 0.99:
                chain += f",colorchannelmixer=aa={opacity}"
            chain += f"[{label}]"
            filter_parts.append(chain)
            input_idx += 1
        else:
            continue

        layer_labels.append((label, layer))

    if not layer_labels:
        _make_black_segment(output_path, comp_w, comp_h, seg_dur, fps)
        return

    # Build overlay chain: base -> overlay L0 -> overlay L1 -> ...
    prev = "0:v"
    for idx, (label, layer) in enumerate(layer_labels):
        lx = int(layer.get("x", 0))
        ly = int(layer.get("y", 0))
        out = f"out{idx}" if idx < len(layer_labels) - 1 else "vout"
        filter_parts.append(
            f"[{prev}][{label}]overlay={lx}:{ly}:eof_action=pass:format=auto[{out}]"
        )
        prev = out

    filtergraph = ";".join(filter_parts)

    cmd = ["ffmpeg", "-y"] + inputs + [
        "-filter_complex", filtergraph,
        "-map", f"[{prev}]",
        "-c:v", "libx264", "-preset", "ultrafast", "-crf", "18",
        "-pix_fmt", "yuv420p", "-an",
        output_path,
    ]
    _run_ffmpeg(cmd)


def _build_scale_filter(width, height, fit):
    """Build FFmpeg scale+crop/pad filter string for a given fit mode."""
    if fit == "cover":
        return (
            f"scale={width}:{height}:force_original_aspect_ratio=increase,"
            f"crop={width}:{height}"
        )
    # contain: scale down, no padding (transparent background via yuva420p)
    return f"scale={width}:{height}:force_original_aspect_ratio=decrease"


def _even(n):
    """Ensure a dimension is even (required by libx264)."""
    n = max(2, int(n))
    return n + (n % 2)


@app.route("/export-timeline", methods=["POST"])
def export_timeline():
    """
    Render a full timeline from an EDL (Edit Decision List).

    Accepts:
        - multipart/form-data with 'file' field (source video)
        - JSON body 'edl' field with timeline data:
            {
                "tracks": [
                    {"id": "video", "type": "video", "muted": false, "clips": [
                        {"start": 0, "end": 10, "trimStart": 0, "trimEnd": 0}
                    ]},
                    ...
                ]
            }

    Returns:
        Rendered video file (MP4).
    """
    if "file" not in request.files:
        return jsonify({"error": "No video file provided"}), 400

    edl_raw = request.form.get("edl")
    if not edl_raw:
        return jsonify({"error": "No EDL data provided"}), 400

    try:
        edl = json.loads(edl_raw)
    except json.JSONDecodeError:
        return jsonify({"error": "Invalid EDL JSON"}), 400

    file = request.files["file"]
    suffix = os.path.splitext(file.filename)[1] or ".mp4"
    tmp_input = tempfile.NamedTemporaryFile(delete=False, suffix=suffix, dir=TEMP_DIR)
    tmp_output = tempfile.NamedTemporaryFile(delete=False, suffix=".mp4", dir=TEMP_DIR)
    tmp_list = tempfile.NamedTemporaryFile(delete=False, suffix=".txt", dir=TEMP_DIR, mode="w")
    segment_files = []

    try:
        file.save(tmp_input.name)
        tmp_input.close()
        tmp_output.close()

        # Extract video clips from EDL
        video_track = None
        for track in edl.get("tracks", []):
            if track.get("type") == "video":
                video_track = track
                break

        if not video_track or not video_track.get("clips"):
            return jsonify({"error": "No video clips in EDL"}), 400

        clips = sorted(video_track["clips"], key=lambda c: c.get("start", 0))

        for i, clip in enumerate(clips):
            start = clip.get("start", 0) + clip.get("trimStart", 0)
            end = clip.get("end", 0) - clip.get("trimEnd", 0)

            if end <= start:
                continue

            seg_file = os.path.join(TEMP_DIR, f"edl_seg_{os.getpid()}_{i}.mp4")
            segment_files.append(seg_file)

            cmd = [
                "ffmpeg", "-y", "-i", tmp_input.name,
                "-ss", str(start), "-to", str(end),
                "-c:v", "libx264", "-preset", "ultrafast", "-crf", "18",
                "-pix_fmt", "yuv420p",
                "-c:a", "aac",
                seg_file,
            ]
            _run_ffmpeg(cmd)
            tmp_list.write(f"file '{seg_file}'\n")

        tmp_list.close()

        if not segment_files:
            return jsonify({"error": "No valid segments to render"}), 400

        # Concatenate segments
        cmd = [
            "ffmpeg", "-y", "-f", "concat", "-safe", "0",
            "-i", tmp_list.name,
            "-c:v", "libx264", "-preset", "medium", "-crf", "23",
            "-pix_fmt", "yuv420p",
            "-c:a", "aac",
            "-movflags", "+faststart",
            tmp_output.name,
        ]
        _run_ffmpeg(cmd)

        logger.info(f"[VIDEO-EDITOR] Timeline exported: {len(segment_files)} segments")

        return send_file(
            tmp_output.name,
            mimetype="video/mp4",
            as_attachment=True,
            download_name="timeline_export.mp4",
        )
    except subprocess.CalledProcessError as e:
        logger.error(f"[VIDEO-EDITOR] FFmpeg error: {e.stderr}")
        return jsonify({"error": "Timeline export failed", "details": e.stderr}), 500
    except Exception as e:
        logger.error(f"[VIDEO-EDITOR] Export error: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        _cleanup(tmp_input.name, tmp_output.name, tmp_list.name, *segment_files)


# Ensure temp directory exists
os.makedirs(TEMP_DIR, exist_ok=True)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=3341, debug=True)
