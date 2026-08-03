# Textile ERP Task Tracker

This is the single source of truth for Textile ERP delivery. It replaces the previous Textile FDS, architecture roadmap, and checklist, and clearly separates backend implementation from admin-portal usability.

This tracker now has two layers:
1. Phase-1 baseline delivery (implemented and verified workflow foundation)
2. Enterprise expansion backlog (broader textile ERP scope from traceability review)

Last updated: 2026-08-03

## How to read this tracker

| Status | Meaning |
|---|---|
| `[x]` | Fully implemented, visible where required, and verified. |
| `[~]` | Backend foundation exists and is tested, but the required admin UI is not complete. |
| `[ ]` | Not implemented. |

An item cannot be considered a complete user-facing feature until its backend, authorization, admin UI, and focused verification are complete.

## Shared UI rule (mandatory for all agents)

All textile admin UX must reuse the centralized shared components and helpers before creating any page-local UI pattern.

Required reuse-first order:
1. Use shared workflow/action utilities from `resources/js/components/textile` (for example workflow columns/actions and textile select field) for status-driven actions, selectors, helper text, and disabled reasons.
2. Extend shared components when a new UX need appears; do not duplicate the same logic across multiple pages.
3. Keep behavior consistent across Procurement, Sales, Manufacturing, and Processing screens by implementing cross-flow UX improvements in shared files first.
4. A task remains `[~]` (not `[x]`) if it introduces divergent page-level UX that should have been centralized.
5. Standard page composition is mandatory: KPI overview at top, then focused create/update forms, then records list/tables; use Inventory and Procurement pages as the reference layout pattern.
6. Menu and submenu wiring is mandatory: every new workflow slice must add or update sidebar menu placement and submenu links so the page or section is directly reachable in UI.
7. Controlled vocabulary fields (for example `*type`, `unit`, `machine`) must be select-based and sourced from textile master CRUD; avoid free-text for such fields in workflow forms.

Verification gate for this rule:
- Confirm changed textile pages consume shared components rather than introducing one-off equivalents.
- Confirm common behavior changes (grouping, action visibility, state messaging) are implemented in shared files and inherited by pages.
- Confirm each workflow-heavy page follows KPI -> forms -> list ordering before marking the task complete.
- Confirm menu and submenu entries exist for each delivered slice and navigate to the expected section/route before marking the task complete.
- Confirm controlled vocabulary fields are wired to master CRUD options (not free-text) before marking the task complete.

## Adaptive architecture rule book (mandatory for all future textile slices)

This ERP must adapt per company operating reality instead of assuming one fixed textile flow.

Required architecture rules:
1. Keep core workflow fields static and first-class when they drive status, costing, approvals, traceability, reports, or downstream business logic.
2. Use textile master CRUD for controlled vocabularies such as `type`, `unit`, `machine`, `status`, `reason`, `result`, and similar enumerated fields.
3. Use tenant operating policy for flow enablement. If a company does not run a capability such as sizing, own looms, shift planning, maintenance, job-work weaving, or job-work processing, the related menu entries, page sections, and server actions must be hidden or blocked.
4. Do not rely on frontend hiding alone. Any adaptive visibility rule must have matching server-side capability enforcement.
5. Prefer fine-grained capability keys over coarse domain flags when gating subflows. Example: `manufacturing_sizing` is preferred over only `manufacturing`.
6. Custom fields are additive only. They may extend metadata capture, but must not replace core operational fields or weaken typed validation for workflow-critical inputs.
7. New domain work must check whether earlier slices in the same operational path should be upgraded to the same adaptive capability model before adding more static behavior.
8. Fail open only for backward compatibility when capability data is absent; once a capability model is introduced for a slice, both menu and action handling must consume it consistently.

Verification gate for this rule:
- Confirm the slice has matching company settings or capability derivation logic when business applicability can differ per company.
- Confirm sidebar visibility, page section visibility, and controller/service enforcement follow the same capability model.
- Confirm earlier adjacent slices were reviewed for compatibility with the same adaptive architecture before closing the task.

For enterprise backlog items, this tracker also references feature classification from the traceability matrix:
- `Reuse` = already available in ERPGo and/or current textile slices.
- `Extend` = base exists and needs textile-specific enrichment.
- `Modify` = existing behavior must be changed/refactored for enterprise use.
- `New` = new package/module needed.

Reference artifact: `plans/textile-enterprise-traceability-matrix.md`

## UI Shell Redesign: workspace left-rail navigation (app-wide)

Problem: workspace pages (Procurement, Manufacturing, Finance, Reports, etc.) use hardcoded tab bars with 2-14 tabs that wrap and overlap; the sidebar submenu (144 items, 3 levels) duplicates the same section list, and every page repeats the same section orchestration + KPI + form + table blocks (33 duplicated `grid gap-6 xl:grid-cols-2` blocks across 11 files).

Target design: left-rail section navigation inside each workspace (Linear/Supabase pattern), deep-linked via `?section=`, with per-section KPIs. One registry + two shared components; no per-page duplication.

### Phase 0 — Foundation (shared, single place)

- [ ] `resources/js/components/textile/textile-workspaces.ts` — app-wide registry: every workspace `{ id, label, icon, route, capability, sections: [{ id, label, icon, capability? }] }`; drives sidebar menu AND workspace rails (single source of truth, no drift).
- [ ] `TextileWorkspace` component — left rail (icon rail, collapsible), workspace header (title + breadcrumb), URL `?section=` handling, capability filtering, per-section KPI slot, standard `KPI -> form -> table` body via `TextileSection`.
- [ ] `TextileSection` component — standard section body (KPI strip + form card + data table card) replacing the 33 duplicated grid blocks.
- [ ] `useTextileSection()` hook — section param read/write; `useSectionKpis(rows)` helper — per-section status counts.
- [ ] `company-menu.ts` — collapse submenu items to workspace level for rail-driven workspaces (menu becomes group > workspace, 2 levels; rail owns sections).

### Phase 1 — Pilot

- [ ] Refactor `Procurement` (6 sections) onto the registry + `TextileWorkspace`; verify tsc/build/browser + menu route diff.

### Phase 2 — Workflow workspaces (rollout, smallest first)

- [ ] `Sales` (3 sections) → `Transport` (3) → `Packing` (4) → `Dispatch` (2-6) → `Maintenance` (6) → `Finance` (7) → `Quality` (8) → `Manufacturing` (7, largest page 1873 lines, split during refactor) → `Processing` (11) → `Reports` (14; rail, revisit hub-cards only if sections grow independent filter bars).

### Phase 3 — Master/CRUD pages (light touch)

- [ ] `Masters`, `Specifications`, `Costing`, `Approvals`, `CostCenters`, `CustomFields`, `OperatingPolicy`, `Logs`, `DispatchVehicles`, `DispatchDrivers`, `DispatchRoutes` — adopt shared `TextileFormCard`/`TextileSection`/KPIs where duplicated; no rail needed (single-purpose pages).

### Phase 4 — Dashboard decision

- [ ] `Dashboard` (7 chart tabs) — decision: keep tabs (charts want horizontal space) or convert to rail; do not force one pattern on a charts-first page without a visual check.

### Verification gate (per phase)

- `npx tsc --noEmit` => 0 errors; `npm run build` => pass; `php artisan test tests/Feature/Textile` => pass; browser check of every refactored workspace (rail renders, `?section=` deep links work, capability filtering respected); `route()` diff between menu/registry and `php artisan route:list` => empty.

## Layer 1: Phase-1 baseline (delivered)

## Platform access and tenant enablement

| Task | Backend | Admin UI | Status | Current evidence / gap |
|---|---:|---:|---|---|
| Superadmin access to Settings | Yes | Yes | `[x]` | Superadmin bypass added to settings permission checks. |
| Superadmin access to Plans | Yes | Yes | `[x]` | `/plans` is routed through Laravel instead of Apache's physical `plans/` directory. |
| Customer Textile enablement | Yes | Yes | `[x]` | Superadmin can assign `Textile` or `Standard` to a company through the Users page. |
| Show company access plan and industry in Users list | Yes | Yes | `[x]` | Superadmin sees the company access-plan name and Textile/Standard status in list and grid views. |
| Reassign a company to Textile | Yes | Yes | `[x]` | One Industry Access action adds or removes TextileCore and TextileInventory without changing the base access plan. |

## TextileCore

| Task | Backend | Admin UI | Status | Current evidence / gap |
|---|---:|---:|---|---|
| Specifications create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile specification admin test. |
| Specifications edit/deactivate | Yes | Yes | `[x]` | Added update/deactivate endpoints and UI actions; verified in Textile specification admin test. |
| Quality profiles create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile master-data admin test. |
| Quality profiles edit/deactivate | Yes | Yes | `[x]` | Added update/deactivate endpoints and UI actions; verified in Textile master-data admin test. |
| Route recipes create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile master-data admin test. |
| Route recipes edit/deactivate | Yes | Yes | `[x]` | Added update/deactivate endpoints and UI actions; verified in Textile master-data admin test. |
| Unit conversions create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile master-data admin test. |
| Unit conversions edit/deactivate | Yes | Yes | `[x]` | Added update/deactivate endpoints and UI actions; verified in Textile master-data admin test. |
| Cost centers create/list/edit/deactivate (tenant scoped) | Yes | Yes | `[x]` | Added cost-center master with tenant-scoped admin page/menu and verified in Textile cost-center admin test. |

## TextileInventory

| Task | Backend | Admin UI | Status | Current evidence / gap |
|---|---:|---:|---|---|
| Lots create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile inventory admin test. |
| Lots roll drilldown/edit/deactivate | Yes | Yes | `[x]` | Lot drilldown page plus lot status update/archive controls implemented and verified in Textile inventory admin test. |
| Location master create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile inventory admin test; rack/bin location metadata added and verified in Textile inventory admin test (1 test, 36 assertions). |
| Location deactivate/archive | Yes | Yes | `[x]` | Location archive control implemented and verified in Textile inventory admin test. |
| Movement create/list (receipt/issue/transfer) | Yes | Yes | `[x]` | Implemented and verified in Textile inventory admin test. |
| Movement filtered history (type/status/lot/location) | Yes | Yes | `[x]` | Implemented and verified in Textile inventory admin test. |
| Reservation create/list + lot availability visibility | Yes | Yes | `[x]` | Implemented and verified in Textile inventory admin test. |
| Reservation release/unreserve | Yes | Yes | `[x]` | Reservation release (unreserve) implemented and verified in Textile inventory admin test. |
| Reservation to demand-allocation linkage | Yes | Yes | `[x]` | Reservation allocation-link action implemented and verified in Textile inventory admin test. |

## TextileProcurement

