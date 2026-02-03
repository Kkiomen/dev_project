import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Add locale header and rewrite API URLs for panel session auth
window.axios.interceptors.request.use((config) => {
    // Try to get locale from localStorage (set by vue-i18n) or default to 'pl'
    const locale = localStorage.getItem('locale') || document.documentElement.lang || 'pl';
    config.headers['X-Locale'] = locale;

    // Rewrite API URLs to use panel prefix (session auth instead of Sanctum tokens)
    if (config.url) {
        if (config.url.startsWith('/api/v1/')) {
            config.url = config.url.replace('/api/v1/', '/api/panel/');
        } else if (config.url.startsWith('/api/admin/')) {
            config.url = config.url.replace('/api/admin/', '/api/panel/admin/');
        } else if (config.url.startsWith('/api/user')) {
            config.url = config.url.replace('/api/user', '/api/panel/user');
        }
    }

    return config;
});

// Initialize Laravel Echo for WebSocket connections
if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => {
            return {
                authorize: (socketId, callback) => {
                    axios.post('/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name,
                    })
                    .then(response => {
                        callback(null, response.data);
                    })
                    .catch(error => {
                        callback(error);
                    });
                },
            };
        },
    });
}
