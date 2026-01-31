"""
PSD Parser Flask Server

Parses PSD files and returns structured JSON data
suitable for importing into a canvas-based graphics editor.
"""

import os
import io
import tempfile
from flask import Flask, request, jsonify
from flask_cors import CORS
from psd_tools import PSDImage
from psd_tools.api.layers import Group

from utils.layer_mapper import map_layer, collect_fonts
from utils.image_extractor import rgba_to_hex

app = Flask(__name__)
CORS(app)

# Configuration
MAX_FILE_SIZE = 100 * 1024 * 1024  # 100MB
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
        layer_counter = {"index": 0}  # Use dict for mutable counter in nested function

        def process_layers(container, is_root=True):
            """Process layers recursively, preserving group hierarchy."""
            result = []

            for layer in container:
                if isinstance(layer, Group):
                    # Map the group itself
                    group_data = map_layer(layer, layer_counter["index"], width, height, is_group=True)
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
                    # Regular layer - only skip full-canvas SolidColorFill at root level
                    mapped = map_layer(layer, layer_counter["index"], width, height, is_root=is_root)
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

                        result.append(mapped)

            return result

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


@app.errorhandler(413)
def request_entity_too_large(error):
    return jsonify({"error": "File too large"}), 413


@app.errorhandler(500)
def internal_server_error(error):
    return jsonify({"error": "Internal server error"}), 500


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 3335))
    app.run(host="0.0.0.0", port=port, debug=True)