| Task | Backend | Admin UI | Status | Current evidence / gap |
|---|---:|---:|---|---|
| Purchase requisition workflow | Yes | Yes | `[x]` | Procurement screen now supports requisition create/list/approve and is verified in Textile procurement admin test. |
| Purchase order workflow | Yes | Yes | `[x]` | Procurement screen now supports purchase order create/list/approve and is verified in Textile procurement admin test. |
| GRN and incoming QC workflow | Yes | Yes | `[x]` | Procurement screen supports GRN create/release, incoming QC create/finalize, and GRN draft purchase-invoice sync visibility, verified in Textile procurement admin test. |

## TextileSales

| Task | Backend | Admin UI | Status | Current evidence / gap |
|---|---:|---:|---|---|
| Sales order workflow | Yes | Yes | `[x]` | Sales screen now supports sales-order create/list/approve and is verified in Textile sales admin test. |
| Allocation and dispatch workflow | Yes | Yes | `[x]` | Sales screen now supports allocation create/release and dispatch create/release and is verified in Textile sales admin test. |
| Challan and POD workflow | Yes | Yes | `[x]` | Sales screen now supports challan create and POD marking with invoice-ready metadata and is verified in Textile sales admin test. |

## TextileManufacturing

| Task | Backend | Admin UI | Status | Current evidence / gap |
|---|---:|---:|---|---|
| Beam and production batch workflow | Yes | Yes | `[x]` | Manufacturing screen now supports beam create/approve and production batch create/release and is verified in Textile manufacturing admin test. |
| Weaving and grey output workflow | Yes | Yes | `[x]` | Manufacturing screen now supports weaving output recording from released batches and is verified in Textile manufacturing admin test. |
| Waste and rework workflow | Yes | Yes | `[x]` | Manufacturing screen now supports waste and rework recording and is verified in Textile manufacturing admin test. |

## Remaining roadmap phases

| Task | Backend | Admin UI | Status | Next outcome |
|---|---:|---:|---|---|
| Inspection and hold/release workflow | Yes | Yes | `[x]` | Textile quality admin workflow now supports inspection create/finalize and lot hold/release with tenant scope, verified in Textile quality admin test. |
| Job work and processing workflow | Yes | Yes | `[x]` | Textile processing admin workflow now supports job-work outward/inward custody, processing batch release, and outward-inward reconciliation with tenant scope, verified in Textile processing admin test. |
| Costing and margin tracking | Yes | Yes | `[x]` | Textile costing admin workflow now supports cost entry capture, finalize posting, total/unit cost computation, and margin snapshot generation with tenant scope, verified in Textile costing admin test. |
| Textile dashboards and reports | Yes | Yes | `[x]` | Textile dashboard page now shows tenant-scoped workflow aggregates, recent documents, and margin snapshots, verified in Textile dashboard admin test. |
| API support for implemented workflows | Yes | Not applicable | `[x]` | Textile API endpoints exist for current backend slices. |
| Tenant-isolation verification | Yes | Not applicable | `[x]` | Textile feature tests cover tenant-scoped behavior. |

## Simple usage guide (layman SOP)

Use this section as the day-to-day operating order. Follow steps in sequence.

### A. First-time setup (do once per company)

1. Assign company industry as `Textile` from Users.
2. Create users and roles: Purchase, Store, QC, Production, Sales, Manager.
3. Create core masters:
	- Specifications
	- Quality Profiles
	- Route Recipes
	- Unit Conversions
4. Create business masters:
	- Products (yarn/fabric and categories)
	- Vendors
	- Customers
5. Create inventory structure:
	- Warehouses/Locations
	- Opening lots/opening stock (if migration case)

### B. Daily operations flow

1. Procurement (buy)
	- Requisition -> Purchase Order -> GRN -> Incoming QC
2. Inventory (control)
	- Lot receipt/movement/reservation and location tracking
3. Manufacturing (if in-house)
	- Beam -> Production Batch -> Weaving Output -> Waste/Rework
4. Processing (if job work)
	- Job Work Outward -> Processing Batch -> Job Work Inward -> Reconciliation
5. Sales (sell)
	- Sales Order -> Allocation -> Dispatch -> Challan -> POD
6. Costing and review
	- Costing entry -> Margin snapshot -> Dashboard review

### C. Exception handling flow

1. If incoming material fails QC: hold/reject and stop downstream usage.
2. If stock is insufficient: stop allocation and resolve via purchase/transfer.
3. If production variance occurs: log waste/rework before closing batch.

### D. Role-wise quick start

1. Purchase user: Requisition, PO, GRN follow-up.
2. Store user: Lots, movements, reservations, location controls.
3. QC user: Incoming QC, inspection, hold/release.
4. Production user: Beam/batch/output/waste/rework.
5. Sales user: SO/allocation/dispatch/challan/POD.
6. Manager: approval, costing, dashboard, exceptions.

### E. Go-live trial (recommended)

1. Run one complete purchase cycle.
2. Run one complete production or processing cycle.
3. Run one complete sales-dispatch cycle.
4. Validate costing and dashboard numbers.
5. Then onboard all users.

## Guide-to-tracker alignment (single source check)

| SOP block | Tracker section |
|---|---|
| First-time setup | Layer 1: Platform access + TextileCore + Inventory masters |
| Procurement flow | Layer 1: TextileProcurement |
| Inventory control | Layer 1: TextileInventory |
| Manufacturing flow | Layer 1: TextileManufacturing |
| Processing flow | Layer 1: Remaining roadmap phases (job work and processing) |
| Sales flow | Layer 1: TextileSales |
| Costing and review | Layer 1: Remaining roadmap phases (costing, dashboards/reports) |
| Enterprise expansion beyond baseline | Layer 2: Enterprise atomic master checklist |

Alignment rule:
- Any new flow step discovered during implementation must be added in both this SOP section and the relevant Layer 1/Layer 2 task row.

## Domain-first execution mode (how to work this plan)

Use this operating mode to reduce planning noise: finish one domain end-to-end before moving to the next.

Priority labels still exist in the matrix, but they are planning annotations only. They do not override the domain execution order below.

Execution rule per domain:
1. Pick the next domain in the order below.
2. Complete the full domain slice end-to-end using shared UI, tenant scope, route/menu placement, and focused verification.
3. Update the domain row evidence the same day.
4. Move to the next domain only after the current one is materially done.

Status discipline:
- Keep `[~]` if any part is pending (backend, UI, validation, or menu/submenu gate).
- Mark `[x]` only when all done criteria are satisfied.

Suggested weekly rhythm:
1. Pick 1 domain.
2. Finish it end-to-end.
3. Run focused tests and build.
4. Update status and evidence in this tracker same day.

Current execution order:
1. Core ERP
2. Supplier Management
3. Product Master
4. Yarn Management
5. Purchase
6. Inventory
7. Warping and Sizing
8. Beam Management
9. Loom Management and Production Planning
10. Weaving Production and Grey Fabric
11. Quality
12. Finance


## Layer 2: Enterprise atomic master checklist (single source for marking)

This is the one-by-one execution checklist for the complete enterprise scope.
Use this for marking so work is never split across multiple lists.

Status rules for this section:
- `[x]` = Done for enterprise expectation (implemented/reused and verified).
- `[~]` = Partially covered; extension or modification still pending.
- `[ ]` = Not started (new module or major pending work).

Source mapping: `plans/textile-enterprise-traceability-matrix.md`.

### ERP menu and submenu architecture (mandatory gate)

This section ensures ERP-style navigation reuse is treated as a required deliverable for every domain.

| Task | Scope | Status | Done criteria |
|---|---|---|---|
| Reuse ERPGo visual pattern for sidebar, groups, collapse behavior, and icon style | Global | `[~]` | Textile navigation follows existing ERPGo design language and interaction pattern without introducing conflicting UX patterns. |
| Define top-level textile menu groups | Global | `[~]` | Main groups are fixed and documented (Core, CRM, Suppliers, Product, Purchase, Inventory, Planning, Manufacturing, Quality, Packing, Dispatch, Finance, Reports, Dashboards, Integrations). |
| Define per-group submenu map (one feature page per menu where appropriate) | Global | `[~]` | Each feature in Layer 2 maps to a clear submenu destination and route target. |
| Standardize page type decision (`popup` vs `full page`) | Global | `[x]` | Small transactional actions use modal/popup; large workflows use dedicated pages with list/filter/detail patterns. |
| Add navigation acceptance checklist to every domain delivery | Global | `[x]` | No domain task is marked `[x]` unless menu placement, submenu route, and breadcrumb behavior are verified. |
| Tenant-aware menu filtering (Textile vs Standard + module assignment) | Global | `[x]` | Already implemented in middleware/menu resolver; continue as a non-regression gate for new menus. |
| Breadcrumb and page-title consistency per submenu | Global | `[~]` | Every submenu page has predictable breadcrumb root and domain-specific title naming. |
| Searchability and quick-jump in menu for large domains | Global | `[ ]` | Menu search and/or quick command supports deep feature navigation at enterprise scale. |

Menu completion rule:
- A feature stays `[~]` (not `[x]`) if backend is done but final ERP-style menu/submenu placement is not delivered and validated.

### Page type decision standard (locked)

Use this matrix for all future slices:

| Interaction size | Pattern | Examples |
|---|---|---|
| Small, single-purpose action with 1-3 inputs | Popup/modal | Approve, release, quick status update, attach brief note |
| Medium action with cross-field dependency but no records table context switch | Inline form block inside the page section | Allocation link, reservation link, hold/release with selector + reason |
| Large workflow with lifecycle, multiple dependent actions, and records/history visibility | Full page section with KPI -> forms -> list/tables | Procurement, Inventory, Manufacturing, Processing, Sales, Quality |

Rules:
- Do not create a dedicated new route for actions that qualify as popup/modal unless they need substantial context.
- Do not collapse full workflows into modal-only experiences.
- When uncertain, default to full page if action needs historical context or multiple downstream transitions.

### Navigation acceptance checklist (mandatory before marking `[x]`)

Every delivered task must include this evidence in its progress note:

1. Menu placement: parent group and sidebar ordering verified.
2. Submenu route: direct link lands on the intended route and section/subsection.
3. Breadcrumb: root is stable (`Textile`) and domain label is correct.
4. Page title: title reflects submenu context and is consistent with breadcrumb endpoint.
5. Controlled selectors: domain-controlled fields are select-based (no free-text enum fields).
6. Verification command: include focused test/build command output reference.

Progress note (2026-08-03):
- Page-type decision matrix and navigation acceptance checklist are now defined in this tracker and become hard gate criteria for all subsequent domain rows.
- Adaptive operating capability layer extended: tenant operating policy now supports fine-grained company settings (for example warping, sizing, looms, weaving, shift planning, maintenance, job-work modes) so sidebar submenus and textile page sections can progressively adapt per company beyond coarse procurement/manufacturing/processing toggles.

