import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/css/admin/dashboard-redesign.css',
                'resources/css/admin/pages/banners.css',
                'resources/css/admin/pages/combos.css',
                'resources/css/admin/pages/movies.css',
                'resources/css/admin/pages/orders.css',
                'resources/css/admin/pages/posts.css',
                'resources/css/admin/pages/roles-permissions.css',
                'resources/css/admin/pages/seat-layout-templates.css',
                'resources/css/admin/pages/seat-layout.css',
                'resources/css/admin/pages/showtimes.css',
                'resources/css/admin/pages/stats.css',
                'resources/css/admin/pages/users.css',
                'resources/css/staff/order-print.css',
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
