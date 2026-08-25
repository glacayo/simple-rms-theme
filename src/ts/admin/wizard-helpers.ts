export type StepOutcomePresentation = 'success' | 'progress' | 'error';

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
