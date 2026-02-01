"""
Font matcher utility for matching PSD fonts to Google Fonts.
Uses dynamic font loading approach - preserves original font names and lets
the frontend try to load them from Google Fonts API.
"""

from rapidfuzz import fuzz, process

# System fonts that need to be mapped to Google Fonts equivalents
# (these fonts are not available on Google Fonts)
SYSTEM_FONT_MAPPINGS = {
    "arial": "Open Sans",
    "helvetica": "Inter",
    "helvetica neue": "Inter",
    "times new roman": "Libre Baskerville",
    "times": "Libre Baskerville",
    "georgia": "Lora",
    "verdana": "Open Sans",
    "tahoma": "Source Sans Pro",
    "trebuchet ms": "Fira Sans",
    "courier": "Roboto Mono",
    "courier new": "Roboto Mono",
    "impact": "Anton",
    "comic sans ms": "Caveat",
    "lucida console": "Source Code Pro",
    "lucida sans unicode": "Nunito",
    "palatino linotype": "Crimson Text",
    "book antiqua": "EB Garamond",
    "garamond": "EB Garamond",
    "century gothic": "Poppins",
    "calibri": "Open Sans",
    "cambria": "Merriweather",
    "candara": "Quicksand",
    "consolas": "Fira Code",
    "segoe ui": "Inter",
    "san francisco": "Inter",
    "sf pro": "Inter",
    "sf pro display": "Inter",
    "sf pro text": "Inter",
    "avenir": "Nunito",
    "avenir next": "Nunito",
    "futura": "Josefin Sans",
    "gill sans": "Lato",
    "proxima nova": "Montserrat",
    "gotham": "Montserrat",
    "din": "Barlow",
    "myriad pro": "Source Sans Pro",
    "brandon grotesque": "Raleway",
    "museo sans": "Work Sans",
    "museo slab": "Roboto Slab",
    "neutraface": "Poppins",
    "akzidenz grotesk": "Inter",
    "univers": "Inter",
    "frutiger": "Noto Sans",
}

# Common font name corrections (for fonts with different naming on Google Fonts)
# Maps PSD naming conventions to Google Fonts naming
FONT_NAME_CORRECTIONS = {
    "rounded mplus": "M PLUS Rounded 1c",
    "mplus rounded": "M PLUS Rounded 1c",
    "m plus rounded": "M PLUS Rounded 1c",
    "mplus": "M PLUS 1p",
    "m plus": "M PLUS 1p",
    "noto sans jp": "Noto Sans JP",
    "noto serif jp": "Noto Serif JP",
    "source han sans": "Noto Sans JP",
    "source han serif": "Noto Serif JP",
    "open sans condensed": "Open Sans Condensed",
    "pt sans narrow": "PT Sans Narrow",
    "ubuntu condensed": "Ubuntu Condensed",
    # Common naming variations without spaces
    "bebasneue": "Bebas Neue",
    "opensans": "Open Sans",
    "sourcesanspro": "Source Sans Pro",
    "playfairdisplay": "Playfair Display",
    "robotomono": "Roboto Mono",
    "firasans": "Fira Sans",
    "librebaskerville": "Libre Baskerville",
    "dancingscript": "Dancing Script",
    "permanentmarker": "Permanent Marker",
    "shadowsintolight": "Shadows Into Light",
    "fredokaone": "Fredoka One",
    "plusjakartasans": "Plus Jakarta Sans",
    "spacegrotesk": "Space Grotesk",
    "dmsans": "DM Sans",
}

DEFAULT_FONT = "Montserrat"

# Fonts that are designed as all-caps/uppercase only
# These fonts only contain uppercase glyphs, so text should be transformed to uppercase
ALL_CAPS_FONTS = {
    "bebas",
    "bebas neue",
    "bebasneue",
    "bebas kai",
    "bebas pro",
    "impact",
    "league gothic",
    "leaguegothic",
    "oswald",
    "russo one",
    "russoone",
    "anton",
    "archivo black",
    "archivo narrow",
    "archivonarrow",
    "black ops one",
    "blackopsone",
    "bungee",
    "bungee inline",
    "bungee shade",
    "staatliches",
    "graduate",
    "monoton",
    "faster one",
    "fasterone",
    "shrikhand",
    "alfa slab one",
    "alfaslabone",
    "ultra",
}


def normalize_font_name(font_name) -> str:
    """Normalize font name for matching."""
    if not font_name:
        return ""

    # Handle case where font_name is not a string (e.g., integer index)
    if not isinstance(font_name, str):
        return ""

    # Remove common suffixes
    suffixes_to_remove = [
        "-Regular", "-Bold", "-Italic", "-Light", "-Medium", "-SemiBold",
        "-ExtraBold", "-Black", "-Thin", "-ExtraLight", "Regular", "Bold",
        "Italic", "Light", "Medium", "SemiBold", "ExtraBold", "Black",
        "Thin", "ExtraLight", " Regular", " Bold", " Italic", " Light",
        " Medium", " SemiBold", " ExtraBold", " Black", " Thin", " ExtraLight",
    ]

    normalized = font_name.strip()
    for suffix in suffixes_to_remove:
        if normalized.endswith(suffix):
            normalized = normalized[:-len(suffix)].strip()

    return normalized


