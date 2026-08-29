export type InternalPreviewType = {
  post_id?: number;
  slug?: string;
  status?: string;
  reason?: string;
  identity_source?: string;
  layouts?: string[];
  available?: boolean;
  mapping_needed?: boolean;
  legacy_unconfirmed?: boolean;
};

export type InternalPagePreview = {
  types?: Record<string, InternalPreviewType>;
  unmapped?: Array<{
    post_id?: number;
    slug?: string;
    title?: string;
    mapping_needed?: boolean;
  }>;
  plan?: Record<string, { post_id?: number; layouts?: string[]; status?: string; reason?: string }>;
};

export type CompletionContract = {
  completed?: boolean;
  grandfathered_internal_pages?: boolean;
  required_count?: number;
  completed_count?: number;
  progress_text?: string;
  incomplete_notice?: boolean;
};

export type InternalCardView = {
  type: string;
  label: string;
  status: string;
  reason: string;
  postId: number;
  layouts: string[];
  mappingNeeded: boolean;
  showOverwrite: boolean;
  showConvert: boolean;
  resolved: boolean;
};

export const formatWizardProgress = (
  contract: CompletionContract | undefined,
  completedFallback: number,
  totalFallback: number
): { text: string; completed: number; total: number } => {
  if (contract?.progress_text) {
    return {
      text: contract.progress_text,
      completed: Number(contract.completed_count ?? completedFallback),
      total: Number(contract.required_count ?? totalFallback),
    };
  }

  return {
    text: `${completedFallback} of ${totalFallback} steps complete`,
    completed: completedFallback,
    total: totalFallback,
  };
};

export const displayStepStatus = (
  slug: string,
  status: string,
  contract: CompletionContract | undefined
): string => {
  if (
    contract?.grandfathered_internal_pages
    && slug === 'internal-page-builder'
    && (status === 'pending' || status === '')
  ) {
    return 'optional';
  }

  return status || 'pending';
};

export type StepFinishPlan = {
  reload: boolean;
  statusMessage: string;
  noticeMessage: string;
};

/**
 * Decide what happens when a step run finishes. A canceled destructive
 * confirmation is a strict local no-op: no state reload (no fetch/render),
 * no generic canceled status, no notice — the current panel, form values,
 * and context stay exactly as they were. A confirmed run reloads unless a
 * validation error blocked it.
 */
export const planStepFinish = (input: {
  canceled: boolean;
  validationBlocked: boolean;
}): StepFinishPlan => {
  if (input.canceled) {
    return { reload: false, statusMessage: '', noticeMessage: '' };
  }

  return {
    reload: !input.validationBlocked,
    statusMessage: '',
    noticeMessage: '',
  };
};

export type MapOnlyOutcome = {
  statusMessage: string;
  noticeMessage: string;
};

/**
 * Outcome copy for a metadata-only identity mapping. Never claims the step
 * completed: mapping assigns page types only; building/converting/skipping
 * is what moves the step status.
 */
export const mapOnlyOutcome = (): MapOnlyOutcome => ({
  statusMessage: 'Page types assigned.',
  noticeMessage: 'Page types assigned. Build or convert pages to begin the Internal Page Builder step.',
});

export type MapSelection = {
  postId: number;
  type: string;
};

export const takenMapTypes = (
  selections: MapSelection[],
  exceptPostId?: number
): string[] => (
  selections
    .filter((selection) => selection.type !== '' && selection.postId !== exceptPostId)
    .map((selection) => selection.type)
);

export const exclusiveMapSelections = (selections: MapSelection[]): MapSelection[] => {
  const seen = new Set<string>();

  return selections.map((selection) => {
    if (selection.type === '') {
      return selection;
    }
    if (seen.has(selection.type)) {
      return { ...selection, type: '' };
    }
    seen.add(selection.type);

    return selection;
  });
};

export const mappingConfirmationPayload = (
  mapPages: Array<{ post_id: number; type: string }>,
  confirmed: boolean
): { confirm_map: boolean; confirm_map_types: string[] } => {
  const types = Array.from(new Set(mapPages.map((page) => page.type).filter(Boolean))).sort();

  return {
    confirm_map: confirmed,
    confirm_map_types: confirmed ? types : [],
  };
};

/**
 * Decision payload for the independent mapping confirmation dialog.
 * Distinct from the shared destructive dialog: it carries the exact
 * confirmed type set so the server stays authoritative.
 */
export const mapDialogDecision = (
  confirmed: boolean,
  mapPages: Array<{ post_id: number; type: string }>
): { confirm_map: boolean; confirm_map_types: string[] } => mappingConfirmationPayload(mapPages, confirmed);