### Domain 1: Core ERP (16 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Dashboard | 🟡 Extend Existing | P1 | `[x]` |
| User Management | ✅ Already Available | P1 | `[x]` |
| Roles & Permissions | ✅ Already Available | P1 | `[x]` |
| Multi Company | ✅ Already Available | P1 | `[x]` |
| Multi Branch | 🟡 Extend Existing | P2 | `[~]` |
| Multi Warehouse | ✅ Already Available | P1 | `[x]` |
| Multi Currency | ✅ Already Available | P2 | `[x]` |
| Multi Language | ✅ Already Available | P1 | `[x]` |
| SaaS Subscription | ✅ Already Available | P1 | `[x]` |
| Activity Logs | 🔵 Modify Existing | P1 | `[x]` |
| Audit Logs | 🔵 Modify Existing | P1 | `[x]` |
| Notifications | 🟡 Extend Existing | P2 | `[~]` |
| Email | ✅ Already Available | P2 | `[x]` |
| File Attachments | ✅ Already Available | P2 | `[x]` |
| Approval Workflow | 🆕 New Module Required | P1 | `[x]` |
| Custom Fields | 🆕 New Module Required | P2 | `[x]` |
| Tags | 🆕 New Module Required | P2 | `[ ]` |
| Comments | 🟡 Extend Existing | P2 | `[~]` |

Progress note (2026-08-03):
- Domain 1 custom fields slice delivered in TextileCore as tenant-scoped CRUD (create/edit/deactivate) with controlled field types/options, route wiring, and Core Setup submenu navigation.
- Verification: `php artisan test tests/Feature/Textile/TextileCustomFieldAdminTest.php tests/Feature/Textile/TextileMasterDataAdminTest.php` => `2 passed (69 assertions)`; `npm run build` => pass (existing chunk-size warnings only).

### Domain 2: CRM (11 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Leads | ✅ Already Available | P1 | `[x]` |
| Customers | ✅ Already Available | P1 | `[x]` |
| Contacts | 🟡 Extend Existing | P2 | `[x]` |
| Customer Categories | 🆕 New Module Required | P2 | `[x]` |
| Follow Ups | 🟡 Extend Existing | P2 | `[x]` |
| Quotations | ✅ Already Available | P1 | `[x]` |
| Sales Orders | 🟡 Extend Existing | P1 | `[x]` |
| Customer Operating Model Profiles | 🆕 New Module Required | P1 | `[x]` |
| Customer Price List | 🆕 New Module Required | P2 | `[x]` |
| Credit Limits | 🔵 Modify Existing | P2 | `[x]` |
| Customer Documents | 🟡 Extend Existing | P2 | `[x]` |

Progress note (2026-08-03):
- Domain 2 customer categories slice delivered using Account + Textile navigation: tenant-scoped category master CRUD, category selector in customer create/edit, and customer list/detail visibility for category mapping.
- Navigation: added submenu link under Textile -> Master Setup -> CRM Setup -> Customer Categories and Account -> Accounting -> Customer Categories.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/Account/CustomerCategoryTest.php tests/Feature/Textile/TextileCustomerOperatingProfileAdminTest.php` => `3 passed (38 assertions)`; `npm run build` => pass (existing chunk-size warnings only).
- Domain 2 customer price list slice delivered using Account + Textile navigation: tenant-scoped customer-item pricing CRUD with controlled selectors for Customer, Item, and Currency.
- Navigation: added submenu link under Textile -> Master Setup -> CRM Setup -> Customer Price List and Account -> Accounting -> Customer Price List.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/Account/CustomerPriceListTest.php tests/Feature/Account/CustomerCategoryTest.php tests/Feature/Textile/TextileCustomerOperatingProfileAdminTest.php` => `4 passed (48 assertions)`; `npm run build` => pass (existing chunk-size warnings only).
- Domain 2 contacts/follow-ups/documents/credit-limits slices delivered using Account + Textile navigation: tenant-scoped CRUD pages for Customer Contacts, Customer Follow Ups, and Customer Documents; customer credit limit + currency integrated in customer create/edit/view/list with controlled currency selection.
- Sales Orders CRM extension validation completed: textile sales flow is customer-linked and operating-model aware (`textile.sales.orders.store`) with existing tests retained.
- Navigation: added submenu links under Textile -> Master Setup -> CRM Setup and Account -> Accounting for Customer Contacts, Customer Follow Ups, and Customer Documents.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/Account/CustomerCrmCompletionTest.php tests/Feature/Textile/TextileSalesAdminTest.php tests/Feature/Textile/TextileCustomerOperatingProfileAdminTest.php` => pass; `npm run build` => pass (existing chunk-size warnings only).

### Customer operating model mapping (real-world)

Use this mapping to support different textile customer business styles without forcing one fixed flow.

| Customer type | Material ownership | Billing mode | Primary operational flow | Status |
|---|---|---|---|---|
| Full-package buyer (finished fabric) | Company owned | Sale value | Sales Order -> Allocation -> Dispatch -> Challan -> POD -> Invoice | `[x]` |
| Job-work weaving (customer beam/yarn supplied, powerloom focused) | Customer owned | Conversion charge | Beam Inward/Reference -> Production Batch -> Weaving Output -> Dispatch/POD -> Job-work Invoice | `[x]` |
| Processing-only customer (grey supplied) | Customer owned | Process charge | Job-work Outward -> Processing Batch -> Job-work Inward -> Reconciliation -> Invoice | `[x]` |
| Trader/distributor bulk buyer | Company owned | Sale value with price list/credit rules | Sales Order -> Dispatch -> Invoice -> Collection | `[x]` |
| Export/compliance customer | Mixed | Hybrid | Sales/Dispatch + QC/Certificates + compliance docs | `[x]` |

Implementation gate for this mapping:
- Add customer profile fields: `operating_model`, `material_ownership`, `billing_mode`.
- Add profile-based workflow toggles (menu + validation) so powerloom-only and own-beam customers see only relevant flow steps.
- Add costing split behavior: customer-owned material should post conversion/process costs without company raw-material valuation.

Progress note (2026-08-03):
- Customer profile fields are implemented in Account customer schema/backend/forms/list/view using shared TextileSelectField UX.
- Profile-based flow toggles are now delivered via operating-policy capabilities exposed to shared auth props, menu capability filtering, and server-side sales validation that blocks job-work-only customer profiles from sales-order flow.
- Costing split behavior is now delivered: customer-owned profile entries enforce conversion-only costing by excluding company material valuation (`material_cost` forced to `0`) while preserving entered material cost for audit in metadata.
- Verification: `php artisan test tests/Feature/Textile/TextileCustomerOperatingProfileAdminTest.php tests/Feature/Textile/TextileCostingAdminTest.php tests/Feature/Textile/TextileOperatingPolicyAdminTest.php` => `5 passed (45 assertions)`.
- Mapping hard gates extended: trader bulk now requires positive credit limit + at least one active customer price-list entry before sales order; export/compliance now requires at least one active non-expired compliance document before dispatch.
- Verification: `php artisan test tests/Feature/Textile/TextileCustomerOperatingModelMappingTest.php tests/Feature/Textile/TextileCustomerOperatingProfileAdminTest.php tests/Feature/Textile/TextileSalesAdminTest.php` => pass; `npm run build` => pass (existing chunk-size warnings only).
- Operating model governance extended to support multi-select profile mapping with effective-dated profile history (`textile_operating_profiles`) while preserving primary profile compatibility for existing flows.
- Verification: `php artisan test tests/Feature/Textile/TextileOperatingPolicyAdminTest.php tests/Feature/Textile/TextileCustomerOperatingModelMappingTest.php tests/Feature/Textile/TextileSalesAdminTest.php` => `5 passed (54 assertions)`; `npm run build` => pass (existing chunk-size warnings only).
- SaaS governance control slice completed: added tenant module entitlements, company self-serve activation/deactivation, superadmin approval flow for restricted modules, and governance audit logging with safe deactivation guards.
- UI wiring: new `Settings -> Module Governance` section for both company and superadmin users, with tenant selector, entitlement toggles, activation actions, request review, and audit feed.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/ModuleGovernanceFlowTest.php tests/Feature/Textile/TextileOperatingPolicyAdminTest.php tests/Feature/Textile/TextileCustomerOperatingModelMappingTest.php` => `6 passed (34 assertions)`; `npm run build` => pass (existing chunk-size warnings only).
### Domain 3: Supplier Management (9 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Yarn Suppliers | 🟡 Extend Existing | P1 | `[x]` |
| Chemical Suppliers | 🟡 Extend Existing | P1 | `[x]` |
| Spare Part Suppliers | 🟡 Extend Existing | P2 | `[x]` |
| Processing Vendors | 🟡 Extend Existing | P2 | `[x]` |
| Dyeing Vendors | 🟡 Extend Existing | P2 | `[x]` |
| Transport Vendors | 🔵 Modify Existing | P2 | `[x]` |
| Vendor Rating | 🆕 New Module Required | P2 | `[x]` |
| Vendor Performance | 🆕 New Module Required | P2 | `[x]` |
| Job Workers | 🔵 Modify Existing | P2 | `[x]` |

Progress note (2026-08-03):
- Vendor CRUD now supports supplier classification and filtering through the shared Account vendor workflow, covering yarn supplier usage first without introducing a separate supplier UI.
- Verification: `php artisan test tests/Feature/Account/VendorSupplierTypeTest.php` => `1 passed (18 assertions)`.
- Supplier Management now reuses the same vendor workflow for yarn, chemical, spare-part, processing, dyeing, transport, and job-worker classifications.
- Vendor Rating slice completed: tenant-scoped vendor rating CRUD (quality/delivery/service/price with computed overall score), Account + Textile Supplier Setup navigation, and controlled select-driven rating fields.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/Account/VendorRatingTest.php tests/Feature/Account/VendorSupplierTypeTest.php` => `2 passed (37 assertions)`; `php artisan test tests/Feature/Textile/TextileCustomerOperatingModelMappingTest.php tests/Feature/Textile/TextileOperatingPolicyAdminTest.php` => `4 passed (27 assertions)`; `npm run build` => pass (existing chunk-size warnings only).
- Vendor Performance slice completed: monthly tenant-scoped performance snapshots generated from active vendor ratings (rating count + average quality/delivery/service/price/overall) with Supplier Setup navigation and controlled month/vendor selectors.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/Account/VendorRatingTest.php tests/Feature/Account/VendorPerformanceTest.php tests/Feature/Account/VendorSupplierTypeTest.php tests/Feature/Textile/TextileOperatingPolicyAdminTest.php` => `5 passed (64 assertions)`; `npm run build` => pass (existing chunk-size warnings only).
### Domain 4: Product Master (13 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Yarn | 🔵 Modify Existing | P1 | `[x]` |
| Fabric | 🔵 Modify Existing | P1 | `[x]` |
| Grey Fabric | 🔵 Modify Existing | P1 | `[x]` |
| Finished Fabric | 🔵 Modify Existing | P1 | `[x]` |
| Chemicals | 🆕 New Module Required | P2 | `[x]` |
| Packing Materials | 🆕 New Module Required | P2 | `[x]` |
| Spare Parts | 🔵 Modify Existing | P2 | `[x]` |
| Accessories | 🟡 Extend Existing | P2 | `[x]` |
| Product Variants | 🔵 Modify Existing | P2 | `[x]` |
| Product Specifications | ✅ Already Available | P1 | `[x]` |
| Product Images | 🔵 Modify Existing | P2 | `[x]` |
| Product Documents | 🟡 Extend Existing | P2 | `[x]` |

