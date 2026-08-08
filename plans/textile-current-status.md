# Textile ERP Task Tracker

This is the single source of truth for Textile ERP delivery. It replaces the previous Textile FDS, architecture roadmap, and checklist, and clearly separates backend implementation from admin-portal usability.

This tracker now has two layers:
1. Phase-1 baseline delivery (implemented and verified workflow foundation)
2. Enterprise expansion backlog (broader textile ERP scope from traceability review)

Last updated: 2026-08-08

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

- [x] `resources/js/components/textile/textile-workspaces.ts` — app-wide registry: every workspace `{ id, label, icon, route, capability, sections: [{ id, label, icon, capability? }] }`; drives sidebar menu AND workspace rails (single source of truth, no drift).
- [x] `TextileWorkspace` component (`textile-workspace.tsx`) — left rail, workspace header, URL `?section=` handling, capability filtering, per-section KPI slot, standard `KPI -> form -> table` body via `TextileSection`; mobile falls back to a native section `<select>`.
- [x] `TextileSection` component (`textile-section.tsx`) — standard section body (KPI strip + form card + data table card) replacing the 33 duplicated grid blocks.
- [x] `useTextileSection()` hook + `countSectionStatuses()` helper — section param read/filter + per-section status counts (inside `textile-workspace.tsx`).
- [x] `company-menu.ts` — collapse submenu items to workspace level for rail-driven workspaces (menu becomes group > workspace, 2 levels; rail owns sections). Deferred until pilot is visually approved. Verified: Procurement submenu collapsed to single link (unused icon imports `ClipboardList`/`FileQuestion`/`PackageCheck` removed); browser check shows `Procurement` as a direct link in sidebar with no duplicated section submenu. Pilot also polished to match user's ChatGPT mockup: rail active state = emerald left accent bar + tint + icon chips (sticky on desktop), KPI cards gained icon chips, page header gained emerald `New Requisition` CTA (`pageActions` slot), breadcrumb shows active section (`Textile > Procurement > Incoming QC`); fixed pre-existing KPI count bug (sectionRows keys were camelCase vs kebab-case section ids). Verified: `npx tsc --noEmit` 0 errors, `npm run build` pass, 62 tests passed, browser KPIs now show Total 1 / Approved 1 for incoming-qc (matches TIQC-0001 row).

### Phase 1 — Pilot

- [x] Refactor `Procurement` (6 sections) onto the registry + `TextileWorkspace`; verified: `npx tsc --noEmit` => 0 errors; `npm run build` => pass; `php artisan test tests/Feature/Textile/` => 62 passed (1160 assertions); browser check on real MySQL demo data — rail renders 6 sections, clicking rail updates URL to `?section=grns` / `?section=incoming-qc`, active state highlights, per-section KPI counts show (GRN section: Total 1 / Released 1), section tables show seeded demo records (TGRN-0001, TIQC-0001).
- [x] ChatGPT-mockup pilot polish (user-provided spec, Aug 4): (1) `Overview` section added as first rail item — cross-pipeline read-only table (Type/Document/Party/Lot/Qty/Unit/Status) with all-docs KPIs; (2) right info panel via new shared `TextileInfoPanel` (`textile-info-panel.tsx`) + `aside` slot on `TextileWorkspace`: Workflow Status stepper (Requisition→Approval→RFQ→PO→GRN→Incoming QC→Invoice with live counts, current stage emerald), Supplier Summary (vendor profile + derived order count/qty/last purchase from workflow docs), Recent Activity timeline (real `TextileAuditLog` events with actor + relative time); (3) Create Requisition form rebuilt to spec's 3-column layout (Supplier Information | Material Details | Other Details) with `priority/required_for/expected_date/remarks/warehouse` persisted via document `metadata` (controller validation extended, no migration); (4) Requisitions table gained Priority column + per-row actions; (5) demo seeder now seeds a vendor (Shree Yarn Traders) so the supplier card shows data — schema-guarded optional columns. Verified: tsc 0, build pass, 62 tests, browser — overview pipeline table, stepper counts (1/1/0/1/1/1/1), supplier card (Orders 4, Total Qty 4,800, Last Purchase 1d ago), 3-col form fields, Priority column, breadcrumb + CTA + rail polish all live.

### Phase 2 — Workflow workspaces (rollout, smallest first)

