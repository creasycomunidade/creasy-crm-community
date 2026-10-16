import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        globals:     true,
        setupFiles:  ['./resources/js/__tests__/setup.jsx'],
        include:     ['resources/js/__tests__/**/*.{test,spec}.{js,jsx}'],
        coverage: {
            reporter: ['text', 'lcov'],
            include:  ['resources/js/**/*.{js,jsx}'],
            exclude:  ['resources/js/__tests__/**'],
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
