import './bootstrap';

import { createApp } from 'vue';
import App from './App.vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const token = localStorage.getItem('auth_token');

window.Echo = new Echo({
    broadcaster: 'reverb',

    key: import.meta.env.VITE_REVERB_APP_KEY,

    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),

    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',

    enabledTransports: ['ws', 'wss'],

    authEndpoint: '/broadcasting/auth',

    auth: {
        headers: {
            Authorization: token ? `Bearer ${token}` : '',
            Accept: 'application/json',
        },
    },
});

createApp(App).mount('#app');