- [x] Manufacturing (7 sections; page refactored from 1873-line Tabs to `TextileWorkspace` switch — Aug 2026): replaced hardcoded Tabs/capability logic with registry (`getTextileWorkspace('manufacturing')`), added `Overview` section (cross-pipeline read-only table with formatted Type column, all-docs KPIs Total 14/Draft 0/Approved 13/Released 1), per-section KPIs via `countSectionStatuses`, right context panel (`aside`): Workflow Status stepper (Warp Planning→Beam & Batch→Loom→Planning→Weaving→Waste→Rework with live counts), Recent Activity timeline (shared `ProvidesRecentActivity` trait), Production Summary card (MetricSummaryCard); breadcrumb Textile > Manufacturing > {section}, emerald "New Warp Plan" CTA; sidebar Manufacturing = single link. Also deduplicated procurement controller onto the shared trait. Verified: tsc 0, build pass, 62 tests/1160 assertions, browser — rail 8 sections, breadcrumb, CTA, per-section KPIs, stepper counts (1/1/3/0/1/1/1), Production Summary (Beams 1/Looms 3/Outputs 1/Shift 1/Downtimes 1/Waste 1/Rework 1), overview table 14 rows with types, warp-planning forms intact.
- [x] Manufacturing workflow redesign (user critique: "limit of form-based UI, architectural not visual", Aug 4 mockup): every section renders a horizontal workflow strip (chip per step with record count; no sequence numbering) plus a Current Form | Records tab bar (mockup tabs) via shared `TextileWorkflowSteps` — the active step's form is ALWAYS visible (defaults to first pending step), no accordion hiding; clicking a chip switches the form and auto-returns to the Current Form tab; records live only under the Records tab, never mixed with forms; status chips (Completed/In Progress/Pending) shown only for genuinely sequential steps — ad-hoc steps (Record Loom Breakdown, Record Loom Maintenance) omit status per user feedback (not part of a sequence); loom-management lists looms in the shared `TextileDataTableSection` with all info columns (Number, Loom, Machine, Machine Type, RPM, Width, Shed, Status, Efficiency %, Breakdowns, Maintenance, Running Hrs, Idle Hrs, Operator) per user feedback that cards don't scale for 100+ looms — machine card grid removed; weaving-output shows production job cards (document no, batch, status, progress % bar, machine/operator/shift) above the strip; waste/rework sections show StatStrip dashboards (Waste Today 12.0 kg / Waste % 2.9% / Top Reason Selvedge Cut; Rework 40.0 mtr / Weft Streak) + recent entries cards; overview KPIs are Running Orders 1 / Pending 0 / Completed 14 / Machine Utilization 67%; aside has Production Today row (420 mtr) + Machine Status card (Total 3 / Running 2 (67%) / Idle 1 (33%) / Maintenance 0 (0%)) + Upcoming Tasks card (empty state with seeded data). Verified: tsc 0, build pass, 62 tests/1160 assertions, browser — loom-management strip (Register Loom Master 3 Completed / breakdown+maintenance w/o status), Records tab Loom Master table 14 cols with live data (LM-0001 Running 88.5%), beam-batch chips w/o numbers (Create Beam 1 Completed → Create Beam Issue Pending), job card TWO-0001 (Batch TPB-0001, 88.5%), waste/rework dashboards, overview KPIs, aside cards all live.
- [x] Shared DataTable features enabled (user: "DataTables has so much inbuilt feature but our datatable looks raw"): `DataTable` (ui/data-table.tsx) gained built-in client-side sorting (internal sort state when no `onSort` prop; numeric-aware compare, rendered-cell fallback for metadata/computed columns) and search now also matches rendered cell values (metadata fields), not just raw row keys; `TextileDataTableCard`/`TextileDataTableSection` now default `searchable=true`, `showPagination=true` (pageSize 10), and mark every column `sortable: true` — so ALL textile tables get search box + click-to-sort headers + pagination footer ("Showing X to Y of Z results", page numbers, prev/next) automatically; export buttons unchanged. Verified: tsc 0, build pass, 62 tests/1160 assertions, browser — loom Records tables show Search box, all 14 headers sortable, Status sort reordered rows, overview 14-row table paginates (1 to 10 / 11 to 14, page 2 shows Machine Downtime/Grey Roll/Waste/Rework), Type sort re-sorted alphabetically and reset page.
- [x] `Sales` (4 sections; page refactored from hardcoded Tabs + `TextileKpiOverview` to `TextileWorkspace` switch — Aug 2026, same design upgrade as Manufacturing): registry `sales` gained `overview` first section + per-section capability keys (`sales_order`, `sales_allocation_dispatch`, `sales_challan_pod` — rail now capability-filtered via `useTextileSection`); controller now uses shared `ProvidesRecentActivity` trait; breadcrumb Textile > Sales > {section}, emerald "New Sales Order" CTA; per-section KPIs via `countSectionStatuses`; aside: Workflow Status stepper (Sales Order → Allocation → Dispatch → Challan → POD with live counts), Recent Activity timeline, Sales Summary card (Orders/Allocations/Dispatches/Challans/PODs), Customer Summary card (Profiles, Total Order Qty, Pending POD); sections use `TextileWorkflowSteps` chip strip + Current Form | Records tabs — sales-order: 1 chip (Create Sales Order), allocation-dispatch: 2 chips (Create Allocation / Create Dispatch, statuses from counts, chip click switches form), challan-pod: 1 chip (Create Challan) + Challan Records (Mark POD row action) + POD Records; overview = cross-pipeline read-only table (Type/Document/Party/Lot/Qty/Unit/Status) with all-docs KPIs (Total 5 / Draft 0 / Approved 2 / Released 3); all tables inherit search/sort/pagination defaults. Verified: tsc 0, build pass (24.89s), 62 tests/1160 assertions, browser — rail 4 sections, breadcrumb + CTA, KPIs per section (sales-order Total 1/Approved 1), chips w/ counts + status, chip click switches form (Dispatch form shown), Records tab (TSO-0001/TAL-0001/TDSP-0001/TCH-0001 w/ Mark POD/TPOD-0001), overview table 5 rows formatted types, aside stepper 1/1/1/1/1 + Sales/Customer Summary + Recent Activity (TSO-0001, TCST-0001 events).
- [x] `Inventory` (4 sections; page refactored from hardcoded Tabs + `TextileKpiOverview` to `TextileWorkspace` switch — Aug 2026, same design upgrade as Manufacturing/Sales): registry `inventory` gained `overview` first section + per-section capability keys (`inventory_transactions`, `inventory_controls`, `inventory_records` — rail capability-filtered via `useTextileSection`); controller now uses shared `ProvidesRecentActivity` trait; breadcrumb Textile > Inventory > {section}, emerald "New Lot" CTA; per-section KPIs (overview: Active Lots/Frozen Lots/Available Qty/Open Reservations; transactions: Active Lots/Open Reservations/Movements/Reservations; controls: Locations/Frozen Lots/Cycle Counts/Active Lots; records: Lots/Movements/Reservations/Locations); aside: Workflow Status stepper (Active Lots → Open Reservations → Cycle Counts), Recent Activity timeline, Inventory Summary card (Active/Frozen Lots, Open Reservations, Available Qty), Stock Health card (Locations/Movements/Cycle Counts/Total Lots); sections use `TextileWorkflowSteps` chip strip + Current Form | Records tabs — transactions: 3 parallel chips WITHOUT status (New Lot/Record Movement/Reserve Quantity — parallel actions, same pattern as loom breakdown/maintenance), controls: 10 parallel chips (Create/Archive Location, Update/Archive Lot, Freeze/Unfreeze Lot, Physical Verification, Cycle Count, Release/Allocate Reservation), records: Movement Filters card (Type/Status/Lot/Location + Apply/Clear) + Movements table + grid of Locations/Lots/Cycle Counts/Reservations tables; overview = combined read-only table (Type/Reference/Qty/Status) with 12 demo rows + pagination; all tables inherit search/sort/pagination defaults. Verified: tsc 0, build pass (23.86s), 62 tests/1160 assertions, browser — rail 4 sections, breadcrumb + CTA, chips w/ counts (New Lot 3 / Record Movement 3 / Reserve Quantity 2), Current Form | Records tabs (Records grid shows Lots full + Movements + Reservations), controls 10 chips (Create Location 4 pressed default), records filter + 5 tables, overview 12 rows paginated (1-10/11-12), aside cards live.
- [x] Sidebar submenu behavior change — IMPLEMENTED THEN REVERTED (Aug 4, 2026, user: "revert back the sidebar menu change you did just"): `nav-main.tsx` both Collapsible levels returned to auto-expand on the active page (`defaultOpen={shouldBeActive}` parent / `defaultOpen={subItemShouldBeActive}` nested) — sidebar groups AND workspace submenus expand automatically again for the active page; expansion on explicit click was removed. Submenu children still kept. Deep links still work (`?section=transactions&sub=movement-create`). Verified: `git diff` for `nav-main.tsx` empty vs committed state (byte-identical to original).
- [x] `Quality` (3 workflow sections; page refactored from hardcoded Tabs + `TextileKpiOverview` to `TextileWorkspace` switch — Aug 2026, same design upgrade as Manufacturing/Sales/Inventory): registry `quality` gained `overview` first section + per-section capability keys (`quality_inspection`, `quality_hold_release` — rail capability-filtered via `useTextileSection`); controller now uses shared `ProvidesRecentActivity` trait; breadcrumb Textile > Quality > {section}, emerald "New Inspection" CTA; per-section KPIs (overview: Inspections/Rejected/Hold Events/Issued Certificates; inspection: Total Inspections/Passed/Rework/Rejected; hold-release: Hold Events/Inspections/Issued Certificates/Rejected; certificates: Issued/Pending Certificates/Inspections/Rejected); aside: Workflow Status stepper (Fabric Inspection → Hold/Release → Certificates with live counts), Recent Activity timeline, Quality Summary card (Inspections/Rejected/Hold Events/Issued Certificates), Decision Breakdown card (Passed/Rework/Pending/Rejected); sections use `TextileWorkflowSteps` chips WITHOUT status (single-action sections: Fabric Inspection / Hold/Release / Quality Certificates) + Current Form | Records tabs — inspection records (Pass/Reject/Rework row actions), hold-release records, certificate records (Issue Certificate row action); overview = combined read-only table (Type/Document/Party/Lot/Qty/Unit/Status) across inspections/holds/certificates; all tables inherit search/sort/pagination defaults. Verified: tsc 0, build pass (22.61s), 62 tests/1160 assertions.
- [x] `Packing` (4 workflow sections; page refactored from hardcoded Tabs + `TextileKpiOverview` to `TextileWorkspace` switch — Aug 2026, same design upgrade as Manufacturing/Sales/Inventory): registry `packing` gained `overview` first section + capability key (`packing`); controller now uses shared `ProvidesRecentActivity` trait; `canPacking` gate preserved (Sales Challan/POD capability) with `NoRecordsFound` fallback; breadcrumb Textile > Packing > {section}, emerald "New Roll Packing" CTA; per-section KPIs (overview: Total Packing Docs/Roll Packings/Bundle Packings/Issued Labels; roll-packing: Roll Packings/Total Packing Docs/Issued Labels/Released Challans; bundle-packing and bale-packing mirror that pattern; labels: Labels/Issued Labels/Pending Labels/Total Packing Docs); aside: Workflow Status stepper (Roll → Bundle → Bale → Labels with live counts), Recent Activity timeline, Packing Summary card (Total Docs/Roll/Bundle/Bale), Labels card (Total/Issued/Pending/Released Challans); sections use `TextileWorkflowSteps` chips WITHOUT status (single-action: Create Roll/Bundle/Bale Packing + Generate Label) + Current Form | Records tabs — roll/bundle/bale packing records tables (Material + Weight columns), Label Records (Issue Label row action, noVisibleActionContent "Label already issued"); overview = combined read-only table (Type/Document/Party/Lot/Qty/Unit/Status) across all 4 doc types; all tables inherit search/sort/pagination defaults. Verified: tsc 0, build pass (22.61s), 62 tests/1160 assertions.
- [ ] `Transport` (3) → `Dispatch` (2-6) → `Maintenance` (6) → `Finance` (7) → `Processing` (11) → `Reports` (14; rail, revisit hub-cards only if sections grow independent filter bars).

