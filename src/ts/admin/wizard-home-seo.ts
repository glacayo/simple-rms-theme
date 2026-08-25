export const HOME_SEO_PRIMARY_REQUIRED =
  'Enter a primary keyword before generating with SEO targeting enabled.';

export const HOME_SEO_SECONDARY_LIMIT = 10;

export const HOME_SEO_SECONDARY_CLAMPED =
  'Only the first 10 unique secondary keywords were kept. Extra keywords were ignored.';

export const HOME_SEO_HELP_ID = 'rms-wizard-home-seo-help';
export const HOME_SEO_PRIMARY_ERROR_ID = 'rms-wizard-home-seo-primary-error';
export const HOME_PAGE_BUILDER_STEP = 'home-page-builder';

export type HomeSeoStepFinish = {
  step: string;
  persisted?: boolean;
  validationBlocked?: boolean;
};

export function createHomeSeoValidationError(message = HOME_SEO_PRIMARY_REQUIRED): Error {
  const error = new Error(message) as Error & { homeSeoValidation: true };
  error.name = 'HomeSeoValidationError';
  error.homeSeoValidation = true;
  return error;
}

export function isHomeSeoValidationError(error: unknown): boolean {
  return Boolean(
    error
    && typeof error === 'object'
    && 'homeSeoValidation' in error
    && (error as { homeSeoValidation?: boolean }).homeSeoValidation
  );
}

export function shouldReloadWizardStateOnStepFinish(input: Pick<HomeSeoStepFinish, 'validationBlocked'>): boolean {
  return !input.validationBlocked;
}

export function shouldReplaceHomeSeoOnStepFinish(input: HomeSeoStepFinish): boolean {
  return Boolean(input.persisted)
    && !input.validationBlocked
    && input.step === HOME_PAGE_BUILDER_STEP;
}

export type HomeSeoTargetingInput = {
  enabled: boolean;
  primaryKeyword?: string;
  secondaryKeywords?: string | string[];
};

export type HomeSeoTargetingDisabled = {
  enabled: false;
};

export type HomeSeoTargetingEnabled = {
  enabled: true;
  primary_keyword: string;
  secondary_keywords: string[];
};

export type HomeSeoTargetingPayload = HomeSeoTargetingDisabled | HomeSeoTargetingEnabled;

export type HomeSeoSecondaryAnalysis = {
  keywords: string[];
  uniqueCount: number;
  clamped: boolean;
};

export type HomeSeoCollectResult =
  | {
      payload: HomeSeoTargetingDisabled;
      secondaryClamped: false;
    }
  | {
      payload: HomeSeoTargetingEnabled;
      secondaryClamped: boolean;
    }
  | {
      payload: { enabled: true };
      error: 'primary_required';
      message: string;
      secondaryClamped: boolean;
    };

export function normalizeHomeKeyword(value: string): string {
  return value.replace(/\s+/g, ' ').trim();
}

export function analyzeHomeSecondaryKeywords(raw: string | string[], primary = ''): HomeSeoSecondaryAnalysis {
  const items = Array.isArray(raw) ? raw : raw.split(/[\n,]+/);
  const seen = new Set<string>();
  const primaryKey = normalizeHomeKeyword(primary).toLowerCase();
  const keywords: string[] = [];
  let uniqueCount = 0;

  items.forEach((item) => {
    const next = normalizeHomeKeyword(String(item));

    if (!next) {
      return;
    }

    const key = next.toLowerCase();

    if (key === primaryKey || seen.has(key)) {
      return;
    }

    seen.add(key);
    uniqueCount += 1;

    if (keywords.length < HOME_SEO_SECONDARY_LIMIT) {
      keywords.push(next);
    }
  });

  return {
    keywords,
    uniqueCount,
    clamped: uniqueCount > HOME_SEO_SECONDARY_LIMIT,
  };
}

export function normalizeHomeSecondaryKeywords(raw: string | string[], primary = ''): string[] {
  return analyzeHomeSecondaryKeywords(raw, primary).keywords;
}

export function buildHomeSeoTargetingPayload(input: HomeSeoTargetingInput): HomeSeoTargetingPayload {
  if (!input.enabled) {
    return { enabled: false };
  }

  const primaryKeyword = normalizeHomeKeyword(input.primaryKeyword ?? '');

  if (!primaryKeyword) {
    throw new Error(HOME_SEO_PRIMARY_REQUIRED);
  }

  return {
    enabled: true,
    primary_keyword: primaryKeyword,
    secondary_keywords: analyzeHomeSecondaryKeywords(input.secondaryKeywords ?? [], primaryKeyword).keywords,
  };
}

export function persistHomeSeoTargeting(payload: HomeSeoTargetingPayload): HomeSeoTargetingPayload {
  if (!payload.enabled) {
    return { enabled: false };
  }

  return {
    enabled: true,
    primary_keyword: payload.primary_keyword,
    secondary_keywords: [...payload.secondary_keywords],
  };
}

export function markHomeSeoDirty(form: HTMLElement): void {
  form.dataset.wizardHomeSeoDirty = '1';
}

export function clearHomeSeoDirty(form: HTMLElement): void {
  delete form.dataset.wizardHomeSeoDirty;
}

export function isHomeSeoDirty(form: HTMLElement): boolean {
  return form.dataset.wizardHomeSeoDirty === '1';
}

