"""
Layer mapper utility for mapping PSD layers to canvas layer types.
"""

from psd_tools.constants import BlendMode
from psd_tools.api.layers import TypeLayer, PixelLayer, ShapeLayer, SmartObjectLayer, Group
from psd_tools.api.adjustments import SolidColorFill

from .font_matcher import match_font, extract_font_weight, extract_font_style
from .image_extractor import (
    extract_layer_image,
    extract_smart_object_image,
    extract_layer_image_without_mask,
    extract_smart_object_source,
    rgba_to_hex,
    extract_layer_mask,
    analyze_smart_object_transform,
    apply_mask_to_image,
)


# Canvas layer types matching LayerType enum
LAYER_TYPE_TEXT = "text"
LAYER_TYPE_IMAGE = "image"
LAYER_TYPE_RECTANGLE = "rectangle"
LAYER_TYPE_ELLIPSE = "ellipse"
LAYER_TYPE_GROUP = "group"


def extract_vector_mask(layer, width: float, height: float) -> str | None:
    """
    Extract vector mask from a PSD layer and convert to SVG path.

    Args:
        layer: psd-tools layer object
        width: layer width in pixels
        height: layer height in pixels

    Returns:
        SVG path string (e.g., "M0,50 C... Z") or None if no vector mask
    """
    try:
        if not hasattr(layer, "vector_mask") or not layer.vector_mask:
            return None

        paths = layer.vector_mask.paths
        if not paths:
            return None

        svg_path_parts = []

        for path in paths:
            if not hasattr(path, "knots"):
                continue

            knots = list(path.knots)
            if len(knots) < 2:
                continue

            # Photoshop stores coordinates as fractions (0.0-1.0) of the layer size
            # Convert to pixel coordinates
            path_commands = []

            for i, knot in enumerate(knots):
                # Knot has: anchor (the point), leaving (control point for next curve),
                # preceding (control point from previous curve)
                # Coordinates are stored as (y, x) tuples normalized to 0-1
                anchor_y, anchor_x = knot.anchor
                leaving_y, leaving_x = knot.leaving
                preceding_y, preceding_x = knot.preceding

                # Convert to pixel coordinates
                ax = anchor_x * width
                ay = anchor_y * height
                lx = leaving_x * width
                ly = leaving_y * height
                px = preceding_x * width
                py = preceding_y * height

                if i == 0:
                    # Move to first point
                    path_commands.append(f"M{ax:.2f},{ay:.2f}")
                else:
                    # Get previous knot's leaving control point
                    prev_knot = knots[i - 1]
                    prev_leaving_y, prev_leaving_x = prev_knot.leaving
                    plx = prev_leaving_x * width
                    ply = prev_leaving_y * height

                    # Cubic Bezier curve: C control1_x,control1_y control2_x,control2_y end_x,end_y
                    path_commands.append(f"C{plx:.2f},{ply:.2f} {px:.2f},{py:.2f} {ax:.2f},{ay:.2f}")

            # Close the path - connect last point back to first
            if len(knots) > 2:
                first_knot = knots[0]
                first_ay, first_ax = first_knot.anchor
                first_px, first_py = first_knot.preceding[1] * width, first_knot.preceding[0] * height

                last_knot = knots[-1]
                last_ly, last_lx = last_knot.leaving
                last_lx_px = last_lx * width
                last_ly_px = last_ly * height

                # Curve back to first point
                path_commands.append(
                    f"C{last_lx_px:.2f},{last_ly_px:.2f} {first_px:.2f},{first_py:.2f} {first_ax * width:.2f},{first_ay * height:.2f}"
                )
                path_commands.append("Z")

            if path_commands:
                svg_path_parts.append(" ".join(path_commands))

        if svg_path_parts:
            return " ".join(svg_path_parts)

        return None

    except Exception as e:
        print(f"Failed to extract vector mask: {e}")
        return None


def has_vector_mask(layer) -> bool:
    """Check if a layer has a vector mask."""
    try:
        return hasattr(layer, "vector_mask") and layer.vector_mask is not None and len(list(layer.vector_mask.paths)) > 0
    except Exception:
        return False