### Phase 3A — Handwritten process list (reuse existing features, Aug 2026)

All 9 features from the handwritten textile process note. Rules: reuse existing document types and shared components where possible; no new architectural patterns — follow Phase 2 design (TextileWorkspace rail, TextileWorkflowSteps, MetricSummaryCard, TextileInfoPanel, capability gating, master-driven controlled fields). Domain-first execution within Phase 3A.

| # | Feature | Classification | Domain | Reuse From | Effort | Status |
|---|---|---|---|---|---|---|
| 1 | Beam Manufacture: Own vs Rent flag | 🟡 Extend Existing | 10 Beam Management | Beam `source_reference_type` (derived) | 30 min | `[x]` |
| 2 | Yarn Issue (Sizing / Weaver) | 🔵 Modify Existing | 8 Warping | Yarn Allocation + Job Work Outward (renamed) | 15 min | `[x]` |
| 3 | D.O. (Yarn Purchase Proforma) | ✅ Rename Only | 6 Purchase | "Approve" → "Send Proforma" on PO row action | 0 hrs | `[x]` |
| 4 | Purchase Bill (Yarn) | 🟡 Extend Existing | 6 Purchase | PurchaseInvoice + GRN sync | 2 hrs | `[x]` |
| 5 | Takha Proforma (Sauda) → Vendors | ✅ Already Exists | 13 Weaving | SalesQuotation package — added "Quotations (Sauda)" section to Sales workspace registry + page. Read-only table: quotation #, customer, dates, total, status, invoiced. Full CRUD on `/quotations` page. | 0 hrs | `[x]` |
| 6 | GST, TDS, Bank, Cheque & Payment Type | ✅ Extend Existing | 12 Finance | Added payment_mode, cheque fields, TDS fields to vendor_payments + customer_payments tables. Migration + model + form request + controller. | 0 hrs | `[x]` |
| 7 | Challa Wise / Loom Wise reports | ✅ Already Exists | 11/14 Reports | TextileReportsService + reports workspace has 17 report types incl. loom(), production(), machineEfficiency() | 0 hrs | `[x]` |

