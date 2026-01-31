"""
PSD Parser Flask Server

Parses PSD files and returns structured JSON data
suitable for importing into a canvas-based graphics editor.
"""

import os
import io
import tempfile
from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
from psd_tools import PSDImage
from psd_tools.api.layers import Group

from utils.layer_mapper import map_layer, collect_fonts
from utils.image_extractor import rgba_to_hex, extract_smart_object_source
from psd_tools.api.layers import SmartObjectLayer

app = Flask(__name__)
CORS(app)

# Configuration
MAX_FILE_SIZE = 500 * 1024 * 1024  # 500MB
app.config['MAX_CONTENT_LENGTH'] = MAX_FILE_SIZE
MAX_DIMENSIONS = 4096
MAX_LAYERS = 500


@app.route("/health", methods=["GET"])
def health():
    """Health check endpoint."""
    return jsonify({
        "status": "ok",
        "service": "psd-parser",
        "version": "1.0.0",
    })


@app.route("/parse", methods=["POST"])
def parse_psd():
    """
    Parse a PSD file and return structured layer data.

    Expects:
        - multipart/form-data with 'file' field containing the PSD

    Returns:
        JSON with:
            - width: canvas width
            - height: canvas height
            - background_color: hex color string
            - layers: array of layer objects
            - fonts: array of font info objects
            - images: array of image data objects (base64)
            - warnings: array of warning messages
    """
    # Validate request
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]

    if not file.filename:
        return jsonify({"error": "No filename"}), 400

    if not file.filename.lower().endswith(".psd"):
        return jsonify({"error": "File must be a PSD file"}), 400

    # Read file into memory
    file_data = file.read()

    if len(file_data) > MAX_FILE_SIZE:
        return jsonify({"error": f"File too large. Maximum size is {MAX_FILE_SIZE // (1024*1024)}MB"}), 400

    warnings = []

    try:
        # Parse PSD from memory
        psd = PSDImage.open(io.BytesIO(file_data))

        # Validate dimensions
        if psd.width > MAX_DIMENSIONS or psd.height > MAX_DIMENSIONS:
            return jsonify({
                "error": f"PSD dimensions too large. Maximum is {MAX_DIMENSIONS}x{MAX_DIMENSIONS}px"
            }), 400

        # Get document info
        width = psd.width
        height = psd.height

        # Get document resolution (DPI) for font size scaling
        # Default to 72 DPI (screen resolution)
        psd_dpi = 72
        try:
            from psd_tools.constants import Resource
            if psd.image_resources and Resource.RESOLUTION_INFO in psd.image_resources:
                res_info = psd.image_resources[Resource.RESOLUTION_INFO]
                # Resolution info contains horizontal and vertical DPI
                # psd-tools stores it as fixed-point (16.16) or direct value
                if hasattr(res_info, 'horizontal_resolution'):
                    psd_dpi = res_info.horizontal_resolution
                elif hasattr(res_info, 'data') and hasattr(res_info.data, 'horizontal_resolution'):
                    psd_dpi = res_info.data.horizontal_resolution
                print(f"[RESOLUTION] Document DPI: {psd_dpi}")
        except Exception as e:
            print(f"[RESOLUTION] Could not get DPI, using default 72: {e}")
            psd_dpi = 72

        # Try to get background color
        background_color = "#FFFFFF"
        try:
            # Check if there's a background layer or get from composite
            if psd.has_preview():
                composite = psd.composite()
                if composite:
                    # Sample corner pixel for background estimation
                    rgba = composite.convert("RGBA")
                    corner_pixel = rgba.getpixel((0, 0))
                    # Only use if not transparent
                    if corner_pixel[3] > 200:
                        background_color = rgba_to_hex(corner_pixel)
        except Exception as e:
            warnings.append(f"Could not determine background color: {str(e)}")

        # Process layers with hierarchy (groups and children)
        # Returns tree structure with parent-child relationships
        images = []
        masks = []  # Layer mask images (raster masks)

        # Smart Object sources - extract once per unique_id for linked assets
        smart_object_sources = {}  # unique_id -> source image data
        smart_object_layers = []  # Track SmartObjectLayers for source extraction

        def collect_smart_objects(container):
            """First pass: collect all SmartObjectLayers for source extraction."""
            for layer in container:
                if isinstance(layer, Group):
                    collect_smart_objects(layer)
                elif isinstance(layer, SmartObjectLayer):
                    smart_object_layers.append(layer)

        def count_all_layers(container):
            """Count total number of layers including nested ones."""
            total = 0
            for layer in container:
                total += 1
                if isinstance(layer, Group):
                    total += count_all_layers(layer)
            return total

        # Assign positions incrementing from 0
        # psd-tools iterates BOTTOM-to-TOP of Photoshop layer panel
        # Bottom of panel = rendered first (bottom of canvas) = LOWEST position in Konva
        # So first layer from psd-tools should get LOWEST position, we INCREMENT
        layer_counter = {"index": 0}

        def process_layers(container, is_root=True):
            """Process layers recursively, preserving group hierarchy.

            Positions are assigned in ASCENDING order because:
            - psd-tools iterates BOTTOM-to-TOP of Photoshop layer panel
            - Bottom of panel = rendered at bottom in Photoshop
            - Konva renders higher position on top
            - So first layer from psd-tools should get LOWEST position
            """
            result = []

            # Convert container to list for sibling access (needed for clipping mask detection)
            sibling_layers = list(container)

            # Build a set of layers that are clipped to other layers (should be skipped)
            # These are handled via their clipping base's composite
            clipped_layers = set()
            for layer in sibling_layers:
                if hasattr(layer, 'clip_layers') and layer.clip_layers:
                    for clipped in layer.clip_layers:
                        clipped_layers.add(id(clipped))
                        print(f"[CLIP] Layer '{clipped.name}' is clipped to '{layer.name}' - will be skipped")

            for layer in sibling_layers:
                # Skip layers that are clipped to another layer
                if id(layer) in clipped_layers:
                    print(f"[CLIP] Skipping '{layer.name}' - handled by clipping base")
                    continue
                if isinstance(layer, Group):
                    # Map the group itself - assign current position then increment
                    group_data = map_layer(layer, layer_counter["index"], width, height, is_group=True, psd_dpi=psd_dpi)
                    layer_counter["index"] += 1

                    if group_data:
                        # Extract warnings
                        layer_warnings = group_data.pop("warnings", [])
                        for w in layer_warnings:
                            warnings.append(f"Group '{group_data['name']}': {w}")

                        # Recursively process children (not root level)
                        group_data["children"] = process_layers(layer, is_root=False)
                        result.append(group_data)
                else:
                    # Check if this layer has clip_layers (it's a clipping BASE with content clipped to it)
                    has_clip_layers = hasattr(layer, 'clip_layers') and layer.clip_layers

                    if has_clip_layers:
                        # This is a clipping base - extract its composite as an image
                        # The composite includes the clipped content
                        print(f"[CLIP BASE] Layer '{layer.name}' has clip_layers - extracting as image")

                        try:
                            comp = layer.composite()
                            if comp:
                                from utils.image_extractor import extract_layer_image
                                import base64
                                from io import BytesIO

                                # Convert composite to base64
                                comp_rgba = comp.convert("RGBA")
                                buffer = BytesIO()
                                comp_rgba.save(buffer, format="PNG")
                                img_base64 = base64.b64encode(buffer.getvalue()).decode("utf-8")

                                # Create image layer data
                                position = layer_counter["index"]
                                layer_counter["index"] += 1

                                mapped = {
                                    "name": layer.name,
                                    "type": "image",
                                    "position": position,
                                    "visible": layer.visible,
                                    "locked": False,
                                    "x": float(layer.left),
                                    "y": float(layer.top),
                                    "width": float(comp.width),
                                    "height": float(comp.height),
                                    "rotation": 0,
                                    "scale_x": 1.0,
                                    "scale_y": 1.0,
                                    "opacity": layer.opacity / 255.0 if hasattr(layer, "opacity") else 1.0,
                                    "properties": {
                                        "src": None,
                                        "fit": "fill",
                                        "clipPath": None,
                                        "isClipBase": True,  # Mark as clipping base
                                    },
                                }

                                # Add image data
                                image_id = f"img_{position}"
                                images.append({
                                    "id": image_id,
                                    "layer_index": position,
                                    "data": f"data:image/png;base64,{img_base64}",
                                    "mime_type": "image/png",
                                    "width": comp.width,
                                    "height": comp.height,
                                })
                                mapped["image_id"] = image_id

                                result.append(mapped)
                                print(f"[CLIP BASE] Created image layer for '{layer.name}' ({comp.width}x{comp.height})")
                                continue
                        except Exception as e:
                            print(f"[CLIP BASE] Error extracting composite for '{layer.name}': {e}")
                            # Fall through to normal processing

                    # Regular layer - pass sibling_layers for clipping mask detection
                    mapped = map_layer(
                        layer,
                        layer_counter["index"],
                        width,
                        height,
                        is_root=is_root,
                        sibling_layers=sibling_layers,
                        psd_dpi=psd_dpi
                    )
                    if mapped:
                        layer_counter["index"] += 1

                        # Extract warnings
                        layer_warnings = mapped.pop("warnings", [])
                        for w in layer_warnings:
                            warnings.append(f"Layer '{mapped['name']}': {w}")

                        # Handle image data separately
                        image_data = mapped.pop("image_data", None)
                        if image_data:
                            image_id = f"img_{mapped['position']}"
                            images.append({
                                "id": image_id,
                                "layer_index": mapped["position"],
                                **image_data
                            })
                            mapped["image_id"] = image_id

                        # Handle mask data separately (layer masks / raster masks)
                        mask_data = mapped.pop("mask_data", None)
                        if mask_data:
                            mask_id = f"mask_{mapped['position']}"
                            masks.append({
                                "id": mask_id,
                                "layer_index": mapped["position"],
                                **mask_data
                            })
                            mapped["mask_id"] = mask_id
                            print(f"[MASK] Added mask for layer '{mapped['name']}' (position {mapped['position']})")

                        result.append(mapped)

            return result

        # First pass: collect all SmartObjectLayers
        collect_smart_objects(psd)

        # Extract source images for unique smart objects
        for so_layer in smart_object_layers:
            if hasattr(so_layer, "smart_object") and so_layer.smart_object:
                so = so_layer.smart_object
                uid = so.unique_id if hasattr(so, "unique_id") else None

                if uid and uid not in smart_object_sources:
                    print(f"[SMART_OBJECT_SOURCE] Extracting source for unique_id: {uid} (layer: {so_layer.name})")
                    source_data = extract_smart_object_source(so_layer)

                    if source_data:
                        smart_object_sources[uid] = {
                            "id": f"so_{uid}",
                            "unique_id": uid,
                            "data": source_data["data"],
                            "mime_type": source_data["mime_type"],
                            "width": source_data["width"],
                            "height": source_data["height"],
                        }
                        print(f"[SMART_OBJECT_SOURCE] Successfully extracted source: {source_data['width']}x{source_data['height']}")
                    else:
                        print(f"[SMART_OBJECT_SOURCE] Failed to extract source for unique_id: {uid}")

        print(f"[SMART_OBJECT_SOURCE] Total unique sources extracted: {len(smart_object_sources)}")

        # Process all layers starting from PSD root
        mapped_layers = process_layers(psd)

        # Count total layers (including nested)
        def count_layers(layers):
            total = 0
            for layer in layers:
                total += 1
                if layer.get("type") == "group":
                    total += count_layers(layer.get("children", []))
            return total

        total_layer_count = count_layers(mapped_layers)
        if total_layer_count > MAX_LAYERS:
            return jsonify({
                "error": f"Too many layers ({total_layer_count}). Maximum is {MAX_LAYERS} layers"
            }), 400

        # Collect fonts used
        fonts = collect_fonts(mapped_layers)

        return jsonify({
            "width": width,
            "height": height,
            "background_color": background_color,
            "layers": mapped_layers,
            "fonts": fonts,
            "images": images,
            "masks": masks,  # Layer masks (raster masks)
            "smart_object_sources": list(smart_object_sources.values()),
            "warnings": warnings,
        })

    except Exception as e:
        return jsonify({
            "error": f"Failed to parse PSD: {str(e)}"
        }), 500


