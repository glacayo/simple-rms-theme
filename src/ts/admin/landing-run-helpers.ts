export interface LandingHydrationSection {
  layout?: string;
  override_canonical?: boolean;
  item_count?: number;
}

export interface LandingHydrationRow {
  landing_key?: string;
  key?: string;
  id?: number | string | null;
  post_id?: number | string | null;
  title?: string;
  slug?: string;
  landing_type?: string;
  primary_keyword?: string;
  subkeywords?: string[];
  sections?: LandingHydrationSection[];
}

export interface LandingResumeOfferInput {
  processingActive?: boolean;
  runningStep?: string | null;
  runStatus?: string | null;
}

export type LandingClientIntent = 'run' | 'retry' | 'resume';

export interface LandingClientRequestContext {
  intent: LandingClientIntent;
  skipAll?: boolean;
  incompleteRun?: boolean;
}

export type LandingClientRequestDecision =
  | { kind: 'start'; body: { landing_action: 'start' } }
  | { kind: 'process'; body: { landing_action: 'process' } }
  | { kind: 'skip'; body: { skip_all: true } }
  | { kind: 'blocked'; reason: 'incomplete_run' };

/**
 * Decide the Landing Page Builder request from the actual button intent.
 * Run/Retry start a new plan only when no incomplete persisted run exists.
 * Resume continues that plan with process. Skip-all never sends start/process.
 */
export const resolveLandingClientRequest = ({
  intent,
  skipAll = false,
  incompleteRun = false,
}: LandingClientRequestContext): LandingClientRequestDecision => {
  if (skipAll) {
    return { kind: 'skip', body: { skip_all: true } };
  }

  if (incompleteRun) {
    if (intent === 'resume') {
      return { kind: 'process', body: { landing_action: 'process' } };
    }

    return { kind: 'blocked', reason: 'incomplete_run' };
  }

  if (intent === 'resume') {
    return { kind: 'blocked', reason: 'incomplete_run' };
  }

  return { kind: 'start', body: { landing_action: 'start' } };
};

const normalizeKey = (row: LandingHydrationRow): string =>
  String(row.landing_key || row.key || '').trim();

const numericId = (value: unknown): number => {
  const parsed = typeof value === 'number' ? value : Number.parseInt(String(value ?? ''), 10);

  return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
};

/**
 * Merge completed landing rows with the persisted run plan by landing key.
 *
 * Plan sections replace empty/default completed-row sections. Completed post
 * IDs and other durable values are retained.
 */
export const mergeLandingRowsByKey = (
  existingRows: LandingHydrationRow[],
  planItems: LandingHydrationRow[]
): LandingHydrationRow[] => {
  const byKey = new Map<string, LandingHydrationRow>();

  for (const row of existingRows) {
    const key = normalizeKey(row);

    if (key) {
      byKey.set(key, { ...row, landing_key: key });
    }
  }

  for (const item of planItems) {
    const key = normalizeKey(item);

    if (!key) {
      continue;
    }

    const current = byKey.get(key);
    const planId = numericId(item.id ?? item.post_id);
    const existingId = numericId(current?.id ?? current?.post_id);
    const planSections = Array.isArray(item.sections) ? item.sections : [];
    const currentSections = Array.isArray(current?.sections) ? current.sections : [];

    byKey.set(key, {
      ...(current || {}),
      ...item,
      landing_key: key,
      id: existingId > 0 ? existingId : (planId > 0 ? planId : null),
      sections: planSections.length > 0 ? planSections : currentSections,
    });
  }

  return [...byKey.values()];
};

export const shouldOfferLandingResume = ({
  processingActive,
  runningStep,
  runStatus,
}: LandingResumeOfferInput): boolean => {
  if (processingActive || runningStep) {
    return false;
  }

  return runStatus === 'interrupted' || runStatus === 'failed' || runStatus === 'running' || runStatus === 'pending';
};

export const isIncompleteLandingRunStatus = (runStatus?: string | null): boolean => (
  runStatus === 'pending'
  || runStatus === 'running'
  || runStatus === 'interrupted'
  || runStatus === 'failed'
);

/**
 * Map a persisted plan item's sections onto form-ready values.
 * Newly added DOM rows must keep item_count and override_canonical.
 */
export const sectionsFromPlanItem = (item: LandingHydrationRow): LandingHydrationSection[] => {
  if (!Array.isArray(item.sections)) {
    return [];
  }

  return item.sections.flatMap((section) => {
    const layout = typeof section === 'object' && section !== null ? String(section.layout ?? '').trim() : '';

    if (!layout) {
      return [];
    }

    const itemCount = typeof section.item_count === 'number' && Number.isFinite(section.item_count)
      ? section.item_count
      : undefined;

    return [{
      layout,
      override_canonical: Boolean(section.override_canonical),
      ...(itemCount !== undefined ? { item_count: itemCount } : {}),
    }];
  });
};