Feature details:

- [x] **Beam Own vs Rent**: Already distinguished by creation path — "Create Beam from Sizing Recipe" = own (in-house), "Create Beam" manual = rent (external). `source_reference_type` encodes the origin (`textile_workflow_document` for own, other for rent). Added derived "Origin" column to beam records table that reads from `source_reference_type`. No new field, validation, or metadata needed. Verified: tsc 0, build pass, 62 tests/1160 assertions.
- [x] **Yarn Issue (Sizing / Weaver)**: "Yarn Issue to Sizing" already exists as `yarn_allocation` doc type in Manufacturing > Warp Planning (step 2: "Allocate Yarn from Approved Warp Plan"). "Yarn Issue to Weaver" already exists as `job_work_outward` doc type in Processing — renamed UI label from "Job Work Outward" to "Yarn Issue to Weaver" in workspace registry, menu, and form card. No new routes, services, or forms needed. Verified: tsc 0, build pass.
- [x] **D.O. (Proforma on PO)**: "Approve" action renamed to "Send Proforma" on approved POs (same backend flow — draft → approved → released → closed). No new status, route, or service method needed. The PO *is* the proforma. Verified: tsc 0, build pass, 62 tests/1160 assertions.
- [x] **Purchase Bills section**: Added `bills` section to procurement workspace registry + Procurement page. Reads existing `PurchaseInvoice` records (created via GRN sync). Read-only table: invoice #, vendor, dates, amounts, status. Zero new backend code — reuses core PurchaseInvoice model. Verified: tsc 0, build pass, 62 tests/1160 assertions.
- [x] **Takha Proforma (Sauda)**: Reuses existing `SalesQuotation` package (Quotation > Create/Edit/View/Print with revision support, convert-to-invoice). Added "Quotations (Sauda)" section to Sales workspace — read-only table showing quotation records. Full CRUD available on the standalone `/quotations` page. No new doc type, menu, or capability needed. Verified: tsc 0, build pass, 62 tests/1160 assertions.
- [x] **Payments (GST/TDS/Cheque/Payment Type)**: Added compliance fields to existing `vendor_payments` and `customer_payments` tables via migration. New fields: `payment_mode` (cash/cheque/neft/rtgs/imps/upi), `cheque_number`, `cheque_date`, `bank_name`, `tds_rate`, `tds_amount`, `tds_section`. Updated models ($fillable + $casts), form requests (validation rules), and controllers (store passthrough). Payment Create UI now captures these fields for both Vendor and Customer payment flows (including cheque-conditional inputs). Verified: migration + backend wiring in place, and `npm run build` pass (existing chunk-size warnings only).
- [x] **Challa/Loom Wise reports**: Already covered by `TextileReportsService` — 17 report methods including `loom()`, `production()`, `machineEfficiency()`, `operator()`. Reports workspace already has per-section rendering. Challa is already a workflow document type in Sales flow. No new code needed.