@app.route("/analyze", methods=["POST"])
def analyze_psd():
    """
    Analyze PSD file structure without full parsing.
    Returns information about groups/folders and their visibility.

    Useful for debugging and selecting which group to import.
    """
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]

    if not file.filename or not file.filename.lower().endswith(".psd"):
        return jsonify({"error": "File must be a PSD file"}), 400

    file_data = file.read()

    if len(file_data) > MAX_FILE_SIZE:
        return jsonify({"error": "File too large"}), 400

    try:
        psd = PSDImage.open(io.BytesIO(file_data))

        def analyze_structure(container, depth=0):
            """Recursively analyze layer structure."""
            result = []
            for layer in container:
                layer_info = {
                    "name": layer.name,
                    "visible": layer.visible,
                    "type": type(layer).__name__,
                    "depth": depth,
                }

                if isinstance(layer, Group):
                    layer_info["is_group"] = True
                    layer_info["children_count"] = len(list(layer))
                    layer_info["children"] = analyze_structure(layer, depth + 1)
                else:
                    layer_info["is_group"] = False
                    layer_info["bounds"] = {
                        "left": layer.left,
                        "top": layer.top,
                        "width": layer.width,
                        "height": layer.height,
                    }

                result.append(layer_info)
            return result

        structure = analyze_structure(psd)

        # Count visible vs total layers
        def count_layers(items, parent_visible=True):
            total = 0
            visible = 0
            for item in items:
                if item.get("is_group"):
                    t, v = count_layers(item.get("children", []), parent_visible and item["visible"])
                    total += t
                    visible += v
                else:
                    total += 1
                    if parent_visible and item["visible"]:
                        visible += 1
            return total, visible

        total_layers, visible_layers = count_layers(structure)

        return jsonify({
            "width": psd.width,
            "height": psd.height,
            "total_layers": total_layers,
            "visible_layers": visible_layers,
            "structure": structure,
        })

    except Exception as e:
        return jsonify({"error": f"Failed to analyze PSD: {str(e)}"}), 500