def extract_alpha_contour_path(layer, width: float, height: float) -> str | None:
    """
    Extract SVG path from alpha channel contour for any shape.
    Uses marching squares algorithm to find the boundary of opaque pixels.

    Always uses the REAL contour shape (not approximated ellipse) but smooths it
    with Bezier curves for round shapes.

    Returns SVG path string or None if no valid contour found.
    """
    try:
        import numpy as np
        from skimage import measure
        from scipy.ndimage import binary_dilation, binary_erosion, binary_fill_holes

        # Get composite image
        comp = layer.composite()
        if comp is None or comp.mode != 'RGBA':
            return None

        arr = np.array(comp)
        alpha = arr[:, :, 3]
        h, w = alpha.shape

        # Check if there's meaningful transparency
        transparent_count = np.sum(alpha < 128)
        total = alpha.size
        transparent_ratio = transparent_count / total

        # Need some transparency (5-50%) to be considered a shaped mask
        if transparent_ratio < 0.05 or transparent_ratio > 0.50:
            return None

        # Check if corners are transparent (rectangular images don't need masks)
        margin = max(3, int(min(w, h) * 0.03))
        corners = [
            alpha[0:margin, 0:margin],
            alpha[0:margin, w-margin:w],
            alpha[h-margin:h, 0:margin],
            alpha[h-margin:h, w-margin:w],
        ]
        corners_transparent = sum(
            1 for region in corners
            if np.sum(region < 128) / region.size > 0.80
        )

        # At least 3 corners should be transparent for non-rectangular shape
        if corners_transparent < 3:
            return None

        # Create binary mask
        binary = (alpha >= 128).astype(np.uint8)

        # Apply morphological closing to connect disconnected regions
        # This is important for irregular "blob" shapes where marching squares
        # may find multiple separate contours instead of one continuous boundary
        struct = np.ones((5, 5))
        # Fill holes first
        filled = binary_fill_holes(binary).astype(np.uint8)
        # Then dilate and erode to close gaps between regions
        closed = binary_erosion(binary_dilation(filled, struct, iterations=3), struct, iterations=3).astype(np.uint8)

        # Find contours using marching squares on the closed mask
        contours = measure.find_contours(closed, 0.5)

        if not contours:
            # Fallback to original binary if closing didn't help
            contours = measure.find_contours(binary, 0.5)

        if not contours:
            return None

        # Get the largest contour (main shape boundary)
        largest_contour = max(contours, key=len)

        # Verify the contour covers a reasonable portion of the layer
        contour_ys = [p[0] for p in largest_contour]
        contour_xs = [p[1] for p in largest_contour]
        contour_height = max(contour_ys) - min(contour_ys)
        contour_width = max(contour_xs) - min(contour_xs)

        # If contour doesn't cover at least 70% of the opaque region, something is wrong
        opaque_rows = np.any(binary > 0, axis=1)
        opaque_cols = np.any(binary > 0, axis=0)
        expected_height = np.sum(opaque_rows)
        expected_width = np.sum(opaque_cols)

        if contour_height < expected_height * 0.7 or contour_width < expected_width * 0.7:
            print(f"  [CONTOUR] Warning: contour covers only {contour_height:.0f}x{contour_width:.0f} but opaque region is {expected_height}x{expected_width}")
            # Try with stronger closing
            struct_large = np.ones((9, 9))
            closed_strong = binary_erosion(binary_dilation(filled, struct_large, iterations=5), struct_large, iterations=5).astype(np.uint8)
            contours_strong = measure.find_contours(closed_strong, 0.5)
            if contours_strong:
                largest_strong = max(contours_strong, key=len)
                strong_ys = [p[0] for p in largest_strong]
                strong_height = max(strong_ys) - min(strong_ys)
                if strong_height > contour_height:
                    largest_contour = largest_strong
                    print(f"  [CONTOUR] Using stronger closing, improved to {strong_height:.0f}px height")

        # Need enough points for a meaningful shape
        if len(largest_contour) < 10:
            return None

        # Calculate circularity to decide smoothing strategy
        from skimage.measure import regionprops, label

        labeled = label(binary)
        regions = regionprops(labeled)

        circularity = 0
        if regions:
            largest_region = max(regions, key=lambda r: r.area)
            area = largest_region.area
            perimeter = largest_region.perimeter
            if perimeter > 0:
                circularity = (4 * np.pi * area) / (perimeter ** 2)

        # Simplify contour - use fewer points for round shapes, more for complex
        from skimage.measure import approximate_polygon

        if circularity > 0.75:
            # Round shape - use more points for accuracy, will smooth with Bezier
            tolerance = max(0.5, min(w, h) * 0.002)  # 0.2% - finer detail
            simplified = approximate_polygon(largest_contour, tolerance)
            use_bezier = True
            print(f"  Detected round shape (circularity={circularity:.2f}), using {len(simplified)} points with Bezier smoothing")
        else:
            # Angular shape - use polygon
            tolerance = max(1, min(w, h) * 0.005)  # 0.5%
            simplified = approximate_polygon(largest_contour, tolerance)
            use_bezier = False
            print(f"  Detected angular shape (circularity={circularity:.2f}), using {len(simplified)} points as polygon")

        if len(simplified) < 4:
            return None

        # Convert to SVG path
        # Note: contour points are in (row, col) = (y, x) format
        points = [(point[1], point[0]) for point in simplified]  # Convert to (x, y)

        if use_bezier and len(points) >= 4:
            # Generate smooth Bezier curve through points using Catmull-Rom to Bezier conversion
            svg_path = _points_to_smooth_bezier(points)
        else:
            # Simple polygon
            path_parts = []
            for i, (x, y) in enumerate(points):
                if i == 0:
                    path_parts.append(f"M{x:.2f},{y:.2f}")
                else:
                    path_parts.append(f"L{x:.2f},{y:.2f}")
            path_parts.append("Z")
            svg_path = " ".join(path_parts)

        print(f"  Generated clipPath: {len(svg_path)} chars, transparency={transparent_ratio*100:.1f}%")

        return svg_path

    except ImportError:
        # scikit-image not available
        return None
    except Exception as e:
        print(f"Failed to extract alpha contour: {e}")
        return None


def _points_to_smooth_bezier(points):
    """
    Convert a list of points to a smooth closed SVG path using cubic Bezier curves.
    Uses Catmull-Rom spline converted to Bezier control points.
    """
    if len(points) < 4:
        # Fall back to polygon
        parts = [f"M{points[0][0]:.2f},{points[0][1]:.2f}"]
        for x, y in points[1:]:
            parts.append(f"L{x:.2f},{y:.2f}")
        parts.append("Z")
        return " ".join(parts)

    # Close the loop by adding first points at the end
    closed_points = list(points) + [points[0], points[1], points[2]]

    path_parts = [f"M{points[0][0]:.2f},{points[0][1]:.2f}"]

    # Generate Bezier curves using Catmull-Rom to Bezier conversion
    # For each segment, we need 4 points: p0, p1, p2, p3
    # The curve goes from p1 to p2
    for i in range(len(points)):
        p0 = closed_points[i]
        p1 = closed_points[i + 1]
        p2 = closed_points[i + 2]
        p3 = closed_points[i + 3]

        # Catmull-Rom to Bezier control points
        # tension = 0 gives Catmull-Rom, we use 1/6 factor
        cp1x = p1[0] + (p2[0] - p0[0]) / 6
        cp1y = p1[1] + (p2[1] - p0[1]) / 6
        cp2x = p2[0] - (p3[0] - p1[0]) / 6
        cp2y = p2[1] - (p3[1] - p1[1]) / 6

        path_parts.append(f"C{cp1x:.2f},{cp1y:.2f} {cp2x:.2f},{cp2y:.2f} {p2[0]:.2f},{p2[1]:.2f}")

    path_parts.append("Z")
    return " ".join(path_parts)


