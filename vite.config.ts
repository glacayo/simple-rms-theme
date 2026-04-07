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
        // JS global
        main: path.resolve(__dirname, 'src/ts/main.ts'),
        // Base CSS (reset, typography, utilities) — loaded on every page
        'main-css': path.resolve(__dirname, 'src/scss/main.scss'),
        // Section CSS — loaded conditionally per page
        hero: path.resolve(__dirname, 'src/scss/sections/hero.scss'),
        services: path.resolve(__dirname, 'src/scss/sections/services.scss'),
        projects: path.resolve(__dirname, 'src/scss/sections/projects.scss'),
        testimonials: path.resolve(__dirname, 'src/scss/sections/testimonials.scss'),
        cta: path.resolve(__dirname, 'src/scss/sections/cta.scss'),
        'content-section': path.resolve(__dirname, 'src/scss/sections/content-section.scss'),
        slider: path.resolve(__dirname, 'src/scss/sections/slider.scss'),
        badges: path.resolve(__dirname, 'src/scss/sections/badges.scss'),
        'about-us': path.resolve(__dirname, 'src/scss/sections/about-us.scss'),
        'services-v1': path.resolve(__dirname, 'src/scss/sections/services-v1.scss'),
        'services-v2': path.resolve(__dirname, 'src/scss/sections/services-v2.scss'),
        'services-v3': path.resolve(__dirname, 'src/scss/sections/services-v3.scss'),
        // Layout CSS
        'header-one': path.resolve(__dirname, 'src/scss/layout/header-one.scss'),
        // Layout JS
        'header-one-menu': path.resolve(__dirname, 'src/ts/header-one-menu.ts'),
        // Slider JS
        'slider-js': path.resolve(__dirname, 'src/ts/slider.ts'),
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
      port: 3000,
    },
  },
})
