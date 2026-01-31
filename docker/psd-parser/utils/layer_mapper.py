"""
Layer mapper utility for mapping PSD layers to canvas layer types.
"""

from psd_tools.constants import BlendMode
from psd_tools.api.layers import TypeLayer, PixelLayer, ShapeLayer, SmartObjectLayer, Group
from psd_tools.api.adjustments import SolidColorFill

from .font_matcher import match_font, extract_font_weight, extract_font_style
from .image_extractor import extract_layer_image, extract_smart_object_image, rgba_to_hex


# Canvas layer types matching LayerType enum
LAYER_TYPE_TEXT = "text"
LAYER_TYPE_IMAGE = "image"
LAYER_TYPE_RECTANGLE = "rectangle"
LAYER_TYPE_ELLIPSE = "ellipse"
LAYER_TYPE_GROUP = "group"


def get_layer_type(layer) -> str | None:
    """Determine the canvas layer type for a PSD layer."""
    if isinstance(layer, TypeLayer):
        return LAYER_TYPE_TEXT

    if isinstance(layer, (PixelLayer, SmartObjectLayer)):
        return LAYER_TYPE_IMAGE

    if isinstance(layer, ShapeLayer):
        # Try to determine shape type from path data
        shape_type = _detect_shape_type(layer)
        return shape_type

    if isinstance(layer, SolidColorFill):
        # Solid color fill layers are treated as rectangles
        return LAYER_TYPE_RECTANGLE

    return None


def _detect_shape_type(layer: ShapeLayer) -> str:
    """Detect if a shape is a rectangle or ellipse."""
    try:
        # Get vector mask or shape data
        if hasattr(layer, "vector_mask") and layer.vector_mask:
            paths = layer.vector_mask.paths
            if paths:
                # Analyze the path to determine shape type
                # If path has 4 points and is closed, likely a rectangle
                # If path is circular, likely an ellipse
                for path in paths:
                    if hasattr(path, "knots"):
                        knot_count = len(list(path.knots))
                        if knot_count == 4:
                            return LAYER_TYPE_RECTANGLE
                        elif knot_count >= 8:
                            # More points suggest curves/ellipse
                            return LAYER_TYPE_ELLIPSE

        # Default to rectangle for shapes
        return LAYER_TYPE_RECTANGLE

    except Exception:
        return LAYER_TYPE_RECTANGLE


def map_layer(layer, layer_index: int, psd_width: int, psd_height: int, is_group: bool = False, is_root: bool = True) -> dict | None:
    """
    Map a PSD layer to a canvas layer structure.

    Args:
        layer: psd-tools layer object
        layer_index: z-index position
        psd_width: PSD document width
        psd_height: PSD document height
        is_group: whether this layer is a group
        is_root: whether this layer is at the root level (not inside a group)

    Returns:
        dict with layer data or None if layer should be skipped
    """
    # Handle groups
    if is_group or isinstance(layer, Group):
        return _map_group_layer(layer, layer_index)

    # Skip full-canvas SolidColorFill layers ONLY at root level (background fills)
    # Inside groups, keep them as they are intentional design elements
    if is_root and isinstance(layer, SolidColorFill):
        # Check if it covers the full canvas
        if (layer.left <= 0 and layer.top <= 0 and
            layer.width >= psd_width and layer.height >= psd_height):
            return None

    layer_type = get_layer_type(layer)
    if not layer_type:
        return None

    # Base layer data
    base_data = {
        "name": layer.name or f"Layer {layer_index + 1}",
        "type": layer_type,
        "position": layer_index,
        "visible": layer.visible,
        "locked": False,
        "x": float(layer.left),
        "y": float(layer.top),
        "width": float(layer.width) if layer.width > 0 else 100.0,
        "height": float(layer.height) if layer.height > 0 else 100.0,
        "rotation": 0,
        "scale_x": 1.0,
        "scale_y": 1.0,
        "properties": {},
        "image_data": None,
        "warnings": [],
    }

    # Get opacity
    opacity = layer.opacity / 255.0 if hasattr(layer, "opacity") else 1.0

    # Check for unsupported blend modes
    if hasattr(layer, "blend_mode") and layer.blend_mode != BlendMode.NORMAL:
        base_data["warnings"].append(f"Blend mode '{layer.blend_mode.name}' not supported, using normal")

    # Map specific layer type properties
    if layer_type == LAYER_TYPE_TEXT:
        _map_text_layer(layer, base_data, opacity)
    elif layer_type == LAYER_TYPE_IMAGE:
        _map_image_layer(layer, base_data, opacity)
    elif isinstance(layer, SolidColorFill):
        _map_solid_color_fill_layer(layer, base_data, opacity)
    elif layer_type in (LAYER_TYPE_RECTANGLE, LAYER_TYPE_ELLIPSE):
        _map_shape_layer(layer, base_data, opacity)

    return base_data