def detect_alpha_mask_shape(layer, width: float, height: float) -> dict | None:
    """
    Detect mask shape from alpha channel (for images with "baked-in" transparency).
    Only detects ellipse/circle shapes - not rectangles or complex shapes.

    Returns dict with shape info or None if no mask detected:
        - type: "ellipse"
        - clipPath: SVG path string
        - rx, ry: radii for ellipse
        - cx, cy: center for ellipse
    """
    try:
        import numpy as np

        # Get composite image
        comp = layer.composite()
        if comp is None or comp.mode != 'RGBA':
            return None

        arr = np.array(comp)
        alpha = arr[:, :, 3]

        h, w = alpha.shape

        # Check if there's meaningful transparency
        transparent_count = np.sum(alpha < 128)
        opaque_count = np.sum(alpha >= 128)
        total = alpha.size

        transparent_ratio = transparent_count / total

        # Need significant transparency (10-40%) to be considered a shaped mask
        # Too little = no mask, too much = probably just sparse content
        if transparent_ratio < 0.10 or transparent_ratio > 0.40:
            return None

        # Find bounding box of visible (opaque) area
        opaque_rows = np.any(alpha >= 128, axis=1)
        opaque_cols = np.any(alpha >= 128, axis=0)

        if not np.any(opaque_rows) or not np.any(opaque_cols):
            return None

        first_y = np.argmax(opaque_rows)
        last_y = h - 1 - np.argmax(opaque_rows[::-1])
        first_x = np.argmax(opaque_cols)
        last_x = w - 1 - np.argmax(opaque_cols[::-1])

        visible_width = last_x - first_x
        visible_height = last_y - first_y

        # Must fill most of the layer (at least 80% in both dimensions)
        # This filters out logos and sparse images
        if visible_width < w * 0.8 or visible_height < h * 0.8:
            return None

        # Check corners - must ALL be transparent for ellipse
        margin = max(5, int(min(w, h) * 0.05))  # 5% margin or at least 5px
        corner_regions = [
            alpha[0:margin, 0:margin],              # top-left
            alpha[0:margin, w-margin:w],            # top-right
            alpha[h-margin:h, 0:margin],            # bottom-left
            alpha[h-margin:h, w-margin:w],          # bottom-right
        ]

        # All corners must be mostly transparent (>90% transparent pixels)
        corners_transparent = all(
            np.sum(region < 128) / region.size > 0.90
            for region in corner_regions
        )

        if not corners_transparent:
            return None

        # Check edge centers - must be mostly opaque for ellipse
        cx_int = w // 2
        cy_int = h // 2
        edge_margin = max(10, int(min(w, h) * 0.1))

        edge_centers = [
            alpha[0:margin, cx_int-edge_margin:cx_int+edge_margin],      # top center
            alpha[h-margin:h, cx_int-edge_margin:cx_int+edge_margin],    # bottom center
            alpha[cy_int-edge_margin:cy_int+edge_margin, 0:margin],      # left center
            alpha[cy_int-edge_margin:cy_int+edge_margin, w-margin:w],    # right center
        ]

        # Edge centers must be mostly opaque (>70% opaque pixels)
        edges_opaque = all(
            np.sum(region >= 128) / region.size > 0.70
            for region in edge_centers
        )

        if not edges_opaque:
            return None

        # This looks like an ellipse - generate the path
        cx = w / 2
        cy = h / 2
        rx = visible_width / 2
        ry = visible_height / 2

        # Generate SVG ellipse path using bezier curves
        # Approximation of ellipse using 4 cubic bezier curves
        # Magic number for control points: ~0.5523
        k = 0.5523

        svg_path = (
            f"M{cx + rx:.2f},{cy:.2f} "
            f"C{cx + rx:.2f},{cy + ry * k:.2f} {cx + rx * k:.2f},{cy + ry:.2f} {cx:.2f},{cy + ry:.2f} "
            f"C{cx - rx * k:.2f},{cy + ry:.2f} {cx - rx:.2f},{cy + ry * k:.2f} {cx - rx:.2f},{cy:.2f} "
            f"C{cx - rx:.2f},{cy - ry * k:.2f} {cx - rx * k:.2f},{cy - ry:.2f} {cx:.2f},{cy - ry:.2f} "
            f"C{cx + rx * k:.2f},{cy - ry:.2f} {cx + rx:.2f},{cy - ry * k:.2f} {cx + rx:.2f},{cy:.2f} Z"
        )

        print(f"  Detected ellipse mask: center=({cx:.0f},{cy:.0f}), radii=({rx:.0f},{ry:.0f}), transparency={transparent_ratio*100:.1f}%")

        return {
            "type": "ellipse",
            "clipPath": svg_path,
            "cx": cx,
            "cy": cy,
            "rx": rx,
            "ry": ry,
        }

    except Exception as e:
        print(f"Failed to detect alpha mask shape: {e}")
        return None


def extract_mask_from_layer(layer, width: float, height: float) -> str | None:
    """
    Extract mask from a layer - tries vector mask first, then alpha channel detection.

    Returns SVG path string or None.
    """
    layer_name = getattr(layer, 'name', 'unknown')

    # Debug: Check what mask types are available
    has_vm = hasattr(layer, "vector_mask") and layer.vector_mask is not None
    has_mask = hasattr(layer, "mask") and layer.mask is not None
    has_clip = hasattr(layer, "clip_layers") and layer.clip_layers
    is_clipping = getattr(layer, "clipping", False)  # Use 'clipping' not deprecated 'clipping_layer'

    # Always log for image layers
    import sys
    layer_type = type(layer).__name__
    print(f"  [MASK DEBUG] Layer '{layer_name}' ({layer_type}): vector_mask={has_vm}, mask={has_mask}, clip_layers={has_clip}, is_clipping={is_clipping}")
    sys.stdout.flush()

    # First try vector mask
    clip_path = extract_vector_mask(layer, width, height)
    if clip_path:
        print(f"  [MASK] Layer '{layer_name}': Got vector_mask clipPath")
        return clip_path

    # Then try general contour extraction (accurate, any shape)
    # This extracts the actual shape from alpha channel using marching squares
    contour_path = extract_alpha_contour_path(layer, width, height)
    if contour_path:
        print(f"  [MASK] Layer '{layer_name}': Got alpha contour path")
        return contour_path

    # Finally try ellipse detection as fallback (approximation)
    alpha_mask = detect_alpha_mask_shape(layer, width, height)
    if alpha_mask:
        print(f"  [MASK] Layer '{layer_name}': Got alpha channel ellipse")
        return alpha_mask.get("clipPath")

    return None


