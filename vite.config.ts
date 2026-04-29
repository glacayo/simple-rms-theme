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
        hero: path.resolve(__dirname, 'src/scss/templates/hero.scss'),
        services: path.resolve(__dirname, 'src/scss/templates/services.scss'),
        projects: path.resolve(__dirname, 'src/scss/templates/projects.scss'),
        testimonials: path.resolve(__dirname, 'src/scss/templates/testimonials.scss'),
        cta: path.resolve(__dirname, 'src/scss/templates/cta.scss'),
        'content-section': path.resolve(__dirname, 'src/scss/templates/content-section.scss'),
        slider: path.resolve(__dirname, 'src/scss/templates/slider.scss'),
        badges: path.resolve(__dirname, 'src/scss/templates/badges.scss'),
        'about-us': path.resolve(__dirname, 'src/scss/templates/about-us.scss'),
        'services-v1': path.resolve(__dirname, 'src/scss/templates/services-v1.scss'),
        'services-v2': path.resolve(__dirname, 'src/scss/templates/services-v2.scss'),
        'services-v3': path.resolve(__dirname, 'src/scss/templates/services-v3.scss'),
        'cta-v1': path.resolve(__dirname, 'src/scss/templates/cta-v1.scss'),
        'cta-v2': path.resolve(__dirname, 'src/scss/templates/cta-v2.scss'),
        'cta-v3': path.resolve(__dirname, 'src/scss/templates/cta-v3.scss'),
        'portfolio-v1': path.resolve(__dirname, 'src/scss/templates/portfolio-v1.scss'),
        'portfolio-v2': path.resolve(__dirname, 'src/scss/templates/portfolio-v2.scss'),
        'portfolio-v3': path.resolve(__dirname, 'src/scss/templates/portfolio-v3.scss'),
        'testimonials-v1': path.resolve(__dirname, 'src/scss/templates/testimonials-v1.scss'),
        'testimonials-v2': path.resolve(__dirname, 'src/scss/templates/testimonials-v2.scss'),
        'testimonials-v3': path.resolve(__dirname, 'src/scss/templates/testimonials-v3.scss'),
        'blog-v1': path.resolve(__dirname, 'src/scss/templates/blog-v1.scss'),
        'area-coverage-v1': path.resolve(__dirname, 'src/scss/templates/area-coverage-v1.scss'),
        'vision-mission-v1': path.resolve(__dirname, 'src/scss/templates/vision-mission-v1.scss'),
        'vision-mission-v2': path.resolve(__dirname, 'src/scss/templates/vision-mission-v2.scss'),
        'seo-content': path.resolve(__dirname, 'src/scss/templates/seo-content.scss'),
        // Internal page: Services
        'services-page': path.resolve(__dirname, 'src/scss/templates/services-page.scss'),
        // Internal page: Gallery / Projects
        'gallery-grid': path.resolve(__dirname, 'src/scss/templates/gallery-grid.scss'),
        // Internal page: Contact Us
        'contact-info': path.resolve(__dirname, 'src/scss/templates/contact-info.scss'),
        'contact-map':  path.resolve(__dirname, 'src/scss/templates/contact-map.scss'),
        // Internal page: Blog
        'blog-listing': path.resolve(__dirname, 'src/scss/templates/blog-listing.scss'),
        // Single post
        'single-post': path.resolve(__dirname, 'src/scss/templates/single-post.scss'),
        // Thank You page
        'thank-you': path.resolve(__dirname, 'src/scss/templates/thank-you.scss'),
        // Internal page breadcrumbs
        'breadcrumb': path.resolve(__dirname, 'src/scss/templates/breadcrumb.scss'),
        // Footer
        'footer-v2': path.resolve(__dirname, 'src/scss/layout/footer-v2.scss'),
        // Layout CSS
        'header-one': path.resolve(__dirname, 'src/scss/layout/header-one.scss'),
        'header-two': path.resolve(__dirname, 'src/scss/layout/header-two.scss'),
        'header-three': path.resolve(__dirname, 'src/scss/layout/header-three.scss'),
        // Layout JS
        'header-one-menu': path.resolve(__dirname, 'src/ts/header-one-menu.ts'),
        'header-two-menu': path.resolve(__dirname, 'src/ts/header-two-menu.ts'),
        'header-three-menu': path.resolve(__dirname, 'src/ts/header-three-menu.ts'),
        // Slider JS
        'slider-js': path.resolve(__dirname, 'src/ts/slider.ts'),
        // Lightbox (shared component)
        lightbox: path.resolve(__dirname, 'src/scss/components/lightbox.scss'),
        'lightbox-js': path.resolve(__dirname, 'src/ts/lightbox.ts'),
        // Portfolio filter JS
        'portfolio-filter-js': path.resolve(__dirname, 'src/ts/portfolio-filter.ts'),
        // FAQ CSS + JS
        'faq-v1': path.resolve(__dirname, 'src/scss/templates/faq-v1.scss'),
        'faq-v2': path.resolve(__dirname, 'src/scss/templates/faq-v2.scss'),
        'faq-js': path.resolve(__dirname, 'src/ts/faq.ts'),
        // Video CSS + JS
        'video-v1': path.resolve(__dirname, 'src/scss/templates/video-v1.scss'),
        'video-v2': path.resolve(__dirname, 'src/scss/templates/video-v2.scss'),
        'video-v1-js': path.resolve(__dirname, 'src/ts/video-v1.ts'),
        'video-v2-js': path.resolve(__dirname, 'src/ts/video-v2.ts'),
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
    host: 'localhost',
  },
})