Progress note (2026-08-03):
- ProductService item taxonomy now supports textile product classifications through the shared item workflow, including yarn, fabric, grey fabric, finished fabric, chemical, packing material, spare part, and accessory labels.
- Verification: `php artisan test tests/Feature/ProductService/ProductTypeTest.php` => `1 passed (20 assertions)`.
- Domain 4 completion slice delivered: tenant-scoped Product Variants, Product Images, and Product Documents workflows with KPI -> one-form -> list pattern, edit/delete actions, and submenu wiring under Textile -> Master Setup -> Product Setup and Account -> Accounting.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/ProductService/ProductTypeTest.php tests/Feature/ProductService/ProductMasterExtensionTest.php` => `2 passed`; `npm run build` => pass (existing chunk-size warnings only).
### Domain 5: Yarn Management (14 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Yarn Type | 🔵 Modify Existing | P1 | `[x]` |
| Yarn Count | 🔵 Modify Existing | P1 | `[x]` |
| Denier | 🔵 Modify Existing | P1 | `[x]` |
| Blend | 🔵 Modify Existing | P1 | `[x]` |
| Shade | 🔵 Modify Existing | P1 | `[x]` |
| Mill | 🔵 Modify Existing | P1 | `[x]` |
| Brand | 🟡 Extend Existing | P1 | `[x]` |
| Lot Number | ✅ Already Available | P1 | `[x]` |
| Cone Number | 🆕 New Module Required | P1 | `[x]` |
| Cone Weight | 🆕 New Module Required | P1 | `[x]` |
| Net Weight | 🔵 Modify Existing | P1 | `[x]` |
| Gross Weight | 🔵 Modify Existing | P1 | `[x]` |
| Moisture | 🟡 Extend Existing | P2 | `[x]` |
| Quality Grade | ✅ Already Available | P1 | `[x]` |
| Yarn Cost | 🔵 Modify Existing | P2 | `[x]` |
| Yarn Barcode | 🆕 New Module Required | P2 | `[x]` |
| Yarn QR Code | 🆕 New Module Required | P2 | `[x]` |

Progress note (2026-08-03):
- TextileCore specifications now carry yarn attributes alongside the existing fabric dimensions, reusing the shared TextileCore master-data workflow.
- Verification: `php artisan test tests/Feature/Textile/TextileSpecificationAdminTest.php` => `1 passed (11 assertions)`.
- Domain 5 completion slice delivered in ProductService shared item flow: controlled yarn tracking fields for cone number, cone weight, yarn barcode, and yarn QR code in create/edit paths with tenant scoping.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/ProductService/ProductTypeTest.php tests/Feature/ProductService/ProductMasterExtensionTest.php` => `2 passed`; `npm run build` => pass (existing chunk-size warnings only).
### Domain 6: Purchase (8 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Purchase Requisition | ✅ Already Available | P1 | `[x]` |
| RFQ | 🟡 Extend Existing | P2 | `[x]` |
| Purchase Order | ✅ Already Available | P1 | `[x]` |
| Goods Receipt (GRN) | ✅ Already Available | P1 | `[x]` |
| Purchase Invoice | 🟡 Extend Existing | P1 | `[x]` |
| Purchase Return | ✅ Already Available | P1 | `[x]` |
| Supplier QC | ✅ Already Available | P1 | `[x]` |
| Supplier Claims | 🆕 New Module Required | P2 | `[x]` |

Progress note (2026-08-03):
- Purchase invoices and purchase returns now reuse the shared vendor supplier classification in their list filters and summary columns, so yarn/chemical/processing vendors can be segmented without a separate purchase taxonomy.
- Verification: `php artisan test tests/Feature/Purchase/PurchaseInvoiceVendorTypeTest.php` => `1 passed (36 assertions)`.
- Domain 6 completion slice delivered in Textile Procurement workflow: RFQ create/send/close lifecycle, PO conversion from requisition or RFQ source, and Supplier Claims create/approve/settle from released GRN with structured claim metadata.
- Navigation: added Procurement submenu entries for RFQ and Supplier Claims.
- Verification: `php artisan migrate --force`; `php artisan test tests/Feature/Textile/TextileProcurementAdminTest.php` => `1 passed`; `npm run build` => pass (existing chunk-size warnings only).

Progress note (2026-08-03):
- Inventory location metadata now includes rack/bin, lot tracking now includes batch number, and adjustment movements now support increase/decrease direction with availability sync.
- Verification: `php artisan test tests/Feature/Textile/TextileInventoryAdminTest.php` => `1 passed (50 assertions)`.

Progress note (2026-08-03):
- Inventory physical verification now posts variance as an auto-adjustment movement (`reference_type=physical_verification`) and synchronizes lot availability.
- Verification: `php artisan test tests/Feature/Textile/TextileInventoryAdminTest.php` => `1 passed (52 assertions)`.

Progress note (2026-08-03):
- Inventory stock-freeze controls now support lot freeze/unfreeze with freeze note, and frozen lots are blocked from movement/reservation until unfreeze.
- Verification: `php artisan test tests/Feature/Textile/TextileInventoryAdminTest.php` => `1 passed (55 assertions)`.

Progress note (2026-08-03):
- Inventory domain completion slice delivered: lot-level barcode/QR fields, cycle-count posting with variance records, and cycle-count auto-adjustment movement sync for lot availability.
- Verification: `php artisan test tests/Feature/Textile/TextileInventoryAdminTest.php` => `1 passed (62 assertions)`.
### Domain 7: Inventory (12 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Multi Warehouse | ✅ Already Available | P1 | `[x]` |
| Rack | 🟡 Extend Existing | P2 | `[x]` |
| Bin | 🔵 Modify Existing | P2 | `[x]` |
| Lot Tracking | ✅ Already Available | P1 | `[x]` |
| Batch Tracking | 🟡 Extend Existing | P1 | `[x]` |
| Barcode | 🆕 New Module Required | P2 | `[x]` |
| QR Code | 🆕 New Module Required | P2 | `[x]` |
| Stock Transfer | 🟡 Extend Existing | P1 | `[x]` |
| Stock Adjustment | 🔵 Modify Existing | P2 | `[x]` |
| Stock Reservation | ✅ Already Available | P1 | `[x]` |
| Stock Freeze | 🆕 New Module Required | P2 | `[x]` |
| Cycle Count | 🆕 New Module Required | P2 | `[x]` |
| Physical Verification | 🟡 Extend Existing | P2 | `[x]` |
### Domain 8: Warping (5 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Warp Planning | 🆕 New Module Required | P2 | `[x]` |
| Yarn Allocation | 🟡 Extend Existing | P2 | `[x]` |
| Warp Sheet | 🆕 New Module Required | P1 | `[x]` |
| Warp Production | 🆕 New Module Required | P1 | `[x]` |
| Warp Cost | 🔵 Modify Existing | P2 | `[~]` |

- Warp Planning + Yarn Allocation admin slice now supports create/approve warp-plan and approved-plan yarn-allocation flow in Textile Manufacturing, with tenant isolation verification in TextileManufacturingAdminTest.
- Warp Sheet slice now supports create-from-yarn-allocation flow in Textile Manufacturing (web + API + shared UX), with tenant isolation verification extended in TextileManufacturingAdminTest.
- Warp Production slice now supports create-from-warp-sheet flow in Textile Manufacturing (web + API + shared UX), with tenant isolation verification extended in TextileManufacturingAdminTest.
### Domain 9: Sizing (5 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Sizing Recipe | 🟡 Extend Existing | P1 | `[x]` |
| Chemical Consumption | 🆕 New Module Required | P1 | `[x]` |
| Beam Creation | 🟡 Extend Existing | P1 | `[x]` |
| Beam Inspection | 🟡 Extend Existing | P2 | `[x]` |
| Beam Cost | 🔵 Modify Existing | P2 | `[x]` |

- Sizing Recipe slice now supports create-from-warp-production flow in Textile Manufacturing (web + API + shared UX), with tenant isolation verification extended in TextileManufacturingAdminTest.
- Chemical Consumption slice now supports create-from-sizing-recipe flow in Textile Manufacturing (web + API + shared UX) with controlled chemical selector (Product Master chemical items), composition percentage, and consumption quantity/unit capture.
- Navigation: added Manufacturing submenu visibility for Sizing and Chemical under Daily Operations.
- Verification: `php artisan test tests/Feature/Textile/TextileManufacturingAdminTest.php` => `1 passed (87 assertions)`; `npm run build` => pass (existing chunk-size warnings only).
- Beam Creation slice now supports create-from-sizing-recipe flow in Textile Manufacturing (web + API + shared UX), with tenant isolation verification extended in TextileManufacturingAdminTest.
- Beam Inspection slice now supports record-from-completed-beam flow in Textile Manufacturing (web + API + shared UX) with controlled inspection result and remarks, verified in TextileManufacturingAdminTest.
- Beam Cost slice now supports record-from-completed-beam flow in Textile Manufacturing (web + API + shared UX) with controlled cost-type selector, cost amount, quantity/unit capture, and computed cost-per-unit metadata; verified in TextileManufacturingAdminTest.
### Domain 10: Beam Management (7 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Beam Master | ✅ Already Available | P1 | `[x]` |
| Beam Number | ✅ Already Available | P1 | `[x]` |
| Beam Status | ✅ Already Available | P1 | `[x]` |
| Beam Warehouse | ✅ Already Available | P1 | `[x]` |
| Beam Issue | 🟡 Extend Existing | P1 | `[x]` |
| Beam Return | 🟡 Extend Existing | P1 | `[x]` |
| Remaining Beam | 🔵 Modify Existing | P1 | `[x]` |
| Beam History | 🟡 Extend Existing | P2 | `[x]` |

- Beam Management slice now supports beam issue and beam return workflows plus remaining-beam summary visibility inside Textile Manufacturing (web + API + shared UX), verified in TextileManufacturingAdminTest.
- Beam History is now available in Textile Manufacturing as a unified issue/return timeline (event type, beam link, source document, quantity, status, timestamp), verified in TextileManufacturingAdminTest.
### Domain 11: Loom Management (8 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Loom Master | 🆕 New Module Required | P1 | `[x]` |
| Machine Type | 🟡 Extend Existing | P1 | `[x]` |
| RPM | 🔵 Modify Existing | P2 | `[x]` |
| Width | 🔵 Modify Existing | P1 | `[x]` |
| Shed | 🔵 Modify Existing | P2 | `[x]` |
| Status | 🟡 Extend Existing | P2 | `[x]` |
| Running | 🟡 Extend Existing | P2 | `[x]` |
| Idle | 🟡 Extend Existing | P2 | `[x]` |
| Breakdown | 🆕 New Module Required | P2 | `[x]` |
| Maintenance | 🆕 New Module Required | P2 | `[x]` |
| Operator Assignment | 🟡 Extend Existing | P2 | `[x]` |

