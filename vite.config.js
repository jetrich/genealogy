import { defineConfig } from 'vite';
import laravel, { refreshPaths } from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: [
                ...refreshPaths,
                'app/Livewire/**',
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: 'localhost',  // Explicit localhost binding
        cors: true,
        fs: {
            strict: true,   // Enable strict file system access
            deny: ['.env', '.env.*', '*.{pem,crt,key}', 'id_rsa*', '*.p12', '*.log']  // Block sensitive files
        }
    }
});
