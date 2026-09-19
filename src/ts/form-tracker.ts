/**
 * Raven form lifecycle tracker — issue #128 slice I128-03.
 *
 * Pushes exactly two identity-only events to `window.dataLayer`: `form_start`
 * once per tracked lead form per page view on the first `focusin`/`input`, and
 * `form_success` only on Contact Form 7's confirmed `wpcf7mailsent`.
 * Tracked forms are CF7 or explicitly marked; field data is never read or serialized.
 */
const START_EVENT = 'form_start';
const SUCCESS_EVENT = 'form_success';
const CF7_WRAPPER_ID = /wpcf7-f(\d+)(?:-o\d+)?/;
const CF7_KEY = /^cf7-(\d+)$/;
type RavenScope = typeof globalThis & { dataLayer?: Array<Record<string, string>> };
let initialized = false;
const startedForms = new Set<string>();

function hasClass(token: string | null, name: string): boolean {
  return !!token && token.split(/\s+/).indexOf(name) !== -1;
}
function ancestors(element: Element): Element[] {
  const chain: Element[] = [];
  let current = element.parentElement;
  while (current) {
    chain.push(current);
    current = current.parentElement;
  }
  return chain;
}
function isCf7Form(form: Element): boolean {
  if (hasClass(form.getAttribute('class'), 'wpcf7-form')) return true;
  return ancestors(form).some((parent) => hasClass(parent.getAttribute('class'), 'wpcf7'));
}
export function isTrackedForm(form: Element): boolean {
  const explicit = form.getAttribute('data-raven-form-id');
  return (!!explicit && explicit.trim() !== '') || isCf7Form(form);
}
function cf7Number(token: unknown): string | null {
  if (typeof token === 'number' && Number.isInteger(token) && token >= 0) return String(token);
  if (typeof token !== 'string') return null;
  const trimmed = token.trim();
  const match = CF7_WRAPPER_ID.exec(trimmed);
  if (match) return match[1];
  return /^\d+$/.test(trimmed) ? trimmed : null;
}
function domCf7Number(form: Element): string | null {
  const scopes = [form].concat(ancestors(form)).map((scope) => [scope.getAttribute('id'), scope.getAttribute('class')].filter(Boolean).join(' '));
  for (let i = 0; i < scopes.length; i += 1) {
    const match = CF7_WRAPPER_ID.exec(scopes[i]);
    if (match) return match[1];
  }
  return null;
}
export function resolveFormId(form: Element, detail?: unknown): string {
  const explicit = form.getAttribute('data-raven-form-id');
  if (explicit && explicit.trim() !== '') return explicit.trim();
  const payload = (detail && typeof detail === 'object' ? detail : {}) as { contactFormId?: unknown; unitTag?: unknown };
  const number = cf7Number(payload.contactFormId) || cf7Number(payload.unitTag) || domCf7Number(form);
  return number ? 'cf7-' + number : 'cf7-form';
}
export function resolveFormName(form: Element, formId: string): string {
  const explicit = form.getAttribute('data-raven-form-name');
  if (explicit && explicit.trim() !== '') return explicit.trim();
  const label = form.getAttribute('aria-label');
  if (label && label.trim() !== '') return label.trim();
  const match = CF7_KEY.exec(formId);
  return match ? 'Contact Form 7 #' + match[1] : 'Contact Form';
}
function findTrackedForm(target: Element | null): Element | null {
  if (!target) return null;
  const form = target.closest('form') || target.closest('.wpcf7')?.querySelector('form') || null;
  return form && isTrackedForm(form) ? form : null;
}
function emit(eventName: string, form: Element, detail?: unknown): void {
  const scope = (typeof window === 'undefined' ? globalThis : window) as RavenScope;
  const dataLayer = (scope.dataLayer = scope.dataLayer || []);
  const formId = resolveFormId(form, detail);
  dataLayer.push({ event: eventName, form_id: formId, form_name: resolveFormName(form, formId) });
}
function handleInteraction(event: Event): void {
  const form = findTrackedForm(event.target as Element | null);
  if (!form) return;
  const formId = resolveFormId(form);
  if (startedForms.has(formId)) return;
  startedForms.add(formId);
  emit(START_EVENT, form);
}
function handleMailSent(event: Event): void {
  // CF7 6.1.7 bubbles `wpcf7mailsent` from the form itself; resolve that tracked
  // form but require CF7 markup so explicit raw Raven forms stay success-silent.
  const form = findTrackedForm(event.target as Element | null);
  if (form && isCf7Form(form)) emit(SUCCESS_EVENT, form, (event as CustomEvent).detail);
}
export function initFormTracker(): void {
  if (initialized) return;
  initialized = true;
  if (typeof document === 'undefined') return;
  const attach = (): void => {
    document.addEventListener('focusin', handleInteraction, true);
    document.addEventListener('input', handleInteraction, true);
    document.addEventListener('wpcf7mailsent', handleMailSent);
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attach, { once: true });
    return;
  }
  attach();
}