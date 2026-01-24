import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Add locale header to all requests
window.axios.interceptors.request.use((config) => {
    // Try to get locale from localStorage (set by vue-i18n) or default to 'pl'
    const locale = localStorage.getItem('locale') || document.documentElement.lang || 'pl';
    config.headers['X-Locale'] = locale;
    return config;
});