@app.route("/render", methods=["POST"])
def render_preview():
    """
    Render parsed PSD data to an image for AI analysis.

    Expects JSON body with:
        - width: canvas width
        - height: canvas height
        - layers: array of layer objects (same format as /parse response)
        - images: array of image data (base64)

    Returns:
        PNG image
    """
    from PIL import Image, ImageDraw, ImageFont
    import base64

    try:
        data = request.get_json()

        if not data:
            return jsonify({"error": "No JSON data provided"}), 400

        width = data.get("width", 1080)
        height = data.get("height", 1080)
        layers = data.get("layers", [])
        images_data = {img["id"]: img for img in data.get("images", [])}

        # Create canvas
        canvas = Image.new("RGBA", (width, height), (255, 255, 255, 255))
        draw = ImageDraw.Draw(canvas)

        def render_layers(layer_list):
            """Render layers recursively."""
            for layer in layer_list:
                if not layer.get("visible", True):
                    continue

                layer_type = layer.get("type")
                x = int(layer.get("x", 0))
                y = int(layer.get("y", 0))
                w = int(layer.get("width", 100))
                h = int(layer.get("height", 100))
                opacity = layer.get("opacity", 1.0)
                props = layer.get("properties", {})

                if layer_type == "group":
                    render_layers(layer.get("children", []))

                elif layer_type == "rectangle":
                    fill = props.get("fill", "#CCCCCC")
                    try:
                        if fill.startswith("#"):
                            r = int(fill[1:3], 16)
                            g = int(fill[3:5], 16)
                            b = int(fill[5:7], 16)
                            if opacity < 1.0:
                                # Create transparent layer and composite for proper alpha blending
                                shape_layer = Image.new("RGBA", (w, h), (r, g, b, 255))
                                # Apply opacity
                                alpha = shape_layer.split()[3]
                                alpha = alpha.point(lambda p: int(p * opacity))
                                shape_layer.putalpha(alpha)
                                canvas.paste(shape_layer, (x, y), shape_layer)
                            else:
                                draw.rectangle([x, y, x + w, y + h], fill=(r, g, b, 255))
                    except Exception as e:
                        print(f"Error drawing rectangle: {e}")

                elif layer_type == "ellipse":
                    fill = props.get("fill", "#CCCCCC")
                    try:
                        if fill.startswith("#"):
                            r = int(fill[1:3], 16)
                            g = int(fill[3:5], 16)
                            b = int(fill[5:7], 16)
                            if opacity < 1.0:
                                # Create transparent layer and composite for proper alpha blending
                                shape_layer = Image.new("RGBA", (w, h), (0, 0, 0, 0))
                                shape_draw = ImageDraw.Draw(shape_layer)
                                shape_draw.ellipse([0, 0, w, h], fill=(r, g, b, 255))
                                # Apply opacity
                                alpha = shape_layer.split()[3]
                                alpha = alpha.point(lambda p: int(p * opacity))
                                shape_layer.putalpha(alpha)
                                canvas.paste(shape_layer, (x, y), shape_layer)
                            else:
                                draw.ellipse([x, y, x + w, y + h], fill=(r, g, b, 255))
                    except Exception as e:
                        print(f"Error drawing ellipse: {e}")

                elif layer_type == "text":
                    text = props.get("text", "")
                    fill = props.get("fill", "#000000")
                    font_size = int(props.get("fontSize", 24))

                    try:
                        # Try to load font, fallback to default with size
                        try:
                            font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", font_size)
                        except Exception:
                            # Use default font with explicit size (PIL 10+)
                            font = ImageFont.load_default(size=font_size)

                        if fill.startswith("#"):
                            r = int(fill[1:3], 16)
                            g = int(fill[3:5], 16)
                            b = int(fill[5:7], 16)
                            color = (r, g, b, int(255 * opacity))

                        # Draw text with word wrap if fixedWidth
                        if props.get("fixedWidth") and w > 0:
                            # Simple word wrap
                            words = text.split()
                            lines = []
                            current_line = ""
                            for word in words:
                                test_line = current_line + " " + word if current_line else word
                                bbox = draw.textbbox((0, 0), test_line, font=font)
                                if bbox[2] - bbox[0] <= w:
                                    current_line = test_line
                                else:
                                    if current_line:
                                        lines.append(current_line)
                                    current_line = word
                            if current_line:
                                lines.append(current_line)

                            line_height = font_size * 1.2
                            for i, line in enumerate(lines):
                                draw.text((x, y + i * line_height), line, fill=color, font=font)
                        else:
                            draw.text((x, y), text, fill=color, font=font)

                    except Exception as e:
                        print(f"Error drawing text '{text[:20]}...': {e}")

                elif layer_type == "image":
                    image_id = layer.get("image_id")
                    if image_id and image_id in images_data:
                        try:
                            img_data = images_data[image_id]
                            base64_data = img_data.get("data", "")
                            if base64_data.startswith("data:"):
                                base64_data = base64_data.split(",")[1]

                            img_bytes = base64.b64decode(base64_data)
                            img = Image.open(io.BytesIO(img_bytes)).convert("RGBA")

                            # Resize to layer dimensions
                            img = img.resize((w, h), Image.Resampling.LANCZOS)

                            # Apply opacity
                            if opacity < 1.0:
                                alpha = img.split()[3]
                                alpha = alpha.point(lambda p: int(p * opacity))
                                img.putalpha(alpha)

                            # Paste onto canvas
                            canvas.paste(img, (x, y), img)
                        except Exception as e:
                            print(f"Error rendering image: {e}")
                            # Draw placeholder
                            draw.rectangle([x, y, x + w, y + h], fill=(200, 200, 200, 128), outline=(150, 150, 150))

        render_layers(layers)

        # Save to bytes
        img_bytes = io.BytesIO()
        canvas.save(img_bytes, format="PNG")
        img_bytes.seek(0)

        return send_file(img_bytes, mimetype="image/png")

    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({"error": f"Render failed: {str(e)}"}), 500


