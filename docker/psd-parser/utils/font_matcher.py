"""
Font matcher utility for matching PSD fonts to Google Fonts.
Uses fuzzy matching with rapidfuzz library.
"""

from rapidfuzz import fuzz, process

# Popular Google Fonts list for matching
GOOGLE_FONTS = [
    "Roboto",
    "Open Sans",
    "Lato",
    "Montserrat",
    "Oswald",
    "Source Sans Pro",
    "Raleway",
    "PT Sans",
    "Merriweather",
    "Nunito",
    "Playfair Display",
    "Poppins",
    "Ubuntu",
    "Rubik",
    "Work Sans",
    "Noto Sans",
    "Inter",
    "Quicksand",
    "Karla",
    "Fira Sans",
    "Barlow",
    "Mulish",
    "Josefin Sans",
    "Cabin",
    "DM Sans",
    "Manrope",
    "Arimo",
    "Teko",
    "Libre Franklin",
    "Archivo",
    "IBM Plex Sans",
    "Outfit",
    "Sora",
    "Space Grotesk",
    "Lexend",
    "Plus Jakarta Sans",
    "Red Hat Display",
    "Figtree",
    "Gabarito",
    "Geist",
    # Serif fonts
    "Roboto Slab",
    "Lora",
    "PT Serif",
    "Noto Serif",
    "Libre Baskerville",
    "Cormorant Garamond",
    "Crimson Text",
    "Bitter",
    "Source Serif Pro",
    "Zilla Slab",
    "IBM Plex Serif",
    "Playfair Display",
    "Spectral",
    "EB Garamond",
    "Vollkorn",
    # Display / decorative fonts
    "Bebas Neue",
    "Permanent Marker",
    "Pacifico",
    "Dancing Script",
    "Lobster",
    "Caveat",
    "Satisfy",
    "Great Vibes",
    "Abril Fatface",
    "Alfa Slab One",
    "Anton",
    "Righteous",
    "Bangers",
    "Fredoka One",
    "Comfortaa",
    # Monospace fonts
    "Roboto Mono",
    "Source Code Pro",
    "Fira Code",
    "JetBrains Mono",
    "IBM Plex Mono",
    "Space Mono",
    "Ubuntu Mono",
    "Inconsolata",
]

# Common font name mappings (system fonts to Google Fonts)
FONT_MAPPINGS = {
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

DEFAULT_FONT = "Montserrat"


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

    Returns:
        dict with keys:
            - original: original PSD font name
            - matched: matched Google Font name
            - confidence: matching confidence (0-100)
            - is_fallback: whether fallback font was used
    """
    if not psd_font_name or not isinstance(psd_font_name, str):
        return {
            "original": str(psd_font_name) if psd_font_name else None,
            "matched": DEFAULT_FONT,
            "confidence": 0,
            "is_fallback": True,
        }

    normalized = normalize_font_name(psd_font_name)
    if not normalized:
        return {
            "original": psd_font_name,
            "matched": DEFAULT_FONT,
            "confidence": 0,
            "is_fallback": True,
        }

    normalized_lower = normalized.lower()

    # Check direct mappings first
    if normalized_lower in FONT_MAPPINGS:
        return {
            "original": psd_font_name,
            "matched": FONT_MAPPINGS[normalized_lower],
            "confidence": 100,
            "is_fallback": False,
        }

    # Check if already a Google Font
    for gf in GOOGLE_FONTS:
        if normalized_lower == gf.lower():
            return {
                "original": psd_font_name,
                "matched": gf,
                "confidence": 100,
                "is_fallback": False,
            }

    # Fuzzy match against Google Fonts
    result = process.extractOne(
        normalized,
        GOOGLE_FONTS,
        scorer=fuzz.token_sort_ratio,
        score_cutoff=60
    )

    if result:
        matched_font, score, _ = result
        return {
            "original": psd_font_name,
            "matched": matched_font,
            "confidence": int(score),
            "is_fallback": False,
        }

    # Use fallback
    return {
        "original": psd_font_name,
        "matched": DEFAULT_FONT,
        "confidence": 0,
        "is_fallback": True,
    }


def extract_font_weight(psd_font_name) -> str:
    """Extract font weight from PSD font name."""
    if not psd_font_name or not isinstance(psd_font_name, str):
        return "normal"

    name_lower = psd_font_name.lower()

    weight_map = {
        "thin": "100",
        "hairline": "100",
        "extralight": "200",
        "ultralight": "200",
        "light": "300",
        "regular": "normal",
        "normal": "normal",
        "medium": "500",
        "semibold": "600",
        "demibold": "600",
        "bold": "bold",
        "extrabold": "800",
        "ultrabold": "800",
        "black": "900",
        "heavy": "900",
    }

    for weight_name, weight_value in weight_map.items():
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
