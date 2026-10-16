import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    envDir: process.env.RESPONDESK_RELEASE_BUILD === '1' ? 'installer/build-env' : '.',
    server: {
        host: '127.0.0.1', // Avoid IPv6 [::1] so CSP script-src matches without parsing issues
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
});
