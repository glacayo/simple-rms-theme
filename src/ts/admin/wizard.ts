import {
  isIncompleteLandingRunStatus,
  mergeLandingRowsByKey,
  resolveLandingClientRequest,
  sectionsFromPlanItem,
  shouldOfferLandingResume,
  type LandingClientIntent,
  type LandingHydrationRow,
} from './landing-run-helpers';
import {
  applyApiKeyInputSafety,
  buildGeneratePagePayloadItem,
  presentStepOutcome,
  sanitizeWizardPageType,
  summarizeDependencyResult,
  type GeneratePagePayloadItem,
} from './wizard-helpers';
import {
  buildInternalCardViews,
  displayStepStatus,
  exclusiveMapSelections,
  formatWizardProgress,
  internalPageProgress,
  mapOnlyOutcome,
  openMapConfirmationDialog,
  planStepFinish,
  takenMapTypes,
  type CompletionContract,
  type InternalPagePreview,
} from './wizard-internal-preview';
import {
  applyHomeSeoTargetingUi,
  clearHomeSeoDirty,
  collectHomeSeoTargetingFromForm,
  createHomeSeoValidationError,
  hydrateHomeSeoTargeting,
  isHomeSeoValidationError,
  markHomeSeoDirty,
  presentHomeSeoCollectionResult,
  shouldReplaceHomeSeoOnStepFinish,
} from './wizard-home-seo';

type StepStatus = 'pending' | 'running' | 'complete' | 'failed';

export {};

interface WizardSettings {
  root: string;
  nonce: string;
}

interface WizardLogEntry {
  timestamp?: string;
  level?: string;
  message?: string;
}

interface GeneratedPage {
  id?: number | string;
  title?: string;
  slug?: string;
  role?: string;
  type?: string;
}

interface LandingPageState {
  id?: number | string;
  landing_key?: string;
  title?: string;
  slug?: string;
  landing_type?: 'seo' | 'ads' | string;
  menu_eligible?: boolean;
  primary_keyword?: string;
  subkeywords?: string[];
  keywords?: {
    primary_keyword?: string;
    subkeywords?: string[];
  };
}

interface LandingRunItem {
  key?: string;
  landing_key?: string;
  id?: number;
  title?: string;
  slug?: string;
  landing_type?: string;
  menu_eligible?: boolean;
  primary_keyword?: string;
  subkeywords?: string[];
  sections?: Array<{ layout?: string; override_canonical?: boolean; item_count?: number }>;
  status?: 'pending' | 'running' | 'completed' | 'interrupted' | 'failed' | string;
  post_id?: number;
  error_code?: string;
  error_message?: string;
}

interface LandingRunPlan {
  run_id?: string;
  status?: string;
  total?: number;
  completed?: number;
  current_index?: number;
  processing_active?: boolean;
  lease_expires_at?: number | null;
  items?: LandingRunItem[];
}

interface WizardPageTemplate {
  title?: string;
  slug?: string;
  description?: string;
  role?: string;
  type?: string;
}

interface HomeSectionTemplate {
  layout?: string;
  label?: string;
  description?: string;
  has_repeaters?: boolean;
  has_fillable_fields?: boolean;
  default_item_count?: number;
}

interface LandingSectionOption {
  layout?: string;
  label?: string;
  description?: string;
  is_keyword_layout?: boolean;
  is_default?: boolean;
  default_item_count?: number;
  has_fillable_fields?: boolean;
}

interface HomeSectionPayload {
  layout: string;
  item_count: number;
}

interface LandingSectionPayload {
  layout: string;
  override_canonical: boolean;
  item_count?: number;
}

interface LandingPayloadItem {
  id: number | null;
  landing_key: string;
  title: string;
  slug: string;
  landing_type: 'seo' | 'ads';
  primary_keyword: string;
  subkeywords: string[];
  sections: LandingSectionPayload[];
}

type PagePayloadItem = GeneratePagePayloadItem;

interface WizardState {
  current_step?: string;
  step_status?: Record<string, StepStatus>;
  client_data?: Record<string, unknown>;
  ai_config?: {
    provider?: string;
    provider_label?: string;
    model?: string;
    credential?: {
      has_key?: boolean;
      status?: string;
    };
    has_credentials?: boolean;
    configured_at?: string;
  };
  generated_pages?: GeneratedPage[];
  internal_pages?: Record<string, {
    post_id?: number;
    layouts?: string[];
    status?: string;
    reason?: string;
    updated_at?: string;
  }>;
  home_seo_targeting?: {
    enabled?: boolean;
    primary_keyword?: string;
    secondary_keywords?: string[];
  };
  landing_pages?: LandingPageState[];
  landing_run?: LandingRunPlan | null;
  locked?: boolean;
  unlocked?: boolean;
  completed_flag?: boolean;
  force_unlocked?: boolean;
  controlled_unlock_ui?: boolean;
  has_unlock_marker?: boolean;
  logs?: WizardLogEntry[];
  completion_contract?: CompletionContract;
  internal_page_preview?: InternalPagePreview;
}

interface StepConfig {
  slug: string;
  label: string;
}

interface StepResponse {
  success?: boolean;
  step?: string;
  result?: unknown;
  state?: WizardState;
  message?: string;
}

interface AiModelOption {
  id: string;
  label: string;
}

interface AiModelsResponse {
  success?: boolean;
  provider?: string;
  models?: AiModelOption[];
  credential?: {
    has_key?: boolean;
    status?: string;
  };
}

type NestedFormValue = string | number | NestedFormValue[] | { [key: string]: NestedFormValue };
type NestedFormObject = Record<string, NestedFormValue>;
type NestedFormContainer = NestedFormObject | NestedFormValue[];
type StepPayload = Record<string, unknown>;
type FieldElement = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

interface MediaAttachmentData {
  id?: number | string;
  url?: string;
  sizes?: Record<string, { url?: string }>;
}

interface WpMediaAttachment {
  id?: number | string;
  toJSON?: () => MediaAttachmentData;
}

interface WpMediaSelection {
  first: () => WpMediaAttachment | undefined;
}

interface WpMediaState {
  get: (key: 'selection') => WpMediaSelection;
}

interface WpMediaFrame {
  on: (event: 'select', callback: () => void) => void;
  open: () => void;
  state: () => WpMediaState;
}

interface WpMediaOptions {
  title: string;
  button: { text: string };
  library: { type: string };
  multiple: boolean;
}

interface WpMediaApi {
  media: (options: WpMediaOptions) => WpMediaFrame;
}

declare global {
  interface Window {
    rmsWizardSettings?: WizardSettings;
    wp?: WpMediaApi;
  }
}

