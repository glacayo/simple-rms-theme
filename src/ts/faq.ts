/**
 * FAQ Accordion — Vanilla TypeScript
 *
 * Handles expand/collapse for both faq-v1 and faq-v2 accordions.
 * Uses ARIA attributes for accessibility.
 *
 * @see https://www.w3.org/WAI/ARIA/apg/patterns/accordion/
 */

(function () {
  'use strict';

  // ─── FAQ V1 Accordion ──────────────────────────────────────────────

  const faq1Items = document.querySelectorAll<HTMLElement>('.faq-v1__item');

  faq1Items.forEach((item) => {
    const trigger = item.querySelector<HTMLButtonElement>('.faq-v1__question');
    const answer = item.querySelector<HTMLElement>('.faq-v1__answer');

    if (!trigger || !answer) return;

    trigger.addEventListener('click', () => {
      const isExpanded = trigger.getAttribute('aria-expanded') === 'true';

      // Close all other items (single-open behavior)
      faq1Items.forEach((otherItem) => {
        const otherTrigger = otherItem.querySelector<HTMLButtonElement>('.faq-v1__question');
        const otherAnswer = otherItem.querySelector<HTMLElement>('.faq-v1__answer');
        if (otherTrigger && otherAnswer && otherItem !== item) {
          otherTrigger.setAttribute('aria-expanded', 'false');
          otherAnswer.hidden = true;
        }
      });

      // Toggle current
      trigger.setAttribute('aria-expanded', String(!isExpanded));
      answer.hidden = isExpanded;
    });

    // Keyboard: Enter and Space toggle
    trigger.addEventListener('keydown', (e: KeyboardEvent) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        trigger.click();
      }
    });
  });

  // ─── FAQ V2 Card Toggles ────────────────────────────────────────────

  const faq2Cards = document.querySelectorAll<HTMLElement>('.faq-v2__card');

  faq2Cards.forEach((card) => {
    const toggle = card.querySelector<HTMLButtonElement>('.faq-v2__card-toggle');
    const answer = card.querySelector<HTMLElement>('.faq-v2__card-answer');

    if (!toggle || !answer) return;

    toggle.addEventListener('click', () => {
      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

      // Close all other cards (single-open behavior)
      faq2Cards.forEach((otherCard) => {
        const otherToggle = otherCard.querySelector<HTMLButtonElement>('.faq-v2__card-toggle');
        const otherAnswer = otherCard.querySelector<HTMLElement>('.faq-v2__card-answer');
        if (otherToggle && otherAnswer && otherCard !== card) {
          otherToggle.setAttribute('aria-expanded', 'false');
          otherAnswer.hidden = true;
        }
      });

      // Toggle current
      toggle.setAttribute('aria-expanded', String(!isExpanded));
      answer.hidden = isExpanded;
    });

    // Keyboard: Enter and Space toggle
    toggle.addEventListener('keydown', (e: KeyboardEvent) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggle.click();
      }
    });

    // Click anywhere on card header also toggles
    const header = card.querySelector<HTMLElement>('.faq-v2__card-header');
    if (header) {
      header.addEventListener('click', (e: Event) => {
        if (e.target === toggle) return; // Already handled
        toggle.click();
      });
    }
  });

  // ─── Escape key closes open items ───────────────────────────────────

  document.addEventListener('keydown', (e: KeyboardEvent) => {
    if (e.key !== 'Escape') return;

    // Close FAQ V1
    faq1Items.forEach((item) => {
      const trigger = item.querySelector<HTMLButtonElement>('.faq-v1__question');
      const answer = item.querySelector<HTMLElement>('.faq-v1__answer');
      if (trigger && answer && trigger.getAttribute('aria-expanded') === 'true') {
        trigger.setAttribute('aria-expanded', 'false');
        answer.hidden = true;
        trigger.focus();
      }
    });

    // Close FAQ V2
    faq2Cards.forEach((card) => {
      const toggle = card.querySelector<HTMLButtonElement>('.faq-v2__card-toggle');
      const answer = card.querySelector<HTMLElement>('.faq-v2__card-answer');
      if (toggle && answer && toggle.getAttribute('aria-expanded') === 'true') {
        toggle.setAttribute('aria-expanded', 'false');
        answer.hidden = true;
        toggle.focus();
      }
    });
  });
})();