def _map_text_layer(layer: TypeLayer, data: dict, opacity: float):
    """Map text layer properties."""
    try:
        text = layer.text or ""

        # Get text engine data for detailed properties
        font_family = "Montserrat"  # Default
        font_size = 24
        font_weight = "normal"
        font_style = "normal"
        text_color = "#000000"
        text_align = "left"
        line_height = 1.2

        # Try to get font info from text engine
        if hasattr(layer, "engine_dict") and layer.engine_dict:
            engine = layer.engine_dict

            # Build font list from DocumentResources
            font_list = []
            if "DocumentResources" in engine:
                doc_res = engine["DocumentResources"]
                if "FontSet" in doc_res:
                    for font_entry in doc_res["FontSet"]:
                        font_name = font_entry.get("Name", "")
                        if isinstance(font_name, str):
                            font_list.append(font_name)
                        else:
                            font_list.append("")

            # Get font info from style runs
            if "StyleRun" in engine:
                style_run = engine["StyleRun"]
                run_array = style_run.get("RunArray", [])

                # Warn if multiple style runs (mixed formatting not fully supported)
                if len(run_array) > 1:
                    data["warnings"].append(
                        "Text has mixed styles (partial bold/italic). Only primary style will be used."
                    )

                # Find the longest run (most text) to use as primary style
                longest_run = None
                longest_length = 0
                for run in run_array:
                    run_length = run.get("Length", 0)
                    if run_length > longest_length:
                        longest_length = run_length
                        longest_run = run

                if longest_run and "StyleSheet" in longest_run:
                    style = longest_run["StyleSheet"].get("StyleSheetData", {})

                    # Font family - Font is an index into FontSet
                    if "Font" in style:
                        font_index = style["Font"]
                        psd_font_name = None
                        if isinstance(font_index, int) and font_index < len(font_list):
                            psd_font_name = font_list[font_index]
                        elif isinstance(font_index, str):
                            psd_font_name = font_index

                        if psd_font_name:
                            font_info = match_font(psd_font_name)
                            font_family = font_info["matched"]
                            font_weight = extract_font_weight(psd_font_name)
                            font_style = extract_font_style(psd_font_name)

                    # Font size
                    if "FontSize" in style:
                        font_size = float(style["FontSize"])

                    # Color
                    if "FillColor" in style:
                        fill_color = style["FillColor"]
                        if "Values" in fill_color:
                            values = fill_color["Values"]
                            if len(values) >= 4:
                                # CMYK or RGB values
                                r = int(values[1] * 255) if len(values) > 1 else 0
                                g = int(values[2] * 255) if len(values) > 2 else 0
                                b = int(values[3] * 255) if len(values) > 3 else 0
                                text_color = f"#{r:02x}{g:02x}{b:02x}"

            # Get paragraph style
            if "ParagraphRun" in engine:
                para_run = engine["ParagraphRun"]
                if "RunArray" in para_run and para_run["RunArray"]:
                    first_para = para_run["RunArray"][0]
                    if "ParagraphSheet" in first_para:
                        para_style = first_para["ParagraphSheet"].get("Properties", {})

                        # Text alignment
                        justify = para_style.get("Justification", 0)
                        if justify == 0:
                            text_align = "left"
                        elif justify == 1:
                            text_align = "right"
                        elif justify == 2:
                            text_align = "center"

        # Check if text has a defined bounding box (for multi-line/wrapped text)
        has_fixed_width = data.get("width", 0) > 0

        data["properties"] = {
            "text": text,
            "fontFamily": font_family,
            "fontSize": font_size,
            "fontWeight": font_weight,
            "fontStyle": font_style,
            "lineHeight": line_height,
            "letterSpacing": 0,
            "fill": text_color,
            "align": text_align,
            "verticalAlign": "top",
            "textDirection": "horizontal",
            "fixedWidth": has_fixed_width,  # Preserve original width from PSD
        }

    except Exception as e:
        data["warnings"].append(f"Could not fully parse text properties: {str(e)}")
        data["properties"] = {
            "text": layer.text or "",
            "fontFamily": "Montserrat",
            "fontSize": 24,
            "fontWeight": "normal",
            "fontStyle": "normal",
            "lineHeight": 1.2,
            "letterSpacing": 0,
            "fill": "#000000",
            "align": "left",
            "verticalAlign": "top",
            "textDirection": "horizontal",
            "fixedWidth": False,
        }


def _map_image_layer(layer, data: dict, opacity: float):
    """Map image/pixel layer properties."""
    try:
        # Extract the image
        if isinstance(layer, SmartObjectLayer):
            image_data = extract_smart_object_image(layer)
        else:
            image_data = extract_layer_image(layer)

        if image_data:
            data["image_data"] = image_data
            data["properties"] = {
                "src": None,  # Will be set after image is saved
                "fit": "cover",
            }
        else:
            data["warnings"].append("Could not extract image from layer")
            data["properties"] = {
                "src": None,
                "fit": "cover",
            }

    except Exception as e:
        data["warnings"].append(f"Failed to extract image: {str(e)}")
        data["properties"] = {
            "src": None,
            "fit": "cover",
        }


