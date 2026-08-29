export const SAVED_KEY_PLACEHOLDER = 'Leave blank to use the saved encrypted key';

export type ApiKeyInputLike = {
  value: string;
  placeholder: string;
  getAttribute?: (name: string) => string | null;
};

export type StepOutcomePresentation = 'success' | 'progress' | 'error';

/**
 * Clear plaintext from the API-key input after a successful Test/Load or
 * save, and whenever hydration sees a saved credential. Failure/correction
 * paths must call this with both flags false so the typed value is kept.
 */
export const applyApiKeyInputSafety = (
  input: ApiKeyInputLike,
  options: { clear: boolean; hasSavedCredential: boolean }
): void => {
  if (!options.clear && !options.hasSavedCredential) {
    return;
  }

  input.value = '';
  input.placeholder = SAVED_KEY_PLACEHOLDER;
};

/**
 * Client presentation for a step response.
 *
 * complete + success → green "Step completed"
 * running + success → in-progress (issue #27 must keep this; never green, never error)
 * anything else → error
 */
export const presentStepOutcome = (
  stepStatus: string,
  responseSuccess: boolean
): StepOutcomePresentation => {
  if (stepStatus === 'complete' && responseSuccess) {
    return 'success';
  }

  if (stepStatus === 'running' && responseSuccess) {
    return 'progress';
  }

  return 'error';
};

export const summarizeDependencyResult = (result: unknown): string => {
  if (typeof result !== 'object' || result === null) {
    return 'Dependencies did not complete. Check the result details and retry.';
  }

  const plugins = Object.values(result) as Array<Record<string, unknown>>;
  const activeCount = plugins.filter((plugin) => Boolean(plugin.active)).length;

  if (plugins.length === 0) {
    return 'No required plugins were found. Ensure TGMPA is configured.';
  }

  const lines = plugins.map((plugin) => {
    const name = String(plugin.name ?? plugin.slug ?? 'Unknown');
    const action = String(plugin.action ?? '');
    const installed = Boolean(plugin.installed);
    const active = Boolean(plugin.active);

    if (active) {
      return `${name}: active (${action || 'already_active'})`;
    }

    if (installed) {
      return `${name}: installed but not active (${action || 'activation_failed'})`;
    }

    return `${name}: not installed (${action || 'install_failed'})`;
  });

  return `${activeCount} of ${plugins.length} dependencies active. ${lines.join(' ')}`;
};

export type GeneratePagePayloadItem = {
  slug: string;
  title: string;
  generate: true;
  role: string;
  type?: string;
};

export const sanitizeWizardPageType = (value: string): string => (
  value.trim().toLowerCase().replace(/[^a-z0-9-]+/g, '').replace(/^-+|-+$/g, '')
);

export const buildGeneratePagePayloadItem = (input: {
  slug: string;
  title: string;
  role: string;
  type?: string;
}): GeneratePagePayloadItem => {
  const item: GeneratePagePayloadItem = {
    slug: input.slug,
    title: input.title,
    generate: true,
    role: input.role,
  };
  const type = sanitizeWizardPageType(input.type ?? '');

  if (type !== '') {
    item.type = type;
  }

  return item;
};

export const inputContainsSecret = (input: ApiKeyInputLike, sentinel: string): boolean => {
  if (input.value.includes(sentinel)) {
    return true;
  }

  if (input.placeholder.includes(sentinel)) {
    return true;
  }

  if (typeof input.getAttribute !== 'function') {
    return false;
  }

  const attributeNames = ['value', 'data-api-key', 'data-value', 'data-credential', 'data-key'];

  return attributeNames.some((name) => (input.getAttribute?.(name) ?? '').includes(sentinel));
};