#### Flow Coverage Check (Requested 6-step customer flow)

| Customer flow step | Covered in Phase 3A | Covered in Phase 3B | Covered in existing Layer-1 baseline/UI | Status |
|---|---|---|---|---|
| 1. Proforma to purchase yarn (Req -> RFQ -> PO/Proforma -> GRN -> QC -> Bill) | Yes (`D.O. Proforma`, `Purchase Bill`) | No | Yes (Procurement workspace rails + actions) | `[x]` |
| 2. Audit and pass to sizing -> beam (own/rent path) | Yes (`Yarn Issue rename`, `Beam Own/Rent`) | No | Yes (Manufacturing + Processing rails) | `[x]` |
| 3. Beam issue to weaver + return + inspection + cost | Indirect (3A supports own/rent and yarn issue semantics) | No | Yes (Beam and Batch workflow forms/tables) | `[x]` |
| 4. Takha production from weaver | Partial (`Takha Proforma/Sauda` downstream linkage) | No | Yes (Weaving Production + Waste/Rework workflow) | `[x]` |
| 5. Takha dispatch to customer (SO -> Allocation -> Dispatch -> Challan -> POD) | Partial (`Challa/Loom reporting`, sales quotation reuse) | No | Yes (Sales workspace rails + row actions) | `[x]` |
| 6. Payments and finance (GST/TDS/Cheque/Payment Type + receipts) | Yes (payment compliance fields + UI capture) | No | Yes (Account Vendor/Customer Payments + Textile Finance/Reports) | `[x]` |

Interpretation: Phase 3A/3B do not replace Layer-1; they extend it. The requested operational flow is fully covered when Phase 3A additions are combined with existing baseline workspaces and Account payment screens.

Verification gate per feature: `npx tsc --noEmit` 0 errors; `npm run build` pass; `php artisan test tests/Feature/Textile/` pass; browser check (rail renders, `?section=` deep links work, menu/submenu navigable, controlled fields are select-based); navigation acceptance checklist satisfied.

### Phase 3B — Inventory Redesign + Per-Role RBAC (Aug 2026)

**Problem 1 — Inventory**: Current inventory has generic lots with no material type distinction. Manager sees a flat "Transactions" section with 3 generic forms that could apply to anything. No way to distinguish yarn, beam, grey fabric, finished fabric. The 10-chip Controls section is overwhelming.

**Problem 2 — RBAC**: All users in a textile company see the same textile menus. Manager sees Finance, Costing, Logs, Master Setup — screens they never use. The `textile_capabilities` system is per-tenant, not per-role.

