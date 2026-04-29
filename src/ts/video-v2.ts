/**
 * Video V2 — Lightbox Modal
 *
 * Opens a YouTube video in a modal lightbox when a card is clicked.
 * Iframe is only loaded when the lightbox opens.
 */

(function () {
  'use strict';

  const lightbox = document.getElementById('video-v2-lightbox');
  const backdrop = document.getElementById('video-v2-lightbox-backdrop');
  const closeBtn = document.getElementById('video-v2-lightbox-close');
  const iframe = document.getElementById('video-v2-lightbox-iframe') as HTMLIFrameElement | null;
  const lightboxTitle = document.getElementById('video-v2-lightbox-title');

  if (!lightbox || !backdrop || !closeBtn || !iframe) return;

  const videoCards = document.querySelectorAll<HTMLButtonElement>('.video-v2__card');

  // ─── Open lightbox ──────────────────────────────────────────────

  videoCards.forEach((card) => {
    card.addEventListener('click', () => {
      const videoId = card.dataset.videoId;
      const videoTitle = card.dataset.videoTitle || '';

      if (!videoId) return;

      // Set iframe src
      const baseUrl = 'https://www.youtube.com/embed/';
      iframe.src = `${baseUrl}${videoId}?autoplay=1&rel=0`;
      iframe.title = videoTitle;

      // Set title
      if (lightboxTitle) {
        lightboxTitle.textContent = videoTitle;
      }

      // Show lightbox
      lightbox.hidden = false;

      // Prevent body scroll
      document.body.style.overflow = 'hidden';

      // Focus close button for accessibility
      closeBtn.focus();
    });
  });

  // ─── Close lightbox ──────────────────────────────────────────────

  function closeLightbox() {
    lightbox.hidden = true;

    // Clear iframe src to stop video
    iframe.src = '';

    // Restore body scroll
    document.body.style.overflow = '';

    // Return focus to the card that opened it
    // (we don't track which one, just return to a sensible place)
    const firstCard = videoCards[0];
    if (firstCard) {
      firstCard.focus();
    }
  }

  backdrop.addEventListener('click', closeLightbox);
  closeBtn.addEventListener('click', closeLightbox);

  // Escape key closes
  document.addEventListener('keydown', (e: KeyboardEvent) => {
    if (e.key === 'Escape' && !lightbox.hidden) {
      closeLightbox();
    }
  });

  // ─── Trap focus inside lightbox ─────────────────────────────────

  lightbox.addEventListener('keydown', (e: KeyboardEvent) => {
    if (e.key !== 'Tab') return;

    const focusableElements = lightbox.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstEl = focusableElements[0];
    const lastEl = focusableElements[focusableElements.length - 1];

    if (e.shiftKey && document.activeElement === firstEl) {
      e.preventDefault();
      lastEl.focus();
    } else if (!e.shiftKey && document.activeElement === lastEl) {
      e.preventDefault();
      firstEl.focus();
    }
  });
})();