def extract_rotation_from_vector_mask(layer) -> float:
    """
    Try to extract rotation from a layer's vector mask.

    For rotated rectangles, the vector mask contains the actual corners of the shape.
    We can calculate rotation from the angle of the top edge.

    Returns:
        Rotation in degrees, or 0.0 if not determinable
    """
    try:
        import math

        if not hasattr(layer, 'vector_mask') or not layer.vector_mask:
            return 0.0

        paths = layer.vector_mask.paths
        if not paths:
            return 0.0

        # Find the first path with enough knots (rectangle has 4 corners)
        for path in paths:
            knots = list(path)
            if len(knots) >= 4:
                # Get the first two points (top-left and top-right for a rectangle)
                # Knots are bezier points with anchor coordinates
                if hasattr(knots[0], 'anchor'):
                    # anchor is (y, x) normalized to image size
                    p1 = knots[0].anchor
                    p2 = knots[1].anchor

                    # Calculate angle of top edge (from p1 to p2)
                    # Note: anchor coordinates are (y, x) not (x, y)
                    dy = p2[0] - p1[0]  # y difference
                    dx = p2[1] - p1[1]  # x difference

                    angle = math.degrees(math.atan2(dy, dx))

                    # Only report significant rotation (> 1 degree)
                    if abs(angle) > 1.0:
                        print(f"    [ROTATION] Detected rotation from vector_mask: {angle:.1f}°")
                        return angle

        return 0.0

    except Exception as e:
        print(f"    [ROTATION] Error extracting rotation from vector_mask: {e}")
        return 0.0


def extract_rotation_from_origination(layer) -> float:
    """
    Try to extract rotation from a layer's origination data.

    Origination data often contains transform information for shapes.

    Returns:
        Rotation in degrees, or 0.0 if not determinable
    """
    try:
        import math

        if not hasattr(layer, 'origination') or not layer.origination:
            return 0.0

        for orig in layer.origination:
            # Check for transform data
            if hasattr(orig, 'transform'):
                transform = orig.transform
                if transform and len(transform) >= 4:
                    xx, xy = transform[0], transform[1]
                    angle = math.degrees(math.atan2(xy, xx))
                    if abs(angle) > 1.0:
                        print(f"    [ROTATION] Detected rotation from origination transform: {angle:.1f}°")
                        return angle

            # Check for bounds that might indicate rotation
            if hasattr(orig, 'bounds'):
                bounds = orig.bounds
                print(f"    [ROTATION] origination bounds: {bounds}")

        return 0.0

    except Exception as e:
        print(f"    [ROTATION] Error extracting rotation from origination: {e}")
        return 0.0


def collect_clipping_base_layers(clipping_layer, sibling_layers: list, layer_offset_x: float, layer_offset_y: float) -> list:
    """
    Collect information about clipping base layers for complex clipping mask rendering.

    Each base layer can have its own rotation, opacity, and position.
    The frontend will render the image multiple times, once for each base layer.

    Args:
        clipping_layer: The layer with clipping=True
        sibling_layers: All sibling layers in the same group (in order)
        layer_offset_x: X offset of the clipping layer
        layer_offset_y: Y offset of the clipping layer

    Returns:
        List of base layer info dicts with: x, y, width, height, rotation, opacity, name
    """
    try:
        # Find index of clipping layer
        clipping_index = None
        for i, layer in enumerate(sibling_layers):
            if layer is clipping_layer:
                clipping_index = i
                break

        if clipping_index is None or clipping_index == 0:
            return []

        # Collect base layers (non-clipping layers before this one)
        base_layers = []
        for i in range(clipping_index - 1, -1, -1):
            layer = sibling_layers[i]
            # Stop at another clipping layer
            if getattr(layer, "clipping", False):
                break
            base_layers.append(layer)

        if not base_layers:
            return []

        print(f"  [CLIPPING] Found {len(base_layers)} base layers for clipping")

        # Collect info about each base layer
        clipping_bases = []

        for base_layer in base_layers:
            # Calculate absolute position (not relative to clipping layer)
            abs_x = base_layer.left
            abs_y = base_layer.top
            w = base_layer.width
            h = base_layer.height

            # Get opacity from layer (0-255 -> 0.0-1.0)
            base_opacity = base_layer.opacity / 255.0 if hasattr(base_layer, 'opacity') else 1.0

            # Try multiple methods to get rotation
            rotation = 0.0

            # Method 1: Try vector_mask (for shapes)
            rotation = extract_rotation_from_vector_mask(base_layer)

            # Method 2: Try origination data
            if abs(rotation) < 0.1:
                rotation = extract_rotation_from_origination(base_layer)

            # Method 3: For SmartObjectLayers, use transform analysis
            if abs(rotation) < 0.1 and isinstance(base_layer, SmartObjectLayer):
                transform_info = analyze_smart_object_transform(base_layer)
                if transform_info and transform_info.get("rotation"):
                    rotation = transform_info["rotation"]
                    print(f"    [ROTATION] Base layer '{base_layer.name}': rotation from SmartObject = {rotation}°")

            base_info = {
                "name": base_layer.name,
                "x": abs_x,
                "y": abs_y,
                "width": w,
                "height": h,
                "rotation": rotation,
                "opacity": base_opacity,
            }

            clipping_bases.append(base_info)
            print(f"    [CLIPPING] Base layer '{base_layer.name}': pos=({abs_x}, {abs_y}) size={w}x{h} rotation={rotation}° opacity={base_opacity:.2f}")

        print(f"  [CLIPPING] Collected {len(clipping_bases)} clipping bases with full properties")

        return clipping_bases

    except Exception as e:
        print(f"  [CLIPPING] Error collecting clipping base layers: {e}")
        import traceback
        traceback.print_exc()
        return []