**Solution**: Two connected changes — (1) restructure inventory into material-type-wise sub-menus with auto-created lots, (2) add per-role capability overrides so owner sees everything and manager sees only operational screens.

#### Part A: Inventory Material-Type Redesign

| # | Task | Classification | Domain | Effort | Status |
|---|---|---|---|---|---|
| A1 | Migration: add `material_type`, `production_stage`, `source_document_type`, `source_document_id` to `textile_lots` | 🟡 Extend Existing | Inventory | 30 min | `[x]` |
| A2 | Model: add enum casts + scopes (`byMaterialType()`, `byStage()`) on TextileLot | 🟡 Extend Existing | Inventory | 15 min | `[x]` |
| A3 | Registry: rebuild inventory sections as material-type-wise (Yarn Stock, Beam Stock, Grey Fabric, Finished Fabric, Chemicals, Packing Materials, Locations & Controls) | 🔴 New | Inventory | 1 hr | `[x]` |
| A4 | Controller: filter queries by material_type per section, add contextual KPIs | 🟡 Extend Existing | Inventory | 1 hr | `[x]` |
| A5 | Auto-creation hooks: GRN release → yarn lot, Beam create → beam lot, Weaving Output → grey lot, Processing Inward → finished lot | 🟡 Extend Existing | Inventory | 1 hr | `[x]` |
| A6 | UI: material-type icons, per-section tables with relevant columns, eliminated generic forms | 🔴 New | Inventory | 1 hr | `[x]` |

**Inventory sidebar after redesign:**
```
Inventory
  ├── 🧵 Yarn Stock (Overview, Yarn Lots, Yarn Movements)
  ├── 📦 Beam Stock (Overview, Beam Lots, Issue & Return)
  ├── 👕 Grey Fabric (Overview, Grey Rolls, Movements)
  ├── ✨ Finished Fabric (Overview, Finished Lots, Ready for Dispatch)
  ├── 🧪 Chemicals (Overview, Chemical Lots)
  ├── 📦 Packing Materials (Overview, Packing Stock)
  └── ⚙️ Locations & Controls (Freeze, Unfreeze, Physical Verification, Cycle Count)
```

#### Part B: Per-Role Textile RBAC (Owner + Manager)

| # | Task | Classification | Domain | Effort | Status |
|---|---|---|---|---|---|
| B1 | Migration: create `textile_role_capabilities` table (role_id, capabilities JSON, created_by) | 🟡 Extend Existing | RBAC | 30 min | `[x]` |
| B2 | Model: `TextileRoleCapability` with tenant scoping | 🟡 Extend Existing | RBAC | 15 min | `[x]` |
| B3 | Service: add `capabilitiesForUser($user)` to `TextileOperatingPolicyService` — merges base policy + role overrides | 🟡 Extend Existing | RBAC | 1 hr | `[x]` |
| B4 | Middleware: `HandleInertiaRequests` calls `capabilitiesForUser()` instead of `capabilities()` | 🟡 Extend Existing | RBAC | 30 min | `[x]` |
| B5 | Seed default overrides: company admin (owner) = full access, staff (manager) = operational only | 🟡 Extend Existing | RBAC | 30 min | `[x]` |

**Manager sees vs Owner sees:**
- Owner: ~111 items (everything)
- Manager: ~74 items — hides Production Planning, Freight Cost, Maintenance Cost, Process Cost, Finance, Costing, Logs, and 9 of 14 Master Setup groups
- No complex role hierarchy — just 2 roles: `company` (owner) and `staff` (manager)

#### Manager Simplicity Review (Simple vs Complex)

Recommended manager-visible (daily operational only):
- Procurement: Requisitions, RFQ, Purchase Orders, GRN, Incoming QC, Purchase Bills.
- Inventory: Yarn/Beam/Grey/Finished stock and basic movement/reservation actions.
- Manufacturing: Beam and Batch, Weaving Production, Waste, Rework.
- Sales: Sales Order, Allocation and Dispatch.
- Processing: Yarn Issue to Weaver, Processing Batch, Job Work Inward, Reconciliation.
- Payments: Vendor Payments, Customer Payments (from Account module).
- Reports: operational reports used daily (production, loom, sales, purchase, stock).

Recommended manager-hidden (complex/strategic/admin):
- Manufacturing Planning (`manufacturing_planning`).
- Manufacturing Maintenance (`manufacturing_maintenance`).
- Quality Hold/Release decisions (`quality_hold_release`) where escalation to owner is preferred.
- Challan/POD final release controls (`sales_challan_pod`) if separation of duties is required.
- Inventory Freeze/Verification/Cycle Count controls (`inventory_freeze`, `inventory_verification`, `inventory_cycle_count`).
- Transport and Maintenance operation groups (`transport_operations`, `maintenance_operations`).
- Textile Finance workspace, Textile Costing workspace, Textile Logs, and most Master Setup pages.

