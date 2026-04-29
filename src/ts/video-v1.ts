/**
 * Video V1 — Poster-to-Iframe Load
 *
 * Video only loads the YouTube iframe when the user clicks play.
 * This improves page performance (LCP, CLS) by deferring the heavy iframe.
 */

(function () {
  'use strict';

  const playBtn = document.getElementById('video-v1-play-btn');
  const iframeWrapper = document.getElementById('video-v1-iframe-wrapper');
  const iframe = document.getElementById('video-v1-iframe') as HTMLIFrameElement | null;

  if (!playBtn || !iframeWrapper || !iframe) return;

  playBtn.addEventListener('click', () => {
    // Load the iframe src from data-src (lazy load)
    if (iframe.dataset.src) {
      iframe.src = iframe.dataset.src;
    }

    // Show iframe wrapper, hide poster
    iframeWrapper.hidden = false;
    const poster = iframeWrapper.previousElementSibling as HTMLElement | null;
    if (poster) {
      poster.style.display = 'none';
    }
    const overlay = document.querySelector('.video-v1__overlay') as HTMLElement | null;
    if (overlay) {
      overlay.style.display = 'none';
    }

    // Hide play button and meta
    playBtn.style.display = 'none';
    const meta = document.querySelector('.video-v1__meta') as HTMLElement | null;
    if (meta) {
      meta.style.display = 'none';
    }
  });
})();
