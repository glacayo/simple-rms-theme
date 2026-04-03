import { defineConfig } from 'vite';
import liveReload from 'vite-plugin-live-reload';
import path from 'path';
import { fileURLToPath } from 'url';
import { dirname } from 'path';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';

// Manual shim for __dirname when using ESM in Node.js
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig({
    plugins: [
        // Recarga el navegador al cambiar archivos PHP
        liveReload([path.resolve(__dirname, '/**/*.php')]),
        ViteImageOptimizer({
            test: /\.(jpe?g|png|gif|tiff|webp|svg|avif)$/i,
            exclude: undefined,
            include: undefined,
            includePublic: true,
            logStats: true,
            ansiColors: true,
            png: { quality: 80 },
            jpeg: { quality: 75 },
            jpg: { quality: 75 },
            webp: { lossless: true },
            avif: { lossless: true },
        }),
    ],

    root: '', // La raíz es la carpeta del tema
    base: process.env.NODE_ENV === 'production' ? './' : '/',

    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true, // Genera manifest.json para que PHP localice los assets
        rollupOptions: {
            // Definimos cada archivo que queremos que sea un "entry point"
            input: {
                main: path.resolve(__dirname, 'src/ts/main.ts'),
                'hero-section': path.resolve(__dirname, 'src/scss/sections/hero.scss'),
            },
            output: {
                entryFileNames: 'assets/[name].[hash].js',
                assetFileNames: 'assets/[name].[hash].[ext]',
            },
        },
        minify: 'terser', // Minificacion agresiva
    },

    server: {
        // Configuración para que funcione con tu servidor local (LocalWP, Laragon, etc.)
        cors: true,
        strictPort: true,
        port: 3000,
        hmr: {
            host: 'localhost',
        },
    },
});