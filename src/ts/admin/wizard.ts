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

interface WizardState {
  current_step?: string;
  step_status?: Record<string, StepStatus>;
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
    { slug: 'ai-generation', label: 'AI Generation' },
    { slug: 'content-creation', label: 'Content Creation' },
  ];

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

    [...runButtons, ...retryButtons].forEach((button) => {
      const step = button.dataset.wizardRunStep || button.dataset.wizardRetryStep || '';
      button.disabled = isLocked || runningStep !== null || statusFor(step) === 'running';
    });

    loadModelButtons.forEach((button) => {
      button.disabled = isLocked || runningStep !== null;
    });

    nextButtons.forEach((button) => {
      const step = button.dataset.wizardNextStep || '';
      const target = button.dataset.wizardNextTarget || nextStepFor(step);
      const canContinue = statusFor(step) === 'complete' && target !== '';

      button.disabled = isLocked || runningStep !== null || !canContinue;
      button.classList.toggle('is-ready', canContinue && !isLocked);
      button.setAttribute(
        'aria-label',
        canContinue ? `Continue to ${labelFor(target)}` : `Complete ${labelFor(step)} before continuing`
      );
    });

    if (completeButton) {
      const allComplete = steps.every((step) => statusFor(step.slug) === 'complete');
      completeButton.disabled = isLocked || runningStep !== null || !allComplete;
    }
  };

  const render = (): void => {
    const nextStep = state.current_step && steps.some((step) => step.slug === state.current_step)
      ? state.current_step
      : activeStep;

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
    state = await request<WizardState>('state', { method: 'GET' }, 1);
    render();
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

    updateButtons();
  };

  const runStep = async (step: string): Promise<void> => {
    runningStep = step;
    setActiveStep(step);
    setStepActionStatus(step, 'Running step...', 'info');
    setNotice('Running setup wizard step. Keep this page open.', 'info');
    render();

    try {
      const payload = collectPayload(step);
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
      setStepActionStatus(step, errorMessage(error), 'error');
      setNotice(errorMessage(error), 'error');
    } finally {
      runningStep = null;
      await loadState().catch(() => render());
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

  const collectPayload = (step: string): Record<string, unknown> => {
    const panel = root.querySelector<HTMLElement>(`[data-wizard-step-panel="${step}"]`);
    const form = panel?.querySelector<HTMLFormElement>('form');

    if (step === 'dependencies') {
      return { install: true };
    }

    if (!form) {
      return {};
    }

    const data = new FormData(form);

    if (step === 'client-data') {
      return { client_data: collectFormPayload(form) };
    }

    if (step === 'content-creation') {
      const rawPages = String(data.get('pages') ?? '').trim();
      return { pages: rawPages ? JSON.parse(rawPages) as unknown : [] };
    }

    return collectFormPayload(form);
  };

  const loadAiModels = async (button: HTMLButtonElement): Promise<void> => {
    const form = button.closest<HTMLFormElement>('form');

    if (!form) {
      return;
    }

    const providerSelect = form.querySelector<HTMLSelectElement>('[data-wizard-ai-provider]');
    const apiKeyInput = form.querySelector<HTMLInputElement>('input[name="api_key"]');
    const modelSelect = form.querySelector<HTMLSelectElement>('[data-wizard-ai-model]');
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

      populateModelSelect(modelSelect, models);

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
      setInlineStatus(modelStatus, message, 'error');
      setNotice(message, 'error');
    } finally {
      button.disabled = false;
      button.textContent = originalText;
      updateButtons();
    }
  };

  const populateModelSelect = (select: HTMLSelectElement, models: AiModelOption[]): void => {
    const previousValue = select.value;

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
      }
    });

    root.addEventListener('change', (event) => {
      const target = event.target;

      if (target instanceof HTMLInputElement && target.matches('[data-wizard-media-input]')) {
        const wrapper = target.closest<HTMLElement>('[data-wizard-media-field]');
        const attachmentId = Number.parseInt(target.value, 10);

        if (wrapper) {
          updateMediaPreview(wrapper, Number.isFinite(attachmentId) ? attachmentId : 0);
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
  void loadState().catch(handleStateLoadError);
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