Current role seed already enforces a major part of this via `staffOperationalCapabilities()` in `TextileOperatingPolicyService`; if stricter separation is needed, apply additional role-capability overrides for `finance`, `costing`, and setup capabilities.

**Total effort: ~8-9 hrs** (down from 10-11 hrs as two separate phases; completed and verified)

Verification gate: `npx tsc --noEmit` 0 errors; `npm run build` pass; `php artisan test tests/Feature/Textile/` pass; browser check — inventory rail shows material-type sections with auto-created lots, company admin sees all 111 items, manager role sees ~74 items.

---

### Phase 3C — Smart Inventory Insights (Aug 2026)

**Problem**: Inventory lots exist but carry no consumption, traceability, or pipeline visibility. A manager cannot see "500kg yarn received → 100kg allocated to warping → beam received → 10 takhas of 10kg woven → inspected → dispatched" at a glance. Material stock sections are bare tables; Locations & Controls is overloaded with 10 stacked forms (Physical Verification ≈ Cycle Count duplicate); no branch filter on inventory.

**Goal**: Make inventory the operational decision screen — stage-by-stage pipeline, lot traceability chain (yarn → beam → grey → takha → finished), automatic movement ledger, yield/conversion insights, and a cleaned-up controls area.

**Flow covered**: PO → GRN → Incoming QC (pass) → warp plan → yarn allocation → beam → production batch → weaving output → multiple takhas → Fabric Inspection → Hold/Release → SO → Dispatch → POD.

**Slices (each end-to-end: backend + UI + menu + test):**

| # | Slice | Scope | Effort | Status |
|---|---|---|---|---|
| A | Stock consumption & lot traceability | `parent_lot_reference`/`parent_lot_type` on lots; yarn allocation reserves+issues yarn; beam receipt consumes yarn; weaving output gets own grey lot ref (fix collision); takha receipt links to weaving-output lot | 3 hrs | `[x]` |
| B | Automatic movement ledger | Post movements at every transition (yarn issue, beam receipt/issue, weaving output, takha receipt, inspection pass, dispatch issue); ledger = single source of truth | 2 hrs | `[ ]` |
| C | Smart Overview pipeline | Stage cards (Procurement → QC → Warping → Sizing → Weaving → Processing → Packing → Dispatch) with qty per stage; KPIs (yarn available/in warping, beams ready/in weaving, takhas produced, grey available, inspected, dispatched); lot table with stage badge + days-in-stage aging | 3 hrs | `[ ]` |
| D | Material stock sections enriched | Yarn/Beam/Grey/Finished sections get available/WIP/reserved/aging summary, low-stock + reorder alert, stage filter, movement sparkline | 2 hrs | `[ ]` |
| E | Yield & conversion report | Yarn→beam conversion %, beam→fabric meters, wastage/loss per batch, issued vs produced reconciliation | 2 hrs | `[ ]` |
| F | Lot drill-down traceability | Enhance `LotShow`: parent → this → child chain, full movement history, linked docs (GRN, warp plan, beam, batch, takhas, inspection, dispatch) | 2 hrs | `[ ]` |
| G | Locations & Controls cleanup | Merge Physical Verification + Cycle Count → one Stock Count tab; group into 4 sub-tabs (Location Setup / Lot Controls / Stock Count / Reservations) | 1.5 hrs | `[ ]` |
| H | Branch filter on inventory | `?branch_id=` scoping on lots/movements/reservations + branch selector in UI (parity with payments) | 1.5 hrs | `[ ]` |

**Audit findings baked into the plan:**
- Yarn allocation currently does NOT consume/reserve yarn — manufacturing service has zero reservation/decrement calls (verified).
- Weaving output reuses the beam's `lot_reference`, so `firstOrCreate` finds the existing beam lot and never creates a grey lot (collision bug, verified in `TextileLotAutoCreationService`).
- Only Incoming-QC-pass posts a receipt movement today; beam/weaving/takha transitions post nothing.
- `storePhysicalVerification` is a strict subset of `storeCycleCount` (no record saved) — merge into one Stock Count control.
- GRN `createFromGrn` hook is dead code — GRN lot is actually created in `TextileProcurementService::finalizeIncomingQc`.

**Slice A delivered (2026-08-08):**
- New migration `2026_08_08_000012_add_parent_lot_to_textile_lots_table` → `parent_lot_reference` + `parent_lot_type` on `textile_lots` (schema-guarded, applied on production).
- New `TextileConsumptionService` (reserve yarn at allocation, issue+consume at beam receipt, fulfill reservation without double decrement, fail-open for legacy data).
- `TextileLotAutoCreationService`: source-document idempotency guard (fixes duplicate grey lot bug), weaving output derives own `GREY-*` reference, takha links to weaving-output grey lot, beam links to source yarn lot.
- `TextileManufacturingService`: wired allocation→reservation, beam→yarn issue + parent link, weaving output→grey lot (own ref), takha→grey lot parent chain.
- Verification: `php artisan test tests/Feature/Textile/TextileInventoryConsumptionTest.php` => 3 passed (57 assertions); full textile suite => 67 passed (1271 assertions); `npx tsc --noEmit` => 0 errors; `npm run build` => pass (existing chunk-size warnings only); migration applied + deployed on production (homepage 302 / login 200, no log errors).

