import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/css/user.css',
                'resources/js/main.js',
                'resources/js/user-shell.js',
                'resources/js/admin-shell.js',
                'resources/js/pages/booking.js',
                'resources/js/pages/profile.js',
                'resources/js/admin-navigation.js',
                'resources/js/admin-ticket-scanner-bootstrap.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
