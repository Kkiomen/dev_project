import { ref, computed } from 'vue';

// Google Fonts API key (free tier)
const GOOGLE_FONTS_API_URL = 'https://fonts.googleapis.com/css2';

// Popular fonts list (curated for better UX)
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

// State
const loadedFonts = ref(new Set(SYSTEM_FONTS));
const loadingFonts = ref(new Set());

/**
 * Load a Google Font dynamically
 */
const loadFont = async (fontFamily, weights = ['400', '700']) => {
    // Already loaded
    if (loadedFonts.value.has(fontFamily)) {
        return true;
    }

    // System font - already available
    if (SYSTEM_FONTS.includes(fontFamily)) {
        loadedFonts.value.add(fontFamily);
        return true;
    }

    // Currently loading
    if (loadingFonts.value.has(fontFamily)) {
        return new Promise((resolve) => {
            const checkLoaded = setInterval(() => {
                if (loadedFonts.value.has(fontFamily)) {
                    clearInterval(checkLoaded);
                    resolve(true);
                }
            }, 100);
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

        return new Promise((resolve, reject) => {
            link.onload = () => {
                loadedFonts.value.add(fontFamily);
                loadingFonts.value.delete(fontFamily);
                resolve(true);
            };
            link.onerror = () => {
                loadingFonts.value.delete(fontFamily);
                reject(new Error(`Failed to load font: ${fontFamily}`));
            };
            document.head.appendChild(link);
        });
    } catch (error) {
        loadingFonts.value.delete(fontFamily);
        console.error('Error loading font:', error);
        return false;
    }
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
        isFontLoaded,
        isFontLoading,
        getAllFonts,
        searchFonts,
        loadedFonts: computed(() => loadedFonts.value),
        loadingFonts: computed(() => loadingFonts.value),
        popularFonts: POPULAR_FONTS,
        systemFonts: SYSTEM_FONTS,
    };
}
