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
from psd_tools.api.layers import Group, PixelLayer, ShapeLayer, SmartObjectLayer

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
        # psd-tools iterates in layer panel order (top to bottom visually in Photoshop)
        # BUT top of Photoshop panel = rendered ON TOP = needs HIGHEST z-index
        # Wait, actually psd-tools returns layers in reverse order - first = bottom
        # So we INCREMENT: first layer gets lowest position (rendered first = behind)
        layer_counter = {"index": 0}

        def is_background_group(layer):
            """Check if a group should be treated as a background layer.

            Groups containing primarily images/SmartObjects or with background-related
            names are treated as backgrounds and should render behind other groups.
            """
            if not isinstance(layer, Group):
                return False

            name_lower = layer.name.lower() if layer.name else ""
            # Check for background-related names
            background_keywords = ['image', 'placeholder', 'background', 'bg', 'photo']
            if any(kw in name_lower for kw in background_keywords):
                return True

            # Check if group contains primarily image layers
            image_count = 0
            shape_count = 0
            for child in layer:
                if isinstance(child, SmartObjectLayer) or isinstance(child, PixelLayer):
                    image_count += 1
                elif isinstance(child, ShapeLayer):
                    shape_count += 1

            # If more images than shapes, treat as background
            return image_count > 0 and image_count >= shape_count

        def process_layers(container, is_root=True):
            """Process layers recursively, preserving group hierarchy.

            Positions are assigned in ASCENDING order because:
            - psd-tools iterates from bottom to top of layer stack
            - First layer = bottom of stack = rendered behind = lowest position
            - Last layer = top of stack = rendered on top = highest position

            At root level, groups containing images/SmartObjects are sorted to render
            first (behind) to ensure proper z-ordering when masks aren't supported.
            """
            result = []

            # Convert container to list for sibling access (needed for clipping mask detection)
            sibling_layers = list(container)

            # At root level, sort groups to put image-heavy groups first (behind)
            if is_root:
                # Separate background groups from foreground groups
                bg_groups = [l for l in sibling_layers if is_background_group(l)]
                fg_groups = [l for l in sibling_layers if not is_background_group(l)]
                # Background groups first, then foreground groups
                sibling_layers = bg_groups + fg_groups
                if bg_groups:
                    print(f"[Z-ORDER] Reordered groups: backgrounds={[l.name for l in bg_groups]}, foregrounds={[l.name for l in fg_groups]}")

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
                        # This is a clipping base - extract composite WITH clipped layers
                        print(f"[CLIP BASE] Layer '{layer.name}' has {len(layer.clip_layers)} clip_layers")
                        for clip_layer in layer.clip_layers:
                            print(f"  [CLIP] Clipped layer: '{clip_layer.name}'")

                        try:
                            from utils.image_extractor import extract_layer_image
                            import base64
                            from io import BytesIO
                            from PIL import Image

                            # Get base layer composite
                            base_comp = layer.composite()
                            if not base_comp:
                                print(f"[CLIP BASE] No composite for '{layer.name}', skipping")
                                continue

                            base_comp = base_comp.convert("RGBA")

                            # Use ONLY base layer bounds - this is the clipping mask!
                            # Clipped layers are cropped TO the base, not expanded beyond it
                            min_x = layer.left
                            min_y = layer.top
                            result_width = base_comp.width
                            result_height = base_comp.height
                            result_img = Image.new("RGBA", (result_width, result_height), (0, 0, 0, 0))

                            # Paste base layer
                            base_x = int(layer.left - min_x)
                            base_y = int(layer.top - min_y)
                            result_img.paste(base_comp, (base_x, base_y), base_comp)

                            # Process clipped layers - they are clipped TO the base's alpha
                            for clip_layer in layer.clip_layers:
                                clip_comp = clip_layer.composite()
                                if not clip_comp:
                                    continue
                                clip_comp = clip_comp.convert("RGBA")

                                # Position on result canvas
                                clip_x = int(clip_layer.left - min_x)
                                clip_y = int(clip_layer.top - min_y)

                                # Create a temporary image for this clipped layer
                                temp = Image.new("RGBA", (result_width, result_height), (0, 0, 0, 0))
                                temp.paste(clip_comp, (clip_x, clip_y), clip_comp)

                                # Clip to base layer's alpha (use base alpha as mask)
                                # Create mask from base layer alpha channel
                                base_alpha = Image.new("L", (result_width, result_height), 0)
                                base_with_alpha = Image.new("RGBA", (result_width, result_height), (0, 0, 0, 0))
                                base_with_alpha.paste(base_comp, (base_x, base_y), base_comp)
                                base_alpha = base_with_alpha.split()[3]

                                # Apply clip mask - only show clipped layer where base has alpha
                                temp_r, temp_g, temp_b, temp_a = temp.split()
                                # Multiply clipped layer alpha with base alpha
                                clipped_alpha = Image.composite(temp_a, Image.new("L", temp_a.size, 0), base_alpha)
                                temp = Image.merge("RGBA", (temp_r, temp_g, temp_b, clipped_alpha))

                                # Composite onto result
                                result_img = Image.alpha_composite(result_img, temp)

                            comp = result_img
                            print(f"[CLIP BASE] Composited '{layer.name}' with clipped layers: {result_width}x{result_height}")

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
                                "x": float(min_x),
                                "y": float(min_y),
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

                            # Apply tint color if specified (for recoloring icon images)
                            tint_color = props.get("tintColor")
                            if tint_color and tint_color.startswith("#"):
                                try:
                                    tr = int(tint_color[1:3], 16)
                                    tg = int(tint_color[3:5], 16)
                                    tb = int(tint_color[5:7], 16)
                                    # Create solid color image with same size
                                    tint_layer = Image.new("RGBA", img.size, (tr, tg, tb, 255))
                                    # Use original image's alpha as mask
                                    # This replaces all non-transparent pixels with tint color
                                    img = Image.composite(tint_layer, Image.new("RGBA", img.size, (0, 0, 0, 0)), img)
                                except Exception as te:
                                    print(f"Error applying tint: {te}")

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


