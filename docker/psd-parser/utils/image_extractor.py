"""
Image extractor utility for extracting images from PSD layers.
Converts layer images to base64 encoded PNG.
"""

import base64
import io
from PIL import Image


def extract_layer_image(layer, max_dimension: int = 4096) -> dict | None:
    """
    Extract image from a PSD layer and convert to base64.

    Args:
        layer: psd-tools layer object
        max_dimension: Maximum width/height for the extracted image

    Returns:
        dict with keys:
            - data: base64 encoded image data
            - mime_type: image MIME type
            - width: image width
            - height: image height
        or None if extraction fails
    """
    try:
        # Get the layer's composite image
        pil_image = layer.composite()

        if pil_image is None:
            return None

        # Ensure RGBA mode for transparency support
        if pil_image.mode != "RGBA":
            pil_image = pil_image.convert("RGBA")

        # Resize if too large
        width, height = pil_image.size
        if width > max_dimension or height > max_dimension:
            ratio = min(max_dimension / width, max_dimension / height)
            new_width = int(width * ratio)
            new_height = int(height * ratio)
            pil_image = pil_image.resize((new_width, new_height), Image.LANCZOS)
            width, height = new_width, new_height

        # Convert to base64
        buffer = io.BytesIO()
        pil_image.save(buffer, format="PNG", optimize=True)
        buffer.seek(0)

        base64_data = base64.b64encode(buffer.getvalue()).decode("utf-8")

        return {
            "data": f"data:image/png;base64,{base64_data}",
            "mime_type": "image/png",
            "width": width,
            "height": height,
        }

    except Exception as e:
        print(f"Failed to extract layer image: {e}")
        return None


def extract_layer_image_without_mask(layer, max_dimension: int = 4096) -> dict | None:
    """
    Extract image from a PSD layer WITHOUT applying the vector mask.
    This gives us the full rectangular image that can be clipped by CSS/Canvas.

    Args:
        layer: psd-tools layer object
        max_dimension: Maximum width/height for the extracted image

    Returns:
        dict with image data or None if extraction fails
    """
    try:
        pil_image = None

        # Try to get the raw pixel data without mask applied
        # Method 1: Use topil() which gets raw layer pixels
        if hasattr(layer, "topil"):
            try:
                pil_image = layer.topil()
            except Exception:
                pass

        # Method 2: For SmartObjects, try to extract embedded data
        if pil_image is None and hasattr(layer, "smart_object") and layer.smart_object:
            try:
                data = layer.smart_object.data
                if data:
                    pil_image = Image.open(io.BytesIO(data))
            except Exception:
                pass

        # Method 3: Fall back to composite (will have mask baked in)
        if pil_image is None:
            try:
                pil_image = layer.composite()
            except Exception:
                pass

        if pil_image is None:
            return None

        # Ensure RGBA mode for transparency support
        if pil_image.mode != "RGBA":
            pil_image = pil_image.convert("RGBA")

        # Resize if too large
        width, height = pil_image.size
        if width > max_dimension or height > max_dimension:
            ratio = min(max_dimension / width, max_dimension / height)
            new_width = int(width * ratio)
            new_height = int(height * ratio)
            pil_image = pil_image.resize((new_width, new_height), Image.LANCZOS)
            width, height = new_width, new_height

        # Convert to base64
        buffer = io.BytesIO()
        pil_image.save(buffer, format="PNG", optimize=True)
        buffer.seek(0)

        base64_data = base64.b64encode(buffer.getvalue()).decode("utf-8")

        return {
            "data": f"data:image/png;base64,{base64_data}",
            "mime_type": "image/png",
            "width": width,
            "height": height,
        }

    except Exception as e:
        print(f"Failed to extract layer image without mask: {e}")
        return None


def extract_smart_object_image(layer, max_dimension: int = 4096) -> dict | None:
    """
    Extract image from a Smart Object layer.

    Args:
        layer: psd-tools layer object with smart object
        max_dimension: Maximum width/height

    Returns:
        dict with image data or None if extraction fails
    """
    try:
        # Try to get the smart object data
        if hasattr(layer, "smart_object") and layer.smart_object:
            data = layer.smart_object.data
            if data:
                # Try to open as image
                try:
                    pil_image = Image.open(io.BytesIO(data))
                    return _process_pil_image(pil_image, max_dimension)
                except Exception:
                    pass

        # Fall back to composite
        return extract_layer_image(layer, max_dimension)

    except Exception as e:
        print(f"Failed to extract smart object: {e}")
        return extract_layer_image(layer, max_dimension)


def _process_pil_image(pil_image: Image.Image, max_dimension: int) -> dict | None:
    """Process a PIL image and return base64 data."""
    try:
        if pil_image.mode != "RGBA":
            pil_image = pil_image.convert("RGBA")

        width, height = pil_image.size
        if width > max_dimension or height > max_dimension:
            ratio = min(max_dimension / width, max_dimension / height)
            new_width = int(width * ratio)
            new_height = int(height * ratio)
            pil_image = pil_image.resize((new_width, new_height), Image.LANCZOS)
            width, height = new_width, new_height

        buffer = io.BytesIO()
        pil_image.save(buffer, format="PNG", optimize=True)
        buffer.seek(0)

        base64_data = base64.b64encode(buffer.getvalue()).decode("utf-8")

        return {
            "data": f"data:image/png;base64,{base64_data}",
            "mime_type": "image/png",
            "width": width,
            "height": height,
        }

    except Exception as e:
        print(f"Failed to process PIL image: {e}")
        return None


def rgba_to_hex(rgba: tuple, default: str = "#CCCCCC") -> str:
    """Convert RGBA tuple to hex color string."""
    if not rgba or len(rgba) < 3:
        return default

    try:
        r, g, b = rgba[:3]
        # Ensure values are in valid range
        r = max(0, min(255, int(r)))
        g = max(0, min(255, int(g)))
        b = max(0, min(255, int(b)))
        return f"#{r:02x}{g:02x}{b:02x}"
    except (TypeError, ValueError):
        return default