def _map_shape_layer(layer: ShapeLayer, data: dict, opacity: float):
    """Map shape layer properties."""
    try:
        fill_color = "#CCCCCC"
        stroke_color = None
        stroke_width = 0
        corner_radius = 0

        # Try to get fill color
        if hasattr(layer, "vector_mask") and layer.vector_mask:
            pass  # Shape colors are complex in PSD

        # Try to get from layer effects or direct fill
        if hasattr(layer, "tagged_blocks"):
            for block in layer.tagged_blocks:
                if hasattr(block, "data"):
                    # Look for solid color fill
                    if hasattr(block.data, "color"):
                        color = block.data.color
                        if hasattr(color, "red"):
                            fill_color = f"#{int(color.red):02x}{int(color.green):02x}{int(color.blue):02x}"

        # Try composite approach - sample dominant color from rendered layer
        try:
            composite = layer.composite()
            if composite:
                # Get a sample pixel from the center
                composite_rgba = composite.convert("RGBA")
                w, h = composite_rgba.size
                if w > 0 and h > 0:
                    center_pixel = composite_rgba.getpixel((w // 2, h // 2))
                    if center_pixel and len(center_pixel) >= 4 and center_pixel[3] > 0:  # If not fully transparent
                        extracted_color = rgba_to_hex(center_pixel, fill_color)
                        # Only use if we got a valid color
                        if extracted_color and extracted_color != "#000000":
                            fill_color = extracted_color
        except Exception as e:
            print(f"Could not extract shape color: {e}")

        if data["type"] == LAYER_TYPE_RECTANGLE:
            data["properties"] = {
                "fill": fill_color,
                "stroke": stroke_color,
                "strokeWidth": stroke_width,
                "cornerRadius": corner_radius,
            }
        else:  # Ellipse
            data["properties"] = {
                "fill": fill_color,
                "stroke": stroke_color,
                "strokeWidth": stroke_width,
            }

    except Exception as e:
        data["warnings"].append(f"Could not fully parse shape properties: {str(e)}")
        if data["type"] == LAYER_TYPE_RECTANGLE:
            data["properties"] = {
                "fill": "#CCCCCC",
                "stroke": None,
                "strokeWidth": 0,
                "cornerRadius": 0,
            }
        else:
            data["properties"] = {
                "fill": "#CCCCCC",
                "stroke": None,
                "strokeWidth": 0,
            }


def _map_solid_color_fill_layer(layer: SolidColorFill, data: dict, opacity: float):
    """Map solid color fill layer properties."""
    try:
        fill_color = "#CCCCCC"

        # Extract color from layer.data
        if hasattr(layer, "data") and layer.data:
            color_data = layer.data
            # Keys are bytes in psd-tools: b'Rd  ', b'Grn ', b'Bl  '
            r = int(color_data.get(b'Rd  ', 204))
            g = int(color_data.get(b'Grn ', 204))
            b = int(color_data.get(b'Bl  ', 204))
            fill_color = f"#{r:02x}{g:02x}{b:02x}"

        data["properties"] = {
            "fill": fill_color,
            "stroke": None,
            "strokeWidth": 0,
            "cornerRadius": 0,
        }

    except Exception as e:
        data["warnings"].append(f"Could not parse solid color fill: {str(e)}")
        data["properties"] = {
            "fill": "#CCCCCC",
            "stroke": None,
            "strokeWidth": 0,
            "cornerRadius": 0,
        }


def _map_group_layer(layer: Group, layer_index: int) -> dict:
    """Map a group/folder layer."""
    return {
        "name": layer.name or f"Group {layer_index + 1}",
        "type": LAYER_TYPE_GROUP,
        "position": layer_index,
        "visible": layer.visible,
        "locked": False,
        "x": 0,
        "y": 0,
        "width": 0,
        "height": 0,
        "rotation": 0,
        "scale_x": 1.0,
        "scale_y": 1.0,
        "properties": {
            "expanded": True,
        },
        "image_data": None,
        "warnings": [],
        "children": [],  # Will be populated by server.py
    }


def collect_fonts(layers: list) -> list:
    """
    Collect all unique fonts from mapped layers (including nested in groups).

    Returns:
        list of font info dicts
    """
    fonts = {}

    def process_layer(layer):
        if layer["type"] == LAYER_TYPE_TEXT:
            props = layer.get("properties", {})
            font_family = props.get("fontFamily")
            font_weight = props.get("fontWeight", "normal")
            font_style = props.get("fontStyle", "normal")

            if font_family:
                key = f"{font_family}:{font_weight}:{font_style}"
                if key not in fonts:
                    fonts[key] = {
                        "fontFamily": font_family,
                        "fontWeight": font_weight,
                        "fontStyle": font_style,
                    }
        elif layer["type"] == LAYER_TYPE_GROUP:
            # Process children recursively
            for child in layer.get("children", []):
                process_layer(child)

    for layer in layers:
        process_layer(layer)

    return list(fonts.values())