@app.route("/render-with-substitution", methods=["POST"])
def render_with_substitution():
    """
    Render PSD with substituted values for tagged layers.
    Uses psd.composite() for 1:1 accuracy, then overlays substituted content.

    Expects JSON body with:
        - psd_path: path to PSD file (or 'file' in form data)
        - variant_path: path to variant group (e.g. "Post 01")
        - tags: dict mapping layer paths to semantic tags
        - data: substitution data (header, subtitle, main_image, primary_color, etc.)

    Returns: PNG image
    """
    from PIL import Image, ImageDraw, ImageFont
    import base64

    try:
        # Get request data
        if request.is_json:
            req_data = request.get_json()
            psd_path = req_data.get('psd_path')
            if psd_path:
                with open(psd_path, 'rb') as f:
                    file_data = f.read()
            else:
                return jsonify({"error": "No psd_path provided"}), 400
        elif "file" in request.files:
            file_data = request.files["file"].read()
            req_data = {
                'variant_path': request.form.get('variant_path'),
                'tags': request.form.get('tags', '{}'),
                'data': request.form.get('data', '{}')
            }
            if isinstance(req_data['tags'], str):
                import json as json_module
                req_data['tags'] = json_module.loads(req_data['tags'])
            if isinstance(req_data['data'], str):
                import json as json_module
                req_data['data'] = json_module.loads(req_data['data'])
        else:
            return jsonify({"error": "No file or psd_path provided"}), 400

        variant_path = req_data.get('variant_path', '')
        tags = req_data.get('tags', {})
        sub_data = req_data.get('data', {})

        # Parse PSD
        psd = PSDImage.open(io.BytesIO(file_data))

        # Find variant group if specified
        def find_group(layers, path):
            parts = path.split('/') if path else []
            current = layers
            for part in parts:
                found = None
                for layer in current:
                    if layer.name == part:
                        found = layer
                        break
                if found is None:
                    return None
                current = found
            return current

        target_group = find_group(list(psd), variant_path) if variant_path else psd

        # Start with blank canvas
        canvas = Image.new("RGBA", (psd.width, psd.height), (255, 255, 255, 255))

        def get_layer_path(layer, parent_path=""):
            return f"{parent_path}/{layer.name}" if parent_path else layer.name

        def render_layer_recursive(layer, parent_path="", parent_visible=True):
            """Render layer using native composite, with substitutions for tagged layers."""
            layer_path = get_layer_path(layer, parent_path)
            is_visible = layer.visible and parent_visible

            print(f"[RENDER] Processing layer: '{layer_path}' visible={is_visible} type={type(layer).__name__}")

            # Check if this layer path has a tag
            tag_info = tags.get(layer_path, {})
            semantic_tag = tag_info.get('semantic_tag') if isinstance(tag_info, dict) else tag_info
            if semantic_tag:
                print(f"[RENDER] Layer '{layer_path}' has semantic_tag: {semantic_tag}")

            if hasattr(layer, '__iter__'):  # Group
                for child in layer:
                    render_layer_recursive(child, layer_path, is_visible)
            else:
                if not is_visible:
                    return

                # Get native composite (1:1 with Photoshop)
                comp = layer.composite()
                if not comp:
                    return

                comp = comp.convert("RGBA")
                x, y = layer.left, layer.top

                # Apply substitution if layer has semantic tag and data exists
                if semantic_tag and sub_data:
                    value = sub_data.get(semantic_tag)
                    print(f"[SUBST] Layer '{layer_path}' has tag '{semantic_tag}', value exists: {value is not None}")

                    if value:
                        # Text substitution
                        if semantic_tag in ['header', 'subtitle', 'paragraph', 'social_handle', 'url', 'cta']:
                            if hasattr(layer, 'engine_dict'):
                                # Draw new text over the composite
                                try:
                                    # Get original text properties
                                    font_size = 24
                                    font_family = "DejaVuSans"
                                    fill_color = (0, 0, 0, 255)

                                    # Try to extract from layer
                                    if hasattr(layer, 'engine_dict') and layer.engine_dict:
                                        ed = layer.engine_dict
                                        if 'StyleRun' in ed and 'RunArray' in ed['StyleRun']:
                                            style = ed['StyleRun']['RunArray'][0].get('StyleSheet', {}).get('StyleSheetData', {})
                                            font_size = int(style.get('FontSize', 24))
                                            fc = style.get('FillColor', {}).get('Values', [1, 0, 0, 0])
                                            if len(fc) >= 4:
                                                fill_color = (int(fc[1]*255), int(fc[2]*255), int(fc[3]*255), int(fc[0]*255))

                                    # Create new text image
                                    try:
                                        font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", font_size)
                                    except:
                                        font = ImageFont.load_default(size=font_size)

                                    # Measure text
                                    temp_draw = ImageDraw.Draw(Image.new("RGBA", (1, 1)))
                                    bbox = temp_draw.textbbox((0, 0), value, font=font)
                                    text_w = bbox[2] - bbox[0]
                                    text_h = bbox[3] - bbox[1]

                                    # Create text image
                                    text_img = Image.new("RGBA", (max(comp.width, text_w + 10), max(comp.height, text_h + 10)), (0, 0, 0, 0))
                                    draw = ImageDraw.Draw(text_img)
                                    draw.text((0, 0), value, fill=fill_color, font=font)

                                    comp = text_img
                                except Exception as e:
                                    print(f"[SUBST] Error substituting text: {e}")

                        # Image substitution
                        elif semantic_tag in ['main_image', 'logo']:
                            print(f"[SUBST IMAGE] Attempting image substitution for '{layer_path}'")
                            print(f"[SUBST IMAGE] Original comp size: {comp.width}x{comp.height}")
                            print(f"[SUBST IMAGE] Value starts with: {value[:50] if isinstance(value, str) else 'N/A'}...")
                            try:
                                img_data = value
                                if img_data.startswith('data:'):
                                    img_data = img_data.split(',')[1]
                                    print(f"[SUBST IMAGE] Stripped data: prefix")
                                img_bytes = base64.b64decode(img_data)
                                new_img = Image.open(io.BytesIO(img_bytes)).convert("RGBA")
                                print(f"[SUBST IMAGE] Loaded new image: {new_img.width}x{new_img.height}")

                                # Resize to fit layer bounds, maintaining aspect ratio
                                new_img = new_img.resize((comp.width, comp.height), Image.Resampling.LANCZOS)
                                print(f"[SUBST IMAGE] Resized to: {new_img.width}x{new_img.height}")

                                # Use original alpha as mask (for clipping)
                                if comp.mode == 'RGBA':
                                    orig_alpha = comp.split()[3]
                                    new_img.putalpha(orig_alpha)
                                    print(f"[SUBST IMAGE] Applied original alpha mask")

                                comp = new_img
                                print(f"[SUBST IMAGE] Image substitution complete")
                            except Exception as e:
                                print(f"[SUBST IMAGE] Error substituting image: {e}")
                                import traceback
                                traceback.print_exc()

                        # Color substitution
                        elif semantic_tag in ['primary_color', 'secondary_color']:
                            try:
                                color = value
                                if color.startswith('#'):
                                    r = int(color[1:3], 16)
                                    g = int(color[3:5], 16)
                                    b = int(color[5:7], 16)

                                    # Recolor - keep alpha, change RGB
                                    if comp.mode == 'RGBA':
                                        _, _, _, a = comp.split()
                                        colored = Image.new("RGBA", comp.size, (r, g, b, 255))
                                        colored.putalpha(a)
                                        comp = colored
                            except Exception as e:
                                print(f"[SUBST] Error substituting color: {e}")

                # Paste onto canvas
                canvas.paste(comp, (x, y), comp)

        # Render all layers in variant
        if variant_path:
            for layer in target_group:
                render_layer_recursive(layer, variant_path, True)
        else:
            for layer in psd:
                render_layer_recursive(layer, "", layer.visible)

        # Return PNG
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