def generate_clipping_base_path(clipping_layer, sibling_layers: list, layer_offset_x: float, layer_offset_y: float) -> str | None:
    """
    Generate SVG path from clipping base layers (layers that a clipped layer clips to).

    NOTE: This generates simple rectangles WITHOUT rotation. For rotated clipping masks,
    use collect_clipping_base_layers() and render multiple images in frontend.

    In Photoshop, a clipping mask clips to ALL non-clipping layers below it until
    the first clipping layer or end of group.

    Args:
        clipping_layer: The layer with clipping=True
        sibling_layers: All sibling layers in the same group (in order)
        layer_offset_x: X offset of the clipping layer
        layer_offset_y: Y offset of the clipping layer

    Returns:
        Combined SVG path string or None
    """
    try:
        # Find index of clipping layer
        clipping_index = None
        for i, layer in enumerate(sibling_layers):
            if layer is clipping_layer:
                clipping_index = i
                break

        if clipping_index is None or clipping_index == 0:
            return None

        # Collect base layers (non-clipping layers before this one)
        base_layers = []
        for i in range(clipping_index - 1, -1, -1):
            layer = sibling_layers[i]
            # Stop at another clipping layer
            if getattr(layer, "clipping", False):
                break
            base_layers.append(layer)

        if not base_layers:
            return None

        print(f"  [CLIPPING] Found {len(base_layers)} base layers for clipping")

        # Generate SVG paths from base layers
        svg_paths = []

        for base_layer in base_layers:
            # Calculate relative position to clipping layer
            rel_x = base_layer.left - layer_offset_x
            rel_y = base_layer.top - layer_offset_y

            # For rectangles, generate simple rect path
            if isinstance(base_layer, ShapeLayer) or hasattr(base_layer, 'width'):
                w = base_layer.width
                h = base_layer.height

                # Simple rectangle path (relative to clipping layer origin)
                rect_path = f"M{rel_x},{rel_y} L{rel_x + w},{rel_y} L{rel_x + w},{rel_y + h} L{rel_x},{rel_y + h} Z"
                svg_paths.append(rect_path)
                print(f"    [CLIPPING] Base layer '{base_layer.name}': rect at ({rel_x}, {rel_y}) size {w}x{h}")

        if not svg_paths:
            return None

        # Combine all paths
        combined_path = " ".join(svg_paths)
        print(f"  [CLIPPING] Generated combined clipPath with {len(svg_paths)} shapes")

        return combined_path

    except Exception as e:
        print(f"  [CLIPPING] Error generating clipping base path: {e}")
        import traceback
        traceback.print_exc()
        return None


def get_layer_type(layer) -> str | None:
    """Determine the canvas layer type for a PSD layer."""
    if isinstance(layer, TypeLayer):
        return LAYER_TYPE_TEXT

    if isinstance(layer, (PixelLayer, SmartObjectLayer)):
        return LAYER_TYPE_IMAGE

    if isinstance(layer, ShapeLayer):
        # Check if this is a complex shape (rotated, combined paths, etc.)
        # Complex shapes have "Invalidated" origination and should be rendered as images
        if _is_complex_shape(layer):
            print(f"  [COMPLEX_SHAPE] Layer '{layer.name}' has complex path - treating as image")
            return LAYER_TYPE_IMAGE
        # Simple shapes (Rectangle, Ellipse) can be rendered as shapes
        shape_type = _detect_shape_type(layer)
        return shape_type

    if isinstance(layer, SolidColorFill):
        # Solid color fill layers are treated as rectangles
        return LAYER_TYPE_RECTANGLE

    return None


def _is_complex_shape(layer: ShapeLayer) -> bool:
    """
    Check if a shape layer is complex (rotated, combined paths, etc.)
    Complex shapes have "Invalidated" origination entries and cannot be
    accurately represented as simple rectangles/ellipses.
    """
    try:
        if hasattr(layer, 'origination') and layer.origination:
            for orig in layer.origination:
                orig_type = type(orig).__name__
                if orig_type == 'Invalidated':
                    return True
        return False
    except Exception:
        return False


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