export type MapDialogDoc = {
  activeElement: { focus(): void } | null;
  addEventListener(type: 'keydown', fn: (event: KeyboardEvent) => void): void;
  removeEventListener(type: 'keydown', fn: (event: KeyboardEvent) => void): void;
};

export type MapDialogView = {
  dialog: { hidden: boolean };
  message: { textContent: string };
  accept: {
    focus(): void;
    addEventListener(type: 'click', fn: () => void): void;
    removeEventListener(type: 'click', fn: () => void): void;
  };
  cancel: Array<{
    addEventListener(type: 'click', fn: () => void): void;
    removeEventListener(type: 'click', fn: () => void): void;
  }>;
  doc: MapDialogDoc;
};

/**
 * Independent accessible mapping confirmation dialog. Own focus, cancel,
 * confirm, and Escape behavior; never reuses the shared destructive dialog
 * node or its controls. Resolves the exact-set mapping payload.
 */
export const openMapConfirmationDialog = (
  view: MapDialogView,
  copy: string,
  mapPages: Array<{ post_id: number; type: string }>
): Promise<{ confirm_map: boolean; confirm_map_types: string[] }> => {
  const previousFocus = view.doc.activeElement;
  view.message.textContent = copy;
  view.dialog.hidden = false;
  view.accept.focus();

  return new Promise((resolve) => {
    let onAccept: () => void;
    let onCancel: () => void;
    let onKeyDown: (event: KeyboardEvent) => void;
    const close = (confirmed: boolean): void => {
      view.dialog.hidden = true;
      view.accept.removeEventListener('click', onAccept);
      view.cancel.forEach((control) => control.removeEventListener('click', onCancel));
      view.doc.removeEventListener('keydown', onKeyDown);
      if (previousFocus) {
        previousFocus.focus();
      }
      resolve(mapDialogDecision(confirmed, mapPages));
    };
    onAccept = (): void => close(true);
    onCancel = (): void => close(false);
    onKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        close(false);
      }
    };
    view.accept.addEventListener('click', onAccept);
    view.cancel.forEach((control) => control.addEventListener('click', onCancel));
    view.doc.addEventListener('keydown', onKeyDown);
  });
};

/**
 * Build one card per unique eligible generated shell, keyed by post_id.
 * Resolved shells come from the server preview (available === true); unresolved
 * custom-slug shells come from preview.unmapped. Blueprints with no live shell
 * are excluded entirely — they are not generated pages and must not count in
 * progress or expose controls. Duplicate post_ids collapse to one card.
 */
export const buildInternalCardViews = (
  blueprints: Array<{ type: string; label: string; layouts: string[] }>,
  preview: InternalPagePreview | undefined
): InternalCardView[] => {
  const types = preview?.types ?? {};
  const byId = new Map<number, InternalCardView>();

  blueprints.forEach((blueprint) => {
    const previewEntry = types[blueprint.type] ?? {};
    if (previewEntry.available !== true) {
      return;
    }
    const postId = Number(previewEntry.post_id ?? 0);
    if (postId <= 0) {
      return;
    }
    const planEntry = (preview?.plan ?? {})[blueprint.type] ?? {};
    const status = String(previewEntry.status ?? planEntry.status ?? 'pending');
    const reason = String(previewEntry.reason ?? planEntry.reason ?? '');
    byId.set(postId, {
      type: blueprint.type,
      label: blueprint.label,
      status,
      reason,
      postId,
      layouts: Array.isArray(previewEntry.layouts) && previewEntry.layouts.length > 0
        ? previewEntry.layouts
        : (Array.isArray(planEntry.layouts) && planEntry.layouts.length > 0 ? planEntry.layouts : blueprint.layouts),
      mappingNeeded: false,
      showOverwrite: status === 'complete',
      showConvert: status === 'skipped' && reason === 'legacy_unconfirmed',
      resolved: true,
    });
  });

  (preview?.unmapped ?? []).forEach((page) => {
    const postId = Number(page.post_id ?? 0);
    if (postId <= 0 || byId.has(postId)) {
      return;
    }
    byId.set(postId, {
      type: '',
      label: page.title || page.slug || 'Unmapped generated page',
      status: 'skipped',
      reason: 'mapping_needed',
      postId,
      layouts: [],
      mappingNeeded: true,
      showOverwrite: false,
      showConvert: false,
      resolved: false,
    });
  });

  return Array.from(byId.values());
};

/**
 * Progress counts unique eligible generated shell post IDs only.
 * Complete counts resolved complete shells; mapping_needed shells are
 * unresolved, never duplicates.
 */
export const internalPageProgress = (cards: InternalCardView[]): { total: number; complete: number } => {
  const total = cards.length;
  const complete = cards.filter((card) => card.resolved && card.status === 'complete').length;

  return { total, complete };
};
