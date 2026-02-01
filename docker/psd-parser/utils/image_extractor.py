"""
Image extractor utility for extracting images from PSD layers.
Converts layer images to base64 encoded PNG.
"""

import base64
import io
from PIL import Image
from psd_tools import PSDImage


def extract_layer_image(layer, max_dimension: int = 4096, apply_mask: bool = True, normalize_opacity: bool = True) -> dict | None:
    """
    Extract image from a PSD layer and convert to base64.

    Args:
        layer: psd-tools layer object
        max_dimension: Maximum width/height for the extracted image
        apply_mask: Whether to manually apply layer mask if present
        normalize_opacity: Whether to remove layer opacity from alpha channel
                          (opacity should be applied at render time instead)

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

        # Normalize opacity: psd-tools composite() bakes layer opacity into alpha channel
        # We need to remove this so opacity can be applied at render time separately
        # This prevents double-application of opacity (once in image, once in render)
        if normalize_opacity and hasattr(layer, 'opacity') and layer.opacity < 255:
            layer_opacity = layer.opacity / 255.0
            if layer_opacity > 0:
                # Split into channels
                r, g, b, a = pil_image.split()
                # Normalize alpha by dividing by layer opacity (clamped to 255)
                import numpy as np
                a_array = np.array(a, dtype=np.float32)
                a_normalized = np.clip(a_array / layer_opacity, 0, 255).astype(np.uint8)
                a = Image.fromarray(a_normalized, mode='L')
                pil_image = Image.merge('RGBA', (r, g, b, a))
                print(f"  [OPACITY] Normalized alpha channel (removed layer opacity {layer.opacity}/255)")

        # Check if layer has a mask that might need manual application
        # Sometimes composite() doesn't apply layer masks correctly
        if apply_mask and hasattr(layer, 'has_mask') and layer.has_mask():
            try:
                mask = layer.mask
                if mask is not None:
                    mask_image = mask.topil(real=True)
                    if mask_image is not None:
                        # Ensure mask matches layer size
                        if mask_image.size != pil_image.size:
                            mask_image = mask_image.resize(pil_image.size, Image.LANCZOS)
                        # Apply mask to alpha channel
                        pil_image = apply_mask_to_image(pil_image, mask_image)
                        print(f"  [MASK] Applied layer mask to image")
            except Exception as mask_error:
                print(f"  [MASK] Warning: Could not apply layer mask: {mask_error}")

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
        import traceback
        traceback.print_exc()
        return None


def extract_layer_image_without_mask(layer, max_dimension: int = 4096, normalize_opacity: bool = True) -> dict | None:
    """
    Extract image from a PSD layer WITHOUT applying the vector mask.
    This gives us the full rectangular image that can be clipped by CSS/Canvas.

    IMPORTANT: If the layer has effects (Color Overlay, etc.), always use composite()
    because effects are only visible in the composite image.

    Args:
        layer: psd-tools layer object
        max_dimension: Maximum width/height for the extracted image
        normalize_opacity: Whether to remove layer opacity from alpha channel

    Returns:
        dict with image data or None if extraction fails
    """
    try:
        pil_image = None
        layer_name = getattr(layer, 'name', 'unknown')

        # Check if layer has effects - if so, MUST use composite() to get effects
        layer_has_effects = has_layer_effects(layer)
        if layer_has_effects:
            print(f"  [IMAGE] Layer '{layer_name}' has effects - using composite() to preserve them")
            try:
                pil_image = layer.composite()
            except Exception as e:
                print(f"  [IMAGE] composite() failed for '{layer_name}': {e}")

        # If no effects, try other methods
        if pil_image is None and not layer_has_effects:
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

        # Normalize opacity: psd-tools composite() bakes layer opacity into alpha channel
        # We need to remove this so opacity can be applied at render time separately
        if normalize_opacity and hasattr(layer, 'opacity') and layer.opacity < 255:
            layer_opacity = layer.opacity / 255.0
            if layer_opacity > 0:
                import numpy as np
                r, g, b, a = pil_image.split()
                a_array = np.array(a, dtype=np.float32)
                a_normalized = np.clip(a_array / layer_opacity, 0, 255).astype(np.uint8)
                a = Image.fromarray(a_normalized, mode='L')
                pil_image = Image.merge('RGBA', (r, g, b, a))
                print(f"  [OPACITY] Normalized alpha (layer opacity was {layer.opacity}/255)")

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
    Extract image from a Smart Object layer WITH all masks and transparency applied.

    This uses layer.composite() to get the final rendered result including:
    - Layer masks (raster masks)
    - Vector masks
    - Layer opacity
    - All transformations

    For the raw source image (without masks), use extract_smart_object_source() instead.

    Args:
        layer: psd-tools layer object with smart object
        max_dimension: Maximum width/height

    Returns:
        dict with image data or None if extraction fails
    """
    # Use extract_layer_image which now manually applies layer mask if present
    return extract_layer_image(layer, max_dimension, apply_mask=True)


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


