/**
 * Lightbox — Click-to-zoom modal for portfolio images
 * Shared across all portfolio section variations.
 */

export function initLightbox(): void {
  // Create modal element once
  const modal = document.createElement('div')
  modal.className = 'lightbox'
  modal.setAttribute('role', 'dialog')
  modal.setAttribute('aria-label', 'Image preview')
  modal.innerHTML = `
    <button class="lightbox__close" type="button" aria-label="Close">&times;</button>
    <div class="lightbox__image-wrapper">
      <img class="lightbox__image" src="" alt="">
      <p class="lightbox__caption"></p>
    </div>
  `
  document.body.appendChild(modal)

  const img = modal.querySelector<HTMLImageElement>('.lightbox__image')!
  const caption = modal.querySelector<HTMLElement>('.lightbox__caption')!
  const closeBtn = modal.querySelector<HTMLButtonElement>('.lightbox__close')!

  function open(src: string, alt: string, label: string): void {
    img.src = src
    img.alt = alt
    caption.textContent = label
    modal.classList.add('is-open')
    document.body.classList.add('lightbox-open')
    closeBtn.focus()
  }

  function close(): void {
    modal.classList.remove('is-open')
    document.body.classList.remove('lightbox-open')
  }

  // Close: button, backdrop click, escape
  closeBtn.addEventListener('click', close)
  modal.addEventListener('click', (e) => {
    if (e.target === modal) close()
  })
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) {
      close()
    }
  })

  // Click on any [data-lightbox] element
  document.addEventListener('click', (e) => {
    const trigger = (e.target as HTMLElement).closest<HTMLElement>('[data-lightbox]')
    if (!trigger) return

    e.preventDefault()
    const src = trigger.dataset.lightbox || ''
    const imgEl = trigger.querySelector('img')
    const alt = imgEl?.alt || ''
    const label = trigger.querySelector('[class*="__label"]')?.textContent || ''

    if (src) open(src, alt, label)
  })
}

// Auto-init
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initLightbox)
} else {
  initLightbox()
}