def match_font(psd_font_name) -> dict:
    """
    Match a PSD font name to a Google Font.

    Strategy (like Photopea):
    1. Normalize the font name (remove style suffixes)
    2. Check if it's a system font that needs mapping
    3. Apply name corrections for known fonts with different naming
    4. Otherwise, return the normalized name - let frontend try to load it from Google Fonts
    5. Fallback only if the font name is empty/invalid

    Returns:
        dict with keys:
            - original: original PSD font name
            - matched: matched Google Font name (to use)
            - confidence: matching confidence (0-100)
            - is_fallback: whether fallback font was used
            - is_dynamic: whether frontend should try dynamic loading
    """
    if not psd_font_name or not isinstance(psd_font_name, str):
        return {
            "original": str(psd_font_name) if psd_font_name else None,
            "matched": DEFAULT_FONT,
            "confidence": 0,
            "is_fallback": True,
            "is_dynamic": False,
        }

    normalized = normalize_font_name(psd_font_name)
    if not normalized:
        return {
            "original": psd_font_name,
            "matched": DEFAULT_FONT,
            "confidence": 0,
            "is_fallback": True,
            "is_dynamic": False,
        }

    normalized_lower = normalized.lower()

    # 1. Check if it's a system font that needs mapping to Google equivalent
    if normalized_lower in SYSTEM_FONT_MAPPINGS:
        return {
            "original": psd_font_name,
            "matched": SYSTEM_FONT_MAPPINGS[normalized_lower],
            "confidence": 100,
            "is_fallback": False,
            "is_dynamic": False,
        }

    # 2. Check for known name corrections (fonts with different naming on Google Fonts)
    for pattern, correct_name in FONT_NAME_CORRECTIONS.items():
        if pattern in normalized_lower:
            return {
                "original": psd_font_name,
                "matched": correct_name,
                "confidence": 95,
                "is_fallback": False,
                "is_dynamic": True,
            }

    # 3. Use the normalized font name directly - let frontend try to load it
    # Google Fonts has 1000+ fonts, so we give it a chance
    return {
        "original": psd_font_name,
        "matched": normalized,  # Use the cleaned-up name
        "confidence": 80,       # Medium confidence - frontend will verify
        "is_fallback": False,
        "is_dynamic": True,     # Signal that frontend should try dynamic loading
    }


def extract_font_weight(psd_font_name) -> str:
    """Extract font weight from PSD font name."""
    if not psd_font_name or not isinstance(psd_font_name, str):
        return "normal"

    name_lower = psd_font_name.lower()

    # Order matters: check longer/more specific variants first
    # to avoid "bold" matching before "extrabold"
    weight_checks = [
        ("extrabold", "800"),
        ("ultrabold", "800"),
        ("semibold", "600"),
        ("demibold", "600"),
        ("extralight", "200"),
        ("ultralight", "200"),
        ("hairline", "100"),
        ("thin", "100"),
        ("light", "300"),
        ("medium", "500"),
        ("regular", "normal"),
        ("normal", "normal"),
        ("bold", "bold"),  # Check after extrabold/semibold
        ("black", "900"),
        ("heavy", "900"),
    ]

    for weight_name, weight_value in weight_checks:
        if weight_name in name_lower:
            return weight_value

    return "normal"


def extract_font_style(psd_font_name) -> str:
    """Extract font style from PSD font name."""
    if not psd_font_name or not isinstance(psd_font_name, str):
        return "normal"

    name_lower = psd_font_name.lower()

    if "italic" in name_lower or "oblique" in name_lower:
        return "italic"

    return "normal"


def is_all_caps_font(font_name) -> bool:
    """
    Check if the font is an all-caps/uppercase-only font.

    These fonts are designed with only uppercase glyphs, so any text
    rendered with them will appear uppercase in Photoshop regardless
    of the source text case.

    Args:
        font_name: Font name to check

    Returns:
        True if the font is known to be all-caps only
    """
    if not font_name or not isinstance(font_name, str):
        return False

    # Normalize the font name for comparison
    normalized = font_name.lower().replace("-", " ").replace("_", " ").strip()
    # Remove common suffixes
    for suffix in [" regular", " bold", " light", " medium", " italic"]:
        if normalized.endswith(suffix):
            normalized = normalized[:-len(suffix)].strip()

    # Check exact match first
    if normalized in ALL_CAPS_FONTS:
        return True

    # Check partial match (font name contains any of the all-caps fonts)
    for caps_font in ALL_CAPS_FONTS:
        if caps_font in normalized or normalized in caps_font:
            return True

    return False
