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

declare global {
  interface Window {
    rmsWizardSettings?: WizardSettings;
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
      setNotice(`${labelFor(step)} completed successfully.`, 'success');
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
      return { client_data: Object.fromEntries(data.entries()) };
    }

    if (step === 'content-creation') {
      const rawPages = String(data.get('pages') ?? '').trim();
      return { pages: rawPages ? JSON.parse(rawPages) as unknown : [] };
    }

    return Object.fromEntries(data.entries());
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

  const delay = (duration: number): Promise<void> => new Promise((resolve) => {
    window.setTimeout(resolve, duration);
  });

  const errorMessage = (error: unknown): string => error instanceof Error ? error.message : 'The wizard request failed.';

  const escapeHtml = (value: string): string => {
    const node = document.createElement('span');
    node.textContent = value;
    return node.innerHTML;
  };

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
