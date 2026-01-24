import { createI18n } from 'vue-i18n';
import pl from './locales/pl.json';
import en from './locales/en.json';

// Get saved locale from localStorage or default to 'pl'
const savedLocale = localStorage.getItem('locale') || 'pl';

const i18n = createI18n({
    legacy: false,
    locale: savedLocale,
    fallbackLocale: 'en',
    messages: {
        pl,
        en,
    },
});

// Function to change locale and persist to localStorage
export function setLocale(locale) {
    if (['pl', 'en'].includes(locale)) {
        i18n.global.locale.value = locale;
        localStorage.setItem('locale', locale);
        document.documentElement.lang = locale;
    }
}

export default i18n;