@app.route("/render-psd", methods=["POST"])
def render_psd_file():
    """
    Parse and render a PSD file in one request.
    Returns PNG image for AI analysis.

    Expects:
        - multipart/form-data with 'file' field containing the PSD
        - Optional 'scale' query param (default 0.5 for smaller preview)

    Returns:
        PNG image
    """
    from PIL import Image, ImageDraw, ImageFont
    import base64

    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]
    scale = float(request.args.get("scale", 0.5))

    if not file.filename or not file.filename.lower().endswith(".psd"):
        return jsonify({"error": "File must be a PSD file"}), 400

    file_data = file.read()

    try:
        psd = PSDImage.open(io.BytesIO(file_data))

        # Create scaled canvas
        width = int(psd.width * scale)
        height = int(psd.height * scale)

        # Get composite image from PSD (full render)
        composite = psd.composite()
        if composite:
            # Resize
            composite = composite.convert("RGBA")
            composite = composite.resize((width, height), Image.Resampling.LANCZOS)

            # Save to bytes
            img_bytes = io.BytesIO()
            composite.save(img_bytes, format="PNG")
            img_bytes.seek(0)

            return send_file(img_bytes, mimetype="image/png")
        else:
            return jsonify({"error": "Could not composite PSD"}), 500

    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({"error": f"Render failed: {str(e)}"}), 500


@app.errorhandler(413)
def request_entity_too_large(error):
    return jsonify({"error": "File too large"}), 413


@app.errorhandler(500)
def internal_server_error(error):
    return jsonify({"error": "Internal server error"}), 500


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 3335))
    app.run(host="0.0.0.0", port=port, debug=True)
