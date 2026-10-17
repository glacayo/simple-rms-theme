/**
 * Header One — Mobile Menu Controller
 * Handles: toggle, overlay, focus trap, escape close, submenu accordion, aria states.
 */

const CLASS_ACTIVE = 'is-active'
const CLASS_OPEN = 'is-open'
const CLASS_BODY_LOCK = 'rms-menu-open'

export function initHeaderMenu(): void {
  const _toggle = document.querySelector<HTMLButtonElement>('.rms-header__menu-toggle')
  const _closeBtn = document.querySelector<HTMLButtonElement>('.rms-header__menu-close')
  const _overlay = document.querySelector<HTMLDivElement>('.rms-header__overlay')
  const _menu = document.querySelector<HTMLElement>('.rms-header__mobile-menu')

  if (!_toggle || !_closeBtn || !_overlay || !_menu) return

  // Non-null after guard
  const toggle = _toggle
  const closeBtn = _closeBtn
  const overlay = _overlay
  const menu = _menu

  let previouslyFocused: HTMLElement | null = null

  // ─── Open ───────────────────────────────────────────────────────
  function openMenu(): void {
    previouslyFocused = document.activeElement as HTMLElement

    toggle.setAttribute('aria-expanded', 'true')
    menu.setAttribute('aria-hidden', 'false')
    overlay.classList.add(CLASS_ACTIVE)
    overlay.setAttribute('aria-hidden', 'false')
    menu.classList.add(CLASS_OPEN)
    document.body.classList.add(CLASS_BODY_LOCK)

    requestAnimationFrame(() => {
      closeBtn.focus()
    })
  }

  // ─── Close ──────────────────────────────────────────────────────
  function closeMenu(): void {
    toggle.setAttribute('aria-expanded', 'false')
    menu.setAttribute('aria-hidden', 'true')
    overlay.classList.remove(CLASS_ACTIVE)
    overlay.setAttribute('aria-hidden', 'true')
    menu.classList.remove(CLASS_OPEN)
    document.body.classList.remove(CLASS_BODY_LOCK)

    // Collapse every open submenu instantly so the drawer reopens collapsed
    menu.querySelectorAll<HTMLUListElement>('.rms-header__mobile-submenu.' + CLASS_OPEN).forEach((sub) => {
      sub.classList.remove(CLASS_OPEN)
      sub.style.maxHeight = ''
    })
    menu.querySelectorAll<HTMLButtonElement>('[aria-expanded="true"]').forEach((btn) => {
      btn.setAttribute('aria-expanded', 'false')
    })

    if (previouslyFocused) {
      previouslyFocused.focus()
    }
  }

  // ─── Toggle ─────────────────────────────────────────────────────
  toggle.addEventListener('click', () => {
    const isOpen = toggle.getAttribute('aria-expanded') === 'true'
    if (isOpen) {
      closeMenu()
    } else {
      openMenu()
    }
  })

  closeBtn.addEventListener('click', closeMenu)
  overlay.addEventListener('click', closeMenu)

  // ─── Escape key ─────────────────────────────────────────────────
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
      closeMenu()
    }
  })

  // ─── Focus trap ─────────────────────────────────────────────────
  menu.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return

    const focusable = Array.from(
      menu.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
      )
    ).filter(isReachable)
    if (focusable.length === 0) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (e.shiftKey) {
      if (document.activeElement === first) {
        e.preventDefault()
        last.focus()
      }
    } else {
      if (document.activeElement === last) {
        e.preventDefault()
        first.focus()
      }
    }
  })

  // ─── Mobile submenu accordion ───────────────────────────────────
  menu.querySelectorAll<HTMLButtonElement>('.rms-header__mobile-nav-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('aria-controls')
      const submenu = id ? document.getElementById(id) : null
      if (!submenu) return

      setSubmenuOpen(btn, submenu, btn.getAttribute('aria-expanded') !== 'true')
    })
  })
}

/**
 * Animate a submenu between 0 and its real content height. Once open, the cap
 * is lifted (`none`) so nested submenus can grow; closing starts from the
 * measured height so the collapse still animates.
 */
function setSubmenuOpen(btn: HTMLButtonElement, submenu: HTMLElement, open: boolean): void {
  btn.setAttribute('aria-expanded', String(open))

  const animated = parseFloat(getComputedStyle(submenu).transitionDuration) > 0

  if (open) {
    submenu.classList.add(CLASS_OPEN)
    if (!animated) {
      submenu.style.maxHeight = 'none'
      return
    }
    submenu.style.maxHeight = `${submenu.scrollHeight}px`
    submenu.addEventListener(
      'transitionend',
      function liftCap(e: TransitionEvent) {
        if (e.target !== submenu || e.propertyName !== 'max-height') return
        submenu.removeEventListener('transitionend', liftCap)
        if (submenu.classList.contains(CLASS_OPEN)) submenu.style.maxHeight = 'none'
      }
    )
    return
  }

  submenu.style.maxHeight = `${submenu.scrollHeight}px`
  void submenu.offsetHeight // commit the start height before collapsing
  submenu.classList.remove(CLASS_OPEN)
  submenu.style.maxHeight = ''
}

// Links inside collapsed submenus are hidden and must not join the focus trap.
function isReachable(el: HTMLElement): boolean {
  return el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden'
}

// Auto-init
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initHeaderMenu)
} else {
  initHeaderMenu()
}
