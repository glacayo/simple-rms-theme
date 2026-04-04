/**
 * Hero Slider — Auto-advance + dot navigation
 * Complements CSS Scroll Snap with timed transitions and clickable indicators.
 */

const AUTOPLAY_MS = 3500

export function initSlider(): void {
  const track = document.querySelector<HTMLElement>('.slider__track')
  const slides = document.querySelectorAll<HTMLElement>('.slider__slide')
  const dots = document.querySelectorAll<HTMLButtonElement>('.slider__dot')

  if (!track || slides.length === 0 || dots.length === 0) return

  const el = track as HTMLElement
  let current = 0
  let timer: ReturnType<typeof setInterval> | null = null

  function goTo(index: number): void {
    if (index < 0) index = slides.length - 1
    if (index >= slides.length) index = 0

    current = index
    el.scrollTo({
      left: slides[current].offsetLeft,
      behavior: 'smooth',
    })

    dots.forEach((dot, i) => {
      dot.classList.toggle('slider__dot--active', i === current)
    })
  }

  function startAutoplay(): void {
    stopAutoplay()
    timer = setInterval(() => goTo(current + 1), AUTOPLAY_MS)
  }

  function stopAutoplay(): void {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  // Dot clicks
  dots.forEach((dot) => {
    dot.addEventListener('click', () => {
      const target = Number(dot.dataset.slide)
      if (!isNaN(target)) {
        goTo(target)
        startAutoplay()
      }
    })
  })

  // Pause on hover
  el.addEventListener('mouseenter', stopAutoplay)
  el.addEventListener('mouseleave', startAutoplay)

  // Pause on touch
  el.addEventListener('touchstart', stopAutoplay, { passive: true })
  el.addEventListener('touchend', () => {
    requestAnimationFrame(() => {
      const scrollLeft = el.scrollLeft
      const slideWidth = slides[0].offsetWidth
      const nearest = Math.round(scrollLeft / slideWidth)
      if (nearest !== current) {
        current = nearest
        dots.forEach((dot, i) => {
          dot.classList.toggle('slider__dot--active', i === current)
        })
      }
      startAutoplay()
    })
  })

  // Start
  startAutoplay()
}

// Auto-init
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSlider)
} else {
  initSlider()
}
