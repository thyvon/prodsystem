import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
            '@': path.resolve(__dirname, 'resources/js'),
            '@public': path.resolve(__dirname, 'public'),
        },
    },
    build: {
        // Increase limit to avoid chunk size warnings
        chunkSizeWarningLimit: 1000, // 1MB
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Split vendor libraries into separate chunks
                    if (id.includes('node_modules')) {
                        if (id.includes('vue')) return 'vendor_vue';
                        if (id.includes('@inertiajs')) return 'vendor_inertia';
                        return 'vendor';
                    }
                },
            },
        },
    },
    // server: {
    //     // Optional: improve hot reload for Laravel + Docker
    //     host: '0.0.0.0',
    //     port: 5173,
    //     strictPort: true,
    // },
});