export function applyHomeSeoTargetingUi(
  form: HTMLElement,
  enabled: boolean,
  values?: { primary?: string; secondary?: string[] }
): void {
  const toggle = form.querySelector<HTMLInputElement>('[data-wizard-home-seo-enabled]');
  const fields = form.querySelector<HTMLElement>('[data-wizard-home-seo-fields]');
  const primary = form.querySelector<HTMLInputElement>('[data-wizard-home-seo-primary]');
  const secondary = form.querySelector<HTMLInputElement | HTMLTextAreaElement>('[data-wizard-home-seo-secondary]');

  if (toggle) {
    toggle.checked = enabled;
    toggle.setAttribute('aria-expanded', enabled ? 'true' : 'false');
  }

  if (fields) {
    fields.hidden = !enabled;
  }

  if (primary) {
    primary.disabled = !enabled;
    primary.required = enabled;
    primary.setAttribute('aria-required', enabled ? 'true' : 'false');

    if (!enabled) {
      primary.value = '';
    } else if (values?.primary !== undefined) {
      primary.value = values.primary;
    }
  }

  if (secondary) {
    secondary.disabled = !enabled;

    if (!enabled) {
      secondary.value = '';
    } else if (values?.secondary !== undefined) {
      secondary.value = values.secondary.join(', ');
    }
  }

  if (!enabled) {
    clearHomeSeoFeedback(form);
  }
}

export function collectHomeSeoTargetingFromForm(form: HTMLElement): HomeSeoCollectResult {
  const enabled = Boolean(form.querySelector<HTMLInputElement>('[data-wizard-home-seo-enabled]')?.checked);
  const primaryKeyword = form.querySelector<HTMLInputElement>('[data-wizard-home-seo-primary]')?.value ?? '';
  const secondaryKeywords = form.querySelector<HTMLInputElement | HTMLTextAreaElement>('[data-wizard-home-seo-secondary]')?.value ?? '';
  const normalizedPrimary = normalizeHomeKeyword(primaryKeyword);
  const analysis = analyzeHomeSecondaryKeywords(secondaryKeywords, normalizedPrimary);

  if (!enabled) {
    return {
      payload: { enabled: false },
      secondaryClamped: false,
    };
  }

  if (!normalizedPrimary) {
    return {
      payload: { enabled: true },
      error: 'primary_required',
      message: HOME_SEO_PRIMARY_REQUIRED,
      secondaryClamped: analysis.clamped,
    };
  }

  return {
    payload: {
      enabled: true,
      primary_keyword: normalizedPrimary,
      secondary_keywords: analysis.keywords,
    },
    secondaryClamped: analysis.clamped,
  };
}

export function presentHomeSeoCollectionResult(
  form: HTMLElement,
  result: HomeSeoCollectResult,
  options?: { focus?: boolean }
): void {
  if (result.payload.enabled) {
    applyHomeSeoTargetingUi(form, true);

    if (!('error' in result) && result.secondaryClamped) {
      const secondary = form.querySelector<HTMLInputElement | HTMLTextAreaElement>('[data-wizard-home-seo-secondary]');

      if (secondary) {
        secondary.value = result.payload.secondary_keywords.join(', ');
      }
    }
  } else {
    applyHomeSeoTargetingUi(form, false);
  }

  if ('error' in result && result.error === 'primary_required') {
    setHomeSeoPrimaryInvalid(form, options?.focus !== false);
  } else {
    clearHomeSeoPrimaryInvalid(form);
  }

  setHomeSeoSecondaryClampNotice(form, Boolean(result.payload.enabled && result.secondaryClamped));
}

export function hydrateHomeSeoTargeting(
  form: HTMLElement,
  raw: unknown,
  options?: { force?: boolean }
): void {
  if (!options?.force && isHomeSeoDirty(form)) {
    return;
  }

  if (options?.force) {
    clearHomeSeoDirty(form);
  }

  const state = isRecord(raw) ? raw : {};
  const enabled = Boolean(state.enabled);
  const primary = typeof state.primary_keyword === 'string' ? state.primary_keyword : '';
  const secondary = Array.isArray(state.secondary_keywords)
    ? state.secondary_keywords.filter((item): item is string => typeof item === 'string')
    : [];

  applyHomeSeoTargetingUi(form, enabled, { primary, secondary });
  clearHomeSeoFeedback(form);
}

export function clearHomeSeoFeedback(form: HTMLElement): void {
  clearHomeSeoPrimaryInvalid(form);
  setHomeSeoSecondaryClampNotice(form, false);
}

export function setHomeSeoPrimaryInvalid(form: HTMLElement, focus = true): void {
  const primary = form.querySelector<HTMLInputElement>('[data-wizard-home-seo-primary]');
  const error = form.querySelector<HTMLElement>('[data-wizard-home-seo-primary-error]');

  applyHomeSeoTargetingUi(form, true);

  if (primary) {
    primary.setAttribute('aria-invalid', 'true');
    primary.setAttribute('aria-describedby', `${HOME_SEO_HELP_ID} ${HOME_SEO_PRIMARY_ERROR_ID}`);

    if (focus && typeof primary.focus === 'function') {
      primary.focus();
    }
  }

  if (error) {
    error.hidden = false;
    error.textContent = HOME_SEO_PRIMARY_REQUIRED;
  }
}

export function clearHomeSeoPrimaryInvalid(form: HTMLElement): void {
  const primary = form.querySelector<HTMLInputElement>('[data-wizard-home-seo-primary]');
  const error = form.querySelector<HTMLElement>('[data-wizard-home-seo-primary-error]');

  if (primary) {
    primary.setAttribute('aria-invalid', 'false');
    primary.setAttribute('aria-describedby', HOME_SEO_HELP_ID);
  }

  if (error) {
    error.hidden = true;
    error.textContent = '';
  }
}

export function setHomeSeoSecondaryClampNotice(form: HTMLElement, visible: boolean): void {
  const notice = form.querySelector<HTMLElement>('[data-wizard-home-seo-secondary-notice]');

  if (!notice) {
    return;
  }

  notice.hidden = !visible;
  notice.textContent = visible ? HOME_SEO_SECONDARY_CLAMPED : '';
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