- Loom Management slice now includes loom master registration in Textile Manufacturing (web + API + shared UX), with tenant isolation verified in TextileManufacturingAdminTest.
- Master Setup now includes separate CRUD for Source Types and Machine Types, and workflow forms consume select options sourced from Source Type and Unit Conversion masters (with Machine Type master in Loom Management), verified across textile admin tests.
- Loom operational slice now captures width, shed type, loom status, running hours, idle hours, and operator assignment during loom registration, backed by master-configurable Shed Types and Loom Statuses in Loom Setup and validated in web/API flows.
- Verification: `php artisan test tests/Feature/Textile/TextileMasterDataAdminTest.php tests/Feature/Textile/TextileManufacturingAdminTest.php` => pass; `npm run build` => pass (existing chunk-size warnings only).
- Loom downtime and care slice now supports Breakdown and Maintenance records from Loom Management with master-configurable Breakdown Reasons and Maintenance Types, controlled forms, tenant-safe storage, and dedicated records tables.
- Navigation: Loom Setup now includes Breakdown Reasons and Maintenance Types under Master Setup for user-manageable controlled values.
### Domain 12: Production Planning (6 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Production Calendar | 🆕 New Module Required | P2 | `[x]` |
| Capacity Planning | 🆕 New Module Required | P2 | `[x]` |
| Shift Planning | 🆕 New Module Required | P2 | `[x]` |
| Machine Planning | 🟡 Extend Existing | P1 | `[x]` |
| Material Planning | 🆕 New Module Required | P1 | `[x]` |
| Production Order | ✅ Already Available | P1 | `[x]` |
| Production Schedule | 🆕 New Module Required | P2 | `[x]` |

- Domain 12 completion slice now provides a unified Production Planning workspace inside Textile Manufacturing covering calendar, capacity, shift, machine, material, and production schedule planning with shared UX, tenant isolation, and direct submenu access.
- Verification: `php artisan test tests/Feature/Textile/TextileManufacturingAdminTest.php tests/Feature/Textile/TextileMasterDataAdminTest.php` => pass; `npm run build` => pass (existing chunk-size warnings only).
### Domain 13: Weaving Production (8 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Daily Production | ✅ Already Available | P1 | `[x]` |
| Shift Production | 🔵 Modify Existing | P1 | `[x]` |
| Takha Entry | 🆕 New Module Required | P2 | `[x]` |
| Roll Generation | ✅ Already Available | P1 | `[x]` |
| Loom Efficiency | 🟡 Extend Existing | P2 | `[x]` |
| Operator Efficiency | 🟡 Extend Existing | P2 | `[x]` |
| Machine Downtime | 🆕 New Module Required | P2 | `[x]` |
| Waste | ✅ Already Available | P1 | `[x]` |
| Production Cost | 🔵 Modify Existing | P2 | `[x]` |

- Domain 13 completion slice now expands the Weaving Production workspace inside Textile Manufacturing to cover shift production, takha entry, loom efficiency, operator efficiency, machine downtime, production cost, waste, and rework with shared UX and tenant isolation.
- Navigation: Manufacturing submenu label now reflects Weaving Production and lands on the full weaving section rather than output-only scope.

UI Verification (Domain 13)

