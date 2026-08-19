# Proposal: Wizard User-Friendly Content Flow

## Intent

Replace the raw Pages JSON content step with guided wizard steps non-technical users can complete: select pages, assign menus, configure AI, then build the Home page from reusable ACF sections.

## Scope

### In Scope
- Expand wizard order to: Dependencies → ACF Import → Client Data → Generate Pages → Menu Setup → IA Generation → Home Page Builder.
- Generate selected pages and let users mark Home and Blog.
- Configure primary/mobile menus from generated or selected pages.
- Keep IA Generation as standalone provider/model/API key setup before Home Page Builder.
- Build Home page sections with AI-filled content from Client Data; use image placeholders/fallbacks initially.

### Out of Scope
- Editing `wizard-prd.html`.
- Custom image generation/upload flow.
- Pushing local commits or changing delivery strategy.

## Capabilities

### New Capabilities
- `wizard-page-generation`: Guided page selection, page creation/update, and Home/Blog role assignment.
- `wizard-menu-setup`: Primary and mobile menu creation/assignment from selected pages.
- `wizard-ai-configuration`: Standalone AI provider/model/API key configuration for later content generation.
- `wizard-home-page-builder`: Home section selection and AI-assisted ACF flexible-content population using Client Data.

### Modified Capabilities
- None; no existing `openspec/specs/` capabilities are present.

## Approach

Extend the existing step architecture with new step handlers/services for page generation, menu setup, and Home page building. Remove the raw `content-creation` JSON UI path. Reuse `Content_Builder::build_page()`, existing ACF layouts, placeholder image fallbacks, encrypted AI credential storage, and the generic REST step dispatch.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/wizard/wizard-init.php` | Modified | Step list, required completion order, and guided panel markup. |
| `inc/wizard/class-step-controller.php` | Modified | Dispatch new step slugs and completion requirements. |
| `inc/wizard/class-content-builder.php` | Modified | Reuse/extend page and section creation helpers. |
| `inc/wizard/` | New | Add menu/page/home-builder step services as needed. |
| `src/ts/admin/wizard.ts` | Modified | Collect payloads for page, menu, AI config, and section selections. |
| `src/scss/admin/wizard.scss` | Modified | Style guided controls. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Step-count change breaks completion | Med | Update required slug lists and state transitions together. |
| Menu APIs are untested here | Med | Isolate menu builder and verify with WordPress menu functions. |
| Review exceeds 400 lines | High | Use chained PRs after asking, per strategy. |

## Rollback Plan

Revert the change branch commits to restore the 5-step wizard and original JSON-based `content-creation` path. Wizard state remains option-based; remove newly stored step state if needed during rollback.

## Dependencies

- Existing local Ollama Cloud provider integration.
- ACF PRO and current ACF JSON layouts.
- WordPress menu APIs.

## Success Criteria

- [ ] Users can complete the seven-step flow without writing JSON.
- [ ] Selected pages are created/updated and Home/Blog are assigned.
- [ ] Primary and mobile menus are assigned from selected pages.
- [ ] Home sections save to ACF flexible content with AI text and placeholder images.
