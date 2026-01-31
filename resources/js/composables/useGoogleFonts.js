import { ref, computed } from 'vue';

// Google Fonts API key (free tier)
const GOOGLE_FONTS_API_URL = 'https://fonts.googleapis.com/css2';

// Popular fonts list (curated for better UX in font picker)
const POPULAR_FONTS = [
    'Roboto',
    'Open Sans',
    'Lato',
    'Montserrat',
    'Poppins',
    'Source Sans Pro',
    'Raleway',
    'Nunito',
    'Ubuntu',
    'Playfair Display',
    'Merriweather',
    'PT Sans',
    'Oswald',
    'Inter',
    'Rubik',
    'Work Sans',
    'Nunito Sans',
    'Quicksand',
    'Mukta',
    'Titillium Web',
    'Fira Sans',
    'Karla',
    'Barlow',
    'Libre Baskerville',
    'Bebas Neue',
    'Dancing Script',
    'Pacifico',
    'Lobster',
    'Caveat',
    'Permanent Marker',
    'Satisfy',
    'Shadows Into Light',
    'Comfortaa',
    'Abril Fatface',
    'Anton',
    'Russo One',
    'Righteous',
    'Bangers',
    'Fredoka One',
    'Bungee',
    // Additional popular fonts for broader coverage
    'M PLUS Rounded 1c',
    'M PLUS 1p',
    'Noto Sans JP',
    'Noto Serif JP',
    'DM Sans',
    'Space Grotesk',
    'Plus Jakarta Sans',
    'Outfit',
    'Sora',
    'Figtree',
];

// System fonts fallback
const SYSTEM_FONTS = [
    'Arial',
    'Helvetica',
    'Times New Roman',
    'Georgia',
    'Verdana',
    'Courier New',
    'Impact',
    'Comic Sans MS',
];

// Default fallback font when a font cannot be loaded
const DEFAULT_FALLBACK_FONT = 'Montserrat';

// State
const loadedFonts = ref(new Set(SYSTEM_FONTS));
const loadingFonts = ref(new Set());
const failedFonts = ref(new Set()); // Track fonts that failed to load

/**
 * Load a Google Font dynamically.
 * Unlike before, this now attempts to load ANY font from Google Fonts,
 * not just fonts from a predefined list (like Photopea does).
 *
 * @param {string} fontFamily - The font family name to load
 * @param {string[]} weights - Font weights to load (default: ['400', '700'])
 * @returns {Promise<boolean>} - True if font loaded successfully, false otherwise
 */
const loadFont = async (fontFamily, weights = ['400', '700']) => {
    // Skip empty or invalid font names
    if (!fontFamily || typeof fontFamily !== 'string') {
        return false;
    }

    // Already loaded
    if (loadedFonts.value.has(fontFamily)) {
        return true;
    }

    // Previously failed to load - don't retry
    if (failedFonts.value.has(fontFamily)) {
        return false;
    }

    // System font - already available
    if (SYSTEM_FONTS.includes(fontFamily)) {
        loadedFonts.value.add(fontFamily);
        return true;
    }

    // Currently loading - wait for it
    if (loadingFonts.value.has(fontFamily)) {
        return new Promise((resolve) => {
            const checkLoaded = setInterval(() => {
                if (loadedFonts.value.has(fontFamily)) {
                    clearInterval(checkLoaded);
                    resolve(true);
                } else if (failedFonts.value.has(fontFamily)) {
                    clearInterval(checkLoaded);
                    resolve(false);
                }
            }, 100);

            // Timeout after 10 seconds
            setTimeout(() => {
                clearInterval(checkLoaded);
                resolve(false);
            }, 10000);
        });
    }

    loadingFonts.value.add(fontFamily);

    try {
        // Create the Google Fonts URL
        const weightsString = weights.join(';');
        const fontUrl = `${GOOGLE_FONTS_API_URL}?family=${encodeURIComponent(fontFamily)}:wght@${weightsString}&display=swap`;

        // Check if link already exists
        const existingLink = document.querySelector(`link[href*="${encodeURIComponent(fontFamily)}"]`);
        if (existingLink) {
            loadedFonts.value.add(fontFamily);
            loadingFonts.value.delete(fontFamily);
            return true;
        }

        // Create and append link element
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = fontUrl;

        return new Promise((resolve) => {
            link.onload = () => {
                loadedFonts.value.add(fontFamily);
                loadingFonts.value.delete(fontFamily);
                resolve(true);
            };
            link.onerror = () => {
                loadingFonts.value.delete(fontFamily);
                failedFonts.value.add(fontFamily);
                console.warn(`Font "${fontFamily}" not available on Google Fonts. Using fallback.`);
                resolve(false);
            };
            document.head.appendChild(link);
        });
    } catch (error) {
        loadingFonts.value.delete(fontFamily);
        failedFonts.value.add(fontFamily);
        console.warn(`Error loading font "${fontFamily}":`, error.message);
        return false;
    }
};

/**
 * Try to load a font with automatic fallback.
 * If the primary font fails, loads the fallback font instead.
 *
 * @param {string} fontFamily - The font family name to load
 * @param {string} fallbackFont - Fallback font if primary fails (default: Montserrat)
 * @returns {Promise<string>} - The font family that was successfully loaded
 */
const loadFontWithFallback = async (fontFamily, fallbackFont = DEFAULT_FALLBACK_FONT) => {
    const success = await loadFont(fontFamily);
    if (success) {
        return fontFamily;
    }

    // Try fallback
    await loadFont(fallbackFont);
    return fallbackFont;
};

/**
 * Check if a font is available (loaded or system font)
 */
const isFontAvailable = (fontFamily) => {
    return loadedFonts.value.has(fontFamily) || SYSTEM_FONTS.includes(fontFamily);
};

/**
 * Check if a font failed to load
 */
const isFontFailed = (fontFamily) => {
    return failedFonts.value.has(fontFamily);
};

/**
 * Load multiple fonts at once
 */
const loadFonts = async (fontFamilies) => {
    return Promise.all(fontFamilies.map((font) => loadFont(font)));
};

/**
 * Check if a font is loaded
 */
const isFontLoaded = (fontFamily) => {
    return loadedFonts.value.has(fontFamily);
};

/**
 * Check if a font is loading
 */
const isFontLoading = (fontFamily) => {
    return loadingFonts.value.has(fontFamily);
};

/**
 * Get all available fonts (system + popular Google Fonts)
 */
const getAllFonts = () => {
    return [...SYSTEM_FONTS, ...POPULAR_FONTS];
};

/**
 * Search fonts by name
 */
const searchFonts = (query) => {
    if (!query) return getAllFonts();

    const lowerQuery = query.toLowerCase();
    return getAllFonts().filter((font) => font.toLowerCase().includes(lowerQuery));
};

export function useGoogleFonts() {
    return {
        loadFont,
        loadFonts,
        loadFontWithFallback,
        isFontLoaded,
        isFontLoading,
        isFontAvailable,
        isFontFailed,
        getAllFonts,
        searchFonts,
        loadedFonts: computed(() => loadedFonts.value),
        loadingFonts: computed(() => loadingFonts.value),
        failedFonts: computed(() => failedFonts.value),
        popularFonts: POPULAR_FONTS,
        systemFonts: SYSTEM_FONTS,
        defaultFallbackFont: DEFAULT_FALLBACK_FONT,
    };
}