| Feature | Menu/Submenu | Direct URL |
|---|---|---|
| Daily Production | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=daily-production` |
| Shift Production | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=shift-production` |
| Takha Entry | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=takha-entry` |
| Roll Generation | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=roll-generation` |
| Loom Efficiency | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=loom-efficiency` |
| Operator Efficiency | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=operator-efficiency` |
| Machine Downtime | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=machine-downtime` |
| Waste | Daily Operations > Manufacturing > Waste | `/textile/manufacturing?section=waste` |
| Production Cost | Daily Operations > Manufacturing > Weaving Production > Domain 13: Weaving Production | `/textile/manufacturing?section=weaving-output&sub=production-cost` |

### Domain 14: Grey Fabric (6 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Roll Number | 🔵 Modify Existing | P1 | `[x]` |
| Roll Barcode | 🆕 New Module Required | P2 | `[x]` |
| Roll QR Code | 🆕 New Module Required | P2 | `[x]` |
| Roll Weight | 🔵 Modify Existing | P1 | `[x]` |
| Roll Length | 🔵 Modify Existing | P1 | `[x]` |
| GSM | 🔵 Modify Existing | P2 | `[x]` |
| Width | 🔵 Modify Existing | P1 | `[x]` |
| Defects | 🟡 Extend Existing | P2 | `[x]` |
| Grade | 🔵 Modify Existing | P1 | `[x]` |
| Warehouse | ✅ Already Available | P1 | `[x]` |
| Roll History | 🟡 Extend Existing | P2 | `[x]` |

- Domain 14 completion slice delivered in Weaving Production: grey fabric roll generation now supports roll number, barcode, QR, weight, length, GSM, width, defects, grade, warehouse, and roll history events; updates persist in roll metadata and append lifecycle records.
- Controlled values for defects and grades are delivered via Master Setup under Beam and Cost Setup and consumed in workflow forms as select-based fields.

UI Verification (Domain 14)

| Feature | Menu/Submenu | Direct URL |
|---|---|---|
| Roll Number | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-number` |
| Roll Barcode | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-barcode` |
| Roll QR Code | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-qr-code` |
| Roll Weight | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-weight` |
| Roll Length | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-length` |
| GSM | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-gsm` |
| Width | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-width` |
| Defects | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-defects` |
| Grade | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-grade` |
| Warehouse | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-warehouse` |
| Roll History | Daily Operations > Manufacturing > Weaving Production > Domain 14: Grey Fabric | `/textile/manufacturing?section=weaving-output&sub=grey-roll-history` |

### Domain 15: Processing (9 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Internal Processing | 🟡 Extend Existing | P2 | `[x]` |
| Job Work | ✅ Already Available | P1 | `[x]` |
| Dyeing | 🔵 Modify Existing | P2 | `[x]` |
| Printing | 🆕 New Module Required | P2 | `[x]` |
| Bleaching | 🆕 New Module Required | P2 | `[x]` |
| Calendaring | 🆕 New Module Required | P2 | `[x]` |
| Compacting | 🆕 New Module Required | P2 | `[x]` |
| Finishing | 🟡 Extend Existing | P2 | `[x]` |
| Recipe | ✅ Already Available | P1 | `[x]` |
| Shade Card | 🆕 New Module Required | P2 | `[x]` |
| Batch | ✅ Already Available | P1 | `[x]` |
| Process Cost | 🔵 Modify Existing | P2 | `[x]` |

- Domain 15 completion slice extends Textile Processing to include internal processing, dyeing, printing, bleaching, calendaring, compacting, finishing, shade card, and process cost with stage-specific workflow records, shared UI forms/tables, and tenant-safe visibility.

UI Verification (Domain 15)

| Feature | Menu/Submenu | Direct URL |
|---|---|---|
| Internal Processing | Daily Operations > Processing > Internal Processing | `/textile/processing?section=internal-processing` |
| Job Work | Daily Operations > Processing > Job Work Outward / Job Work Inward | `/textile/processing?section=job-work-outward` |
| Dyeing | Daily Operations > Processing > Dyeing | `/textile/processing?section=dyeing` |
| Printing | Daily Operations > Processing > Printing | `/textile/processing?section=printing` |
| Bleaching | Daily Operations > Processing > Bleaching | `/textile/processing?section=bleaching` |
| Calendaring | Daily Operations > Processing > Calendaring | `/textile/processing?section=calendaring` |
| Compacting | Daily Operations > Processing > Compacting | `/textile/processing?section=compacting` |
| Finishing | Daily Operations > Processing > Finishing | `/textile/processing?section=finishing` |
| Recipe | Master Setup > Core Setup > Route Recipes | `/textile/route-recipes` |
| Shade Card | Daily Operations > Processing > Shade Card | `/textile/processing?section=shade-card` |
| Batch | Daily Operations > Processing > Processing Batch | `/textile/processing?section=processing-batch` |
| Process Cost | Daily Operations > Processing > Process Cost | `/textile/processing?section=process-cost` |

### Domain 16: Quality (8 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Incoming QC | ✅ Already Available | P1 | `[x]` |
| Process QC | 🟡 Extend Existing | P2 | `[x]` |
| Final QC | 🟡 Extend Existing | P1 | `[x]` |
| Defect Library | 🆕 New Module Required | P2 | `[x]` |
| Shade Matching | 🆕 New Module Required | P2 | `[x]` |
| Fabric Inspection | 🟡 Extend Existing | P2 | `[x]` |
| Hold | 🟡 Extend Existing | P1 | `[x]` |
| Reject | 🟡 Extend Existing | P2 | `[x]` |
| Pass | 🟡 Extend Existing | P1 | `[x]` |
| Rework | ✅ Already Available | P1 | `[x]` |
| Quality Certificates | 🆕 New Module Required | P2 | `[x]` |

- Domain 16 completion slice now supports section-aware Quality workflows for Process QC, Final QC, Shade Matching, Fabric Inspection, Hold/Release, Reject, and Quality Certificates with tenant-safe create/finalize/issue actions.
- Quality controlled values are fully master-driven for this domain: Source Types, Source Actions, Inspection Results, and Fabric Defects are now wired under Master Setup > Quality Setup and consumed as select controls in the Quality screen.
- Verification: `php artisan test tests/Feature/Textile/TextileQualityAdminTest.php` => pass; `npm run build` => pass (existing chunk-size warnings only).

UI Verification (Domain 16)

| Feature | Menu/Submenu | Direct URL |
|---|---|---|
| Incoming QC | Daily Operations > Procurement > Incoming QC | `/textile/procurement?section=incoming-qc` |
| Process QC | Daily Operations > Quality > Process QC | `/textile/quality?section=inspection&qc_stage=in_process_qc` |
| Final QC | Daily Operations > Quality > Final QC | `/textile/quality?section=inspection&qc_stage=final_qc` |
| Defect Library | Master Setup > Quality Setup > Fabric Defects | `/textile/master-setup/quality/fabric-defects` |
| Shade Matching | Daily Operations > Quality > Inspection | `/textile/quality?section=inspection&qc_stage=shade_matching` |
| Fabric Inspection | Daily Operations > Quality > Fabric Inspection | `/textile/quality?section=inspection` |
| Hold | Daily Operations > Quality > Hold and Release | `/textile/quality?section=hold-release&action=hold` |
| Reject | Daily Operations > Quality > Inspection | `/textile/quality?section=inspection&decision=fail` |
| Pass | Daily Operations > Quality > Inspection | `/textile/quality?section=inspection&decision=pass` |
| Rework | Daily Operations > Manufacturing > Rework | `/textile/manufacturing?section=rework` |
| Quality Certificates | Daily Operations > Quality > Quality Certificates | `/textile/quality?section=certificates` |

### Domain 17: Packing (5 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Roll Packing | 🆕 New Module Required | P2 | `[x]` |
| Bundle Packing | 🆕 New Module Required | P2 | `[x]` |
| Bale Packing | 🆕 New Module Required | P2 | `[x]` |
| Labels | 🆕 New Module Required | P2 | `[x]` |
| Barcode Labels | 🟡 Extend Existing | P2 | `[x]` |
| QR Labels | 🟡 Extend Existing | P2 | `[x]` |
| Packing Material | 🟡 Extend Existing | P2 | `[x]` |
| Weight | 🟡 Extend Existing | P2 | `[x]` |

Progress note (2026-08-03):

- Domain 17 completion slice now supports section-aware Packing workflows for Roll Packing, Bundle Packing, Bale Packing, and Label generation/issue with tenant-safe document operations.
- Packing controlled values are master-driven for this domain: Source Types and Source Actions are wired under Master Setup > Packing Setup and consumed as select controls in the Packing screen.
- Barcode and QR label generation is integrated into the Labels section with issued-state transition and challan linkage checks.
- Verification: `php artisan test tests/Feature/Textile/TextilePackingAdminTest.php` => pass; `npm run build` => pass (existing chunk-size warnings only).

UI Verification (Domain 17)

| Feature | Menu/Submenu | Direct URL |
|---|---|---|
| Roll Packing | Daily Operations > Packing > Roll Packing | `/textile/packing?section=roll-packing` |
| Bundle Packing | Daily Operations > Packing > Bundle Packing | `/textile/packing?section=bundle-packing` |
| Bale Packing | Daily Operations > Packing > Bale Packing | `/textile/packing?section=bale-packing` |
| Labels | Daily Operations > Packing > Labels | `/textile/packing?section=labels` |
| Packing Source Types | Master Setup > Packing Setup > Source Types | `/textile/master-setup/packing/source-types` |
| Packing Source Actions (Materials) | Master Setup > Packing Setup > Source Actions | `/textile/master-setup/packing/source-actions` |

### Domain 18: Dispatch (7 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Dispatch Planning | 🟡 Extend Existing | P2 | `[x]` |
| Delivery Challan | ✅ Already Available | P1 | `[x]` |
| Truck | 🟡 Extend Existing | P2 | `[x]` |
| Container | 🆕 New Module Required | P2 | `[x]` |
| Driver | 🆕 New Module Required | P2 | `[x]` |
| Vehicle | 🟡 Extend Existing | P2 | `[x]` |
| LR Number | 🆕 New Module Required | P2 | `[x]` |
| E-Way Bill | 🆕 New Module Required | P3 | `[x]` |
| Freight | 🔵 Modify Existing | P2 | `[x]` |
| POD | ✅ Already Available | P1 | `[x]` |
| Dispatch Tracking | 🆕 New Module Required | P3 | `[x]` |

Progress note (2026-08-03):

- Domain 18 completion slice now supports a dedicated Dispatch workspace with section-aware Dispatch Planning and Dispatch Tracking flows, linked to released challans and POD visibility.
- Dispatch planning captures Truck, Container, Driver, Vehicle, LR Number, E-Way Bill, and Freight metadata with tenant-safe workflows.
- Dispatch controlled values are master-driven for this domain: Source Types, Source Actions, Truck Numbers, Container Numbers, Drivers, Vehicles, LR Numbers, and E-Way Bills are wired under Master Setup > Dispatch Setup and consumed as select controls in Dispatch planning/tracking forms.
- Enterprise entity uplift (2026-08-03): Driver and Vehicle are now implemented as first-class tenant-scoped modules (not generic text fields), with CRUD setup screens and dispatch workflow selection by entity IDs.
- Verification: `php artisan test tests/Feature/Textile/TextileDispatchSetupAdminTest.php tests/Feature/Textile/TextileDispatchAdminTest.php` => pass (2 tests, 44 assertions); `npm run build` => pass (existing chunk-size warnings only).

UI Verification (Domain 18)

| Feature | Menu/Submenu | Direct URL |
|---|---|---|
| Dispatch Planning | Daily Operations > Dispatch > Dispatch Planning | `/textile/dispatch?section=planning` |
| Truck Dispatch | Daily Operations > Dispatch > Truck Dispatch | `/textile/dispatch?section=planning&mode=truck` |
| Container Dispatch | Daily Operations > Dispatch > Container Dispatch | `/textile/dispatch?section=planning&mode=container` |
| Dispatch Tracking | Daily Operations > Dispatch > Dispatch Tracking | `/textile/dispatch?section=tracking` |
| Delivery Challan | Daily Operations > Dispatch > Delivery Challan | `/textile/sales?section=challan-pod` |
| POD | Daily Operations > Dispatch > POD | `/textile/sales?section=challan-pod` |
| Dispatch Source Types | Master Setup > Dispatch Setup > Source Types | `/textile/master-setup/dispatch/source-types` |
| Dispatch Source Actions | Master Setup > Dispatch Setup > Source Actions | `/textile/master-setup/dispatch/source-actions` |
| Dispatch Truck Numbers | Master Setup > Dispatch Setup > Truck Numbers | `/textile/master-setup/dispatch/dispatch-truck-numbers` |
| Dispatch Container Numbers | Master Setup > Dispatch Setup > Container Numbers | `/textile/master-setup/dispatch/dispatch-container-numbers` |
| Dispatch Drivers (Entity) | Master Setup > Dispatch Setup > Drivers | `/textile/dispatch-drivers` |
| Dispatch Vehicles (Entity) | Master Setup > Dispatch Setup > Vehicles | `/textile/dispatch-vehicles` |
| Dispatch Routes (Entity) | Master Setup > Dispatch Setup > Routes | `/textile/dispatch-routes` |
| Dispatch LR Numbers | Master Setup > Dispatch Setup > LR Numbers | `/textile/master-setup/dispatch/dispatch-lr-numbers` |
| Dispatch E-Way Bills | Master Setup > Dispatch Setup > E-Way Bills | `/textile/master-setup/dispatch/dispatch-eway-bills` |

### Domain 19: Transport (5 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Own Vehicles | 🔵 Modify Existing | P2 | `[x]` |
| Transport Vendors | 🟡 Extend Existing | P2 | `[x]` |
| Drivers | 🟡 Extend Existing | P2 | `[x]` |
| Routes | 🆕 New Module Required | P2 | `[x]` |
| Fuel | 🆕 New Module Required | P3 | `[x]` |
| Freight Cost | 🔵 Modify Existing | P2 | `[x]` |
| Vehicle Maintenance | 🆕 New Module Required | P3 | `[x]` |

Progress note (2026-08-03):

- Domain 19 transport progression: Route Registry is now implemented as a first-class tenant-scoped entity (CRUD setup screen + menu route), aligned with dispatch Driver/Vehicle architecture.
- Dispatch planning and tracking now select Route via entity ID and persist `route_id`/`route_name` metadata for downstream reuse.
- Domain 19 transport vendor progression: transport vendor selection is now linked to Account Vendor master (`supplier_type=transport`) across Driver, Vehicle, Route setup modules and Dispatch planning/tracking (`transport_vendor_id` + `transport_vendor_name` metadata), with Supplier Setup navigation entry for Transport Vendors.
- Own vs vendor handling is explicit: Driver now has `driver_source` (`own`/`vendor`) and Vehicle ownership (`owned`/`hired`/`vendor`) is enforced; transport vendor is required only when source/ownership is vendor.
- Transport operating policy: Operating Model settings grid now exposes `Own Transport` and `Vendor Transport` toggles (`has_transport_own`/`has_transport_vendor`, default both on); Driver and Vehicle setup reject vendor mode when vendor transport is disabled and own mode when own transport is disabled (`assertTransportModeAllowed`).
- Transport workspace (Domain 19): new `Daily Operations > Transport` workspace with Fuel, Freight Cost and Vehicle Maintenance tabs; each tab follows KPI cards -> form -> table layout; all records are tenant-scoped with denormalized vehicle/driver/route/vendor snapshots.
- Transport masters: `Master Setup > Transport Setup` added with Fuel Types, Freight Types and Maintenance Types (domain `transport`) consumed via select controls in the workspace forms (no free-text).
- Verification: `php artisan test tests/Feature/Textile/TextileTransportAdminTest.php` => pass (1 test, 20 assertions: fuel/freight/maintenance store, denormalized names, tenant isolation); `php artisan test tests/Feature/Textile/TextileDispatchSetupAdminTest.php tests/Feature/Textile/TextileDispatchAdminTest.php` => pass (2 tests, 62 assertions); `npm run build` => pass (existing chunk-size warnings only).

UI Verification (Domain 19)

| Feature | Menu/Submenu | Direct URL |
|---|---|---|
| Transport Workspace | Daily Operations > Transport | `/textile/transport` |
| Fuel | Daily Operations > Transport > Fuel | `/textile/transport?section=fuel` |
| Freight Cost | Daily Operations > Transport > Freight Cost | `/textile/transport?section=freight-cost` |
| Vehicle Maintenance | Daily Operations > Transport > Vehicle Maintenance | `/textile/transport?section=vehicle-maintenance` |
| Fuel Types | Master Setup > Transport Setup > Fuel Types | `/textile/master-setup/transport/fuel-types` |
| Freight Types | Master Setup > Transport Setup > Freight Types | `/textile/master-setup/transport/freight-types` |
| Maintenance Types | Master Setup > Transport Setup > Maintenance Types | `/textile/master-setup/transport/maintenance-types` |
| Own/Vendor Transport Policy | Master Setup > Core Setup > Operating Model | `/textile/operating-policy` |

Progress note (2026-08-04):

- Demo data seeder shipped: `php artisan textile:demo {user?}` (defaults to first company user) seeds realistic demo data across every module — masters + reference masters (152), workflow documents (35, incl. full weave-to-dispatch chain), inventory lots/movements, maintenance (PM schedules, breakdowns, service schedule, spare parts, costs), finance (machine/power/chemical/labour costs), transport (drivers, vehicles, routes, fuel, freight, vehicle maintenance), HR (departments, shifts, employees, attendance), approvals + audit logs. Idempotent via `updateOrCreate` (re-run safe). Verified: all 26 tables populated on real MySQL, re-run keeps counts identical (workflow_docs 35, attendances 4, movements 3).
- Sitewide 500s fixed: pending textile migrations (000007-000014) were applied to the real MySQL DB (`migrate:status` => 0 pending); root cause was tests using a separate test DB masking missing tables.
- `TextileApprovalAdminTest` fixed: seeds a jobwork operating policy (sales disabled) for companyA and asserts `auth.user.textile_capabilities.sales_order` is `false` via `assertInertia`; suite now passes (18 assertions).
- Frontend type-checking enabled for packages: root `tsconfig.json` now includes `packages/DigitalFuzed/DigitalFuzedTextileCore` and `DigitalFuzedTextileInventory` JS (scoped to textile packages to keep the `tsc` build step green; legacy packages were never type-checked and are out of scope). `npx tsc --noEmit` => 0 errors; `npm run build` => pass.
- 33 real type errors fixed in textile packages: `TextileWorkflowActionRule.statuses` widened to `readonly string[]`, `TextileField` gained `min/max/step` props, `TextileDataTableSection` gained `children` support, Processing page `WorkflowDocument` gained `metadata`, duplicate global `route` declarations removed from both textile `company-menu.ts` files (already declared in `resources/js/types/global.d.ts`).
- Hrm `EmployeeFilters` fixed: dotted `user.name` (a copy-paste from the sort key) corrected to `user_name`, matching the filter state used by the page.
- Blank-UI/Ziggy crash fixed: 8 `textile.master-domains.*` route groups referenced by the menu were never registered (cost-types, inspection-results, fabric-defects, fabric-grades, dispatch-truck-numbers, dispatch-container-numbers, dispatch-lr-numbers, dispatch-eway-bills — all with index/store/update/archive). Added to `src/Routes/web.php`; verified via diff of all menu `route()` calls against `php artisan route:list` => zero missing.
- "5600" investigated: chart-of-accounts code for Tax Expense in AccountUtility, not an application error.
- Verification: `npx tsc --noEmit` => 0 errors; `npm run build` => pass; route diff menu vs `route:list` => empty; all Textile feature tests pass (`php artisan test tests/Feature/Textile/`).

### Domain 20: Maintenance (6 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Preventive Maintenance | 🆕 New Module Required | P2 | `[x]` |
| Breakdown | 🆕 New Module Required | P2 | `[x]` |
| Service Schedule | 🆕 New Module Required | P2 | `[x]` |
| Spare Parts | 🟡 Extend Existing | P2 | `[x]` |
| Machine History | 🟡 Extend Existing | P2 | `[x]` |
| Downtime | 🟡 Extend Existing | P2 | `[x]` |
| Maintenance Cost | 🔵 Modify Existing | P2 | `[x]` |

- Maintenance workspace (Domain 20): new `Daily Operations > Maintenance` workspace with Preventive Maintenance, Breakdowns, Service Schedule, Spare Parts, Maintenance Cost and Machine History tabs; each tab follows KPI cards -> form -> table layout; machines come from approved loom masters (`loom_master` workflow documents) via select controls — no free-text machine names; PM frequency is a controlled select (days/hours/cycles), breakdown symptom is a controlled select from Breakdown Reasons master (`breakdown_reason`), maintenance type from Maintenance Types master (`maintenance_type`).
- Downtime covered: breakdowns record `downtime_minutes`; the Maintenance Overview KPIs show Total Downtime and Open Breakdowns; machine history merges PM/breakdown/service/cost events into a per-machine timeline with machine filter.
- Denormalization: every record snapshots `machine_name` (= loom document number) and `machine_type` at save time; spare part usage resolves `machine_name` from the linked PM/breakdown/service record; computed totals are stored (spare part `total_cost` = quantity × unit_cost; cost `total_cost` = labor + parts + external).
- Tenant safety: all five record tables store `created_by`/`creator_id` and are scoped on read/write; capability `maintenance_operations` gated via operating policy (`SETTING_HAS_MAINTENANCE`).
- Verification: `php artisan migrate --force` => 5 migrations applied (000015-000019); `php artisan test tests/Feature/Textile/TextileMaintenanceAdminTest.php` => pass (1 test, 32 assertions: all 5 stores, denormalized machine names, computed totals, tenant isolation); full suite `php artisan test tests/Feature/Textile/` => 55 passed (918 assertions); `npx tsc --noEmit` => 0 errors; `npm run build` => pass; route diff menu vs `route:list` => empty.
### Domain 21: HR (10 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Employees | ✅ Already Available | P1 | `[x]` |
| Attendance | ✅ Already Available | P1 | `[x]` |
| Shift | ✅ Already Available | P1 | `[x]` |
| Payroll | ✅ Already Available | P1 | `[x]` |
| Incentives | 🟡 Extend Existing | P2 | `[~]` |
| Production Incentives | 🔵 Modify Existing | P2 | `[~]` |
| Operator Skills | 🆕 New Module Required | P2 | `[ ]` |
| Performance | 🟡 Extend Existing | P2 | `[~]` |
### Domain 22: Finance (9 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Accounts | ✅ Already Available | P1 | `[x]` |
| Ledger | ✅ Already Available | P2 | `[x]` |
| Journal | ✅ Already Available | P2 | `[x]` |
| Cost Centers | 🆕 New Module Required | P1 | `[x]` |
| Production Cost | ✅ Already Available | P1 | `[x]` |
| Cost Per Meter | 🔵 Modify Existing | P2 | `[x]` |
| Cost Per Roll | 🔵 Modify Existing | P2 | `[x]` |
| Machine Cost | 🔵 Modify Existing | P2 | `[x]` |
| Power Cost | 🆕 New Module Required | P2 | `[x]` |
| Chemical Cost | 🔵 Modify Existing | P2 | `[x]` |
| Labour Cost | 🔵 Modify Existing | P2 | `[x]` |
| Profitability | 🔵 Modify Existing | P2 | `[x]` |

- Finance workspace (Domain 22): new `Insights > Finance` workspace with Cost Per Meter, Cost Per Roll, Machine Cost, Power Cost, Chemical Cost, Labour Cost and Profitability tabs; each cost tab follows KPI cards -> form -> table layout (KPI overview shows Total Revenue, Total Cost, Margin %, Cost Per Meter 4dp, Cost Per Roll 2dp, Operating Costs).
- Computed metrics: cost per meter is derived from approved costing entries (total cost ÷ meters, weighted average); cost per roll requires `rolls_count` captured on the costing entry form (total cost ÷ rolls, average); profitability = margin snapshots (revenue − product cost) minus operating costs summed from maintenance, machine, power, chemical and labour record tables, returned with margin value/percent and a per-cost-type breakdown.
- Controlled selects: machine comes from approved loom masters (`loom_master` workflow documents) via select controls, cost center from the Cost Centers master (`textile_cost_centers`), process stage and shift from controlled option lists — no free-text values; machine type options come from the Machine Types master via `referenceOptions` with fallback.
- Denormalization: every cost record snapshots its reference (machine `machine_name`/`machine_type`, cost center `cost_center_name`/`shift_name`); computed totals are stored (machine = dep + maintenance + power + labor + other; power units = end − start reading, total = units × rate; chemical = qty × unit cost; labour = workers × hours × rate).
- Tenant safety: all four cost tables store `created_by`/`creator_id` and are scoped on read/write; Finance follows the Costing precedent — company/superadmin access only, no capability gating.
- Verification: `php artisan migrate --force` => 4 migrations applied (000020-000023); `php artisan test tests/Feature/Textile/TextileFinanceAdminTest.php` => pass (2 tests, 35 assertions: all 4 stores + computed totals + denormalized refs + tenant isolation; cost-per-roll computed from costing entry rolls_count); full suite `php artisan test tests/Feature/Textile/` => 57 passed (953 assertions); `npx tsc --noEmit` => 0 errors; `npm run build` => pass; route diff menu vs `route:list` => empty.
- Navigation: menu `Insights > Finance`; URLs `/textile/finance` and `/textile/finance?section=cost-per-meter|cost-per-roll|machine-cost|power-cost|chemical-cost|labour-cost|profitability`; breadcrumb + page title render from the shared authenticated layout; tab selection is a controlled selector synced to the URL section param.
### Domain 23: Reports (12 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Production Reports | 🟡 Extend Existing | P2 | `[x]` |
| Loom Reports | 🟡 Extend Existing | P2 | `[x]` |
| Operator Reports | 🟡 Extend Existing | P2 | `[x]` |
| Yarn Consumption | 🟡 Extend Existing | P2 | `[x]` |
| Beam Reports | 🟡 Extend Existing | P2 | `[x]` |
| Grey Fabric Reports | 🟡 Extend Existing | P2 | `[x]` |
| Finished Fabric Reports | 🟡 Extend Existing | P2 | `[x]` |
| Dispatch Reports | 🟡 Extend Existing | P2 | `[x]` |
| Purchase Reports | 🟡 Extend Existing | P2 | `[x]` |
| Sales Reports | 🟡 Extend Existing | P2 | `[x]` |
| Stock Reports | 🟡 Extend Existing | P2 | `[x]` |
| Profit Reports | 🔵 Modify Existing | P2 | `[x]` |
| Machine Efficiency | 🟡 Extend Existing | P2 | `[x]` |
| Waste Analysis | 🟡 Extend Existing | P2 | `[x]` |
| Power Consumption | 🆕 New Module Required | P3 | `[x]` |
| Daily MIS | 🟡 Extend Existing | P2 | `[x]` |

- Reports workspace (Domain 23): new `Insights > Reports` workspace with 16 tabs (Production, Loom, Operator, Yarn, Beam, Grey Fabric, Finished, Dispatch, Purchase, Sales, Stock, Profit, Efficiency, Waste, Power, Daily MIS); each tab follows KPI cards -> table layout with a global date-range filter (from/to) applied to all reports; tab selection synced to the URL `section` param.
- Report sources: Production = production_batch/weaving_output/shift_production workflow documents; Loom = loom_master + breakdowns + loom_efficiency; Operator = operator_efficiency; Yarn = yarn_allocation + chemical_consumption; Beam = beam/beam_issue/beam_return/beam_inspection; Grey Fabric = grey_fabric_roll; Finished Fabric = active textile lots; Dispatch = dispatch_plan (freight from metadata); Purchase = purchase_order/purchase_requisition/grn; Sales = sales_order; Stock = textile movements; Profit = margin_snapshots; Efficiency = loom_efficiency; Waste = waste + rework; Power = textile_power_costs; Daily MIS = day-wise production/dispatch/revenue/waste aggregate.
- Table export: shared `DataTable` (used by all textile tables) ships four separate export buttons — CSV, Excel, PDF, Print — next to the search box (no dropdown), exporting the currently filtered rows; new `resources/js/lib/table-export.ts` helper; `TextileDataTableCard`/`TextileDataTableSection` forward `exportable`/`exportFilename` props.
- Server-side export: Excel and PDF are true server-generated files, not browser print dialogs — `GET /textile/reports/export?section=<tab>&format=xlsx|pdf&from=&to=`; Excel via PhpSpreadsheet (`phpoffice/phpspreadsheet`, bold headers, auto-sized columns), PDF via Dompdf (`barryvdh/laravel-dompdf`, blade template `digitalfuzed-textile-core::reports.export` with generated-at + period meta); per-section server column maps (`COLUMNS`) mirror the frontend tables; filename slugs the report title with optional `-from-to` period suffix; section/format fall back to `production`/`xlsx` when invalid; `TextileDataTableCard` gains `exportUrl` so Excel/PDF hit the server endpoint while CSV/Print stay client-side.
- Tenant safety: every report query scopes `created_by` to the current tenant (workflow documents, lots, movements, power costs, breakdowns); the service falls back to unscoped when no auth context (safe for console use).
- Verification: `php artisan test tests/Feature/Textile/TextileReportsAdminTest.php` => pass (3 tests, 84 assertions: all 16 report shapes on the page, aggregation values, date filters, company A/B isolation, xlsx content-type + zip shared-strings tenant isolation, pdf content-type via dompdf, invalid section/format fallbacks); full suite `php artisan test tests/Feature/Textile/` => 60 passed (1037 assertions); `npx tsc --noEmit` => 0 errors; `npm run build` => pass; route diff menu vs `route:list` => empty.
- Navigation: menu `Insights > Reports`; URL `/textile/reports` and `/textile/reports?section=production|loom|operator|yarn-consumption|beam|grey-fabric|finished-fabric|dispatch|purchase|sales|stock|profit|machine-efficiency|waste-analysis|power-consumption|daily-mis`; export URL `/textile/reports/export?section=&format=xlsx|pdf&from=&to=`; breadcrumb + page title render from the shared authenticated layout; date filters are controlled inputs synced to `from`/`to` URL params.
| Monthly MIS | 🟡 Extend Existing | P2 | `[~]` |
| Annual MIS | 🟡 Extend Existing | P3 | `[~]` |
### Domain 24: Dashboards (9 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| CEO Dashboard | 🟡 Extend Existing | P2 | `[x]` |
| Factory Dashboard | 🟡 Extend Existing | P2 | `[x]` |
| Production Dashboard | ✅ Already Available | P1 | `[x]` |
| Purchase Dashboard | 🟡 Extend Existing | P2 | `[x]` |
| Inventory Dashboard | 🟡 Extend Existing | P2 | `[x]` |
| Sales Dashboard | 🟡 Extend Existing | P2 | `[x]` |
| Finance Dashboard | 🟡 Extend Existing | P2 | `[x]` |
| Maintenance Dashboard | 🟡 Extend Existing | P2 | `[x]` |
| HR Dashboard | 🟡 Extend Existing | P2 | `[x]` |

- CEO + Factory dashboard (Domain 24): the `Textile > Dashboard` workspace was rebuilt as a modern charts-first overview using `recharts` (already vendored) — 6 gradient KPI cards (Total Revenue with 30d delta hint, Total Cost, Total Margin, Margin %, Workflow Documents, In Progress) -> 4 chart panels: Production Trend (area, daily output vs dispatch, last 14 days), Revenue vs Cost (multi-line from margin snapshots), Document Mix (donut, top 8 types), Status Distribution (bar), Machine Efficiency (horizontal bar, latest % per loom), Power Consumption (units + cost per billing period); empty states render a NoRecordsFound-style placeholder instead of a blank chart; recent documents table + login/audit activity cards retained below.
- Dashboard data source: new `TextileDashboardService` (productionTrend/dispatchTrend from production_batch + weaving_output + shift_production and dispatch_plan grouped per day, financialTrend + revenue delta from margin_snapshot metadata, machineEfficiency latest log per loom, powerTrend from TextilePowerCost, kpis via TextileCostingService::summary + workflow counts); controller passes existing props unchanged plus `kpis`, `productionTrend`, `dispatchTrend`, `financialTrend`, `machineEfficiency`, `powerTrend`, `statusDistribution`, `typeDistribution`.
- Verification: `php artisan test tests/Feature/Textile/TextileDashboardAdminTest.php` => pass (2 tests, 80 assertions: chart series lengths, per-day quantities 250 total, financial 5000/3000/2000, machine efficiency 88.5, power 750/6000, KPI strings with thousand separators, status/type distributions, company A/B isolation on every series); full textile suite => 61 passed (1092 assertions); `npx tsc --noEmit` => 0 errors; `npm run build` => pass.
- Domain sub-dashboards (Purchase / Inventory / Sales / Finance / Maintenance / HR): the dashboard page is now a 7-tab workspace (`?view=overview|purchase|inventory|sales|finance|maintenance|hr`, tab clicks via `router.get` with `preserveState` so chart state survives switching) — each domain tab renders its own 6 KPI cards + trend/chart panels in the shared `panels.tsx` (`KpiRow`, `KpiCard`, `ChartCard`, `Donut`, `VerticalBars`, `ChartEmpty`, `CHART_COLORS`); `TextileDashboardService` gained tenant-scoped `purchase()` (docs/orders/requisitions/GRN qty + trend + status + top-5 types), `inventory()` (lots/available/allocated + 4-series movement trend + lot status), `sales()` (orders/qty/approved/released/in-progress + trend + status), `finance()` (costing summary KPIs + cost breakdown across Power/Chemicals/Labour/Machines/Maintenance + financial trend + power trend), `maintenance()` (breakdowns/open/downtime hrs/cost + daily trend + downtime by machine + cost trend), `hr()` (employees/departments/today's attendance/overtime/hours/absent + attendance trend + headcount by department, from `Workdo\Hrm` models); every query scoped `created_by` to the tenant.
- Verification: `php artisan test tests/Feature/Textile/TextileDashboardAdminTest.php` => pass (3 tests, 148 assertions incl. `test_domain_dashboards_provide_tenant_scoped_data`: per-domain KPI values, purchase trend 190, movement receipt 50/issue 10, cost breakdown Chemicals 1200/Power 6000, downtime 1.50 hrs + maintenance cost 5,000.00, HR attendance 2 + Weaving headcount 1, company B sees zero domain data); full textile suite => 62 passed (1160 assertions); `npx tsc --noEmit` => 0 errors; `npm run build` => pass.
### Domain 25: Mobile (5 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Production Entry | 🟡 Extend Existing | P2 | `[~]` |
| QC Entry | 🟡 Extend Existing | P2 | `[~]` |
| Barcode Scanner | 🆕 New Module Required | P2 | `[ ]` |
| QR Scanner | 🆕 New Module Required | P2 | `[ ]` |
| Stock Lookup | 🆡 Extend Existing | P2 | `[~]` |
| Dispatch | 🟡 Extend Existing | P2 | `[~]` |
| Attendance | 🟡 Extend Existing | P2 | `[~]` |
### Domain 26: Integrations (11 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| WhatsApp | 🟡 Extend Existing | P3 | `[~]` |
| SMS | 🟡 Extend Existing | P3 | `[~]` |
| Email | ✅ Already Available | P2 | `[x]` |
| Barcode Printers | 🆕 New Module Required | P2 | `[ ]` |
| QR Printers | 🆕 New Module Required | P2 | `[ ]` |
| Weighing Scale | 🆕 New Module Required | P2 | `[ ]` |
| Tally Export | 🟡 Extend Existing | P2 | `[~]` |
| GST | 🆕 New Module Required | P3 | `[ ]` |
| E-Way Bill | 🆕 New Module Required | P3 | `[ ]` |
| Payment Gateway | 🟡 Extend Existing | P3 | `[~]` |
| API | 🟡 Extend Existing | P2 | `[~]` |

### Enterprise review checkpoints

| Checkpoint | Status | Notes |
|---|---|---|
| Full enterprise feature mapping completed | `[x]` | See `plans/textile-enterprise-traceability-matrix.md`. |
| Atomic enterprise checklist consolidated in this tracker | `[x]` | Layer 2 now holds one-row-per-feature marking list. |
| P1 execution sequencing signed off | `[ ]` | Confirm final P1 order before implementation sprint starts. |
| P2 and P3 wave plan approved | `[ ]` | Finalize milestones, dependencies, and owners. |

## Verification evidence

- Textile backend suite (latest): `php artisan test tests/Feature/Textile`
- Last recorded result (latest): `Tests: 41 passed (348 assertions)`.
- Approval workflow foundation: `php artisan test tests/Feature/Textile/TextileApprovalWorkflowTest.php` passed: `Tests: 2 passed (11 assertions)`.
- Approval slice delivered (backend): tenant-scoped approval rules + decisions, transition gate integration, pending approvals API, and normalized workflow audit payload fields.
- Approval admin UI: `php artisan test tests/Feature/Textile/TextileApprovalAdminTest.php` passed: `Tests: 1 passed (9 assertions)`; includes tenant isolation and web route coverage.
- Previous baseline run: `OK (23 tests, 75 assertions)`.
- Industry assignment: `./vendor/bin/phpunit tests/Feature/Textile/TextileIndustryAssignmentTest.php --testdox --colors=never` passed: `OK (1 test, 5 assertions)`.
- Textile specification admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileSpecificationAdminTest.php --colors=never` passed: `OK (1 test, 11 assertions)`.
- Textile master-data admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileMasterDataAdminTest.php --colors=never` passed: `OK (1 test, 25 assertions)`.
- Textile inventory admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileInventoryAdminTest.php --testdox --colors=never` passed: `OK (1 test, 31 assertions)`.
- Textile procurement admin: `php artisan test tests/Feature/Textile/TextileProcurementAdminTest.php` passed: `Tests: 1 passed (31 assertions)`.
- Textile cost-center admin: `php artisan test tests/Feature/Textile/TextileCostCenterAdminTest.php` passed: `Tests: 1 passed (14 assertions)`.
- Textile sales admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileSalesAdminTest.php --colors=never` passed: `OK (1 test, 27 assertions)`.
- Textile manufacturing admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileManufacturingAdminTest.php --colors=never` passed: `OK (1 test, 25 assertions)`.
- Textile quality admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileQualityAdminTest.php --colors=never` passed: `OK (1 test, 18 assertions)`.
- Textile processing admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileProcessingAdminTest.php --colors=never` passed: `OK (1 test, 25 assertions)`.
- Textile costing admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileCostingAdminTest.php --colors=never` passed: `OK (1 test, 14 assertions)`.
- Textile logs admin: `php artisan test tests/Feature/Textile/TextileLogAdminTest.php tests/Feature/Textile/TextileApprovalWorkflowTest.php` passed: `3 tests, 16 assertions`; the textile logs page now shows tenant-scoped login history and textile audit trail entries.
- Textile dashboard admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileDashboardAdminTest.php --colors=never` passed: `OK (1 test, 10 assertions)`.
- Users-page frontend build: `npm run build` passed. The existing Vite chunk-size warning remains non-blocking.

## Delivery order from here

1. P1: implement customer operating model profiles (including powerloom-only and customer-owned beam/yarn flows) with profile-based workflow gates.
2. P1: add warping/sizing/loom planning baseline and formalize roll plus final-QC data model.
3. P2: deliver packing, transport, maintenance, and expanded report/dashboard packs.
4. P3: deliver mobile, hardware, and statutory integrations.

## Tracker maintenance

- Update this file after each implemented and verified slice.
- Mark a task `[x]` only when its backend, authorization, required admin UI, and focused validation are complete.
- For enterprise tasks, do not mark `[x]` unless ERP-style menu group placement, submenu route mapping, and breadcrumb/title consistency are also verified.
- Keep a task `[~]` when only backend work exists.