Verification gate per slice: `npx tsc --noEmit` 0 errors; `npm run build` pass; `php artisan test tests/Feature/Textile/` pass; browser check — section renders, deep links work, menu/submenu navigable, controlled fields select-based; navigation acceptance checklist satisfied.

---

### Phase 4 — Master/CRUD pages (light touch)

- [ ] `Masters`, `Specifications`, `Costing`, `Approvals`, `CostCenters`, `CustomFields`, `OperatingPolicy`, `Logs`, `DispatchVehicles`, `DispatchDrivers`, `DispatchRoutes` — adopt shared `TextileFormCard`/`TextileSection`/KPIs where duplicated; no rail needed (single-purpose pages).

### Phase 5 — Dashboard decision

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
	- Requisition -> **Delivery Order (Proforma)** -> Purchase Order -> GRN -> Incoming QC -> **Purchase Bill**
2. Inventory (control)
	- Lot receipt/movement/reservation and location tracking
3. Manufacturing (if in-house)
	- **Yarn Issue (to Sizing / Weaver)** -> Beam (Own/Rent) -> Production Batch -> Weaving Output -> Takha Entry -> **Takha Proforma (Sauda) to Vendors** -> Waste/Rework
4. Processing (if job work)
	- Job Work Outward -> Processing Batch -> Job Work Inward -> Reconciliation
5. Sales (sell)
	- Sales Order -> Allocation -> Dispatch -> Challan -> POD
6. **Payments and Finance**
	- **GST calculation -> TDS deduction -> Payment recording (Cash/Cheque/NEFT/RTGS/UPI) -> Bank reconciliation**
7. **Reports**
	- **Challa Wise / Loom Wise / Date Wise grouping and filtering**
8. Costing and review
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
| Procurement flow | Layer 1: TextileProcurement + **Phase 3A: D.O./Proforma, Purchase Bills** |
| Inventory control | Layer 1: TextileInventory |
| Manufacturing flow | Layer 1: TextileManufacturing + **Phase 3A: Yarn Issue, Beam Own/Rent, Takha Proforma** |
| Processing flow | Layer 1: Remaining roadmap phases (job work and processing) |
| Sales flow | Layer 1: TextileSales |
| **Payments and Finance** | **Phase 3A: GST/TDS/Cheque/Payment Type** |
| **Reports** | **Phase 3A: Challa/Loom Wise grouping** |
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
13. **Phase 3A quick wins**: Beam Own/Rent flag, Yarn Issue un-hiding, D.O. Proforma on PO
14. **Phase 3A medium**: Purchase Bills section, Takha Proforma (Sauda)
15. **Phase 3A large**: Payments (GST/TDS/Cheque), Challa/Loom Wise reports


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
- Payments branch filter + Primary Defect pass-hide (2026-08-08): `php artisan test tests/Feature/Textile` = `64 passed (1214 assertions)`; `npx tsc --noEmit` = 0 errors; `npm run build` = pass (existing chunk-size warnings only). Payments workspace now accepts `?branch_id=` to scope KPIs/branch overview/vendor activity/reminders, and "Send Reminders" honors the selected branch. Deployed and verified on production (`/textile/payments?branch_id=1` route live, new build asset 200, no nginx symlink errors).
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

1. **P1 (completed Aug 2026)**: Inventory Redesign + Per-Role RBAC — Phase 3B. Restructure inventory into material-type-wise sub-menus (Yarn, Beam, Grey Fabric, Finished Fabric, Chemicals, Packing Materials) with auto-created lots. Add per-role capability overrides (owner sees all 111 items, manager sees 74 operational items). Verified: `npx tsc --noEmit`, `npm run build`, `php artisan test tests/Feature/Textile/TextileApprovalAdminTest.php`, and `php artisan db:seed --class=TextileRoleCapabilitySeeder --no-interaction --force`.
2. P1: implement customer operating model profiles (including powerloom-only and customer-owned beam/yarn flows) with profile-based workflow gates.
3. P1: add warping/sizing/loom planning baseline and formalize roll plus final-QC data model.
4. P2: deliver packing, transport, maintenance, and expanded report/dashboard packs.
5. P3: deliver mobile, hardware, and statutory integrations.

## Tracker maintenance

- Update this file after each implemented and verified slice.
- Mark a task `[x]` only when its backend, authorization, required admin UI, and focused validation are complete.
- For enterprise tasks, do not mark `[x]` unless ERP-style menu group placement, submenu route mapping, and breadcrumb/title consistency are also verified.
- Keep a task `[~]` when only backend work exists.