def has_layer_effects(layer) -> bool:
    """
    Check if a layer has effects (Color Overlay, Drop Shadow, etc.)
    that would change its appearance from the source image.

    Also checks if composite() looks different from source (e.g., color tint applied).
    """
    try:
        from psd_tools.constants import Tag

        layer_name = getattr(layer, 'name', 'unknown')

        if not hasattr(layer, 'tagged_blocks'):
            return False

        # Check for OBJECT_BASED_EFFECTS_LAYER_INFO which contains layer effects
        try:
            effects = layer.tagged_blocks.get_data(Tag.OBJECT_BASED_EFFECTS_LAYER_INFO)
            if effects:
                print(f"  [EFFECTS] Layer '{layer_name}' has OBJECT_BASED_EFFECTS_LAYER_INFO")
                # Check if effects are enabled (masterFXSwitch)
                if isinstance(effects, dict):
                    master_switch = effects.get(b'masterFXSwitch', 1)
                    if master_switch:
                        # Check for specific effects
                        for effect_key in [b'SoFi', b'DrSh', b'IrSh', b'OrGl', b'IrGl', b'bevl', b'ebbl']:
                            if effect_key in effects:
                                effect_data = effects[effect_key]
                                if isinstance(effect_data, dict):
                                    enabled = effect_data.get(b'enab', True)
                                    if enabled:
                                        print(f"  [EFFECTS] Layer '{layer_name}' has active effect: {effect_key}")
                                        return True
                else:
                    # Effects exist but not in expected dict format - assume active
                    print(f"  [EFFECTS] Layer '{layer_name}' has effects (non-dict format)")
                    return True
        except Exception as e:
            print(f"  [EFFECTS] Error checking effects for '{layer_name}': {e}")

        return False
    except Exception:
        return False


def composite_differs_from_source(layer, threshold: float = 0.1) -> bool:
    """
    Check if layer.composite() is visually different from the source image.
    This detects effects like Color Overlay that are applied to the layer.

    Returns True if the composite appears to have color/effects applied.
    """
    try:
        import numpy as np

        layer_name = getattr(layer, 'name', 'unknown')

        # Get composite
        comp = layer.composite()
        if comp is None:
            return False

        comp_rgba = comp.convert('RGBA')
        comp_arr = np.array(comp_rgba)

        # For SmartObjects, try to get source
        if hasattr(layer, 'smart_object') and layer.smart_object:
            so = layer.smart_object
            if so.data:
                try:
                    source_img = Image.open(io.BytesIO(so.data))
                    source_rgba = source_img.convert('RGBA')
                    source_arr = np.array(source_rgba)

                    # Resize source to match composite if different sizes
                    if source_arr.shape[:2] != comp_arr.shape[:2]:
                        source_img = source_img.resize(comp.size, Image.LANCZOS)
                        source_arr = np.array(source_img.convert('RGBA'))

                    # Compare average colors of non-transparent pixels
                    comp_alpha = comp_arr[:, :, 3]
                    source_alpha = source_arr[:, :, 3]

                    comp_mask = comp_alpha > 50
                    source_mask = source_alpha > 50

                    if np.sum(comp_mask) > 100 and np.sum(source_mask) > 100:
                        comp_rgb_avg = np.mean(comp_arr[:, :, :3][comp_mask], axis=0)
                        source_rgb_avg = np.mean(source_arr[:, :, :3][source_mask], axis=0)

                        # Calculate color difference (normalized 0-1)
                        color_diff = np.abs(comp_rgb_avg - source_rgb_avg) / 255.0
                        max_diff = np.max(color_diff)

                        if max_diff > threshold:
                            print(f"  [EFFECTS] Layer '{layer_name}': composite differs from source by {max_diff:.2%} (threshold: {threshold:.0%})")
                            print(f"    Composite avg RGB: ({comp_rgb_avg[0]:.0f}, {comp_rgb_avg[1]:.0f}, {comp_rgb_avg[2]:.0f})")
                            print(f"    Source avg RGB: ({source_rgb_avg[0]:.0f}, {source_rgb_avg[1]:.0f}, {source_rgb_avg[2]:.0f})")
                            return True

                except Exception as e:
                    print(f"  [EFFECTS] Error comparing composite to source for '{layer_name}': {e}")

        return False

    except Exception as e:
        print(f"  [EFFECTS] Error in composite_differs_from_source: {e}")
        return False


