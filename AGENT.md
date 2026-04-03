# 🚀 Project: High-Performance WordPress Theme (Vite + TS + SASS)

## 📌 Resumen del Proyecto
Este es un tema de WordPress construido desde cero con un enfoque obsesivo en la velocidad de carga (Performance First). El objetivo innegociable es **100/100 en Google PageSpeed Insights (PSI)**. No es un tema headless; es un monolito optimizado que utiliza Vite como orquestador de assets modernos.

**Core Stack:**
- **Build Tool:** Vite 8.x + TypeScript.
- **CSS:** SASS (Modular por secciones).
- **PHP:** Arquitectura desacoplada por componentes/templates.
- **Data:** ACF Pro (Advanced Custom Fields) + Yoast SEO.

---

## 🛠 Comandos de Ejecución
- `npm install`: Instala dependencias.
- `npm run dev`: Inicia el servidor de desarrollo de Vite (HMR) y crea el archivo `hot`.
- `npm run build`: Compila assets para producción, genera el `manifest.json` y elimina el archivo `hot`.

---

## 📂 Estructura de Carpetas & Responsabilidades

### `/src` (Source Code)
- `ts/main.ts`: Punto de entrada global de JS. Solo lógica crítica.
- `scss/base/`: Resets, variables de color, tipografía y mixins.
- `scss/sections/`: **Crucial.** Cada sección del diseño (hero, services, etc.) tiene su propio archivo `.scss`. Estos se registran como inputs individuales en `vite.config.ts`.
- `images/`: Assets estáticos del tema (iconos, decoraciones).

### `/inc` (Lógica de Negocio PHP)
- `vite-integration.php`: Clase que conecta PHP con los assets de Vite (HMR o Manifest).
- `optimize.php`: Scripts para remover basura de WP (emojis, dashicons, block-library).
- `setup.php`: Configuración del tema (image sizes, menus, theme supports).

### `/templates` (Fragmentos HTML)
- Contiene los partials de las secciones. Cada `.php` aquí debe tener un hermano `.scss` en `src/scss/sections/`.

### `/acf-json`
- Sincronización automática de campos de ACF Pro. **No editar manualmente.**

---

## 📏 Directrices de Estilo y Código

### 1. CSS & SASS (The "No-Blocking" Rule)
- **Metodología:** BEM (Block Element Modifier).
- **Critical CSS:** El CSS del Hero DEBE ser inyectado inline en el `<head>` usando el método `get_critical_css()`.
- **Async CSS:** El resto de secciones se encolan condicionalmente con `wp_enqueue_style`.
- **Prohibido:** No uses `@import` masivos en un solo archivo global. Mantén los archivos pequeños (< 10kb gzipped).

### 2. JavaScript & TypeScript
- **Paradigma:** Vanilla JS solamente.
- **Prohibido:** jQuery o frameworks pesados. Si se requiere reactividad, usar Alpine.js (ligero).
- **Performance:** Todos los scripts deben cargarse con `type="module"` (asíncronos por naturaleza).

### 3. Imágenes & Media
- **CLS (Cumulative Layout Shift):** Toda imagen DEBE tener atributos `width` y `height` explícitos.
- **ACF Images:** Usa siempre `wp_get_attachment_image( $id, 'size' )` para aprovechar el `srcset` nativo y lazy loading.

---

## 🔄 Workflow de Desarrollo para el Agente

Cuando se solicite crear una nueva sección (ej: "Testimonios"):
1. **Crear Estilos:** Generar `src/scss/sections/testimonials.scss`.
2. **Registrar en Vite:** Añadir el nuevo archivo al objeto `input` en `vite.config.ts`.
3. **Crear Template:** Generar `templates/testimonials.php` con el marcado HTML semántico.
4. **Vite Bridge:** En el archivo de la página (ej: `front-page.php`), llamar al CSS usando:
   - Si es Above the fold: `Vite_Icons_Integration::get_instance()->get_critical_css('src/scss/sections/testimonials.scss')`.
   - Si es Below the fold: `Vite_Icons_Integration::get_instance()->get_asset('src/scss/sections/testimonials.scss')` vía `wp_enqueue_style`.

---

## 🔒 Consideraciones de Seguridad & SEO
- **Sanitización:** Escapar siempre las salidas de ACF con `esc_html()`, `esc_attr()`, o `wp_kses()`.
- **Yoast SEO:** No sobrescribir títulos o metas manualmente; usar los hooks de Yoast.
- **Data Attributes:** Usar `data-` attributes para pasar información de PHP a JS, evitando variables globales `window.config`.

---

## 🧪 Instrucciones de Prueba (Performance Audit)
Antes de dar una tarea por terminada, verifica:
1. ¿El DOM total tiene menos de 1500 nodos?
2. ¿Hay algún recurso bloqueante en el `<head>` que no sea el CSS crítico inline?
3. ¿Las imágenes tienen `decoding="async"` y `loading="lazy"` (excepto el Hero)?
4. ¿El TTFB es estable (revisar Query Monitor para consultas lentas)?

---
**Firmado:** Geovanny - Senior Web Dev & SEO Specialist.