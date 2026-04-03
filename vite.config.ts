import { defineConfig } from 'vite'
import liveReload from 'vite-plugin-live-reload'
import path from 'path'
import { fileURLToPath } from 'url'
import { dirname } from 'path'
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer'

// __dirname shim for ESM
const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)
const normalizedRoot = __dirname.replace(/\\/g, '/')

export default defineConfig({
  plugins: [
    // Live reload on PHP changes
    liveReload([
      `${normalizedRoot}/**/*.php`,
    ]),

    // Image optimization on build
    ViteImageOptimizer({
      test: /\.(jpe?g|png|gif|tiff|webp|svg|avif)$/i,
      includePublic: true,
      logStats: true,
      png: { quality: 80 },
      jpeg: { quality: 75 },
      jpg: { quality: 75 },
      webp: { lossless: true },
      avif: { lossless: true },
    }),
  ],

  root: '',
  base: process.env.NODE_ENV === 'production' ? './' : '/',

  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'src/ts/main.ts'),
      },
      output: {
        entryFileNames: 'assets/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash].[ext]',
      },
    },
    cssMinify: true,
  },

  server: {
    cors: true,
    strictPort: true,
    port: 3000,
    hmr: {
      host: 'localhost',
    },
  },
})
