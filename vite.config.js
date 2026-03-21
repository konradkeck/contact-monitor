import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/analyse/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        cors: true,
        hmr: {
            host: '172.30.248.202',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