def map_layer(layer, layer_index: int, psd_width: int, psd_height: int, is_group: bool = False, is_root: bool = True, sibling_layers: list = None, psd_dpi: int = 72) -> dict | None:
    """
    Map a PSD layer to a canvas layer structure.

    Args:
        layer: psd-tools layer object
        layer_index: z-index position
        psd_width: PSD document width
        psd_height: PSD document height
        is_group: whether this layer is a group
        is_root: whether this layer is at the root level (not inside a group)
        sibling_layers: list of sibling layers (for clipping mask detection)
        psd_dpi: PSD document resolution in DPI (for font size scaling)

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

    # Get opacity (0.0 - 1.0)
    opacity = layer.opacity / 255.0 if hasattr(layer, "opacity") else 1.0

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
        "opacity": opacity,  # Pass opacity to frontend
        "properties": {},
        "image_data": None,
        "mask_data": None,  # Layer mask image data
        "warnings": [],
    }

    # Check for unsupported blend modes
    if hasattr(layer, "blend_mode") and layer.blend_mode != BlendMode.NORMAL:
        base_data["warnings"].append(f"Blend mode '{layer.blend_mode.name}' not supported, using normal")

    # Map specific layer type properties
    if layer_type == LAYER_TYPE_TEXT:
        _map_text_layer(layer, base_data, opacity, psd_dpi)
    elif layer_type == LAYER_TYPE_IMAGE:
        _map_image_layer(layer, base_data, opacity, sibling_layers)
    elif isinstance(layer, SolidColorFill):
        _map_solid_color_fill_layer(layer, base_data, opacity)
    elif layer_type in (LAYER_TYPE_RECTANGLE, LAYER_TYPE_ELLIPSE):
        _map_shape_layer(layer, base_data, opacity)

    return base_data


def _map_text_layer(layer: TypeLayer, data: dict, opacity: float, psd_dpi: int = 72):
    """Map text layer properties.

    Args:
        layer: psd-tools TypeLayer object
        data: Layer data dict to populate
        opacity: Layer opacity
        psd_dpi: Document DPI for font size scaling (default 72)
    """
    try:
        text = layer.text or ""
        print(f"  [TEXT] Layer '{layer.name}': raw text = {repr(text[:100])}..." if len(text) > 100 else f"  [TEXT] Layer '{layer.name}': raw text = {repr(text)}")
        # Normalize line endings - Photoshop uses \r for paragraph breaks
        text = text.replace('\r\n', '\n').replace('\r', '\n')

        # Try to extract text bounding box from engine_dict
        # Photoshop stores paragraph text box dimensions in Rendered.Shapes.Children[0].Cookie.Photoshop.BoxBounds
        text_box_bounds = None
        is_point_text = True  # Assume point text (no bounding box) by default
        try:
            if hasattr(layer, 'engine_dict') and layer.engine_dict:
                engine = layer.engine_dict
                print(f"  [TEXT DEBUG] Layer '{layer.name}': engine_dict keys = {list(engine.keys()) if hasattr(engine, 'keys') else 'N/A'}")
                if "Rendered" in engine:
                    rendered = engine["Rendered"]
                    print(f"  [TEXT DEBUG] Rendered keys = {list(rendered.keys()) if hasattr(rendered, 'keys') else 'N/A'}")
                    if "Shapes" in rendered:
                        shapes = rendered["Shapes"]
                        print(f"  [TEXT DEBUG] Shapes keys = {list(shapes.keys()) if hasattr(shapes, 'keys') else 'N/A'}")
                        if "Children" in shapes and shapes["Children"]:
                            children = shapes["Children"]
                            print(f"  [TEXT DEBUG] Children count = {len(children)}")
                            if len(children) > 0:
                                first_shape = children[0]
                                print(f"  [TEXT DEBUG] first_shape keys = {list(first_shape.keys()) if hasattr(first_shape, 'keys') else 'N/A'}")
                                if "Cookie" in first_shape:
                                    cookie = first_shape["Cookie"]
                                    print(f"  [TEXT DEBUG] Cookie keys = {list(cookie.keys()) if hasattr(cookie, 'keys') else 'N/A'}")
                                    if "Photoshop" in cookie:
                                        ps_data = cookie["Photoshop"]
                                        print(f"  [TEXT DEBUG] Photoshop keys = {list(ps_data.keys()) if hasattr(ps_data, 'keys') else 'N/A'}")
                                        if "BoxBounds" in ps_data:
                                            bounds = ps_data["BoxBounds"]
                                            print(f"  [TEXT DEBUG] BoxBounds raw = {list(bounds)}")
                                            # BoxBounds is [left, top, right, bottom] - NOT width/height!
                                            if len(bounds) >= 4:
                                                left = float(bounds[0])
                                                top = float(bounds[1])
                                                right = float(bounds[2])
                                                bottom = float(bounds[3])
                                                text_box_bounds = {
                                                    "x": left,
                                                    "y": top,
                                                    "width": right - left,
                                                    "height": bottom - top,
                                                }
                                                is_point_text = False
                                                print(f"  [TEXT BOX] BoxBounds: left={left}, top={top}, right={right}, bottom={bottom} -> width={right-left}, height={bottom-top}")
                                        else:
                                            print(f"  [TEXT DEBUG] No BoxBounds in Photoshop - this is POINT TEXT (no text box)")
        except Exception as e:
            print(f"  [TEXT DEBUG] Exception during BoxBounds extraction: {e}")

        # Update layer dimensions with text box bounds if available
        # This gives us the actual paragraph text box size, not just rendered text bounds
        # IMPORTANT: BoxBounds is in UNTRANSFORMED space, but we need the TRANSFORMED size for rendering
        # The transform is already applied to font size, so we need to also apply it to the box dimensions
        if text_box_bounds and text_box_bounds["width"] > 0:
            # Apply transform scale to BoxBounds to get final rendered dimensions
            transform_scale_x = 1.0
            transform_scale_y = 1.0
            if hasattr(layer, 'transform') and layer.transform and len(layer.transform) >= 4:
                import math
                xx, xy, yx, yy = layer.transform[0], layer.transform[1], layer.transform[2], layer.transform[3]
                transform_scale_x = math.sqrt(xx * xx + xy * xy)
                transform_scale_y = math.sqrt(yx * yx + yy * yy)

            scaled_width = text_box_bounds["width"] * transform_scale_x
            scaled_height = text_box_bounds["height"] * transform_scale_y
            data["width"] = scaled_width
            data["height"] = scaled_height
            print(f"  [TEXT BOX] Scaled dimensions: {text_box_bounds['width']:.1f} * {transform_scale_x:.3f} = {scaled_width:.1f}w, {text_box_bounds['height']:.1f} * {transform_scale_y:.3f} = {scaled_height:.1f}h")

        # Get text engine data for detailed properties
        font_family = "Montserrat"  # Default
        font_size = 24
        font_weight = "normal"
        font_style = "normal"
        text_color = "#000000"
        text_align = "left"
        line_height = 1.2
        original_font_name = None  # Original font name from PSD
        is_dynamic_font = False    # Whether frontend should try dynamic loading

        # Debug: Log layer attributes that might contain transform info
        print(f"  [FONT DEBUG] Layer '{layer.name}':")
        print(f"    - has transform: {hasattr(layer, 'transform')}")
        if hasattr(layer, 'transform'):
            print(f"    - transform value: {layer.transform}")
        print(f"    - layer bounds: ({layer.left}, {layer.top}) size {layer.width}x{layer.height}")

        # Try to get font info from text engine
        if hasattr(layer, "engine_dict") and layer.engine_dict:
            engine = layer.engine_dict

            # Debug: Check for DocumentResources which may contain transform info
            if "DocumentResources" in engine:
                doc_res = engine["DocumentResources"]
                print(f"    - DocumentResources keys: {list(doc_res.keys()) if hasattr(doc_res, 'keys') else 'N/A'}")

            # Build font list from resource_dict (psd-tools uses this)
            font_list = []
            try:
                if hasattr(layer, 'resource_dict') and layer.resource_dict:
                    res_dict = layer.resource_dict
                    if "FontSet" in res_dict:
                        for font_entry in res_dict["FontSet"]:
                            # Access Name directly
                            # psd_tools.psd.engine_data.String has .value property
                            font_name = font_entry["Name"] if "Name" in font_entry else ""
                            # Get the actual string value
                            if hasattr(font_name, 'value'):
                                font_list.append(font_name.value)
                            else:
                                font_list.append(str(font_name).strip("'\""))
            except Exception:
                pass  # Font list extraction failed, will use defaults

            # Get font info from style runs
            if "StyleRun" in engine:
                style_run = engine["StyleRun"]
                # psd-tools Dict may not have .get() method, access directly
                run_array = style_run["RunArray"] if "RunArray" in style_run else []

                # Warn if multiple style runs (mixed formatting not fully supported)
                if len(run_array) > 1:
                    data["warnings"].append(
                        "Text has mixed styles (partial bold/italic). Only primary style will be used."
                    )

                # Use first run as primary style
                primary_run = run_array[0] if run_array else None

                if primary_run and "StyleSheet" in primary_run:
                    stylesheet = primary_run["StyleSheet"]
                    style = stylesheet["StyleSheetData"] if "StyleSheetData" in stylesheet else {}
                    print(f"  [FONT DEBUG] StyleSheetData keys: {list(style.keys()) if hasattr(style, 'keys') else 'empty/invalid'}")

                    # Font family - Font is an index into FontSet
                    if "Font" in style:
                        font_index_raw = style["Font"]
                        # Convert psd_tools Integer to native int
                        font_index = int(font_index_raw) if hasattr(font_index_raw, '__int__') else font_index_raw
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
                            # Store original font info for frontend
                            original_font_name = font_info["original"]
                            is_dynamic_font = font_info.get("is_dynamic", False)

                    # Font size
                    if "FontSize" in style:
                        raw_font_size = float(style["FontSize"])

                        # Get scale factors from style
                        vertical_scale = style["VerticalScale"] if "VerticalScale" in style else 1.0

                        # PSD stores font size in points
                        # Convert to pixels based on document DPI
                        # Formula: pixels = points * (dpi / 72)
                        # At 72 DPI: 1pt = 1px
                        # At 300 DPI: 1pt = 4.17px
                        dpi_scale = psd_dpi / 72.0 if psd_dpi > 0 else 1.0

                        # Check for layer transform matrix (scaling applied to text)
                        # TypeLayer.transform is a 2D affine matrix: [xx, xy, yx, yy, tx, ty]
                        # xx and yy contain the scale factors (when no rotation)
                        transform_scale = 1.0
                        if hasattr(layer, 'transform') and layer.transform:
                            transform = layer.transform
                            print(f"  [FONT] Layer transform: {transform}")
                            if len(transform) >= 4:
                                import math
                                # Extract scale from transform matrix
                                # For affine matrix [xx, xy, yx, yy, tx, ty]:
                                # scale_x = sqrt(xx^2 + yx^2)
                                # scale_y = sqrt(xy^2 + yy^2)
                                xx, xy, yx, yy = transform[0], transform[1], transform[2], transform[3]
                                scale_x = math.sqrt(xx * xx + xy * xy)
                                scale_y = math.sqrt(yx * yx + yy * yy)
                                # Use average of x/y scale for font size
                                # (typically they're the same for uniform scaling)
                                transform_scale = (scale_x + scale_y) / 2.0
                                print(f"  [FONT] Transform scale: xx={xx:.3f}, yy={yy:.3f} -> scale_x={scale_x:.3f}, scale_y={scale_y:.3f}, avg={transform_scale:.3f}")

                        # Ensure all values are native Python floats
                        raw_font_size = float(raw_font_size)
                        vertical_scale = float(vertical_scale)
                        print(f"  [FONT DEBUG] raw_font_size={raw_font_size}, vertical_scale={vertical_scale}, dpi_scale={dpi_scale}, transform_scale={transform_scale}")
                        font_size = raw_font_size * vertical_scale * dpi_scale * transform_scale
                        print(f"  [FONT] Font size: {raw_font_size}pt * {dpi_scale:.2f} (DPI) * {transform_scale:.2f} (transform) = {font_size:.1f}px")

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

                    # FontCaps - text case transformation
                    # 0 = Normal, 1 = Small Caps, 2 = All Caps
                    if "FontCaps" in style:
                        font_caps = int(style["FontCaps"])
                        print(f"  [TEXT] FontCaps: {font_caps}")
                        if font_caps == 2:
                            # All Caps - transform text to uppercase
                            text = text.upper()
                            print(f"  [TEXT] Applied ALL CAPS transformation")
                        elif font_caps == 1:
                            # Small Caps - approximate with uppercase (CSS small-caps is complex)
                            text = text.upper()
                            print(f"  [TEXT] Applied SMALL CAPS (approximated as uppercase)")

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
        # POINT TEXT should NOT have fixedWidth - it displays as single line
        # PARAGRAPH TEXT (with BoxBounds) should have fixedWidth for word wrapping
        has_fixed_width = not is_point_text and data.get("width", 0) > 0
        print(f"  [TEXT] Layer '{layer.name}': is_point_text={is_point_text}, has_fixed_width={has_fixed_width}")

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
            "fixedWidth": has_fixed_width,  # Only true for paragraph text (with BoxBounds)
            "originalFontName": original_font_name,  # Original font name from PSD
            "isDynamicFont": is_dynamic_font,  # Whether to try dynamic Google Fonts loading
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


def _map_image_layer(layer, data: dict, opacity: float, sibling_layers: list = None):
    """Map image/pixel layer properties.

    Args:
        layer: The PSD layer
        data: Layer data dict to populate
        opacity: Layer opacity
        sibling_layers: Optional list of sibling layers (for clipping mask detection)
    """
    try:
        clip_path = None
        smart_object_source_id = None
        layer_name = data.get('name', 'unknown')
        is_clipping = getattr(layer, "clipping", False)

        # Check if this is a SmartObjectLayer and get unique_id for linked asset support
        if isinstance(layer, SmartObjectLayer):
            if hasattr(layer, "smart_object") and layer.smart_object:
                so = layer.smart_object
                if hasattr(so, "unique_id") and so.unique_id:
                    smart_object_source_id = so.unique_id
                    print(f"  [SMART_OBJECT] Layer '{layer_name}' has unique_id: {smart_object_source_id}")

            # Analyze Smart Object transformations (flip, rotation, scale)
            transform_info = analyze_smart_object_transform(layer)
            if transform_info:
                # Apply flip through negative scale values
                if transform_info.get("flip_x"):
                    data["scale_x"] = -1.0
                    print(f"  [TRANSFORM] Layer '{layer_name}': Horizontal flip detected")
                if transform_info.get("flip_y"):
                    data["scale_y"] = -1.0
                    print(f"  [TRANSFORM] Layer '{layer_name}': Vertical flip detected")

        # Check for CLIPPING MASK first
        # If layer is clipping, collect full base layer info (with rotation, opacity)
        clipping_bases = []
        if is_clipping and sibling_layers:
            print(f"  [CLIPPING] Layer '{layer_name}' is a clipping layer")

            # Collect full info about each base layer
            clipping_bases = collect_clipping_base_layers(
                layer,
                sibling_layers,
                layer.left,
                layer.top
            )

            # Also generate simple clipPath for fallback (without rotation)
            clipping_clip_path = generate_clipping_base_path(
                layer,
                sibling_layers,
                layer.left,
                layer.top
            )
            if clipping_clip_path:
                clip_path = clipping_clip_path
                print(f"  [CLIPPING] Generated clipPath from base layers for '{layer_name}'")

        # Extract layer mask (raster mask) - this is separate from vector mask/clipPath
        layer_mask_data = extract_layer_mask(layer)
        has_raster_mask = layer_mask_data is not None

        if has_raster_mask:
            print(f"  [LAYER_MASK] Layer '{layer_name}': Found raster mask {layer_mask_data['width']}x{layer_mask_data['height']}")
            data["mask_data"] = layer_mask_data

        # If no clipping clipPath, try to extract vector mask
        if clip_path is None:
            potential_clip_path = extract_mask_from_layer(layer, data["width"], data["height"])

            if potential_clip_path:
                # Check if this is a "closed" shape (like ellipse) vs "open" decorative element
                import numpy as np

                try:
                    comp = layer.composite()
                    if comp and comp.mode == 'RGBA':
                        arr = np.array(comp)
                        alpha = arr[:, :, 3]
                        h, w = alpha.shape

                        # Check if bottom edge has significant opaque content
                        bottom_edge = alpha[h-5:h, :]
                        bottom_opaque_ratio = np.sum(bottom_edge >= 128) / bottom_edge.size

                        if bottom_opaque_ratio > 0.30:
                            print(f"  Skipping clipPath for '{layer_name}' - decorative element")
                        else:
                            clip_path = potential_clip_path
                            print(f"  Using clipPath for '{layer_name}' - image placeholder")
                    else:
                        clip_path = potential_clip_path
                except Exception as e:
                    print(f"  Could not check bottom edge, using clipPath: {e}")
                    clip_path = potential_clip_path

        # Extract the image WITHOUT mask applied
        # Mask will be applied dynamically in frontend - this allows user to replace image
        # and have the same mask applied to the new image
        if isinstance(layer, SmartObjectLayer):
            # For SmartObjects: use extract_smart_object_source to get CLEAN original image
            # This bypasses any layer masks or transformations
            image_data = extract_smart_object_source(layer)
            if image_data:
                print(f"  [IMAGE] Extracted clean source image for SmartObject '{layer_name}'")
            else:
                # Fallback: try without mask
                image_data = extract_layer_image_without_mask(layer)
                if not image_data:
                    image_data = extract_layer_image(layer, apply_mask=False)
        else:
            # For PixelLayers: extract without baked-in mask
            image_data = extract_layer_image_without_mask(layer)
            if not image_data:
                image_data = extract_layer_image(layer, apply_mask=False)

        if image_data:
            data["image_data"] = image_data
            data["properties"] = {
                "src": None,
                "fit": "cover",
                "clipPath": clip_path,
                "clippingBases": clipping_bases if clipping_bases else None,  # Full base layer info for complex clipping
                "smartObjectSourceId": smart_object_source_id,
                "hasMask": has_raster_mask,
                "isClipping": is_clipping,  # Mark as clipping layer
            }
            print(f"  [DEBUG] Image layer '{layer_name}': clipPath={'YES' if clip_path else 'NO'}, clippingBases={len(clipping_bases) if clipping_bases else 0}, hasMask={has_raster_mask}, isClipping={is_clipping}")
        else:
            data["warnings"].append("Could not extract image from layer")
            data["properties"] = {
                "src": None,
                "fit": "cover",
                "clipPath": clip_path,
                "clippingBases": clipping_bases if clipping_bases else None,
                "smartObjectSourceId": smart_object_source_id,
                "hasMask": has_raster_mask,
                "isClipping": is_clipping,
            }
            print(f"  [DEBUG] Image layer '{layer_name}' (no image_data): clipPath={'YES' if clip_path else 'NO'}, clippingBases={len(clipping_bases) if clipping_bases else 0}, hasMask={has_raster_mask}, isClipping={is_clipping}")

    except Exception as e:
        import traceback
        traceback.print_exc()
        data["warnings"].append(f"Failed to extract image: {str(e)}")
        data["properties"] = {
            "src": None,
            "fit": "cover",
            "clipPath": None,
            "clippingBases": None,
            "hasMask": False,
            "isClipping": False,
        }


def _map_shape_layer(layer: ShapeLayer, data: dict, opacity: float):
    """Map shape layer properties."""
    from psd_tools.constants import Tag

    try:
        fill_color = "#CCCCCC"
        stroke_color = None
        stroke_width = 0
        corner_radius = 0

        # Priority 1: Try to get fill color from SOLID_COLOR_SHEET_SETTING
        # This is the most reliable source for shape fill colors
        if hasattr(layer, "tagged_blocks"):
            try:
                solid_color_data = layer.tagged_blocks.get_data(Tag.SOLID_COLOR_SHEET_SETTING)
                if solid_color_data:
                    # Data structure: {b'Clr ': {b'Rd  ': R, b'Grn ': G, b'Bl  ': B}}
                    color_data = solid_color_data.get(b'Clr ')
                    if color_data:
                        r = int(color_data.get(b'Rd  ', 0))
                        g = int(color_data.get(b'Grn ', 0))
                        b = int(color_data.get(b'Bl  ', 0))
                        fill_color = f"#{r:02x}{g:02x}{b:02x}"
                        print(f"  [SHAPE COLOR] Layer '{layer.name}': extracted from SOLID_COLOR_SHEET_SETTING: {fill_color}")
            except Exception as e:
                print(f"  [SHAPE COLOR] Could not extract from SOLID_COLOR_SHEET_SETTING: {e}")

        # Priority 2: If still default, try composite approach (for complex shapes)
        if fill_color == "#CCCCCC":
            try:
                composite = layer.composite()
                if composite:
                    composite_rgba = composite.convert("RGBA")
                    w, h = composite_rgba.size
                    if w > 0 and h > 0:
                        center_pixel = composite_rgba.getpixel((w // 2, h // 2))
                        if center_pixel and len(center_pixel) >= 4 and center_pixel[3] > 0:
                            extracted_color = rgba_to_hex(center_pixel, fill_color)
                            if extracted_color and extracted_color != "#000000":
                                fill_color = extracted_color
                                print(f"  [SHAPE COLOR] Layer '{layer.name}': extracted from composite: {fill_color}")
            except Exception as e:
                print(f"  [SHAPE COLOR] Could not extract from composite: {e}")

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
