import { createI18n } from 'vue-i18n';
import pl from './locales/pl.json';
import en from './locales/en.json';

const i18n = createI18n({
    legacy: false,
    locale: 'pl', // Default language
    fallbackLocale: 'en',
    messages: {
        pl,
        en,
    },
});

export default i18n;
