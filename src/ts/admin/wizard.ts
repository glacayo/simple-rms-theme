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
}

interface WizardPageTemplate {
  title?: string;
  slug?: string;
  description?: string;
  role?: string;
}

interface HomeSectionTemplate {
  layout?: string;
  label?: string;
  description?: string;
  has_repeaters?: boolean;
  has_fillable_fields?: boolean;
  default_item_count?: number;
}

interface HomeSectionPayload {
  layout: string;
  item_count: number;
}

interface PagePayloadItem {
  slug: string;
  title: string;
  generate: boolean;
  role: string;
}

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
  locked?: boolean;
  logs?: WizardLogEntry[];
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
    const percent = Math.round((completed / steps.length) * 100);

    if (progressBar) {
      progressBar.style.width = `${percent}%`;
      progressBar.setAttribute('aria-valuenow', String(percent));
    }

    if (progressText) {
      progressText.textContent = `${completed} of ${steps.length} steps complete`;
    }
  };

  const updateNav = (): void => {
    navButtons.forEach((button) => {
      const step = button.dataset.wizardStepNav ?? '';
      const status = statusFor(step);
      const statusNode = button.querySelector<HTMLElement>('[data-wizard-step-status]');

      button.classList.remove('is-pending', 'is-running', 'is-complete', 'is-failed');
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

  const updateButtons = (): void => {
    const isLocked = Boolean(state.locked);
    const isHydrating = root.classList.contains('is-hydrating');

    navButtons.forEach((button) => {
      button.disabled = isHydrating || runningStep !== null;
    });

    [...runButtons, ...retryButtons].forEach((button) => {
      const step = button.dataset.wizardRunStep || button.dataset.wizardRetryStep || '';
      button.disabled = isHydrating || isLocked || runningStep !== null || statusFor(step) === 'running';
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
      refreshButton.disabled = isHydrating || runningStep !== null;
    }

    if (completeButton) {
      const allComplete = steps.every((step) => statusFor(step.slug) === 'complete');
      completeButton.disabled = isHydrating || isLocked || runningStep !== null || !allComplete;
    }
  };

  const render = (): void => {
    const nextStep = state.current_step && steps.some((step) => step.slug === state.current_step)
      ? state.current_step
      : activeStep;

    renderGeneratedPageControls();
    hydrateIaGenerationForm();
    syncGuidedControlState();
    setActiveStep(nextStep);
    updateNav();
    updateProgress();
    updateLogs();
    updateButtons();

    if (state.locked) {
      setNotice('The setup wizard is complete and locked. Define RMS_WIZARD_FORCE as true for development reruns.', 'success');
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

  const loadState = async (): Promise<void> => {
    setHydrating(true);
    try {
      state = await request<WizardState>('state', { method: 'GET' }, 1);
      render();
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

  const runStep = async (step: string): Promise<void> => {
    runningStep = step;
    setActiveStep(step);
    setStepActionStatus(step, 'Running step...', 'info');
    setNotice('Running setup wizard step. Keep this page open.', 'info');

    try {
      const payload = collectPayload(step);

      if (!await ensureDestructiveConfirmation(step, payload)) {
        setStepActionStatus(step, 'Step canceled before changes were made.', 'info');
        setNotice('Step canceled before changes were made.', 'info');
        return;
      }

      render();

      const response = await request<StepResponse>(`steps/${step}/run`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      if (response.state) {
        state = response.state;
      }

      setStepResult(step, response.result ?? response);
      setStepActionStatus(step, 'Step completed.', 'success');
      const nextStep = nextStepFor(step);
      setNotice(
        nextStep
          ? `${labelFor(step)} completed successfully. Continue to ${labelFor(nextStep)} when ready.`
          : `${labelFor(step)} completed successfully.`,
        'success'
      );
    } catch (error) {
      const message = errorMessage(error);

      if (step === 'home-page-builder' && message.includes('Missing required client data:')) {
        setHomeHarnessWarning(message, 'error');
      }

      setStepActionStatus(step, message, 'error');
      setNotice(message, 'error');
    } finally {
      runningStep = null;
      await loadState();
    }
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

      pages[slug] = { slug, title: rawTitle, generate: true, role };
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

    return { sections };
  };

  const ensureDestructiveConfirmation = async (step: string, payload: StepPayload): Promise<boolean> => {
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

  const getGeneratedPages = (): GeneratedPage[] => (
    Array.isArray(state.generated_pages) ? state.generated_pages.filter((page) => Boolean(generatedPageValue(page))) : []
  );

  const generatedPageValue = (page: GeneratedPage): string => {
    const id = typeof page.id === 'number' || typeof page.id === 'string' ? String(page.id) : '';

    return id || sanitizeSlug(page.slug ?? '');
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

    if (apiKeyInput && (aiConfig.has_credentials || aiConfig.credential?.has_key)) {
      apiKeyInput.placeholder = '************';
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
    });

    root.addEventListener('blur', (event) => {
      const target = event.target;

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-page-slug]')) {
        updatePageRowFromSlug(target, true);
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
      if (step) void runStep(step);
    });
  });

  retryButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const step = button.dataset.wizardRetryStep;
      if (step) void runStep(step);
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
    void loadState();
  });

  completeButton?.addEventListener('click', () => {
    void completeWizard();
  });

  render();
  void loadState();
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
