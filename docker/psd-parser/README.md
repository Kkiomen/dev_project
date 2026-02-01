# PSD Parser Service

Python/Flask microservice for parsing PSD files and extracting layer data for the graphics editor.

> **For AI/Claude Code:** Use `/fix-psd-import <psd-file-path>` command to automatically debug and fix PSD import issues.

## Service Info

- **Port**: 3335
- **Internal URL**: `http://psd-parser:3335`
- **External URL**: `http://localhost:3335`

## Endpoints

### 1. Health Check
```bash
curl http://localhost:3335/health
```
Returns service status.

### 2. Parse PSD (`POST /parse`)
Parse a PSD file and return structured JSON data.

```bash
curl -X POST -F "file=@path/to/file.psd" http://localhost:3335/parse -o parsed.json
```

**Response structure:**
```json
{
  "width": 2000,
  "height": 2000,
  "background_color": "#ffffff",
  "layers": [...],
  "fonts": [...],
  "images": [...],
  "masks": [...],
  "smart_object_sources": [...],
  "warnings": [...]
}
```

### 3. Analyze PSD (`POST /analyze`)
Quick analysis of PSD structure without full parsing.

```bash
curl -X POST -F "file=@path/to/file.psd" http://localhost:3335/analyze
```

### 4. Render PSD Original (`POST /render-psd`)
Render PSD using Photoshop's composite (how Photoshop sees it).

```bash
# Full size
curl -X POST -F "file=@path/to/file.psd" http://localhost:3335/render-psd -o original.png

# Scaled (50%)
curl -X POST -F "file=@path/to/file.psd" "http://localhost:3335/render-psd?scale=0.5" -o original_small.png
```

### 5. Render Parsed Data (`POST /render`)
Render parsed JSON data to PNG (simple PIL render for debugging).

```bash
curl -X POST -H "Content-Type: application/json" -d @parsed.json http://localhost:3335/render -o rendered.png
```

## Testing & Debugging Workflow

### Compare Original vs Parsed Render

```bash
# 1. Get original Photoshop render
curl -X POST -F "file=@storage/app/private/test.psd" \
  "http://localhost:3335/render-psd?scale=0.5" -o /tmp/original.png

# 2. Parse PSD to JSON
curl -X POST -F "file=@storage/app/private/test.psd" \
  http://localhost:3335/parse -o /tmp/parsed.json

# 3. Render parsed data
curl -X POST -H "Content-Type: application/json" \
  -d @/tmp/parsed.json http://localhost:3335/render -o /tmp/rendered.png

# 4. Compare images (use Read tool on both PNGs)
```

### Analyze Parsed Layer Data

```bash
# Parse and analyze with Python
curl -s -X POST -F "file=@storage/app/private/test.psd" http://localhost:3335/parse | python3 -c "
import sys, json
data = json.load(sys.stdin)

def print_layers(layers, indent=0):
    for layer in layers:
        prefix = '  ' * indent
        t = layer['type']
        name = layer['name']

        if t == 'group':
            print(f'{prefix}[GROUP] {name}')
            print_layers(layer.get('children', []), indent+1)
        elif t == 'text':
            props = layer.get('properties', {})
            text = props.get('text', '')[:30]
            print(f'{prefix}[TEXT] {name}: \"{text}\"')
            print(f'{prefix}  fixedWidth: {props.get(\"fixedWidth\")}')
        elif t == 'image':
            props = layer.get('properties', {})
            print(f'{prefix}[IMAGE] {name}')
            print(f'{prefix}  size: {layer[\"width\"]}x{layer[\"height\"]}')
            print(f'{prefix}  isClipBase: {props.get(\"isClipBase\", False)}')
        else:
            print(f'{prefix}[{t.upper()}] {name}')

print_layers(data['layers'])
"
```

### Check Docker Logs

```bash
# View recent logs
docker compose logs psd-parser --tail=50

# Filter for specific tags
docker compose logs psd-parser 2>&1 | grep -E "(CLIP|TEXT|MASK|FONT)"

# Follow logs in real-time
docker compose logs -f psd-parser
```

### Rebuild Service

```bash
# Quick rebuild
docker compose build psd-parser && docker compose up -d psd-parser

# Force rebuild (no cache)
docker compose build --no-cache psd-parser && docker compose up -d psd-parser
```

### Direct Python Analysis in Container