def extract_smart_object_source(layer, max_dimension: int = 4096) -> dict | None:
    """
    Extract SOURCE image from Smart Object (supports PSB/PSD).
    This is the original image before any transformations/masks.

    IMPORTANT: If the layer has effects (Color Overlay, etc.) or the composite
    looks different from the source (indicating color tint), this returns None
    so that the caller falls back to layer.composite() which includes effects.

    Args:
        layer: psd-tools layer object with smart object
        max_dimension: Maximum width/height for the extracted image

    Returns:
        dict with keys:
            - data: base64 encoded image data
            - mime_type: image MIME type
            - width: image width (original source size)
            - height: image height (original source size)
            - unique_id: smart object unique identifier
        or None if extraction fails or layer has effects
    """
    if not hasattr(layer, "smart_object") or not layer.smart_object:
        return None

    layer_name = getattr(layer, 'name', 'unknown')

    # If layer has effects (Color Overlay, etc.), don't use source image
    # because effects are only visible in composite()
    if has_layer_effects(layer):
        print(f"  [SMART_OBJECT] Layer '{layer_name}' has effects - skipping source extraction, will use composite")
        return None

    # Check if composite looks different from source (color tint applied)
    if composite_differs_from_source(layer, threshold=0.15):
        print(f"  [SMART_OBJECT] Layer '{layer_name}' composite differs from source - using composite for colors")
        return None

    so = layer.smart_object
    data = so.data

    if not data:
        return None

    pil_image = None

    # Check if it's a PSD/PSB file (embedded Photoshop document)
    if so.is_psd():
        try:
            # Open embedded PSB/PSD with psd-tools
            embedded_psd = PSDImage.open(io.BytesIO(data))
            pil_image = embedded_psd.composite()
        except Exception as e:
            print(f"Failed to open embedded PSB/PSD: {e}")

    # If not PSB or PSB failed, try as regular image
    if pil_image is None:
        try:
            pil_image = Image.open(io.BytesIO(data))
        except Exception as e:
            print(f"Failed to open smart object as image: {e}")
            return None

    result = _process_pil_image(pil_image, max_dimension)

    if result:
        # Add the unique_id to the result
        result["unique_id"] = so.unique_id if hasattr(so, "unique_id") else None

    return result


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