(function () {
  'use strict';

  const root = document.querySelector<HTMLElement>('[data-rms-wizard]');
  const settings = getSettings(root);

  if (!root || !settings?.root || !settings?.nonce) {
    showBootstrapError(root, 'Wizard configuration was not found. Refresh the page or rebuild the theme assets.');
    return;
  }

  const steps: StepConfig[] = [
    { slug: 'dependencies', label: 'Dependencies' },
    { slug: 'acf-import', label: 'ACF Import' },
    { slug: 'client-data', label: 'Client Data' },
    { slug: 'generate-pages', label: 'Generate Pages' },
    { slug: 'menu-setup', label: 'Menu Setup' },
    { slug: 'ia-generation', label: 'IA Generation' },
    { slug: 'home-page-builder', label: 'Home Page Builder' },
    { slug: 'landing-page-builder', label: 'Landing Page Builder' },
    { slug: 'internal-page-builder', label: 'Internal Page Builder' },
  ];

  const destructiveWarnings: Record<string, { message: string; checkboxMessage: string }> = {
    'generate-pages': {
      message: 'Existing pages not in your selection will be permanently deleted. This cannot be undone.',
      checkboxMessage: 'Confirm that existing pages can be deleted or replaced before continuing.',
    },
    'menu-setup': {
      message: 'Existing menus and location assignments will be removed and replaced. This cannot be undone.',
      checkboxMessage: 'Confirm that existing menus and location assignments can be replaced before continuing.',
    },
    'landing-page-builder': {
      message: 'Replacing canonical reusable sections overwrites shared copy used by future landings. This cannot be undone.',
      checkboxMessage: 'Confirm replace canonical before overwriting shared reusable sections.',
    },
    'internal-page-builder': {
      message: 'Regenerating or converting a page replaces its current content. Edited ACF fields on unselected pages are kept. This cannot be undone.',
      checkboxMessage: 'Confirm regeneration or conversion for the selected internal pages.',
    },
  };

  const navButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-wizard-step-nav]'));
  const panels = Array.from(root.querySelectorAll<HTMLElement>('[data-wizard-step-panel]'));
  const runButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-wizard-run-step]'));
  const retryButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-wizard-retry-step]'));
  const nextButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-wizard-next-step]'));
  const loadModelButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-wizard-ai-load-models]'));
  const refreshButton = root.querySelector<HTMLButtonElement>('[data-wizard-refresh]');
  const completeButton = root.querySelector<HTMLButtonElement>('[data-wizard-complete]');
  const progressBar = root.querySelector<HTMLElement>('[data-wizard-progress-bar]');
  const progressText = root.querySelector<HTMLElement>('[data-wizard-progress-text]');
  const notice = root.querySelector<HTMLElement>('[data-wizard-notice]');
  const logList = root.querySelector<HTMLElement>('[data-wizard-logs]');

  let state: WizardState = {};
  let activeStep = steps[0].slug;
  let runningStep: string | null = null;

  const setHydrating = (active: boolean): void => {
    root.classList.toggle('is-hydrating', active);
    root.setAttribute('aria-busy', active ? 'true' : 'false');
  };

  const setNotice = (message: string, tone: 'info' | 'success' | 'error' = 'info'): void => {
    if (!notice) return;

    notice.textContent = message;
    notice.hidden = message === '';
    notice.classList.toggle('is-success', tone === 'success');
    notice.classList.toggle('is-error', tone === 'error');
  };

  const statusFor = (step: string): StepStatus => state.step_status?.[step] ?? 'pending';

  const setActiveStep = (step: string): void => {
    activeStep = step;

    navButtons.forEach((button) => {
      const isActive = button.dataset.wizardStepNav === step;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-current', isActive ? 'step' : 'false');
    });

    panels.forEach((panel) => {
      panel.hidden = panel.dataset.wizardStepPanel !== step;
    });
  };

  const updateProgress = (): void => {
    const completed = steps.filter((step) => statusFor(step.slug) === 'complete').length;
    const formatted = formatWizardProgress(state.completion_contract, completed, steps.length);
    const percent = formatted.total > 0 ? Math.round((formatted.completed / formatted.total) * 100) : 0;

    if (progressBar) {
      progressBar.style.width = `${percent}%`;
      progressBar.setAttribute('aria-valuenow', String(percent));
    }

    if (progressText) {
      progressText.textContent = formatted.text;
    }
  };

  const updateNav = (): void => {
    navButtons.forEach((button) => {
      const step = button.dataset.wizardStepNav ?? '';
      const status = displayStepStatus(step, statusFor(step), state.completion_contract);
      const statusNode = button.querySelector<HTMLElement>('[data-wizard-step-status]');

      button.classList.remove('is-pending', 'is-running', 'is-complete', 'is-failed', 'is-optional');
      button.classList.add(`is-${status}`);

      if (statusNode) {
        statusNode.textContent = status;
      }
    });
  };

  const updateLogs = (): void => {
    if (!logList) return;

    const logs = Array.isArray(state.logs) ? state.logs.slice(-20).reverse() : [];

    if (logs.length === 0) {
      logList.innerHTML = '<li class="rms-wizard-log__item">No wizard log entries yet.</li>';
      return;
    }

    logList.innerHTML = logs.map((entry) => {
      const level = escapeHtml(entry.level ?? 'info');
      const message = escapeHtml(entry.message ?? 'No message.');
      const timestamp = escapeHtml(entry.timestamp ?? '');

      return `
        <li class="rms-wizard-log__item">
          <span>${timestamp}</span>
          <span class="rms-wizard-log__level rms-wizard-log__level--${level}">${level}</span>
          <span>${message}</span>
        </li>
      `;
    }).join('');
  };

  const isWizardLocked = (): boolean => Boolean(state.locked) && !state.unlocked && !state.force_unlocked;

  const updateButtons = (): void => {
    const isLocked = isWizardLocked();
    const isHydrating = root.classList.contains('is-hydrating');

    root.classList.toggle('is-unlocked', Boolean(state.unlocked) && !state.force_unlocked);
    root.classList.toggle('is-force-unlocked', Boolean(state.force_unlocked));
    root.classList.toggle('is-locked', isLocked);

    navButtons.forEach((button) => {
      button.disabled = isHydrating || runningStep !== null;
    });

    [...runButtons, ...retryButtons].forEach((button) => {
      const step = button.dataset.wizardRunStep || button.dataset.wizardRetryStep || '';
      const processingBlocks = step === 'landing-page-builder' && isProcessingActive();
      const incompleteLandingRun = step === 'landing-page-builder' && isIncompleteLandingRunStatus(state.landing_run?.status ?? '');
      button.hidden = incompleteLandingRun;
      button.disabled = isHydrating || isLocked || runningStep !== null || statusFor(step) === 'running' || processingBlocks || incompleteLandingRun;
    });

    loadModelButtons.forEach((button) => {
      button.disabled = isHydrating || isLocked || runningStep !== null;
    });

    nextButtons.forEach((button) => {
      const step = button.dataset.wizardNextStep || '';
      const target = button.dataset.wizardNextTarget || nextStepFor(step);
      const canContinue = statusFor(step) === 'complete' && target !== '';

      button.disabled = isHydrating || isLocked || runningStep !== null || !canContinue;
      button.classList.toggle('is-ready', canContinue && !isLocked && !isHydrating);
      button.setAttribute(
        'aria-label',
        canContinue ? `Continue to ${labelFor(target)}` : `Complete ${labelFor(step)} before continuing`
      );
    });

    if (refreshButton) {
      refreshButton.disabled = isHydrating;
    }

    if (completeButton) {
      const allComplete = steps.every((step) => statusFor(step.slug) === 'complete');
      completeButton.disabled = isHydrating || isLocked || runningStep !== null || !allComplete;
    }

    syncLandingSkipAllUi();

    // Disable landing builder editing controls while a run is active/interrupted.
    const landingRunActive = isLandingRunActive();

    root.querySelectorAll<HTMLButtonElement>('[data-wizard-add-landing], [data-wizard-duplicate-landing], [data-wizard-remove-landing]').forEach((button) => {
      button.disabled = isHydrating || isLocked || landingRunActive || runningStep !== null;
    });

    root.querySelectorAll<HTMLInputElement>('[data-wizard-landing-title], [data-wizard-landing-slug], [data-wizard-landing-type], [data-wizard-landing-primary-keyword], [data-wizard-landing-subkeywords]').forEach((field) => {
      field.disabled = isHydrating || isLocked || landingRunActive || runningStep !== null;
    });

    root.querySelectorAll<HTMLButtonElement>('[data-wizard-landing-toggle]').forEach((button) => {
      // Toggle (expand/collapse) stays enabled — it's read-only navigation.
      button.disabled = isHydrating;
    });
  };

  const hydrateInternalPageCards = (): void => {
    const form = root.querySelector<HTMLFormElement>('[data-wizard-internal-page-builder-form]');
    const list = form?.querySelector<HTMLElement>('[data-wizard-internal-cards]');
    const empty = form?.querySelector<HTMLElement>('[data-wizard-internal-empty]');
    const template = form?.querySelector<HTMLTemplateElement>('[data-wizard-internal-card-template]');
    const progress = form?.querySelector<HTMLElement>('[data-wizard-internal-progress]');
    const raw = form?.querySelector<HTMLScriptElement>('[data-wizard-internal-blueprints]')?.textContent ?? '[]';

    if (!form || !list || !template) {
      return;
    }

    let blueprints: Array<{ type: string; label: string; layouts: string[] }> = [];
    try {
      blueprints = JSON.parse(raw) as Array<{ type: string; label: string; layouts: string[] }>;
    } catch {
      blueprints = [];
    }

    const plan = state.internal_pages ?? {};
    let preview = state.internal_page_preview;
    if (!preview) {
      try {
        preview = JSON.parse(form.querySelector<HTMLScriptElement>('[data-wizard-internal-preview]')?.textContent ?? '{}') as InternalPagePreview;
      } catch {
        preview = { types: {}, unmapped: [] };
      }
    }
    if (!preview.plan) {
      preview.plan = plan;
    }
    const views = buildInternalCardViews(blueprints, preview);
    list.replaceChildren();
    let visible = 0;

    views.forEach((view) => {
      const node = template.content.firstElementChild?.cloneNode(true);
      if (!(node instanceof HTMLElement)) {
        return;
      }

      visible += 1;

      node.dataset.wizardPageType = view.type;
      if (view.postId > 0) {
        node.dataset.wizardPostId = String(view.postId);
      }
      const typeInput = node.querySelector<HTMLInputElement>('[data-wizard-internal-type]');
      const labelNode = node.querySelector('[data-wizard-internal-label]');
      const layoutNode = node.querySelector('[data-wizard-internal-layouts]');
      const statusNode = node.querySelector('[data-wizard-internal-status]');
      const overwriteWrap = node.querySelector<HTMLElement>('[data-wizard-internal-overwrite-wrap]');
      const convertWrap = node.querySelector<HTMLElement>('[data-wizard-internal-convert-wrap]');
      const mapWrap = node.querySelector<HTMLElement>('[data-wizard-internal-map-wrap]');
      const editLink = node.querySelector<HTMLAnchorElement>('[data-wizard-internal-edit]');

      if (typeInput) {
        typeInput.value = view.type;
      }
      if (labelNode) {
        labelNode.textContent = view.label;
      }
      if (layoutNode) {
        layoutNode.textContent = view.layouts.join(', ');
      }
      if (statusNode) {
        statusNode.textContent = view.reason && view.status !== 'complete' ? `${view.status} (${view.reason})` : view.status;
      }
      if (overwriteWrap) {
        overwriteWrap.hidden = !view.showOverwrite;
      }
      if (convertWrap) {
        convertWrap.hidden = !view.showConvert;
      }
      if (mapWrap) {
        mapWrap.hidden = !view.mappingNeeded;
        const mapSelect = mapWrap.querySelector<HTMLSelectElement>('[data-wizard-internal-map-type]');
        if (mapSelect && view.mappingNeeded) {
          mapSelect.setAttribute('aria-label', `Assign internal page type for ${view.label}`);
        }
      }
      if (editLink) {
        const canEdit = view.postId > 0;
        editLink.hidden = !canEdit;
        editLink.href = canEdit ? `post.php?post=${view.postId}&action=edit` : '#';
      }

      list.append(node);
    });

    if (empty) {
      empty.hidden = visible > 0;
    }
    if (progress) {
      const tally = internalPageProgress(views);
      progress.textContent = tally.total > 0 ? `${tally.complete} of ${tally.total} internal pages complete` : '';
    }
    if (!form.dataset.wizardMapExclusive) {
      form.dataset.wizardMapExclusive = '1';
      form.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLSelectElement && target.matches('[data-wizard-internal-map-type]')) {
          syncInternalMapExclusivity(form);
        }
      });
    }
    syncInternalMapExclusivity(form);
  };

  const syncInternalMapExclusivity = (form: HTMLFormElement): void => {
    const selects = Array.from(form.querySelectorAll<HTMLSelectElement>('[data-wizard-internal-map-type]'));
    let selections = selects.map((select) => ({
      postId: Number((select.closest('[data-wizard-internal-card]') as HTMLElement | null)?.dataset.wizardPostId ?? 0),
      type: select.value,
    }));
    selections = exclusiveMapSelections(selections);
    selects.forEach((select, index) => {
      const next = selections[index];
      if (next && select.value !== next.type) {
        select.value = next.type;
      }
      const taken = takenMapTypes(selections, next?.postId);
      select.querySelectorAll('option').forEach((option) => {
        option.disabled = option.value !== '' && taken.includes(option.value);
      });
    });
  };

  const render = (options?: { replaceHomeSeo?: boolean }): void => {
    const nextStep = state.current_step && steps.some((step) => step.slug === state.current_step)
      ? state.current_step
      : activeStep;

    renderGeneratedPageControls();
    hydrateIaGenerationForm();
    hydrateHomeSeoTargetingForm(options?.replaceHomeSeo);
    hydrateLandingRowsFromState();
    renderLandingReplaceOptions();
    renderLandingRunProgressFromState();
    hydrateInternalPageCards();
    syncGuidedControlState();
    setActiveStep(nextStep);
    updateNav();
    updateProgress();
    updateLogs();
    updateButtons();

    if (isWizardLocked()) {
      setNotice('The setup wizard is complete and locked. Unlock it for editing or define RMS_WIZARD_FORCE as true for development reruns.', 'success');
    } else if (state.unlocked && !state.force_unlocked) {
      setNotice('The setup wizard is unlocked for editing. Completion state is preserved.', 'info');
    }
  };

  const request = async <T>(path: string, init: RequestInit = {}, attempts = 3): Promise<T> => {
    let lastError: Error | null = null;

    for (let attempt = 1; attempt <= attempts; attempt += 1) {
      try {
        const response = await fetch(`${settings.root}${path.replace(/^\//, '')}`, {
          ...init,
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': settings.nonce,
            ...(init.headers ?? {}),
          },
          credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({})) as T & { message?: string };

        if (!response.ok) {
          throw new Error(data.message || `Request failed with status ${response.status}.`);
        }

        return data;
      } catch (error) {
        lastError = error instanceof Error ? error : new Error('Request failed.');

        if (attempt < attempts) {
          await delay(350 * attempt);
        }
      }
    }

    throw lastError ?? new Error('Request failed.');
  };

  const loadState = async (options?: { replaceHomeSeo?: boolean }): Promise<void> => {
    setHydrating(true);
    try {
      state = await request<WizardState>('state', { method: 'GET' }, 1);

      if (options?.replaceHomeSeo) {
        const form = root.querySelector<HTMLFormElement>('[data-wizard-home-page-builder-form]');

        if (form) {
          clearHomeSeoDirty(form);
        }
      }

      render({ replaceHomeSeo: Boolean(options?.replaceHomeSeo) });
    } catch (error) {
      handleStateLoadError(error);
    } finally {
      setHydrating(false);
      updateButtons();
    }
  };

  const handleStateLoadError = (error: unknown): void => {
    const message = errorMessage(error);

    setNotice(`Unable to load wizard state: ${message}`, 'error');

    if (progressText) {
      progressText.textContent = 'Unable to load progress';
    }

    if (logList) {
      logList.innerHTML = `<li class="rms-wizard-log__item">Unable to load log entries: ${escapeHtml(message)}</li>`;
    }
  };

  const applyStepOutcomePresentation = (step: string, response: StepResponse, successNotice?: string): void => {
    const stepStatus = statusFor(step);
    const responseSuccess = response.success !== false;
    const outcome = presentStepOutcome(stepStatus, responseSuccess);

    if (outcome === 'success') {
      /*
       * Clear the API key input immediately after a successful IA Generation
       * save so the plaintext does not linger in the DOM between the
       * response and the state re-hydration. The saved-key status element
       * already shows masked metadata.
       */
      if (step === 'ia-generation') {
        clearApiKeyInput();
      }

      const nextStep = nextStepFor(step);
      setStepActionStatus(step, 'Step completed.', 'success');
      setNotice(
        successNotice
          ?? (nextStep
            ? `${labelFor(step)} completed successfully. Continue to ${labelFor(nextStep)} when ready.`
            : `${labelFor(step)} completed successfully.`),
        'success'
      );
      return;
    }

    if (outcome === 'progress') {
      /*
       * Issue #27 merge invariant: a healthy landing-page-builder request
       * may finish still `running`. Do not paint that as "Step completed"
       * and do not paint it as a failure.
       */
      const progressMessage = `${labelFor(step)} is still in progress.`;
      setStepActionStatus(step, progressMessage, 'info');
      setNotice(progressMessage, 'info');
      return;
    }

    const summary = stepResultSummary(step, response.result);
    setStepActionStatus(step, summary, 'error');
    setNotice(summary, 'error');
  };

  const runInternalPageBuilderStep = async (payload: StepPayload, intent: LandingClientIntent): Promise<void> => {
    const step = 'internal-page-builder';

    if (payload.skip_all) {
      const skipped = await request<StepResponse>(`steps/${step}/run`, {
        method: 'POST',
        body: JSON.stringify({ skip_all: true }),
      }, 1);

      if (skipped.state) {
        state = skipped.state;
      }

      setStepResult(step, skipped.result ?? skipped);
      applyStepOutcomePresentation(step, skipped, 'Internal pages skipped without changing pages.');
      return;
    }

    const mapPages = Array.isArray(payload.map_pages) ? payload.map_pages as Array<{ post_id: number; type: string }> : [];
    const overwrite = Array.isArray(payload.overwrite) ? payload.overwrite : [];
    const convertLegacy = Array.isArray(payload.convert_legacy) ? payload.convert_legacy : [];

    // Map-only requests are metadata-only: persist identity types without
    // starting/processing, without touching step status, and without a false
    // "Step completed" outcome. The server returns action 'mapped'.
    if (mapPages.length > 0 && overwrite.length === 0 && convertLegacy.length === 0) {
      const mapped = await request<StepResponse>(`steps/${step}/run`, {
        method: 'POST',
        body: JSON.stringify({
          map_pages: mapPages,
          confirm_map: payload.confirm_map,
          confirm_map_types: payload.confirm_map_types,
        }),
      }, 1);

      if (mapped.state) {
        state = mapped.state;
      }

      hydrateInternalPageCards();
      setStepResult(step, mapped.result ?? mapped);
      const outcome = mapOnlyOutcome();
      setStepActionStatus(step, outcome.statusMessage, 'success');
      setNotice(outcome.noticeMessage, 'success');
      return;
    }

    const startResponse = await request<StepResponse>(`steps/${step}/run`, {
      method: 'POST',
      body: JSON.stringify({
        ...payload,
        action: 'start',
        retry_failed: intent === 'retry',
      }),
    }, 1);

    if (startResponse.state) {
      state = startResponse.state;
    }

    hydrateInternalPageCards();
    let guard = 0;
    let lastResponse = startResponse;

    while (statusFor(step) === 'running' && guard < 8) {
      guard += 1;
      setStepActionStatus(step, 'Building the next internal page...', 'info');
      const processed = await request<StepResponse>(`steps/${step}/run`, {
        method: 'POST',
        body: JSON.stringify({
          ...payload,
          action: 'process',
          retry_failed: false,
        }),
      }, 1);

      lastResponse = processed;

      if (processed.state) {
        state = processed.state;
      }

      hydrateInternalPageCards();
    }

    applyStepOutcomePresentation(step, lastResponse);
  };

  const runStep = async (step: string, intent: LandingClientIntent = 'run'): Promise<void> => {
    runningStep = step;
    setActiveStep(step);
    setStepActionStatus(step, 'Running step...', 'info');
    setNotice('Running setup wizard step. Keep this page open.', 'info');

    let persisted = false;
    let validationBlocked = false;
    let canceled = false;

    try {
      const payload = collectPayload(step);

      if (!await ensureDestructiveConfirmation(step, payload)) {
        canceled = true;
        return;
      }

      render();

      if (step === 'landing-page-builder') {
        const decision = resolveLandingClientRequest({
          intent,
          skipAll: Boolean(payload.skip_all),
          incompleteRun: isIncompleteLandingRunStatus(state.landing_run?.status ?? ''),
        });

        if (decision.kind === 'blocked') {
          setStepActionStatus(step, 'An incomplete landing run already exists. Use Resume.', 'error');
          setNotice('An incomplete landing run already exists. Use Resume to continue from the last checkpoint.', 'info');
          return;
        }

        if (decision.kind === 'skip') {
          const response = await request<StepResponse>(`steps/${step}/run`, {
            method: 'POST',
            body: JSON.stringify({ ...payload, ...decision.body }),
          }, 1);

          if (response.state) {
            state = response.state;
          }

          setStepResult(step, response.result ?? response);
          applyStepOutcomePresentation(step, response, 'Landing step completed with skip-all.');
          return;
        }

        if (decision.kind === 'start') {
          await runLandingStepOrchestrated(payload, decision.body);
          return;
        }

        return;
      }

      if (step === 'internal-page-builder') {
        await runInternalPageBuilderStep(payload, intent);
        persisted = true;
        return;
      }

      const response = await request<StepResponse>(`steps/${step}/run`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      if (response.state) {
        state = response.state;
      }

      persisted = true;
      setStepResult(step, response.result ?? response);
      applyStepOutcomePresentation(step, response);
    } catch (error) {
      const message = errorMessage(error);
      validationBlocked = isHomeSeoValidationError(error);

      if (step === 'home-page-builder' && message.includes('Missing required client data:')) {
        setHomeHarnessWarning(message, 'error');
      }

      setStepActionStatus(step, message, 'error');
      setNotice(message, 'error');
    } finally {
      runningStep = null;

      const finish = planStepFinish({ canceled, validationBlocked });

      if (finish.reload) {
        await loadState({
          replaceHomeSeo: shouldReplaceHomeSeoOnStepFinish({
            step,
            persisted,
            validationBlocked,
          }),
        });
      } else {
        if (canceled) {
          setStepActionStatus(step, '', 'info');
          setNotice('', 'info');
        }
        updateButtons();
      }
    }
  };

  const runLandingStepOrchestrated = async (
    payload: StepPayload,
    actionBody: { landing_action: 'start' }
  ): Promise<void> => {
    const step = 'landing-page-builder';

    setStepActionStatus(step, 'Starting landing run...', 'info');
    setNotice('Starting landing run. Each landing is processed one at a time.', 'info');

    try {
      // Start the run plan (persist before AI work). attempts=1 — no retry on non-idempotent start.
      const startPayload = { ...payload, ...actionBody };
      const startResponse = await request<StepResponse>(`steps/${step}/run`, {
        method: 'POST',
        body: JSON.stringify(startPayload),
      }, 1);

      if (startResponse.state) {
        state = startResponse.state;
      }

      const result = startResponse.result as { landing_run?: LandingRunPlan; completed?: number; total?: number; current_title?: string } | undefined;

      if (result?.landing_run) {
        renderLandingRunProgress(result.landing_run, result.current_title ?? '');
      }

      // If the run is already complete after start (all unchanged), finish.
      if (result?.landing_run && result.landing_run.status === 'completed') {
        setStepActionStatus(step, `All ${result.total ?? 0} landings are already complete.`, 'success');
        setNotice(`Landing run complete: ${result.completed ?? 0} of ${result.total ?? 0} landings.`, 'success');
        return;
      }

      // Process items one at a time.
      await processLandingItems(step);
    } catch (error) {
      const message = errorMessage(error);
      setStepActionStatus(step, message, 'error');
      setNotice(message, 'error');
      // On network/HTTP error, load persisted state and offer Resume.
      await loadState();
    }
  };

  const processLandingItems = async (
    step: string,
    actionBody: { landing_action: 'process' } = { landing_action: 'process' }
  ): Promise<void> => {
    const maxIterations = 100; // Safety bound.
    let iteration = 0;

    while (iteration < maxIterations) {
      iteration += 1;

      // attempts=1 — no retry on non-idempotent process. Each request advances at most one item.
      let processResponse: StepResponse;

      try {
        processResponse = await request<StepResponse>(`steps/${step}/run`, {
          method: 'POST',
          body: JSON.stringify(actionBody),
        }, 1);
      } catch (error) {
        // Network/HTTP error: load persisted state once, offer Resume/Retry explicitly.
        const message = errorMessage(error);
        setStepActionStatus(step, message, 'error');
        if (isAlreadyProcessingMessage(message)) {
          setNotice('Landing processing is already active. Refresh state to update.', 'info');
        } else {
          setNotice(`${message} Click Resume to continue from the last checkpoint.`, 'error');
        }
        await loadState();
        return;
      }

      if (processResponse.state) {
        state = processResponse.state;
      }

      const result = processResponse.result as { landing_run?: LandingRunPlan; completed?: number; total?: number; current_title?: string } | undefined;

      if (!result?.landing_run) {
        break;
      }

      renderLandingRunProgress(result.landing_run, result.current_title ?? '');

      if (result.landing_run.status === 'completed') {
        setStepActionStatus(step, `Landing run complete: ${result.completed ?? 0} of ${result.total ?? 0} landings.`, 'success');
        setNotice(`Landing run complete: ${result.completed ?? 0} of ${result.total ?? 0} landings.`, 'success');
        return;
      }

      if (result.landing_run.status === 'interrupted' || result.landing_run.status === 'failed') {
        const failedItem = result.landing_run.items?.find((item) => item.status === 'failed' || item.status === 'interrupted');
        const message = failedItem?.error_message ?? 'The landing run was interrupted.';
        setStepActionStatus(step, message, 'error');
        setNotice(`${message} Click Resume to retry from the last checkpoint.`, 'error');
        return;
      }

      const completed = result.completed ?? 0;
      const total = result.total ?? 0;

      setStepActionStatus(step, `Processing: ${completed} of ${total} completed.`, 'info');
      setNotice(`Landing run in progress: ${completed} of ${total} completed. Current: ${result.current_title ?? ''}.`, 'info');
    }

    setStepActionStatus(step, 'Landing run reached maximum iterations.', 'error');
    setNotice('Landing run reached maximum iterations without completing.', 'error');
  };

  const resumeLandingRun = async (): Promise<void> => {
    const step = 'landing-page-builder';
    const decision = resolveLandingClientRequest({
      intent: 'resume',
      skipAll: false,
      incompleteRun: isIncompleteLandingRunStatus(state.landing_run?.status ?? ''),
    });

    if (decision.kind !== 'process') {
      setStepActionStatus(step, 'No incomplete landing run is available to resume.', 'info');
      setNotice('No incomplete landing run is available to resume.', 'info');
      return;
    }

    runningStep = step;
    setActiveStep(step);
    setStepActionStatus(step, 'Resuming landing run...', 'info');
    setNotice('Resuming landing run from the last checkpoint.', 'info');

    try {
      await processLandingItems(step, decision.body);
    } catch (error) {
      const message = errorMessage(error);
      setStepActionStatus(step, message, 'error');
      setNotice(message, 'error');
      await loadState();
    } finally {
      runningStep = null;
      await loadState();
    }
  };

  const isLandingRunActive = (): boolean => {
    const run = state.landing_run;
    if (!run) {
      return false;
    }
    return run.status === 'running' || run.status === 'pending' || run.status === 'interrupted' || isProcessingActive();
  };

  const isProcessingActive = (): boolean => {
    const run = state.landing_run;
    if (!run) {
      return false;
    }
    if (run.processing_active === true) {
      return true;
    }
    return typeof run.lease_expires_at === 'number' && run.lease_expires_at > Math.floor(Date.now() / 1000);
  };

  const isAlreadyProcessingMessage = (message: string): boolean => (
    message.includes('already running') || message.includes('already active')
  );

  const renderLandingRunProgress = (run: LandingRunPlan, currentTitle: string): void => {
    const progressContainer = root.querySelector<HTMLElement>('[data-wizard-landing-run-progress]');
    const progressText = root.querySelector<HTMLElement>('[data-wizard-landing-run-progress-text]');
    const currentTitleEl = root.querySelector<HTMLElement>('[data-wizard-landing-run-current-title]');
    const resumeButton = root.querySelector<HTMLButtonElement>('[data-wizard-landing-resume]');

    if (!progressContainer) {
      return;
    }

    const completed = run.completed ?? 0;
    const total = run.total ?? 0;
    const processingActive = run.processing_active === true || isProcessingActive();
    const isInterrupted = !processingActive && (run.status === 'interrupted' || run.status === 'failed');
    const canResume = shouldOfferLandingResume({
      processingActive,
      runningStep,
      runStatus: run.status ?? '',
    });

    progressContainer.hidden = false;

    if (progressText) {
      progressText.textContent = processingActive
        ? `${completed} of ${total} completed. Processing is active. Refresh state to update.`
        : `${completed} of ${total} completed`;
    }

    if (currentTitleEl) {
      currentTitleEl.textContent = currentTitle ? `Current: ${currentTitle}` : '';
    }

    if (resumeButton) {
      resumeButton.hidden = !canResume;
      resumeButton.disabled = !canResume;
      resumeButton.setAttribute(
        'aria-label',
        processingActive
          ? `Landing processing is active (${completed} of ${total} completed)`
          : isInterrupted
            ? `Resume interrupted landing run (${completed} of ${total} completed)`
            : `Continue landing run (${completed} of ${total} completed)`
      );
    }
  };

  const renderLandingRunProgressFromState = (): void => {
    const run = state.landing_run;

    if (!run) {
      const progressContainer = root.querySelector<HTMLElement>('[data-wizard-landing-run-progress]');

      if (progressContainer) {
        progressContainer.hidden = true;
      }

      return;
    }

    // Find current item title from items (first pending/interrupted/running).
    const currentTitle = run.items?.find((item) => item.status === 'running' || item.status === 'pending' || item.status === 'interrupted')?.title ?? '';

    renderLandingRunProgress(run, currentTitle);

    // Hydrate all persisted plan rows (including pending/interrupted/failed).
    hydrateLandingRowsFromRunPlan(run);
  };

  /**
   * Hydrate all persisted run plan rows into the landing builder.
   *
   * Full reload/Refresh must restore all plan rows — including
   * pending/interrupted/failed definitions — not only completed landing_pages.
   * Merge/upsert by stable landing key so completed rows keep post IDs
   * while pending plan rows and sections are restored without duplicates.
   */
  const hydrateLandingRowsFromRunPlan = (run: LandingRunPlan): void => {
    const rows = getLandingRowsContainer();

    if (!rows) {
      return;
    }

    const items = Array.isArray(run.items) ? run.items : [];

    if (items.length === 0) {
      return;
    }

    const existingByKey = new Map<string, HTMLElement>();
    const existingRows: LandingHydrationRow[] = [];

    rows.querySelectorAll<HTMLElement>('[data-wizard-landing-row]').forEach((row) => {
      const snapshot = readLandingRowHydration(row);
      const key = (snapshot.landing_key || '').trim();

      if (!key) {
        return;
      }

      existingByKey.set(key, row);
      existingRows.push(snapshot);
    });

    const merged = mergeLandingRowsByKey(existingRows, items);

    merged.forEach((item) => {
      const key = (item.landing_key || item.key || '').trim();
      if (!key) {
        return;
      }

      const existing = existingByKey.get(key);
      if (existing) {
        upsertLandingRowFromPlanItem(existing, item);
        return;
      }

      const rawId = item.id ?? item.post_id;
      const parsedId = typeof rawId === 'number'
        ? rawId
        : (typeof rawId === 'string' ? Number.parseInt(rawId, 10) : NaN);

      addLandingRow({
        id: Number.isFinite(parsedId) && parsedId > 0 ? parsedId : null,
        landing_key: key,
        title: item.title || '',
        slug: item.slug || '',
        landing_type: item.landing_type === 'ads' ? 'ads' : 'seo',
        primary_keyword: item.primary_keyword || '',
        subkeywords: Array.isArray(item.subkeywords) ? item.subkeywords : [],
        sections: sectionsFromPlanItem(item).map((section) => ({
          layout: section.layout || '',
          override_canonical: Boolean(section.override_canonical),
          ...(typeof section.item_count === 'number' ? { item_count: section.item_count } : {}),
        })),
      }, { focus: false });
    });
  };

  const readLandingRowHydration = (row: HTMLElement): LandingHydrationRow => {
    const sections = [...row.querySelectorAll<HTMLElement>('[data-wizard-landing-section-row]')].map((sectionRow) => {
      const rawCount = sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-item-count]')?.value ?? '';
      const parsedCount = Number.parseInt(rawCount, 10);

      return {
        layout: sectionRow.querySelector<HTMLSelectElement>('[data-wizard-landing-section-layout]')?.value ?? '',
        override_canonical: Boolean(sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-override]')?.checked),
        ...(Number.isFinite(parsedCount) ? { item_count: parsedCount } : {}),
      };
    });

    return {
      landing_key: row.querySelector<HTMLInputElement>('[data-wizard-landing-key]')?.value.trim() ?? '',
      id: row.querySelector<HTMLInputElement>('[data-wizard-landing-id]')?.value ?? '',
      title: row.querySelector<HTMLInputElement>('[data-wizard-landing-title]')?.value ?? '',
      slug: row.querySelector<HTMLInputElement>('[data-wizard-landing-slug]')?.value ?? '',
      landing_type: row.querySelector<HTMLSelectElement>('[data-wizard-landing-type]')?.value ?? 'seo',
      primary_keyword: row.querySelector<HTMLInputElement>('[data-wizard-landing-primary-keyword]')?.value ?? '',
      subkeywords: (row.querySelector<HTMLInputElement>('[data-wizard-landing-subkeywords]')?.value ?? '')
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean),
      sections,
    };
  };

  const replaceLandingRowSections = (row: HTMLElement, sections: LandingHydrationRow['sections']): void => {
    const container = row.querySelector<HTMLElement>('[data-wizard-landing-section-rows]');

    if (!container || !Array.isArray(sections) || sections.length === 0) {
      return;
    }

    container.replaceChildren();
    sections.forEach((section) => {
      const layout = typeof section === 'object' && section !== null ? section.layout ?? '' : '';

      if (layout) {
        addLandingSectionRow(row, layout, Boolean(section.override_canonical), section.item_count);
      }
    });
  };

  const upsertLandingRowFromPlanItem = (row: HTMLElement, item: LandingHydrationRow): void => {
    const idInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-id]');
    const titleInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-title]');
    const slugInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-slug]');
    const keywordInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-primary-keyword]');
    const subkeywordsInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-subkeywords]');
    const typeSelect = row.querySelector<HTMLSelectElement>('[data-wizard-landing-type]');
    const rawId = item.id ?? item.post_id;
    const parsedId = typeof rawId === 'number'
      ? rawId
      : (typeof rawId === 'string' ? Number.parseInt(rawId, 10) : NaN);

    if (idInput && (!idInput.value || Number.parseInt(idInput.value, 10) <= 0) && Number.isFinite(parsedId) && parsedId > 0) {
      idInput.value = String(parsedId);
    }

    if (titleInput && !titleInput.value.trim() && item.title) {
      titleInput.value = item.title;
    }

    if (slugInput && !slugInput.value.trim() && item.slug) {
      slugInput.value = item.slug;
    }

    if (keywordInput && !keywordInput.value.trim() && item.primary_keyword) {
      keywordInput.value = item.primary_keyword;
    }

    if (subkeywordsInput && !subkeywordsInput.value.trim() && Array.isArray(item.subkeywords) && item.subkeywords.length > 0) {
      subkeywordsInput.value = item.subkeywords.join(', ');
    }

    if (typeSelect && item.landing_type) {
      typeSelect.value = item.landing_type === 'ads' ? 'ads' : 'seo';
    }

    if (Array.isArray(item.sections) && item.sections.length > 0) {
      replaceLandingRowSections(row, item.sections);
    }

    syncLandingRowSummary(row);
  };

  const completeWizard = async (): Promise<void> => {
    runningStep = 'complete';
    setNotice('Completing setup wizard...', 'info');
    render();

    try {
      const response = await request<StepResponse>('complete', { method: 'POST', body: JSON.stringify({}) });

      if (response.state) {
        state = response.state;
      }

      setNotice('The setup wizard is now complete and locked.', 'success');
    } catch (error) {
      setNotice(errorMessage(error), 'error');
    } finally {
      runningStep = null;
      render();
    }
  };

  const collectPayload = (step: string): StepPayload => {
    const panel = root.querySelector<HTMLElement>(`[data-wizard-step-panel="${step}"]`);
    const form = panel?.querySelector<HTMLFormElement>('form');

    if (step === 'dependencies') {
      return { install: true };
    }

    if (!form) {
      return {};
    }

    if (step === 'client-data') {
      return { client_data: collectFormPayload(form) };
    }

    if (step === 'generate-pages') {
      return collectGeneratePagesPayload(form);
    }

    if (step === 'menu-setup') {
      return collectMenuSetupPayload(form);
    }

    if (step === 'ia-generation') {
      return collectIaGenerationPayload(form);
    }

    if (step === 'home-page-builder') {
      return collectHomePageBuilderPayload(form);
    }

    if (step === 'landing-page-builder') {
      return collectLandingPageBuilderPayload(form);
    }

    if (step === 'internal-page-builder') {
      return collectInternalPageBuilderPayload(form);
    }

    return collectFormPayload(form);
  };

  const collectGeneratePagesPayload = (form: HTMLFormElement): StepPayload => {
    const pages: Record<string, PagePayloadItem> = {};
    const selectedSlugs: string[] = [];
    let homeSlug = '';
    let blogSlug = '';

    form.querySelectorAll<HTMLElement>('[data-wizard-page-row]').forEach((row) => {
      const titleInput = row.querySelector<HTMLInputElement>('[data-wizard-page-title]');
      const slugInput = row.querySelector<HTMLInputElement>('[data-wizard-page-slug]');
      const rawTitle = titleInput?.value.trim() ?? '';
      const rawSlug = slugInput?.value.trim() ?? '';

      if (!rawTitle && !rawSlug) {
        return;
      }

      if (!rawTitle) {
        throw new Error('Every generated page needs a title.');
      }

      const slug = sanitizeSlug(rawSlug);

      if (!slug) {
        throw new Error(`Enter a valid slug for ${rawTitle}.`);
      }

      if (selectedSlugs.includes(slug)) {
        throw new Error(`Page slugs must be unique. "${slug}" is already used.`);
      }

      const isHome = Boolean(row.querySelector<HTMLInputElement>('[data-wizard-page-home]')?.checked);
      const isBlog = Boolean(row.querySelector<HTMLInputElement>('[data-wizard-page-blog]')?.checked);
      const role = isBlog ? 'blog' : (isHome ? 'home' : '');

      pages[slug] = buildGeneratePagePayloadItem({
        slug,
        title: rawTitle,
        role,
        type: row.dataset.wizardPageType ?? '',
      });
      selectedSlugs.push(slug);

      if (isHome) {
        homeSlug = slug;
      }

      if (isBlog) {
        blogSlug = slug;
      }
    });

    if (selectedSlugs.length === 0) {
      throw new Error('Select at least one page to generate.');
    }

    if (!homeSlug || !selectedSlugs.includes(homeSlug)) {
      throw new Error('Please mark one page as Home.');
    }

    if (blogSlug && !selectedSlugs.includes(blogSlug)) {
      throw new Error('Blog page must be one of the selected pages.');
    }

    return {
      pages,
      home_slug: homeSlug,
      blog_slug: blogSlug,
      confirm_cleanup: isDestructiveCheckboxChecked(form, 'generate-pages'),
    };
  };

  const collectMenuSetupPayload = (form: HTMLFormElement): StepPayload => {
    renderGeneratedPageControls();

    if (getGeneratedPages().length === 0) {
      throw new Error('No pages found. Please complete the Generate Pages step first');
    }

    const primary = selectedCheckboxValues(form, 'input[name="primary_page_ids[]"]:checked');
    const mobile = selectedCheckboxValues(form, 'input[name="mobile_page_ids[]"]:checked');

    if (primary.length === 0) {
      throw new Error('Primary menu requires at least one page');
    }

    return {
      primary,
      mobile,
      confirm_cleanup: isDestructiveCheckboxChecked(form, 'menu-setup'),
    };
  };

  const collectIaGenerationPayload = (form: HTMLFormElement): StepPayload => {
    const provider = form.querySelector<HTMLSelectElement>('[data-wizard-ai-provider]')?.value ?? '';
    const apiKey = form.querySelector<HTMLInputElement>('input[name="api_key"]')?.value ?? '';
    const selectedModel = form.querySelector<HTMLSelectElement>('[data-wizard-ai-model]')?.value ?? '';
    const manualModel = form.querySelector<HTMLInputElement>('[data-wizard-ai-model-manual]')?.value.trim() ?? '';
    const model = selectedModel || manualModel;

    if (!provider) {
      throw new Error('Select an AI provider before continuing.');
    }

    if (!model) {
      throw new Error('Select or enter an AI model before continuing.');
    }

    return { provider, api_key: apiKey, model };
  };

  const collectHomePageBuilderPayload = (form: HTMLFormElement): StepPayload => {
    const sections: HomeSectionPayload[] = [];

    form.querySelectorAll<HTMLElement>('[data-wizard-home-section-row]').forEach((row) => {
      const layout = row.querySelector<HTMLInputElement>('[data-wizard-home-section-value]')?.value.trim() ?? '';
      const countInput = row.querySelector<HTMLInputElement>('[data-wizard-home-section-item-count]');

      if (!layout) {
        throw new Error('Every Home section row needs a layout.');
      }

      sections.push({
        layout,
        item_count: normalizeItemCount(countInput?.value ?? '', defaultItemCountForLayout(layout)),
      });
    });

    if (sections.length === 0) {
      setHomeHarnessWarning('Select at least one section for the Home page', 'error');
      throw new Error('Select at least one section for the Home page');
    }

    const missingClientData = missingHomeBuilderClientData();

    if (missingClientData.length > 0) {
      const message = `Missing required client data: ${missingClientData.join(', ')}. Complete your client profile before generating.`;
      setHomeHarnessWarning(message, 'error');
      throw new Error(message);
    }

    setHomeHarnessWarning('', 'info');

    markHomeSeoDirty(form);
    const seoResult = collectHomeSeoTargetingFromForm(form);
    presentHomeSeoCollectionResult(form, seoResult);

    if ('error' in seoResult && seoResult.error) {
      setHomeHarnessWarning(seoResult.message, 'error');
      throw createHomeSeoValidationError(seoResult.message);
    }

    return {
      sections,
      seo_targeting: seoResult.payload,
    };
  };

  const collectInternalPageBuilderPayload = (form: HTMLFormElement): StepPayload => {
    if (form.querySelector<HTMLInputElement>('[data-wizard-internal-skip-all]')?.checked) {
      return { skip_all: true };
    }

    const overwrite: string[] = [];
    const convertLegacy: string[] = [];
    const mapPages: Array<{ post_id: number; type: string }> = [];

    form.querySelectorAll<HTMLElement>('[data-wizard-internal-card]').forEach((card) => {
      const type = card.dataset.wizardPageType || card.querySelector<HTMLInputElement>('[data-wizard-internal-type]')?.value || '';
      const mappedType = card.querySelector<HTMLSelectElement>('[data-wizard-internal-map-type]')?.value || '';
      const postId = Number(card.dataset.wizardPostId ?? 0);
      if (mappedType && postId > 0) {
        mapPages.push({ post_id: postId, type: mappedType });
      }
      if (!type) {
        return;
      }
      if (card.querySelector<HTMLInputElement>('[data-wizard-internal-overwrite]')?.checked) {
        overwrite.push(type);
      }
      if (card.querySelector<HTMLInputElement>('[data-wizard-internal-convert]')?.checked) {
        convertLegacy.push(type);
      }
    });

    return {
      overwrite,
      convert_legacy: convertLegacy,
      map_pages: mapPages,
    };
  };

  const ensureDestructiveConfirmation = async (step: string, payload: StepPayload): Promise<boolean> => {
    if (step === 'internal-page-builder') {
      const mapPages = Array.isArray(payload.map_pages) ? payload.map_pages as Array<{ post_id: number; type: string }> : [];
      if (mapPages.length > 0) {
        const copy = root.querySelector('[data-wizard-internal-map-confirm]')?.textContent?.trim()
          || 'Assigning a page type links that generated page to one Internal Page Builder type. This does not convert or overwrite page content.';
        const dialog = root.querySelector<HTMLElement>('[data-wizard-internal-map-dialog]');
        const message = dialog?.querySelector<HTMLElement>('[data-wizard-internal-map-dialog-message]');
        const accept = dialog?.querySelector<HTMLButtonElement>('[data-wizard-internal-map-dialog-accept]');
        const cancel = dialog ? Array.from(dialog.querySelectorAll<HTMLElement>('[data-wizard-internal-map-dialog-cancel]')) : [];

        if (!dialog || !message || !accept) {
          return false;
        }

        const decision = await openMapConfirmationDialog(
          {
            dialog: dialog as unknown as { hidden: boolean },
            message,
            accept,
            cancel,
            doc: document as unknown as import('./wizard-internal-preview').MapDialogDoc,
          },
          copy,
          mapPages
        );
        if (!decision.confirm_map) {
          return false;
        }
        Object.assign(payload, decision);
      }

      const overwrite = Array.isArray(payload.overwrite) ? payload.overwrite : [];
      const convertLegacy = Array.isArray(payload.convert_legacy) ? payload.convert_legacy : [];
      if (overwrite.length === 0 && convertLegacy.length === 0) {
        return true;
      }

      const warning = destructiveWarnings[step];
      return openConfirmationModal(warning.message);
    }

    if (step === 'landing-page-builder') {
      const replaceMap = (payload.replace_canonical ?? {}) as Record<string, boolean>;
      const needsReplaceConfirm = Object.values(replaceMap).some(Boolean);

      if (!needsReplaceConfirm) {
        return true;
      }

      const warning = destructiveWarnings[step];
      const confirmed = await openConfirmationModal(warning.message);

      if (confirmed) {
        payload.confirm_replace_canonical = true;
      }

      return confirmed;
    }

    const warning = destructiveWarnings[step];

    if (!warning) {
      return true;
    }

    const panel = root.querySelector<HTMLElement>(`[data-wizard-step-panel="${step}"]`);
    const form = panel?.querySelector<HTMLFormElement>('form');

    if (!form || !isDestructiveCheckboxChecked(form, step)) {
      throw new Error(warning.checkboxMessage);
    }

    const confirmed = await openConfirmationModal(warning.message);

    if (confirmed) {
      payload.confirm_cleanup = true;
    }

    return confirmed;
  };

  const collectLandingPageBuilderPayload = (form: HTMLFormElement): StepPayload => {
    const skipAll = Boolean(form.querySelector<HTMLInputElement>('[data-wizard-landing-skip-all]')?.checked);

    if (skipAll) {
      return { skip_all: true, landings: [] };
    }

    const landings: LandingPayloadItem[] = [];
    const seenSlugs = new Set<string>();
    const seenKeys = new Set<string>();
    const seenIds = new Set<number>();

    form.querySelectorAll<HTMLElement>('[data-wizard-landing-row]').forEach((row, index) => {
      const title = row.querySelector<HTMLInputElement>('[data-wizard-landing-title]')?.value.trim() ?? '';
      const slugInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-slug]')?.value.trim() ?? '';
      const slug = sanitizeSlug(slugInput || title);
      const landingTypeRaw = row.querySelector<HTMLSelectElement>('[data-wizard-landing-type]')?.value ?? 'seo';
      const landingType: 'seo' | 'ads' = landingTypeRaw === 'ads' ? 'ads' : 'seo';
      const primaryKeyword = row.querySelector<HTMLInputElement>('[data-wizard-landing-primary-keyword]')?.value.trim() ?? '';
      const subkeywordsRaw = row.querySelector<HTMLInputElement>('[data-wizard-landing-subkeywords]')?.value ?? '';
      const idRaw = row.querySelector<HTMLInputElement>('[data-wizard-landing-id]')?.value.trim() ?? '';
      const landingKey = row.querySelector<HTMLInputElement>('[data-wizard-landing-key]')?.value.trim() || mintLandingKey();
      const id = idRaw ? Number.parseInt(idRaw, 10) : null;

      if (!title && !slug && !primaryKeyword) {
        return;
      }

      if (!title) {
        throw new Error(`Landing ${index + 1} needs a title.`);
      }

      if (!slug) {
        throw new Error(`Landing "${title}" needs a valid slug.`);
      }

      if (!primaryKeyword) {
        throw new Error(`Landing "${title}" requires a primary keyword.`);
      }

      if (seenSlugs.has(slug)) {
        throw new Error(`Landing slugs must be unique. "${slug}" is already used.`);
      }

      if (landingKey && seenKeys.has(landingKey)) {
        throw new Error('Duplicate landing keys are not allowed in one run.');
      }

      if (id && Number.isFinite(id) && id > 0) {
        if (seenIds.has(id)) {
          throw new Error('Duplicate landing ids are not allowed in one run.');
        }

        seenIds.add(id);
      }

      seenSlugs.add(slug);
      seenKeys.add(landingKey);

      const subkeywords = subkeywordsRaw
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean)
        .slice(0, 10);

      const sections: LandingSectionPayload[] = [];

      row.querySelectorAll<HTMLElement>('[data-wizard-landing-section-row]').forEach((sectionRow) => {
        const layout = sectionRow.querySelector<HTMLSelectElement>('[data-wizard-landing-section-layout]')?.value.trim() ?? '';
        const override = Boolean(sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-override]')?.checked);
        const rawCount = sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-item-count]')?.value ?? '';
        const parsedCount = Number.parseInt(rawCount, 10);

        if (!layout) {
          return;
        }

        sections.push({
          layout,
          override_canonical: override,
          ...(Number.isFinite(parsedCount) ? { item_count: parsedCount } : {}),
        });
      });

      if (sections.length === 0) {
        throw new Error(`Landing "${title}" needs at least one section.`);
      }

      landings.push({
        id: id && Number.isFinite(id) && id > 0 ? id : null,
        landing_key: landingKey,
        title,
        slug,
        landing_type: landingType,
        primary_keyword: primaryKeyword,
        subkeywords,
        sections,
      });
    });

    if (landings.length === 0) {
      throw new Error('Add at least one landing page or enable skip-all to complete without landings.');
    }

    const replaceCanonical: Record<string, boolean> = {};

    form.querySelectorAll<HTMLInputElement>('[data-wizard-landing-replace-layout]:checked').forEach((input) => {
      const layout = input.value.trim();

      if (layout) {
        replaceCanonical[layout] = true;
      }
    });

    return {
      landings,
      replace_canonical: replaceCanonical,
      skip_all: false,
    };
  };

  const openConfirmationModal = (message: string): Promise<boolean> => {
    const modal = root.querySelector<HTMLElement>('[data-wizard-confirm-dialog]');
    const messageTarget = modal?.querySelector<HTMLElement>('[data-wizard-confirm-message]');
    const acceptButton = modal?.querySelector<HTMLButtonElement>('[data-wizard-confirm-accept]');
    const cancelControls = modal ? Array.from(modal.querySelectorAll<HTMLElement>('[data-wizard-confirm-cancel]')) : [];

    if (!modal || !messageTarget || !acceptButton) {
      return Promise.resolve(window.confirm(message));
    }

    const previousFocus = document.activeElement;
    messageTarget.textContent = message;
    modal.hidden = false;
    acceptButton.focus();

    return new Promise((resolve) => {
      let onAccept: () => void;
      let onCancel: () => void;
      let onKeyDown: (event: KeyboardEvent) => void;
      const close = (confirmed: boolean): void => {
        modal.hidden = true;
        acceptButton.removeEventListener('click', onAccept);
        cancelControls.forEach((control) => control.removeEventListener('click', onCancel));
        document.removeEventListener('keydown', onKeyDown);

        if (previousFocus instanceof HTMLElement) {
          previousFocus.focus();
        }

        resolve(confirmed);
      };
      onAccept = (): void => close(true);
      onCancel = (): void => close(false);
      onKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') {
          close(false);
        }
      };

      acceptButton.addEventListener('click', onAccept);
      cancelControls.forEach((control) => control.addEventListener('click', onCancel));
      document.addEventListener('keydown', onKeyDown);
    });
  };

  const selectedCheckboxValues = (container: ParentNode, selector: string): string[] => (
    Array.from(container.querySelectorAll<HTMLInputElement>(selector)).map((input) => input.value).filter(Boolean)
  );

  const isDestructiveCheckboxChecked = (form: HTMLFormElement, step: string): boolean => (
    Boolean(form.querySelector<HTMLInputElement>(`[data-wizard-destructive-confirm="${step}"]`)?.checked)
  );

  const renderGeneratedPageControls = (): void => {
    const pages = getGeneratedPages();
    const signature = pages.map((page) => `${generatedPageValue(page)}:${page.title ?? ''}:${page.slug ?? ''}`).join('|') || 'empty';
    const emptyNotice = root.querySelector<HTMLElement>('[data-wizard-menu-empty]');
    const builder = root.querySelector<HTMLElement>('[data-wizard-menu-builder]');
    const containers = Array.from(root.querySelectorAll<HTMLElement>('[data-wizard-menu-list]'));

    if (emptyNotice) {
      emptyNotice.hidden = pages.length > 0;
    }

    if (builder) {
      builder.hidden = pages.length === 0;
    }

    containers.forEach((container) => {
      if (container.dataset.wizardRenderedPages === signature) {
        return;
      }

      container.dataset.wizardRenderedPages = signature;
      container.replaceChildren();

      if (pages.length === 0) {
        const message = document.createElement('p');
        message.className = 'rms-wizard-menu-list__empty';
        message.textContent = 'No generated pages are available yet.';
        container.append(message);
        return;
      }

      const menuType = container.dataset.wizardMenuList === 'mobile' ? 'mobile' : 'primary';
      const fieldName = menuType === 'mobile' ? 'mobile_page_ids[]' : 'primary_page_ids[]';

      pages.forEach((page) => {
        const value = generatedPageValue(page);

        if (!value) {
          return;
        }

        const label = document.createElement('label');
        label.className = 'rms-wizard-menu-page';

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = fieldName;
        input.value = value;
        input.checked = true;

        const text = document.createElement('span');
        const title = document.createElement('strong');
        const meta = document.createElement('small');
        title.textContent = page.title || titleFromSlug(page.slug ?? value);
        meta.textContent = [page.slug ? `/${page.slug}/` : '', page.role ? `${page.role} page` : 'generated page'].filter(Boolean).join(' · ');
        text.append(title, meta);

        label.append(input, text);
        container.append(label);
      });
    });
  };

  /**
   * Pages available to the Menu Setup UI.
   *
   * Merges standard `state.generated_pages` with menu-eligible SEO landings
   * from `state.landing_pages`. Ads and `menu_eligible=false` landings are
   * excluded via `getMenuEligibleLandings()` so they never appear as menu options.
   */
  const getGeneratedPages = (): GeneratedPage[] => {
    const standard = Array.isArray(state.generated_pages)
      ? state.generated_pages.filter((page) => Boolean(generatedPageValue(page)))
      : [];
    const landings = getMenuEligibleLandings().map((landing) => ({
      id: landing.id,
      title: landing.title,
      slug: landing.slug,
      role: landing.landing_type === 'seo' ? 'landing-seo' : 'landing',
    }));

    const seen = new Set<string>();
    const merged: GeneratedPage[] = [];

    [...standard, ...landings].forEach((page) => {
      const value = generatedPageValue(page);

      if (!value || seen.has(value)) {
        return;
      }

      seen.add(value);
      merged.push(page);
    });

    return merged;
  };

  /**
   * SEO landings that may appear in Menu Setup.
   *
   * Filters `state.landing_pages` to `landing_type=seo` with `menu_eligible`
   * true (default true for SEO when the flag is absent). Ads and ineligible
   * rows are always excluded. Requires a resolvable id/slug via
   * `generatedPageValue()`.
   */
  const getMenuEligibleLandings = (): LandingPageState[] => {
    if (!Array.isArray(state.landing_pages)) {
      return [];
    }

    return state.landing_pages.filter((landing) => {
      const type = landing.landing_type === 'ads' ? 'ads' : 'seo';
      const eligible = typeof landing.menu_eligible === 'boolean' ? landing.menu_eligible : type === 'seo';

      return type === 'seo' && eligible && Boolean(generatedPageValue(landing));
    });
  };

  const generatedPageValue = (page: GeneratedPage | LandingPageState): string => {
    const id = typeof page.id === 'number' || typeof page.id === 'string' ? String(page.id) : '';

    return id || sanitizeSlug(page.slug ?? '');
  };

  const mintLandingKey = (): string => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
      return `lp_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`;
    }

    return `lp_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
  };

  const readLandingSectionOptions = (): LandingSectionOption[] => {
    const source = root.querySelector<HTMLScriptElement>('script[data-wizard-landing-sections]');

    if (!source?.textContent) {
      return [];
    }

    try {
      const parsed = JSON.parse(source.textContent) as unknown;

      return Array.isArray(parsed)
        ? parsed.filter((item): item is LandingSectionOption => typeof item === 'object' && item !== null)
        : [];
    } catch (_error) {
      return [];
    }
  };

  const readLandingDefaultLayouts = (): string[] => {
    const source = root.querySelector<HTMLScriptElement>('script[data-wizard-landing-default-layouts]');

    if (!source?.textContent) {
      return ['hero', 'seo-content', 'vision-mission-v1', 'badges', 'portfolio-v1', 'seo-content', 'testimonials-v1', 'seo-content'];
    }

    try {
      const parsed = JSON.parse(source.textContent) as unknown;

      return Array.isArray(parsed) ? parsed.map(String).filter(Boolean) : [];
    } catch (_error) {
      return ['hero', 'seo-content'];
    }
  };

  const getLandingRowsContainer = (): HTMLElement | null => root.querySelector<HTMLElement>('[data-wizard-landing-rows]');

  const getLandingRowTemplate = (): HTMLTemplateElement | null => root.querySelector<HTMLTemplateElement>('template[data-wizard-landing-row-template]');

  const getLandingSectionRowTemplate = (): HTMLTemplateElement | null => root.querySelector<HTMLTemplateElement>('template[data-wizard-landing-section-row-template]');

  const hydrateLandingRowsFromState = (): void => {
    const rows = getLandingRowsContainer();

    if (!rows) {
      return;
    }

    // Never clobber in-progress edits; only seed when the builder is empty.
    if (rows.querySelector('[data-wizard-landing-row]')) {
      syncLandingBuilderEmptyState();
      return;
    }

    const landings = Array.isArray(state.landing_pages) ? state.landing_pages : [];

    if (landings.length === 0) {
      syncLandingBuilderEmptyState();
      return;
    }

    landings.forEach((landing) => {
      const rawId = landing.id;
      const parsedId = typeof rawId === 'number'
        ? rawId
        : (typeof rawId === 'string' ? Number.parseInt(rawId, 10) : NaN);

      addLandingRow({
        id: Number.isFinite(parsedId) && parsedId > 0 ? parsedId : null,
        landing_key: landing.landing_key || mintLandingKey(),
        title: landing.title || '',
        slug: landing.slug || '',
        landing_type: landing.landing_type === 'ads' ? 'ads' : 'seo',
        primary_keyword: landing.primary_keyword || landing.keywords?.primary_keyword || '',
        subkeywords: landing.subkeywords || landing.keywords?.subkeywords || [],
      }, { focus: false });
    });
  };

  const setLandingRowExpanded = (row: HTMLElement, expanded: boolean): void => {
    const toggle = row.querySelector<HTMLButtonElement>('[data-wizard-landing-toggle]');
    const panel = row.querySelector<HTMLElement>('[data-wizard-landing-panel]');

    if (toggle) {
      toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    if (panel) {
      panel.hidden = !expanded;
    }

    row.classList.toggle('is-collapsed', !expanded);
  };

  const toggleLandingRow = (button: HTMLButtonElement): void => {
    const row = button.closest<HTMLElement>('[data-wizard-landing-row]');

    if (!row) {
      return;
    }

    setLandingRowExpanded(row, button.getAttribute('aria-expanded') !== 'true');
  };

  const syncLandingRowSummary = (row: HTMLElement, index = Number.parseInt(row.dataset.wizardLandingIndex ?? '', 10)): void => {
    const resolvedIndex = Number.isFinite(index) ? index : 0;
    const title = row.querySelector<HTMLInputElement>('[data-wizard-landing-title]')?.value.trim() ?? '';
    const landingType = row.querySelector<HTMLSelectElement>('[data-wizard-landing-type]')?.value === 'ads' ? 'ads' : 'seo';
    const keyword = row.querySelector<HTMLInputElement>('[data-wizard-landing-primary-keyword]')?.value.trim() ?? '';
    const heading = row.querySelector<HTMLElement>('[data-wizard-landing-heading]');
    const typeSummary = row.querySelector<HTMLElement>('[data-wizard-landing-type-summary]');
    const keywordSummary = row.querySelector<HTMLElement>('[data-wizard-landing-keyword-summary]');

    if (heading) {
      heading.textContent = title || `Landing ${resolvedIndex + 1}`;
    }

    if (typeSummary) {
      typeSummary.textContent = landingType === 'ads' ? 'Ads' : 'SEO';
    }

    if (keywordSummary) {
      keywordSummary.textContent = keyword || 'No primary keyword';
    }

    row.classList.toggle('is-ads', landingType === 'ads');
  };

  const remapLandingIndexedAttribute = (value: string, index: number): string => (
    value.replace(/rms-wizard-landing-([a-z-]+)-\d+/g, `rms-wizard-landing-$1-${index}`)
  );

  const addLandingRow = (data: Partial<LandingPayloadItem> = {}, options: { focus?: boolean } = {}): HTMLElement | null => {
    const rows = getLandingRowsContainer();
    const template = getLandingRowTemplate();

    if (!rows || !template) {
      return null;
    }

    const index = rows.querySelectorAll('[data-wizard-landing-row]').length;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
    const row = wrapper.firstElementChild instanceof HTMLElement ? wrapper.firstElementChild : null;

    if (!row) {
      return null;
    }

    const idInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-id]');
    const keyInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-key]');
    const titleInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-title]');
    const slugInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-slug]');
    const typeSelect = row.querySelector<HTMLSelectElement>('[data-wizard-landing-type]');
    const keywordInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-primary-keyword]');
    const subkeywordsInput = row.querySelector<HTMLInputElement>('[data-wizard-landing-subkeywords]');

    if (idInput) {
      idInput.value = data.id && Number(data.id) > 0 ? String(data.id) : '';
    }

    if (keyInput) {
      keyInput.value = data.landing_key || mintLandingKey();
    }

    if (titleInput) {
      titleInput.value = data.title || '';
    }

    if (slugInput) {
      slugInput.value = sanitizeSlug(data.slug || data.title || '');
      slugInput.dataset.wizardSlugAuto = data.slug ? '0' : '1';
    }

    if (typeSelect) {
      typeSelect.value = data.landing_type === 'ads' ? 'ads' : 'seo';
    }

    if (keywordInput) {
      keywordInput.value = data.primary_keyword || '';
    }

    if (subkeywordsInput) {
      subkeywordsInput.value = (data.subkeywords || []).join(', ');
    }

    rows.append(row);
    setLandingRowExpanded(row, false);
    syncLandingRowSummary(row, index);

    const sectionLayouts = (data.sections && data.sections.length > 0)
      ? data.sections.map((section) => section.layout)
      : readLandingDefaultLayouts();

    sectionLayouts.forEach((layout, sectionIndex) => {
      const override = Boolean(data.sections?.[sectionIndex]?.override_canonical);
      addLandingSectionRow(row, layout, override, data.sections?.[sectionIndex]?.item_count);
    });

    reindexLandingRows();
    syncLandingBuilderEmptyState();
    syncLandingSkipAllUi();

    if (options.focus !== false) {
      row.querySelector<HTMLButtonElement>('[data-wizard-landing-toggle]')?.focus();
    }

    return row;
  };

  const addLandingSectionRow = (landingRow: HTMLElement, layout = '', override = false, itemCount?: number): void => {
    const container = landingRow.querySelector<HTMLElement>('[data-wizard-landing-section-rows]');
    const template = getLandingSectionRowTemplate();

    if (!container || !template) {
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML
      .replaceAll('__LINDEX__', '0')
      .replaceAll('__SINDEX__', String(container.querySelectorAll('[data-wizard-landing-section-row]').length))
      .trim();
    const sectionRow = wrapper.firstElementChild instanceof HTMLElement ? wrapper.firstElementChild : null;

    if (!sectionRow) {
      return;
    }

    const select = sectionRow.querySelector<HTMLSelectElement>('[data-wizard-landing-section-layout]');
    const overrideInput = sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-override]');
    const countInput = sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-item-count]');
    const options = readLandingSectionOptions();

    if (select) {
      select.replaceChildren();
      options.forEach((option) => {
        if (!option.layout) {
          return;
        }

        const opt = new Option(
          option.label ? `${option.label} (${option.layout})` : option.layout,
          option.layout
        );
        select.append(opt);
      });

      if (layout) {
        select.value = layout;
      } else if (options[0]?.layout) {
        select.value = options[0].layout;
      }

      syncLandingSectionOverrideVisibility(sectionRow);
    }

    if (overrideInput) {
      overrideInput.checked = override;
    }

    if (countInput && typeof itemCount === 'number' && Number.isFinite(itemCount)) {
      countInput.value = String(itemCount);
    }

    container.append(sectionRow);
    reindexLandingRows();
  };

  const syncLandingSectionOverrideVisibility = (sectionRow: HTMLElement): void => {
    const layout = sectionRow.querySelector<HTMLSelectElement>('[data-wizard-landing-section-layout]')?.value ?? '';
    const overrideLabel = sectionRow.querySelector<HTMLElement>('.rms-wizard-landing-section-override');
    const isKeyword = layout === 'hero' || layout === 'seo-content';

    if (overrideLabel) {
      overrideLabel.hidden = isKeyword;
    }

    if (isKeyword) {
      const overrideInput = sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-override]');

      if (overrideInput) {
        overrideInput.checked = false;
      }
    }
  };

  const duplicateLandingRow = (button: HTMLButtonElement): void => {
    const source = button.closest<HTMLElement>('[data-wizard-landing-row]');

    if (!source) {
      return;
    }

    const title = source.querySelector<HTMLInputElement>('[data-wizard-landing-title]')?.value.trim() ?? '';
    const slug = source.querySelector<HTMLInputElement>('[data-wizard-landing-slug]')?.value.trim() ?? '';
    const landingType = source.querySelector<HTMLSelectElement>('[data-wizard-landing-type]')?.value === 'ads' ? 'ads' : 'seo';
    const primaryKeyword = source.querySelector<HTMLInputElement>('[data-wizard-landing-primary-keyword]')?.value.trim() ?? '';
    const subkeywords = (source.querySelector<HTMLInputElement>('[data-wizard-landing-subkeywords]')?.value ?? '')
      .split(',')
      .map((value) => value.trim())
      .filter(Boolean);
    const sections: LandingSectionPayload[] = Array.from(source.querySelectorAll<HTMLElement>('[data-wizard-landing-section-row]')).map((sectionRow) => {
      const rawCount = sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-item-count]')?.value ?? '';
      const parsedCount = Number.parseInt(rawCount, 10);

      return {
        layout: sectionRow.querySelector<HTMLSelectElement>('[data-wizard-landing-section-layout]')?.value.trim() ?? '',
        override_canonical: Boolean(sectionRow.querySelector<HTMLInputElement>('[data-wizard-landing-section-override]')?.checked),
        ...(Number.isFinite(parsedCount) ? { item_count: parsedCount } : {}),
      };
    }).filter((section) => section.layout);

    // Duplicate must clear id and mint a new landing_key.
    addLandingRow({
      id: null,
      landing_key: mintLandingKey(),
      title: title ? `${title} copy` : '',
      slug: slug ? `${slug}-copy` : '',
      landing_type: landingType,
      primary_keyword: primaryKeyword,
      subkeywords,
      sections,
    });
  };

  const removeLandingRow = (button: HTMLButtonElement): void => {
    button.closest<HTMLElement>('[data-wizard-landing-row]')?.remove();
    reindexLandingRows();
    syncLandingBuilderEmptyState();
  };

  const reindexLandingRows = (): void => {
    const rows = Array.from(root.querySelectorAll<HTMLElement>('[data-wizard-landing-row]'));

    rows.forEach((row, index) => {
      row.dataset.wizardLandingIndex = String(index);

      syncLandingRowSummary(row, index);

      row.querySelectorAll<FieldElement>('[name]').forEach((field) => {
        field.name = field.name
          .replace(/landings\[\d+\]/g, `landings[${index}]`)
          .replace(/landings\[__INDEX__\]/g, `landings[${index}]`);
      });

      row.querySelectorAll<HTMLElement>('[id]').forEach((element) => {
        element.id = remapLandingIndexedAttribute(element.id, index);
      });

      row.querySelectorAll<HTMLLabelElement>('label[for]').forEach((label) => {
        label.htmlFor = remapLandingIndexedAttribute(label.htmlFor, index);
      });

      row.querySelectorAll<HTMLElement>('[aria-controls]').forEach((element) => {
        const controls = element.getAttribute('aria-controls');

        if (controls) {
          element.setAttribute('aria-controls', remapLandingIndexedAttribute(controls, index));
        }
      });

      row.querySelectorAll<HTMLElement>('[aria-labelledby]').forEach((element) => {
        const labelledBy = element.getAttribute('aria-labelledby');

        if (labelledBy) {
          element.setAttribute('aria-labelledby', remapLandingIndexedAttribute(labelledBy, index));
        }
      });

      Array.from(row.querySelectorAll<HTMLElement>('[data-wizard-landing-section-row]')).forEach((sectionRow, sectionIndex) => {
        sectionRow.querySelectorAll<FieldElement>('[name]').forEach((field) => {
          field.name = field.name
            .replace(/landings\[\d+\]/g, `landings[${index}]`)
            .replace(/sections\[\d+\]/g, `sections[${sectionIndex}]`)
            .replace(/__LINDEX__/g, String(index))
            .replace(/__SINDEX__/g, String(sectionIndex));
        });
      });
    });
  };

  const syncLandingBuilderEmptyState = (): void => {
    const hasRows = Boolean(root.querySelector('[data-wizard-landing-row]'));
    const emptyMessage = root.querySelector<HTMLElement>('[data-wizard-landing-empty]');

    if (emptyMessage) {
      emptyMessage.hidden = hasRows;
    }
  };

  const syncLandingSkipAllUi = (): void => {
    const form = root.querySelector<HTMLFormElement>('[data-wizard-landing-page-builder-form]');
    const skip = form?.querySelector<HTMLInputElement>('[data-wizard-landing-skip-all]');
    const builder = form?.querySelector<HTMLElement>('[data-wizard-landing-builder]');
    const toolbar = form?.querySelector<HTMLElement>('.rms-wizard-landing-toolbar');
    const replacePanel = form?.querySelector<HTMLElement>('[data-wizard-landing-replace-panel]');
    const skipped = Boolean(skip?.checked);

    if (builder) {
      builder.hidden = skipped;
    }

    if (toolbar) {
      toolbar.hidden = skipped;
    }

    if (replacePanel) {
      replacePanel.hidden = skipped;
    }

    form?.classList.toggle('is-skip-all', skipped);
  };

  const renderLandingReplaceOptions = (): void => {
    const list = root.querySelector<HTMLElement>('[data-wizard-landing-replace-list]');

    if (!list || list.dataset.wizardRendered === '1') {
      return;
    }

    const options = readLandingSectionOptions().filter((option) => option.layout && !option.is_keyword_layout);
    list.replaceChildren();

    options.forEach((option) => {
      if (!option.layout) {
        return;
      }

      const label = document.createElement('label');
      label.className = 'rms-wizard-landing-replace-option';

      const input = document.createElement('input');
      input.type = 'checkbox';
      input.value = option.layout;
      input.dataset.wizardLandingReplaceLayout = '1';
      input.name = `replace_canonical[${option.layout}]`;

      const text = document.createElement('span');
      text.textContent = option.label ? `${option.label} (${option.layout})` : option.layout;

      label.append(input, text);
      list.append(label);
    });

    list.dataset.wizardRendered = '1';
  };

  const syncGuidedControlState = (): void => {
    root.querySelectorAll<HTMLElement>('[data-wizard-page-row]').forEach((row) => {
      const isHome = Boolean(row.querySelector<HTMLInputElement>('[data-wizard-page-home]')?.checked);
      const isBlog = Boolean(row.querySelector<HTMLInputElement>('[data-wizard-page-blog]')?.checked);

      row.classList.toggle('is-home', isHome);
      row.classList.toggle('is-blog', isBlog);
    });

    syncPageBuilderEmptyState();

    syncHomeSectionBuilderEmptyState();
  };

  const sanitizeSlug = (value: string): string => value.trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');

  const titleFromSlug = (value: string): string => (
    value.split(/[-_]/).filter(Boolean).map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`).join(' ') || 'Page'
  );

  const getPageRowsContainer = (): HTMLElement | null => root.querySelector<HTMLElement>('[data-wizard-page-rows]');

  const getPageRowTemplate = (): HTMLTemplateElement | null => root.querySelector<HTMLTemplateElement>('template[data-wizard-page-row-template]');

  const addPageRow = (data: WizardPageTemplate = {}): HTMLElement | null => {
    const rows = getPageRowsContainer();
    const template = getPageRowTemplate();

    if (!rows || !template) {
      return null;
    }

    const index = rows.querySelectorAll('[data-wizard-page-row]').length;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
    const row = wrapper.firstElementChild instanceof HTMLElement ? wrapper.firstElementChild : null;

    if (!row) {
      return null;
    }

    const title = data.title?.trim() ?? '';
    const slug = sanitizeSlug(data.slug ?? title);
    const titleInput = row.querySelector<HTMLInputElement>('[data-wizard-page-title]');
    const slugInput = row.querySelector<HTMLInputElement>('[data-wizard-page-slug]');
    const homeRadio = row.querySelector<HTMLInputElement>('[data-wizard-page-home]');
    const blogRadio = row.querySelector<HTMLInputElement>('[data-wizard-page-blog]');

    if (titleInput) {
      titleInput.value = title;
    }

    if (slugInput) {
      slugInput.value = slug;
      slugInput.dataset.wizardSlugAuto = '1';
    }

    row.dataset.wizardPageType = sanitizeWizardPageType(data.type ?? '');

    rows.append(row);
    updatePageRowRadioValues(row);

    if (homeRadio && data.role === 'home') {
      homeRadio.checked = true;
    }

    if (blogRadio && data.role === 'blog') {
      blogRadio.checked = true;
    }

    reindexPageRows();
    syncGuidedControlState();
    titleInput?.focus();

    return row;
  };

  const addCommonPageRows = (): void => {
    const commonPages = readCommonPageTemplates();
    const existingSlugs = new Set(getCurrentPageSlugs());
    let hasHome = Boolean(root.querySelector<HTMLInputElement>('[data-wizard-page-home]:checked'));
    let hasBlog = Boolean(root.querySelector<HTMLInputElement>('[data-wizard-page-blog]:checked'));
    let added = 0;

    commonPages.forEach((page) => {
      const slug = sanitizeSlug(page.slug ?? page.title ?? '');
      let role = page.role ?? '';

      if (!slug || existingSlugs.has(slug)) {
        return;
      }

      if (role === 'home' && hasHome) {
        role = '';
      }

      if (role === 'blog' && hasBlog) {
        role = '';
      }

      addPageRow({ ...page, slug, role });

      if (role === 'home') {
        hasHome = true;
      }

      if (role === 'blog') {
        hasBlog = true;
      }

      existingSlugs.add(slug);
      added += 1;
    });

    setNotice(
      added > 0
        ? `Added ${added} common page${added === 1 ? '' : 's'}. You can edit or remove them before generating pages.`
        : 'Common pages are already in the list.',
      'info'
    );
  };

  const readCommonPageTemplates = (): WizardPageTemplate[] => {
    const source = root.querySelector<HTMLScriptElement>('script[data-wizard-common-pages]');

    if (!source?.textContent) {
      return [];
    }

    try {
      const parsed = JSON.parse(source.textContent) as unknown;

      if (!Array.isArray(parsed)) {
        return [];
      }

      return parsed.filter((item): item is WizardPageTemplate => typeof item === 'object' && item !== null);
    } catch (_error) {
      return [];
    }
  };

  const getCurrentPageSlugs = (): string[] => (
    Array.from(root.querySelectorAll<HTMLElement>('[data-wizard-page-row]'))
      .map((row) => sanitizeSlug(row.querySelector<HTMLInputElement>('[data-wizard-page-slug]')?.value ?? ''))
      .filter(Boolean)
  );

  const removePageRow = (button: HTMLButtonElement): void => {
    const row = button.closest<HTMLElement>('[data-wizard-page-row]');
    const wasBlog = Boolean(row?.querySelector<HTMLInputElement>('[data-wizard-page-blog]')?.checked);

    row?.remove();

    if (wasBlog) {
      const noBlog = root.querySelector<HTMLInputElement>('[data-wizard-page-no-blog]');

      if (noBlog) {
        noBlog.checked = true;
      }
    }

    reindexPageRows();
    syncGuidedControlState();
  };

  const updatePageRowFromTitle = (input: HTMLInputElement): void => {
    const row = input.closest<HTMLElement>('[data-wizard-page-row]');
    const slugInput = row?.querySelector<HTMLInputElement>('[data-wizard-page-slug]');

    if (!row || !slugInput) {
      return;
    }

    if (slugInput.dataset.wizardSlugAuto !== '0') {
      slugInput.value = sanitizeSlug(input.value);
    }

    updatePageRowRadioValues(row);
  };

  const updatePageRowFromSlug = (input: HTMLInputElement, forceSanitize = false): void => {
    const row = input.closest<HTMLElement>('[data-wizard-page-row]');
    input.dataset.wizardSlugAuto = '0';

    if (forceSanitize) {
      input.value = sanitizeSlug(input.value);
    }

    if (row) {
      updatePageRowRadioValues(row);
    }
  };

  const updatePageRowRadioValues = (row: HTMLElement): void => {
    const title = row.querySelector<HTMLInputElement>('[data-wizard-page-title]')?.value ?? '';
    const slug = sanitizeSlug(row.querySelector<HTMLInputElement>('[data-wizard-page-slug]')?.value ?? title);

    row.querySelectorAll<HTMLInputElement>('[data-wizard-page-home], [data-wizard-page-blog]').forEach((radio) => {
      radio.value = slug;
    });
  };

  const reindexPageRows = (): void => {
    const rows = Array.from(root.querySelectorAll<HTMLElement>('[data-wizard-page-row]'));

    rows.forEach((row, index) => {
      row.dataset.wizardPageIndex = String(index);

      row.querySelectorAll<FieldElement>('[name]').forEach((field) => {
        field.name = field.name.replace(/pages\[\d+\]/g, `pages[${index}]`);
      });

      row.querySelectorAll<HTMLElement>('[id]').forEach((element) => {
        element.id = element.id.replace(/rms-wizard-page-(title|slug)-\d+/g, `rms-wizard-page-$1-${index}`);
      });

      row.querySelectorAll<HTMLLabelElement>('label[for]').forEach((label) => {
        label.htmlFor = label.htmlFor.replace(/rms-wizard-page-(title|slug)-\d+/g, `rms-wizard-page-$1-${index}`);
      });

      updatePageRowRadioValues(row);
    });
  };

  const syncPageBuilderEmptyState = (): void => {
    const hasRows = Boolean(root.querySelector('[data-wizard-page-row]'));
    const emptyMessage = root.querySelector<HTMLElement>('[data-wizard-page-empty]');

    if (emptyMessage) {
      emptyMessage.hidden = hasRows;
    }
  };

  const getHomeSectionRowsContainer = (): HTMLElement | null => root.querySelector<HTMLElement>('[data-wizard-home-section-rows]');

  const getHomeSectionRowTemplate = (): HTMLTemplateElement | null => root.querySelector<HTMLTemplateElement>('template[data-wizard-home-section-row-template]');

  const addSelectedHomeSectionRow = (): void => {
    const select = root.querySelector<HTMLSelectElement>('[data-wizard-home-section-select]');
    const selectedOption = select?.selectedOptions[0];
    const layout = selectedOption?.value.trim() ?? '';

    if (!layout) {
      setNotice('Choose a section layout before adding it.', 'error');
      return;
    }

    addHomeSectionRow({
      layout,
      label: selectedOption?.dataset.label || selectedOption?.textContent?.replace(/\s*\([^)]*\)\s*$/, '').trim() || layout,
      description: selectedOption?.dataset.description || '',
      has_repeaters: selectedOption?.dataset.hasRepeaters === '1',
      has_fillable_fields: selectedOption?.dataset.hasFillableFields === '1',
      default_item_count: Number.parseInt(selectedOption?.dataset.defaultItemCount ?? '', 10),
    });
  };

  const addHomeSectionRow = (section: HomeSectionTemplate): HTMLElement | null => {
    const rows = getHomeSectionRowsContainer();
    const template = getHomeSectionRowTemplate();
    const layout = section.layout?.trim() ?? '';

    if (!rows || !template || !layout) {
      return null;
    }

    const index = rows.querySelectorAll('[data-wizard-home-section-row]').length;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
    const row = wrapper.firstElementChild instanceof HTMLElement ? wrapper.firstElementChild : null;

    if (!row) {
      return null;
    }

    const label = section.label?.trim() || layout;
    const description = section.description?.trim() || 'Flexible Content layout';
    const input = row.querySelector<HTMLInputElement>('[data-wizard-home-section-value]');
    const countWrap = row.querySelector<HTMLElement>('[data-wizard-home-section-count-wrap]');
    const countInput = row.querySelector<HTMLInputElement>('[data-wizard-home-section-item-count]');
    const labelTarget = row.querySelector<HTMLElement>('[data-wizard-home-section-label]');
    const descriptionTarget = row.querySelector<HTMLElement>('[data-wizard-home-section-description]');
    const keyTarget = row.querySelector<HTMLElement>('[data-wizard-home-section-key]');
    const hasRepeaters = Boolean(section.has_repeaters);
    const hasFillableFields = Boolean(section.has_fillable_fields);
    const itemCount = hasFillableFields ? normalizeItemCount(section.default_item_count, defaultItemCountForLayout(layout)) : 0;

    if (input) {
      input.value = layout;
    }

    row.dataset.wizardHomeSectionHasRepeaters = hasRepeaters ? '1' : '0';
    row.dataset.wizardHomeSectionHasFillableFields = hasFillableFields ? '1' : '0';

    if (countWrap) {
      countWrap.hidden = !hasFillableFields;
    }

    if (countInput) {
      countInput.value = String(itemCount);
      countInput.disabled = !hasFillableFields;
    }

    if (labelTarget) {
      labelTarget.textContent = label;
    }

    if (descriptionTarget) {
      descriptionTarget.textContent = description;
    }

    if (keyTarget) {
      keyTarget.textContent = layout;
    }

    const noAiNote = row.querySelector<HTMLElement>('[data-wizard-home-section-no-ai]');
    if (noAiNote) {
      noAiNote.hidden = hasFillableFields;
    }

    rows.append(row);
    reindexHomeSectionRows();
    syncGuidedControlState();

    return row;
  };

  const addCommonHomeSectionRows = (): void => {
    const sections = readHomeSectionTemplates('script[data-wizard-common-home-sections]');
    let added = 0;

    sections.forEach((section) => {
      if (section.layout && addHomeSectionRow(section)) {
        added += 1;
      }
    });

    setNotice(
      added > 0
        ? `Added ${added} common Home section${added === 1 ? '' : 's'}. You can remove sections or add more layouts before running this step.`
        : 'No common Home sections were available to add.',
      added > 0 ? 'info' : 'error'
    );
  };

  const readHomeSectionTemplates = (selector: string): HomeSectionTemplate[] => {
    const source = root.querySelector<HTMLScriptElement>(selector);

    if (!source?.textContent) {
      return [];
    }

    try {
      const parsed = JSON.parse(source.textContent) as unknown;

      if (!Array.isArray(parsed)) {
        return [];
      }

      return parsed.filter((item): item is HomeSectionTemplate => typeof item === 'object' && item !== null);
    } catch (_error) {
      return [];
    }
  };

  const removeHomeSectionRow = (button: HTMLButtonElement): void => {
    button.closest<HTMLElement>('[data-wizard-home-section-row]')?.remove();
    reindexHomeSectionRows();
    syncGuidedControlState();
  };

  const reindexHomeSectionRows = (): void => {
    Array.from(root.querySelectorAll<HTMLElement>('[data-wizard-home-section-row]')).forEach((row, index) => {
      row.dataset.wizardHomeSectionIndex = String(index);

      row.querySelectorAll<FieldElement>('[name]').forEach((field) => {
        field.name = field.name.replace(/sections\[(?:__INDEX__|\d+)\]/g, `sections[${index}]`);
      });

      row.querySelectorAll<HTMLElement>('[id]').forEach((element) => {
        element.id = element.id.replace(/rms-wizard-home-section-count-(?:__INDEX__|\d+)/g, `rms-wizard-home-section-count-${index}`);
      });

      row.querySelectorAll<HTMLLabelElement>('label[for]').forEach((label) => {
        label.htmlFor = label.htmlFor.replace(/rms-wizard-home-section-count-(?:__INDEX__|\d+)/g, `rms-wizard-home-section-count-${index}`);
      });
    });
  };

  const syncHomeSectionBuilderEmptyState = (): void => {
    const hasRows = Boolean(root.querySelector('[data-wizard-home-section-row]'));
    const emptyMessage = root.querySelector<HTMLElement>('[data-wizard-home-section-empty]');

    if (emptyMessage) {
      emptyMessage.hidden = hasRows;
    }
  };

  const hydrateHomeSeoTargetingForm = (replace = false): void => {
    const form = root.querySelector<HTMLFormElement>('[data-wizard-home-page-builder-form]');

    if (!form) {
      return;
    }

    hydrateHomeSeoTargeting(form, state.home_seo_targeting, { force: replace });
  };

  const setHomeHarnessWarning = (message: string, tone: 'info' | 'error'): void => {
    const warning = root.querySelector<HTMLElement>('[data-wizard-home-harness-warning]');
    const target = warning?.querySelector<HTMLElement>('p') ?? warning;

    if (!warning || !target) {
      return;
    }

    target.textContent = message;
    warning.hidden = message === '';
    warning.classList.toggle('notice-error', tone === 'error');
    warning.classList.toggle('notice-warning', tone !== 'error');
  };

  const missingHomeBuilderClientData = (): string[] => {
    if (!Object.prototype.hasOwnProperty.call(state, 'client_data')) {
      return ['company_name'];
    }

    const clientData = state.client_data ?? {};

    return hasUsableValue(clientData.company_name) ? [] : ['company_name'];
  };

  const hasUsableValue = (value: unknown): boolean => {
    if (typeof value === 'string') {
      return value.trim() !== '';
    }

    return typeof value === 'number' || typeof value === 'boolean';
  };

  const normalizeItemCount = (value: string | number | undefined, fallback = 1): number => {
    const parsed = typeof value === 'number' ? value : Number.parseInt(value ?? '', 10);
    const next = Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;

    return Math.max(1, Math.min(12, Math.round(next)));
  };

  const defaultItemCountForLayout = (layout: string): number => {
    const normalized = layout === 'cta-bar' ? 'cta-v1' : layout;
    const defaults: Record<string, number> = {
      slider: 2,
      'area-coverage-v1': 4,
      badges: 4,
      'cta-v3': 3,
      'faq-v1': 4,
      'faq-v2': 4,
      'gallery-grid': 6,
      'portfolio-v1': 3,
      'portfolio-v2': 3,
      'portfolio-v3': 6,
      'services-v1': 3,
      'services-v2': 3,
      'services-v3': 3,
      'testimonials-v1': 3,
      'testimonials-v2': 3,
      'testimonials-v3': 3,
      'video-v2': 2,
      'vision-mission-v1': 2,
      'vision-mission-v2': 3,
    };

    return defaults[normalized] ?? 1;
  };

  const clearApiKeyInput = (): void => {
    const form = root.querySelector<HTMLFormElement>('[data-wizard-ia-generation-form]');
    const apiKeyInput = form?.querySelector<HTMLInputElement>('input[name="api_key"]');

    if (apiKeyInput) {
      applyApiKeyInputSafety(apiKeyInput, { clear: true, hasSavedCredential: true });
    }
  };

  /**
   * Build a truthful per-step summary from the response result.
   *
   * For the dependencies step, renders a per-plugin status line from the
   * authoritative installed/active flags and diagnostic `action` labels so
   * the user sees which plugins failed and why. For other steps, returns a
   * generic incomplete message. Only the final step status (checked by the
   * caller) decides whether success copy is shown.
   */
  const stepResultSummary = (step: string, result: unknown): string => {
    if (step === 'dependencies') {
      return summarizeDependencyResult(result);
    }

    return `${labelFor(step)} did not complete. Check the result details and retry.`;
  };

  const hydrateIaGenerationForm = (): void => {
    const form = root.querySelector<HTMLFormElement>('[data-wizard-ia-generation-form]');
    const aiConfig = state.ai_config;

    if (!form || !aiConfig) {
      return;
    }

    const providerSelect = form.querySelector<HTMLSelectElement>('[data-wizard-ai-provider]');
    const apiKeyInput = form.querySelector<HTMLInputElement>('input[name="api_key"]');
    const modelSelect = form.querySelector<HTMLSelectElement>('[data-wizard-ai-model]');
    const manualModelInput = form.querySelector<HTMLInputElement>('[data-wizard-ai-model-manual]');
    const credentialStatus = form.querySelector<HTMLElement>('[data-wizard-ai-credential-status]');
    const modelStatus = form.querySelector<HTMLElement>('[data-wizard-ai-model-status]');
    const provider = aiConfig.provider ?? '';
    const model = aiConfig.model ?? '';

    if (providerSelect && provider) {
      providerSelect.value = provider;
    }

    if (apiKeyInput) {
      const hasSavedCredential = Boolean(aiConfig.has_credentials || aiConfig.credential?.has_key);

      /*
       * When a saved credential exists, clear any residual value from the
       * input so a plaintext key cannot survive a render triggered by a
       * state reload (e.g. after a successful save or on re-opening the
       * wizard). When no credential is saved (error/correction path), the
       * user's typed value is retained so they can fix and retry.
       */
      applyApiKeyInputSafety(apiKeyInput, {
        clear: false,
        hasSavedCredential,
      });
    }

    if (credentialStatus) {
      credentialStatus.textContent = aiConfig.credential?.status
        ?? (aiConfig.has_credentials ? 'Saved API key configured (hidden)' : 'No key saved');
      credentialStatus.classList.toggle('is-success', Boolean(aiConfig.has_credentials || aiConfig.credential?.has_key));
      credentialStatus.classList.remove('is-error');
    }

    if (modelSelect && model) {
      if (!Array.from(modelSelect.options).some((option) => option.value === model)) {
        modelSelect.append(new Option(`Saved: ${model}`, model));
      }

      modelSelect.value = model;
    }

    if (manualModelInput && model) {
      manualModelInput.value = model;
    }

    setInlineStatus(modelStatus, model ? `Configured model: ${model}` : 'No model configured yet.', model ? 'success' : 'info');
  };

  const loadAiModels = async (button: HTMLButtonElement): Promise<void> => {
    const form = button.closest<HTMLFormElement>('form');

    if (!form) {
      return;
    }

    const providerSelect = form.querySelector<HTMLSelectElement>('[data-wizard-ai-provider]');
    const apiKeyInput = form.querySelector<HTMLInputElement>('input[name="api_key"]');
    const modelSelect = form.querySelector<HTMLSelectElement>('[data-wizard-ai-model]');
    const manualModelInput = form.querySelector<HTMLInputElement>('[data-wizard-ai-model-manual]');
    const modelStatus = form.querySelector<HTMLElement>('[data-wizard-ai-model-status]');
    const credentialStatus = form.querySelector<HTMLElement>('[data-wizard-ai-credential-status]');
    const provider = providerSelect?.value ?? '';

    if (!provider || !modelSelect) {
      setNotice('Select an AI provider before loading models.', 'error');
      return;
    }

    const originalText = button.textContent ?? 'Test / Load models';
    button.disabled = true;
    button.textContent = 'Loading models...';
    setInlineStatus(modelStatus, 'Connecting to provider...', 'info');

    try {
      const response = await request<AiModelsResponse>('ai/models', {
        method: 'POST',
        body: JSON.stringify({
          provider,
          api_key: apiKeyInput?.value ?? '',
        }),
      }, 1);
      const models = response.models ?? [];

      populateModelSelect(modelSelect, models, state.ai_config?.model ?? '');

      if (credentialStatus && response.credential?.status) {
        credentialStatus.textContent = response.credential.status;
      }

      /*
       * After a successful provider test with a newly entered key, the
       * credential is persisted server-side. Clear the plaintext from the
       * input value so it does not linger in the DOM or any accessibility
       * snapshot. The saved-key placeholder/status is shown instead.
       */
      if (apiKeyInput) {
        applyApiKeyInputSafety(apiKeyInput, { clear: true, hasSavedCredential: true });
      }

      setInlineStatus(
        modelStatus,
        models.length > 0 ? `Loaded ${models.length} model${models.length === 1 ? '' : 's'}.` : 'Provider responded, but no models were returned.',
        models.length > 0 ? 'success' : 'error'
      );
      setNotice('AI provider connection succeeded.', 'success');
    } catch (error) {
      const message = errorMessage(error);
      populateModelSelect(modelSelect, []);
      manualModelInput?.focus();
      setInlineStatus(modelStatus, `${message} Enter a model name manually if needed.`, 'error');
      setNotice(`${message} Enter a model name manually if needed.`, 'error');
    } finally {
      button.disabled = false;
      button.textContent = originalText;
      updateButtons();
    }
  };

  const populateModelSelect = (select: HTMLSelectElement, models: AiModelOption[], preferredValue = ''): void => {
    const previousValue = preferredValue || select.value;

    select.replaceChildren();
    select.append(new Option('Select a model', ''));

    models.forEach((model) => {
      select.append(new Option(model.label, model.id));
    });

    if (models.some((model) => model.id === previousValue)) {
      select.value = previousValue;
      return;
    }

    if (models.length === 1) {
      select.value = models[0].id;
    }
  };

  const setInlineStatus = (element: HTMLElement | null, message: string, tone: 'info' | 'success' | 'error'): void => {
    if (!element) {
      return;
    }

    element.textContent = message;
    element.classList.toggle('is-success', tone === 'success');
    element.classList.toggle('is-error', tone === 'error');
  };

  const collectFormPayload = (form: HTMLFormElement): NestedFormObject => {
    const payload: NestedFormObject = {};

    form.querySelectorAll<HTMLElement>('[data-wizard-repeater]').forEach((repeater) => {
      const name = repeater.dataset.wizardRepeater;

      if (name) {
        payload[name] = [];
      }
    });

    Array.from(form.querySelectorAll<FieldElement>('input[name], textarea[name], select[name]')).forEach((field) => {
      if (field.disabled || shouldSkipField(field)) {
        return;
      }

      assignNestedValue(payload, field.name, normalizeFieldValue(field));
    });

    return payload;
  };

  const shouldSkipField = (field: FieldElement): boolean => {
    if (!(field instanceof HTMLInputElement)) {
      return false;
    }

    if (['button', 'submit', 'reset', 'file'].includes(field.type)) {
      return true;
    }

    return (field.type === 'checkbox' || field.type === 'radio') && !field.checked;
  };

  const normalizeFieldValue = (field: FieldElement): NestedFormValue => {
    const fieldType = field.dataset.wizardFieldType ?? '';

    if (field instanceof HTMLSelectElement && field.multiple) {
      return Array.from(field.selectedOptions).map((option) => option.value);
    }

    if (
      field instanceof HTMLInputElement
      && field.type === 'color'
      && field.dataset.wizardEmptyColor === '1'
      && field.dataset.wizardColorTouched !== '1'
    ) {
      return '';
    }

    if (fieldType === 'true_false') {
      return field.value === '1' || field.value === 'true' ? 1 : 0;
    }

    if (fieldType === 'image') {
      const attachmentId = Number.parseInt(field.value, 10);

      return Number.isFinite(attachmentId) && attachmentId > 0 ? attachmentId : '';
    }

    return field.value;
  };

  const assignNestedValue = (target: NestedFormObject, fieldName: string, value: NestedFormValue): void => {
    const path = parseFieldName(fieldName);

    if (path.length === 0) {
      return;
    }

    let current: NestedFormContainer = target;

    path.forEach((part, index) => {
      const isLast = index === path.length - 1;

      if (isLast) {
        setContainerValue(current, part, value);
        return;
      }

      const nextPart = path[index + 1];
      let nextValue = getContainerValue(current, part);

      if (!isContainer(nextValue)) {
        nextValue = typeof nextPart === 'number' ? [] : {};
        setContainerValue(current, part, nextValue);
      }

      current = nextValue;
    });
  };

  const parseFieldName = (fieldName: string): Array<string | number> => {
    const baseMatch = fieldName.match(/^[^[\]]+/);

    if (!baseMatch) {
      return [];
    }

    const path: Array<string | number> = [baseMatch[0]];
    const bracketPattern = /\[([^\]]*)\]/g;
    let match: RegExpExecArray | null = bracketPattern.exec(fieldName);

    while (match) {
      const token = match[1];

      if (token !== '') {
        path.push(/^\d+$/.test(token) ? Number.parseInt(token, 10) : token);
      }

      match = bracketPattern.exec(fieldName);
    }

    return path;
  };

  const isContainer = (value: NestedFormValue | undefined): value is NestedFormContainer => (
    Array.isArray(value) || (typeof value === 'object' && value !== null)
  );

  const getContainerValue = (container: NestedFormContainer, key: string | number): NestedFormValue | undefined => {
    if (Array.isArray(container)) {
      return typeof key === 'number' ? container[key] : (container as unknown as NestedFormObject)[key];
    }

    return container[String(key)];
  };

  const setContainerValue = (container: NestedFormContainer, key: string | number, value: NestedFormValue): void => {
    if (Array.isArray(container)) {
      if (typeof key === 'number') {
        container[key] = value;
        return;
      }

      (container as unknown as NestedFormObject)[key] = value;
      return;
    }

    container[String(key)] = value;
  };

  const setStepActionStatus = (step: string, message: string, tone: 'info' | 'success' | 'error'): void => {
    const panel = root.querySelector<HTMLElement>(`[data-wizard-step-panel="${step}"]`);
    const status = panel?.querySelector<HTMLElement>('[data-wizard-action-status]');

    if (!status) return;

    status.textContent = message;
    status.classList.toggle('is-success', tone === 'success');
    status.classList.toggle('is-error', tone === 'error');
  };

  const setStepResult = (step: string, result: unknown): void => {
    const panel = root.querySelector<HTMLElement>(`[data-wizard-step-panel="${step}"]`);
    const target = panel?.querySelector<HTMLElement>('[data-wizard-step-result]');

    if (!target) return;

    target.hidden = false;
    target.textContent = JSON.stringify(result, null, 2);
  };

  const labelFor = (step: string): string => steps.find((item) => item.slug === step)?.label ?? step;

  const nextStepFor = (step: string): string => {
    const index = steps.findIndex((item) => item.slug === step);

    return index >= 0 ? steps[index + 1]?.slug ?? '' : '';
  };

  const delay = (duration: number): Promise<void> => new Promise((resolve) => {
    window.setTimeout(resolve, duration);
  });

  const errorMessage = (error: unknown): string => error instanceof Error ? error.message : 'The wizard request failed.';

  const escapeHtml = (value: string): string => {
    const node = document.createElement('span');
    node.textContent = value;
    return node.innerHTML;
  };

  const setupDynamicFieldControls = (): void => {
    root.addEventListener('input', (event) => {
      const target = event.target;

      if (target instanceof HTMLInputElement && target.matches('input[type="color"][data-wizard-empty-color]')) {
        target.dataset.wizardColorTouched = '1';
      }
    });

    root.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;

      if (!target) {
        return;
      }

      const addButton = target.closest<HTMLButtonElement>('[data-wizard-repeater-add]');

      if (addButton) {
        event.preventDefault();
        addRepeaterRow(addButton);
        return;
      }

      const removeButton = target.closest<HTMLButtonElement>('[data-wizard-repeater-remove]');

      if (removeButton) {
        event.preventDefault();
        removeRepeaterRow(removeButton);
        return;
      }

      const mediaButton = target.closest<HTMLButtonElement>('[data-wizard-media-open]');

      if (mediaButton) {
        event.preventDefault();
        openMediaLibrary(mediaButton);
        return;
      }

      const mediaClearButton = target.closest<HTMLButtonElement>('[data-wizard-media-clear]');

      if (mediaClearButton) {
        event.preventDefault();
        clearMediaField(mediaClearButton);
        return;
      }

      const addPageButton = target.closest<HTMLButtonElement>('[data-wizard-add-page]');

      if (addPageButton) {
        event.preventDefault();
        addPageRow();
        return;
      }

      const addCommonPagesButton = target.closest<HTMLButtonElement>('[data-wizard-add-common-pages]');

      if (addCommonPagesButton) {
        event.preventDefault();
        addCommonPageRows();
        return;
      }

      const addHomeSectionButton = target.closest<HTMLButtonElement>('[data-wizard-add-home-section]');

      if (addHomeSectionButton) {
        event.preventDefault();
        addSelectedHomeSectionRow();
        return;
      }

      const addCommonHomeSectionsButton = target.closest<HTMLButtonElement>('[data-wizard-add-common-home-sections]');

      if (addCommonHomeSectionsButton) {
        event.preventDefault();
        addCommonHomeSectionRows();
        return;
      }

      const removeHomeSectionButton = target.closest<HTMLButtonElement>('[data-wizard-remove-home-section]');

      if (removeHomeSectionButton) {
        event.preventDefault();
        removeHomeSectionRow(removeHomeSectionButton);
        return;
      }

      const removePageButton = target.closest<HTMLButtonElement>('[data-wizard-remove-page]');

      if (removePageButton) {
        event.preventDefault();
        removePageRow(removePageButton);
        return;
      }

      const addLandingButton = target.closest<HTMLButtonElement>('[data-wizard-add-landing]');

      if (addLandingButton) {
        event.preventDefault();
        addLandingRow();
        return;
      }

      const duplicateLandingButton = target.closest<HTMLButtonElement>('[data-wizard-duplicate-landing]');

      if (duplicateLandingButton) {
        event.preventDefault();
        duplicateLandingRow(duplicateLandingButton);
        return;
      }

      const removeLandingButton = target.closest<HTMLButtonElement>('[data-wizard-remove-landing]');

      if (removeLandingButton) {
        event.preventDefault();
        removeLandingRow(removeLandingButton);
        return;
      }

      const toggleLandingButton = target.closest<HTMLButtonElement>('[data-wizard-landing-toggle]');

      if (toggleLandingButton) {
        event.preventDefault();
        toggleLandingRow(toggleLandingButton);
        return;
      }

      const addLandingSectionButton = target.closest<HTMLButtonElement>('[data-wizard-add-landing-section]');

      if (addLandingSectionButton) {
        event.preventDefault();
        const landingRow = addLandingSectionButton.closest<HTMLElement>('[data-wizard-landing-row]');

        if (landingRow) {
          addLandingSectionRow(landingRow);
        }

        return;
      }

      const removeLandingSectionButton = target.closest<HTMLButtonElement>('[data-wizard-remove-landing-section]');

      if (removeLandingSectionButton) {
        event.preventDefault();
        removeLandingSectionButton.closest<HTMLElement>('[data-wizard-landing-section-row]')?.remove();
        reindexLandingRows();
      }
    });

    root.addEventListener('input', (event) => {
      const target = event.target;

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-page-title]')) {
        updatePageRowFromTitle(target);
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-page-slug]')) {
        updatePageRowFromSlug(target);
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-landing-title]')) {
        const row = target.closest<HTMLElement>('[data-wizard-landing-row]');
        const slugInput = row?.querySelector<HTMLInputElement>('[data-wizard-landing-slug]');

        if (slugInput && slugInput.dataset.wizardSlugAuto !== '0') {
          slugInput.value = sanitizeSlug(target.value);
        }

        if (row) {
          syncLandingRowSummary(row);
        }
      }

      if (
        target instanceof HTMLInputElement
        && target.matches('[data-wizard-home-seo-primary], [data-wizard-home-seo-secondary]')
      ) {
        const form = target.closest<HTMLFormElement>('[data-wizard-home-page-builder-form]');

        if (form) {
          markHomeSeoDirty(form);

          if (target.matches('[data-wizard-home-seo-primary]') && target.value.trim() !== '') {
            presentHomeSeoCollectionResult(form, collectHomeSeoTargetingFromForm(form), { focus: false });
          }
        }
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-landing-primary-keyword]')) {
        const row = target.closest<HTMLElement>('[data-wizard-landing-row]');

        if (row) {
          syncLandingRowSummary(row);
        }
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-landing-slug]')) {
        target.dataset.wizardSlugAuto = '0';
      }
    });

    root.addEventListener('blur', (event) => {
      const target = event.target;

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-page-slug]')) {
        updatePageRowFromSlug(target, true);
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-landing-slug]')) {
        target.value = sanitizeSlug(target.value);
        target.dataset.wizardSlugAuto = '0';
      }
    }, true);

    root.addEventListener('change', (event) => {
      const target = event.target;

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-media-input]')) {
        const wrapper = target.closest<HTMLElement>('[data-wizard-media-field]');
        const attachmentId = Number.parseInt(target.value, 10);

        if (wrapper) {
          updateMediaPreview(wrapper, Number.isFinite(attachmentId) ? attachmentId : 0);
        }
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-page-home], [data-wizard-page-blog], [data-wizard-page-no-blog]')) {
        syncGuidedControlState();
        return;
      }

      if (target instanceof HTMLSelectElement && target.matches('[data-wizard-home-section-select]')) {
        syncGuidedControlState();
        return;
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-home-seo-enabled]')) {
        const form = target.closest<HTMLFormElement>('[data-wizard-home-page-builder-form]');

        if (form) {
          markHomeSeoDirty(form);
          applyHomeSeoTargetingUi(form, target.checked);
        }

        return;
      }

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-landing-skip-all]')) {
        syncLandingSkipAllUi();
        return;
      }

      if (target instanceof HTMLSelectElement && target.matches('[data-wizard-landing-type]')) {
        const row = target.closest<HTMLElement>('[data-wizard-landing-row]');

        if (row) {
          syncLandingRowSummary(row);
        }

        return;
      }

      if (target instanceof HTMLSelectElement && target.matches('[data-wizard-landing-section-layout]')) {
        const sectionRow = target.closest<HTMLElement>('[data-wizard-landing-section-row]');

        if (sectionRow) {
          syncLandingSectionOverrideVisibility(sectionRow);
        }
      }
    });

    root.querySelectorAll<HTMLElement>('[data-wizard-repeater]').forEach(reindexRepeaterRows);
  };

  const addRepeaterRow = (button: HTMLButtonElement): void => {
    const repeater = button.closest<HTMLElement>('[data-wizard-repeater]');
    const rows = repeater?.querySelector<HTMLElement>('[data-wizard-repeater-rows]');
    const template = repeater?.querySelector<HTMLTemplateElement>('template[data-wizard-repeater-template]');

    if (!repeater || !rows || !template) {
      return;
    }

    const index = rows.querySelectorAll('[data-wizard-repeater-row]').length;
    rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
    reindexRepeaterRows(repeater);
  };

  const removeRepeaterRow = (button: HTMLButtonElement): void => {
    const repeater = button.closest<HTMLElement>('[data-wizard-repeater]');
    const row = button.closest<HTMLElement>('[data-wizard-repeater-row]');

    row?.remove();

    if (repeater) {
      reindexRepeaterRows(repeater);
    }
  };

  const reindexRepeaterRows = (repeater: HTMLElement): void => {
    const repeaterName = repeater.dataset.wizardRepeater;

    if (!repeaterName) {
      return;
    }

    const rows = Array.from(repeater.querySelectorAll<HTMLElement>('[data-wizard-repeater-row]'));

    rows.forEach((row, index) => {
      row.dataset.wizardRepeaterIndex = String(index);

      row.querySelectorAll<FieldElement>('[name]').forEach((field) => {
        field.name = replaceRepeaterIndex(field.name, repeaterName, index);
      });

      row.querySelectorAll<HTMLElement>('[id]').forEach((element) => {
        element.id = replaceRepeaterIndex(element.id, repeaterName, index);
      });

      row.querySelectorAll<HTMLLabelElement>('label[for]').forEach((label) => {
        label.htmlFor = replaceRepeaterIndex(label.htmlFor, repeaterName, index);
      });
    });
  };

  const replaceRepeaterIndex = (value: string, repeaterName: string, index: number): string => {
    const bracketPattern = new RegExp(`(${escapeRegExp(repeaterName)}\\[)(?:__INDEX__|\\d+)(\\])`, 'g');
    const idPattern = new RegExp(`(${escapeRegExp(repeaterName)}-)(?:__INDEX__|\\d+)(-)`, 'g');

    return value
      .replace(bracketPattern, (_match, before: string, after: string) => `${before}${index}${after}`)
      .replace(idPattern, (_match, before: string, after: string) => `${before}${index}${after}`);
  };

  const escapeRegExp = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  const openMediaLibrary = (button: HTMLButtonElement): void => {
    const wrapper = button.closest<HTMLElement>('[data-wizard-media-field]');
    const input = wrapper?.querySelector<HTMLInputElement>('[data-wizard-media-input]');
    const media = window.wp?.media;

    if (!wrapper || !input) {
      return;
    }

    if (!media) {
      setNotice('The WordPress Media Library is unavailable. Enter an attachment ID manually.', 'error');
      return;
    }

    const frame = media({
      title: button.dataset.wizardMediaTitle || 'Select image',
      button: { text: 'Use this image' },
      library: { type: 'image' },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first();
      const data = attachment?.toJSON?.() ?? {};
      const attachmentId = Number.parseInt(String(data.id ?? attachment?.id ?? ''), 10);
      const previewUrl = data.sizes?.thumbnail?.url ?? data.url ?? '';

      if (!Number.isFinite(attachmentId) || attachmentId <= 0) {
        return;
      }

      input.value = String(attachmentId);
      updateMediaPreview(wrapper, attachmentId, previewUrl);
    });

    frame.open();
  };

  const clearMediaField = (button: HTMLButtonElement): void => {
    const wrapper = button.closest<HTMLElement>('[data-wizard-media-field]');
    const input = wrapper?.querySelector<HTMLInputElement>('[data-wizard-media-input]');

    if (!wrapper || !input) {
      return;
    }

    input.value = '';
    updateMediaPreview(wrapper, 0);
  };

  const updateMediaPreview = (wrapper: HTMLElement, attachmentId: number, previewUrl = ''): void => {
    const preview = wrapper.querySelector<HTMLElement>('[data-wizard-media-preview]');

    if (!preview) {
      return;
    }

    preview.replaceChildren();

    if (attachmentId > 0 && previewUrl) {
      const image = document.createElement('img');
      image.src = previewUrl;
      image.alt = 'Selected image preview';
      preview.append(image);
    }

    const label = document.createElement('span');
    label.textContent = attachmentId > 0 ? `Attachment ID: ${attachmentId}` : 'No image selected.';
    preview.append(label);
  };

  setupDynamicFieldControls();

  navButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const step = button.dataset.wizardStepNav;
      if (step) setActiveStep(step);
    });
  });

  runButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const step = button.dataset.wizardRunStep;
      if (step) void runStep(step, 'run');
    });
  });

  retryButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const step = button.dataset.wizardRetryStep;
      if (step) void runStep(step, 'retry');
    });
  });

  nextButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const step = button.dataset.wizardNextStep || activeStep;
      const target = button.dataset.wizardNextTarget || nextStepFor(step);

      if (target && statusFor(step) === 'complete') {
        setActiveStep(target);
        setNotice(`Ready for ${labelFor(target)}.`, 'info');
      }
    });
  });

  loadModelButtons.forEach((button) => {
    button.addEventListener('click', () => {
      void loadAiModels(button);
    });
  });

  root.querySelectorAll<HTMLSelectElement>('[data-wizard-ai-provider]').forEach((select) => {
    select.addEventListener('change', () => {
      const form = select.closest<HTMLFormElement>('form');
      const modelSelect = form?.querySelector<HTMLSelectElement>('[data-wizard-ai-model]');
      const modelStatus = form?.querySelector<HTMLElement>('[data-wizard-ai-model-status]') ?? null;

      if (modelSelect) {
        populateModelSelect(modelSelect, []);
      }

      setInlineStatus(modelStatus, 'Load models after changing providers.', 'info');
    });
  });

  refreshButton?.addEventListener('click', () => {
    void loadState({ replaceHomeSeo: true });
  });

  root.querySelector<HTMLButtonElement>('[data-wizard-landing-resume]')?.addEventListener('click', () => {
    void resumeLandingRun();
  });

  completeButton?.addEventListener('click', () => {
    void completeWizard();
  });

  render();
  void loadState({ replaceHomeSeo: true });
})();

function getSettings(root: HTMLElement | null): WizardSettings | undefined {
  if (window.rmsWizardSettings?.root && window.rmsWizardSettings?.nonce) {
    return window.rmsWizardSettings;
  }

  if (!root) {
    return undefined;
  }

  const rootUrl = root.dataset.rmsWizardRoot;
  const nonce = root.dataset.rmsWizardNonce;

  if (!rootUrl || !nonce) {
    return undefined;
  }

  return { root: rootUrl, nonce };
}

function showBootstrapError(root: HTMLElement | null, message: string): void {
  const target = root?.querySelector<HTMLElement>('[data-wizard-notice]')
    ?? root?.querySelector<HTMLElement>('[data-wizard-progress-text]');

  if (!target) {
    return;
  }

  target.hidden = false;
  target.textContent = message;
  target.classList.add('is-error');
}
