/**
 * Portfolio V3 — Category filter
 * Pure CSS show/hide with a small JS toggle for active states.
 */

const CLASS_HIDDEN = 'is-hidden'
const CLASS_ACTIVE = 'portfolio-v3__filter--active'

export function initPortfolioFilter(): void {
  const filters = document.querySelectorAll<HTMLButtonElement>('.portfolio-v3__filter[data-filter]')
  const items = document.querySelectorAll<HTMLElement>('.portfolio-v3__item[data-category]')

  if (filters.length === 0 || items.length === 0) return

  filters.forEach((btn) => {
    btn.addEventListener('click', () => {
      const category = btn.dataset.filter || 'all'

      // Update active button
      filters.forEach((f) => f.classList.remove(CLASS_ACTIVE))
      btn.classList.add(CLASS_ACTIVE)

      // Show/hide items
      items.forEach((item) => {
        if (category === 'all' || item.dataset.category === category) {
          item.classList.remove(CLASS_HIDDEN)
        } else {
          item.classList.add(CLASS_HIDDEN)
        }
      })
    })
  })
}

// Auto-init
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPortfolioFilter)
} else {
  initPortfolioFilter()
}