def extract_layer_mask(layer, max_dimension: int = 4096) -> dict | None:
    """
    Extract layer mask (raster mask) from a PSD layer.

    The layer mask is a grayscale image where:
    - White (255) = fully visible
    - Black (0) = fully hidden
    - Gray values = partial transparency

    Args:
        layer: psd-tools layer object
        max_dimension: Maximum width/height for the extracted mask

    Returns:
        dict with keys:
            - data: base64 encoded PNG image data
            - mime_type: image MIME type
            - width: mask width
            - height: mask height
            - offset_x: mask X offset relative to layer (for alignment)
            - offset_y: mask Y offset relative to layer (for alignment)
            - background_color: mask background color (0 or 255)
            - disabled: whether mask is disabled
        or None if no mask exists
    """
    layer_name = getattr(layer, 'name', 'unknown')

    try:
        # Debug: Check all mask-related properties
        has_mask_method = hasattr(layer, 'has_mask') and callable(getattr(layer, 'has_mask', None))
        has_mask_result = layer.has_mask() if has_mask_method else False
        has_mask_attr = hasattr(layer, 'mask')
        mask_obj = getattr(layer, 'mask', None)

        print(f"  [MASK_DEBUG] Layer '{layer_name}': has_mask()={has_mask_result}, has mask attr={has_mask_attr}, mask={mask_obj is not None}")

        # Check if layer has a mask
        if not has_mask_result:
            print(f"  [MASK_DEBUG] Layer '{layer_name}': has_mask() returned False")
            return None

        if mask_obj is None:
            print(f"  [MASK_DEBUG] Layer '{layer_name}': mask attribute is None")
            return None

        mask = mask_obj

        # Get mask bbox (position in document coordinates)
        mask_bbox = getattr(mask, 'bbox', None)
        layer_left = getattr(layer, 'left', 0)
        layer_top = getattr(layer, 'top', 0)

        # Debug mask properties
        print(f"  [MASK_DEBUG] Mask object: {type(mask)}, size={getattr(mask, 'size', 'N/A')}, bbox={mask_bbox}")
        print(f"  [MASK_DEBUG] Layer position: left={layer_left}, top={layer_top}")

        # Calculate mask offset relative to layer
        # bbox is (x1, y1, x2, y2) in document coordinates
        offset_x = 0
        offset_y = 0
        if mask_bbox and len(mask_bbox) >= 2:
            offset_x = mask_bbox[0] - layer_left
            offset_y = mask_bbox[1] - layer_top
            print(f"  [MASK_DEBUG] Mask offset relative to layer: ({offset_x}, {offset_y})")

        # Check if mask is disabled
        is_disabled = getattr(mask, 'disabled', False)
        if is_disabled:
            print(f"  [MASK] Layer '{layer_name}': mask is disabled, skipping")
            return None

        # Get mask as PIL Image (grayscale 'L' mode)
        # real=True combines pixel mask with vector mask
        try:
            mask_image = mask.topil(real=True)
        except Exception as e:
            print(f"  [MASK_DEBUG] mask.topil(real=True) failed: {e}, trying without real")
            try:
                mask_image = mask.topil()
            except Exception as e2:
                print(f"  [MASK_DEBUG] mask.topil() also failed: {e2}")
                return None

        if mask_image is None:
            print(f"  [MASK] Layer '{layer_name}': mask.topil() returned None")
            return None

        # Ensure it's in grayscale mode
        if mask_image.mode != 'L':
            mask_image = mask_image.convert('L')

        width, height = mask_image.size
        print(f"  [MASK] Layer '{layer_name}': Extracted mask {width}x{height}, offset=({offset_x}, {offset_y})")

        # Track resize ratio for offset adjustment
        resize_ratio = 1.0

        # Resize if too large
        if width > max_dimension or height > max_dimension:
            resize_ratio = min(max_dimension / width, max_dimension / height)
            new_width = int(width * resize_ratio)
            new_height = int(height * resize_ratio)
            mask_image = mask_image.resize((new_width, new_height), Image.LANCZOS)
            width, height = new_width, new_height
            # Adjust offset for resize
            offset_x = int(offset_x * resize_ratio)
            offset_y = int(offset_y * resize_ratio)

        # Convert to PNG (grayscale)
        buffer = io.BytesIO()
        mask_image.save(buffer, format="PNG", optimize=True)
        buffer.seek(0)

        base64_data = base64.b64encode(buffer.getvalue()).decode("utf-8")

        # Get mask properties
        background_color = getattr(mask, 'background_color', 255)

        return {
            "data": f"data:image/png;base64,{base64_data}",
            "mime_type": "image/png",
            "width": width,
            "height": height,
            "offset_x": offset_x,
            "offset_y": offset_y,
            "background_color": background_color,
            "disabled": is_disabled,
        }

    except Exception as e:
        print(f"Failed to extract layer mask for '{layer_name}': {e}")
        import traceback
        traceback.print_exc()
        return None


