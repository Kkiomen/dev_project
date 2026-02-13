FROM python:3.12-slim

WORKDIR /app

# Install system dependencies for video/audio processing and fonts
RUN apt-get update && apt-get install -y \
    ffmpeg \
    fonts-dejavu-core \
    fonts-liberation \
    fontconfig \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements first for better caching
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

# Create directories for temporary files and fonts
RUN mkdir -p /tmp/video-editor /app/fonts /app/caption-styles

# Copy caption style definitions
COPY caption_styles/ /app/caption-styles/

EXPOSE 3341

ENV PYTHONUNBUFFERED=1

CMD ["gunicorn", "--bind", "0.0.0.0:3341", "--workers", "2", "--timeout", "600", "--access-logfile", "-", "--error-logfile", "-", "--capture-output", "server:app"]
