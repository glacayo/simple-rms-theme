# Wizard Menu Setup Specification

## Purpose

A guided wizard step that lets the admin assign pages generated in the previous step to the site's primary and mobile menu locations, then programmatically creates those menus in WordPress.

---

## Requirements

### Requirement: Page Source for Menu Items

The Menu Setup step MUST read the page list exclusively from `state.generated_pages` (populated by the Generate Pages step). Only wizard-generated pages MUST be offered as assignable menu candidates; pre-existing WordPress pages MUST NOT appear as options. If `state.generated_pages` is empty the step MUST display an error and block progression.

#### Scenario: Generated pages available

- GIVEN the Generate Pages step completed and created at least one page
- WHEN the admin opens the Menu Setup step
- THEN a list of wizard-generated page titles is shown as assignable items

#### Scenario: Pre-existing WordPress pages excluded

- GIVEN WordPress has pages that were not created by the current wizard run
- WHEN the admin opens the Menu Setup step
- THEN only pages from `state.generated_pages` appear as assignable items
- AND no pre-existing WordPress pages are shown

#### Scenario: No generated pages in state

- GIVEN `state.generated_pages` is empty
- WHEN the admin opens the Menu Setup step
- THEN the wizard displays: "No pages found. Please complete the Generate Pages step first" and the step cannot be submitted

---

### Requirement: Primary Menu Assignment

The admin MUST be able to select which generated pages to include in the **primary** menu. The wizard MUST create a `wp_nav_menu` named "Primary Menu" (or update it if it already exists), add the selected pages as menu items in the chosen order, and assign it to the `primary` theme location.

#### Scenario: Primary menu created and assigned

- GIVEN the admin selects two or more pages for the primary menu
- WHEN the step runs
- THEN `wp_create_nav_menu()` or `wp_update_nav_menu_item()` creates the items, and `set_theme_mod('nav_menu_locations', [...])` assigns the menu to `primary`

#### Scenario: Primary menu already exists

- GIVEN a menu named "Primary Menu" already exists
- WHEN the step runs
- THEN the wizard retrieves its ID and updates its items rather than creating a duplicate

---

### Requirement: Mobile Menu Assignment

The admin MUST be able to select which generated pages to include in the **mobile** menu independently of the primary menu. The wizard MUST create or update a `wp_nav_menu` named "Mobile Menu" and assign it to the `mobile` theme location.

#### Scenario: Mobile menu assigned to theme location

- GIVEN the admin selects pages for the mobile menu
- WHEN the step runs
- THEN the mobile menu is created/updated and assigned to the `mobile` location via `set_theme_mod`

#### Scenario: Admin assigns same pages to both menus

- GIVEN the admin selects identical pages for primary and mobile
- WHEN the step runs
- THEN two distinct menu objects are created/updated — one per location — without conflict

---

### Requirement: At Least One Menu Required

The wizard MUST require that the primary menu includes at least one page before the step can complete. The mobile menu MAY be left empty; it inherits the primary menu in that case.

#### Scenario: Empty primary menu blocked

- GIVEN no pages are assigned to the primary menu
- WHEN the admin submits the step
- THEN the wizard displays a validation error: "Primary menu requires at least one page"

#### Scenario: Empty mobile menu inherits primary

- GIVEN no pages are assigned to the mobile menu
- WHEN the step completes
- THEN the `mobile` theme location is assigned the same menu as `primary`

---

### Requirement: Step State Persistence

The wizard MUST persist the created menu IDs and location assignments to `state.menu_config` after the step completes. Subsequent steps MAY read this state for reference but MUST NOT depend on it to function.

#### Scenario: Menu IDs stored after completion

- GIVEN the step completes successfully
- WHEN wizard state is inspected
- THEN `state.menu_config` contains `{ primary_menu_id, mobile_menu_id, locations }` with the assigned IDs

---

### Requirement: Destructive Menu Replacement

Before creating wizard menus, the wizard MUST display a confirmation warning informing the admin that all existing WordPress nav menus and their theme location assignments will be removed and replaced by wizard-generated menus. The admin MUST explicitly confirm before the step proceeds. Upon confirmation, the wizard MUST remove all existing nav menus and clear their theme location assignments so that only wizard-generated menus are assigned after the step completes.

#### Scenario: Warning displayed before menu destruction

- GIVEN the admin submits the Menu Setup step
- WHEN WordPress has existing registered nav menus
- THEN the wizard displays: "Existing menus and location assignments will be removed and replaced. This cannot be undone."
- AND the step does not proceed until the admin explicitly confirms

#### Scenario: Admin confirms and existing menus are removed

- GIVEN the admin confirms the destructive warning
- WHEN the step runs
- THEN all existing WordPress nav menus are deleted and their theme location assignments are cleared
- AND only the wizard-generated menus are created and assigned to theme locations

#### Scenario: Admin cancels the warning

- GIVEN the warning is displayed
- WHEN the admin cancels or dismisses it
- THEN no menus are removed or created and the wizard remains on the Menu Setup step