def analyze_smart_object_transform(layer) -> dict | None:
    """
    Analyze Smart Object transform to detect flip, rotation, and scale.

    Returns:
        dict with keys:
            - flip_x: bool - horizontal flip
            - flip_y: bool - vertical flip
            - rotation: float - rotation in degrees
            - scale_x: float - horizontal scale
            - scale_y: float - vertical scale
        or None if not a smart object or no transform data
    """
    try:
        from psd_tools.api.layers import SmartObjectLayer
        import math

        if not isinstance(layer, SmartObjectLayer):
            return None

        if not hasattr(layer, 'smart_object') or layer.smart_object is None:
            return None

        so = layer.smart_object

        # Get transform_box - 8 values for 4 corners
        transform_box = getattr(so, 'transform_box', None)

        if transform_box is None or len(transform_box) != 8:
            print(f"  [TRANSFORM] No transform_box available")
            return None

        x1, y1, x2, y2, x3, y3, x4, y4 = transform_box
        print(f"  [TRANSFORM] transform_box: ({x1:.1f}, {y1:.1f}), ({x2:.1f}, {y2:.1f}), ({x3:.1f}, {y3:.1f}), ({x4:.1f}, {y4:.1f})")

        # Calculate vectors
        # Top edge vector (point 1 to point 2)
        top_dx = x2 - x1
        top_dy = y2 - y1

        # Left edge vector (point 1 to point 4)
        left_dx = x4 - x1
        left_dy = y4 - y1

        # Calculate rotation from top edge (angle from horizontal)
        rotation = math.degrees(math.atan2(top_dy, top_dx))

        # Calculate lengths (scale)
        top_length = math.sqrt(top_dx**2 + top_dy**2)
        left_length = math.sqrt(left_dx**2 + left_dy**2)

        # Get original source dimensions for scale calculation
        source_width = layer.width
        source_height = layer.height

        # Cross product determines orientation (flip detection)
        # Positive = normal, Negative = flipped
        cross_product = top_dx * left_dy - top_dy * left_dx

        # Detect flip
        # The cross product sign tells us if orientation is reversed
        is_flipped = cross_product < 0

        # To distinguish horizontal vs vertical flip, we check vector directions
        # after accounting for rotation
        #
        # For a non-rotated shape:
        # - Normal: top_dx > 0 (right), left_dy > 0 (down)
        # - H-flip: top_dx < 0, left_dy > 0
        # - V-flip: top_dx > 0, left_dy < 0
        # - Both: top_dx < 0, left_dy < 0

        flip_x = False
        flip_y = False

        if is_flipped:
            # Determine which axis is flipped based on the sign of cross product
            # and the relative orientation of vectors

            # Check if we're close to axis-aligned (within ~10 degrees of 0, 90, 180, 270)
            norm_rotation = rotation % 360
            if norm_rotation < 0:
                norm_rotation += 360

            # For near-horizontal orientation (0° or 180°)
            if abs(norm_rotation) < 10 or abs(norm_rotation - 180) < 10:
                # Check if top edge points left instead of right
                if top_dx < 0:
                    flip_x = True
                else:
                    flip_y = True
            # For near-vertical orientation (90° or 270°)
            elif abs(norm_rotation - 90) < 10 or abs(norm_rotation - 270) < 10:
                if left_dy < 0:
                    flip_y = True
                else:
                    flip_x = True
            else:
                # For rotated shapes, use the cross product direction
                # This is a simplification - may not perfectly distinguish H vs V flip
                flip_x = True  # Default to horizontal flip

        # Adjust scale values
        scale_x = top_length / source_width if source_width > 0 else 1.0
        scale_y = left_length / source_height if source_height > 0 else 1.0

        # Apply flip to scale (negative scale represents flip in canvas/konva)
        if flip_x:
            scale_x = -abs(scale_x)
        if flip_y:
            scale_y = -abs(scale_y)

        result = {
            "flip_x": flip_x,
            "flip_y": flip_y,
            "rotation": rotation if abs(rotation) > 0.1 else 0,
            "scale_x": scale_x,
            "scale_y": scale_y,
        }

        print(f"  [TRANSFORM] Result: flip_x={flip_x}, flip_y={flip_y}, rotation={rotation:.1f}°, scale=({scale_x:.2f}, {scale_y:.2f})")

        return result

    except Exception as e:
        print(f"Failed to analyze smart object transform: {e}")
        import traceback
        traceback.print_exc()
        return None


def apply_mask_to_image(image_pil: Image.Image, mask_pil: Image.Image) -> Image.Image:
    """
    Apply a grayscale mask to an image's alpha channel.

    Args:
        image_pil: PIL Image (RGBA)
        mask_pil: PIL Image (L/grayscale) where white=visible, black=hidden

    Returns:
        PIL Image with mask applied to alpha channel
    """
    try:
        # Ensure image is RGBA
        if image_pil.mode != 'RGBA':
            image_pil = image_pil.convert('RGBA')

        # Ensure mask is grayscale
        if mask_pil.mode != 'L':
            mask_pil = mask_pil.convert('L')

        # Resize mask to match image if needed
        if mask_pil.size != image_pil.size:
            mask_pil = mask_pil.resize(image_pil.size, Image.LANCZOS)

        # Split image into channels
        r, g, b, a = image_pil.split()

        # Combine existing alpha with mask
        # Multiply: new_alpha = old_alpha * mask / 255
        import numpy as np

        a_arr = np.array(a, dtype=np.float32)
        m_arr = np.array(mask_pil, dtype=np.float32)

        # Multiply and clip
        new_alpha = (a_arr * m_arr / 255.0).clip(0, 255).astype(np.uint8)

        # Create new alpha channel
        new_a = Image.fromarray(new_alpha, mode='L')

        # Merge back
        result = Image.merge('RGBA', (r, g, b, new_a))

        return result

    except Exception as e:
        print(f"Failed to apply mask to image: {e}")
        return image_pil