```bash
# Copy PSD to container
docker cp path/to/file.psd dev_project-psd-parser-1:/app/test.psd

# Run Python analysis
docker compose exec psd-parser python3 -c "
from psd_tools import PSDImage
from psd_tools.api.layers import Group, TypeLayer, ShapeLayer, SmartObjectLayer

psd = PSDImage.open('/app/test.psd')

def analyze(container, depth=0):
    for layer in container:
        prefix = '  ' * depth
        print(f'{prefix}{layer.name} ({type(layer).__name__})')
        print(f'{prefix}  bounds: ({layer.left}, {layer.top}) {layer.width}x{layer.height}')
        print(f'{prefix}  visible: {layer.visible}')

        # Check for clipping
        if hasattr(layer, 'clip_layers') and layer.clip_layers:
            print(f'{prefix}  clip_layers: {[l.name for l in layer.clip_layers]}')

        # Check for masks
        if hasattr(layer, 'mask') and layer.mask:
            print(f'{prefix}  HAS RASTER MASK')
        if hasattr(layer, 'vector_mask') and layer.vector_mask:
            print(f'{prefix}  HAS VECTOR MASK')

        if isinstance(layer, Group):
            analyze(layer, depth + 1)

analyze(psd)
"
```

## Common Issues & Solutions

### 1. Text wrapping incorrectly
- **Cause**: Point text (no bounding box) treated as paragraph text
- **Check**: `fixedWidth` should be `false` for point text, `true` only for paragraph text
- **Debug**: Look for `[TEXT DEBUG]` logs showing BoxBounds presence

### 2. Image too large / not clipped
- **Cause**: Clipping mask relationship not detected
- **Check**: Look for `clip_layers` on ShapeLayer that should mask the image
- **Debug**: `[CLIP]` and `[CLIP BASE]` logs show clipping detection

### 3. Colors showing as #CCCCCC
- **Cause**: Shape color extraction failed, using fallback
- **Check**: ShapeLayer composite or tagged_blocks for actual color

### 4. Text truncated
- **Cause**: Wrong BoxBounds interpretation or transform scaling
- **Check**: `[TEXT BOX]` logs for BoxBounds values

## Log Tags Reference

| Tag | Description |
|-----|-------------|
| `[CLIP]` | Clipping mask detection |
| `[CLIP BASE]` | Layer with clip_layers (clipping target) |
| `[TEXT]` | Text layer processing |
| `[TEXT DEBUG]` | Text BoxBounds extraction |
| `[TEXT BOX]` | BoxBounds values |
| `[FONT]` | Font size calculation |
| `[FONT DEBUG]` | Font/transform details |
| `[MASK]` | Raster mask detection |
| `[MASK DEBUG]` | Mask type details |
| `[SMART_OBJECT]` | Smart object processing |
| `[TRANSFORM]` | Transform/flip detection |
| `[IMAGE]` | Image extraction |

## Laravel Debug Endpoints

For testing with proper Konva rendering (via template-renderer), use the Laravel debug endpoints.
Requires admin authentication.

```bash
# Get auth token (use your session or generate one)
TOKEN="your-sanctum-token"

# Render parsed JSON using template-renderer (Konva-based, accurate)
curl -X POST "http://localhost/api/v1/debug/render" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d @/tmp/parsed.json -o rendered.png

# Parse and render PSD in one request
curl -X POST "http://localhost/api/v1/debug/render-psd" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.psd" -o rendered.png

# Get original Photoshop composite
curl -X POST "http://localhost/api/v1/debug/psd-original?scale=0.5" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.psd" -o original.png

# Compare both (returns JSON with base64 images)
curl -X POST "http://localhost/api/v1/debug/compare" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.psd"
```

### Endpoints Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/debug/render` | POST | Render parsed JSON (Konva) |
| `/api/v1/debug/render-psd` | POST | Parse + render PSD |
| `/api/v1/debug/psd-original` | POST | Photoshop composite |
| `/api/v1/debug/compare` | POST | Compare original vs rendered |

## File Structure

```
docker/psd-parser/
├── Dockerfile
├── requirements.txt
├── server.py          # Flask endpoints
├── README.md          # This file
└── utils/
    ├── layer_mapper.py    # Layer type mapping & properties
    ├── image_extractor.py # Image/mask extraction
    └── font_matcher.py    # Font name matching
```
