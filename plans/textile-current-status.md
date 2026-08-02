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

For enterprise backlog items, this tracker also references feature classification from the traceability matrix:
- `Reuse` = already available in ERPGo and/or current textile slices.
- `Extend` = base exists and needs textile-specific enrichment.
- `Modify` = existing behavior must be changed/refactored for enterprise use.
- `New` = new package/module needed.

Reference artifact: `plans/textile-enterprise-traceability-matrix.md`

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
| Standardize page type decision (`popup` vs `full page`) | Global | `[ ]` | Small transactional actions use modal/popup; large workflows use dedicated pages with list/filter/detail patterns. |
| Add navigation acceptance checklist to every domain delivery | Global | `[ ]` | No domain task is marked `[x]` unless menu placement, submenu route, and breadcrumb behavior are verified. |
| Tenant-aware menu filtering (Textile vs Standard + module assignment) | Global | `[x]` | Already implemented in middleware/menu resolver; continue as a non-regression gate for new menus. |
| Breadcrumb and page-title consistency per submenu | Global | `[~]` | Every submenu page has predictable breadcrumb root and domain-specific title naming. |
| Searchability and quick-jump in menu for large domains | Global | `[ ]` | Menu search and/or quick command supports deep feature navigation at enterprise scale. |

Menu completion rule:
- A feature stays `[~]` (not `[x]`) if backend is done but final ERP-style menu/submenu placement is not delivered and validated.

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
| Custom Fields | 🆕 New Module Required | P2 | `[ ]` |
| Tags | 🆕 New Module Required | P2 | `[ ]` |
| Comments | 🟡 Extend Existing | P2 | `[~]` |
### Domain 2: CRM (11 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Leads | ✅ Already Available | P1 | `[x]` |
| Customers | ✅ Already Available | P1 | `[x]` |
| Contacts | 🟡 Extend Existing | P2 | `[~]` |
| Customer Categories | 🆕 New Module Required | P2 | `[ ]` |
| Follow Ups | 🟡 Extend Existing | P2 | `[~]` |
| Quotations | ✅ Already Available | P1 | `[x]` |
| Sales Orders | 🟡 Extend Existing | P1 | `[~]` |
| Customer Operating Model Profiles | 🆕 New Module Required | P1 | `[x]` |
| Customer Price List | 🆕 New Module Required | P2 | `[ ]` |
| Credit Limits | 🔵 Modify Existing | P2 | `[~]` |
| Customer Documents | 🟡 Extend Existing | P2 | `[~]` |

### Customer operating model mapping (real-world)

Use this mapping to support different textile customer business styles without forcing one fixed flow.

| Customer type | Material ownership | Billing mode | Primary operational flow | Status |
|---|---|---|---|---|
| Full-package buyer (finished fabric) | Company owned | Sale value | Sales Order -> Allocation -> Dispatch -> Challan -> POD -> Invoice | `[~]` |
| Job-work weaving (customer beam/yarn supplied, powerloom focused) | Customer owned | Conversion charge | Beam Inward/Reference -> Production Batch -> Weaving Output -> Dispatch/POD -> Job-work Invoice | `[ ]` |
| Processing-only customer (grey supplied) | Customer owned | Process charge | Job-work Outward -> Processing Batch -> Job-work Inward -> Reconciliation -> Invoice | `[~]` |
| Trader/distributor bulk buyer | Company owned | Sale value with price list/credit rules | Sales Order -> Dispatch -> Invoice -> Collection | `[~]` |
| Export/compliance customer | Mixed | Hybrid | Sales/Dispatch + QC/Certificates + compliance docs | `[ ]` |

Implementation gate for this mapping:
- Add customer profile fields: `operating_model`, `material_ownership`, `billing_mode`.
- Add profile-based workflow toggles (menu + validation) so powerloom-only and own-beam customers see only relevant flow steps.
- Add costing split behavior: customer-owned material should post conversion/process costs without company raw-material valuation.

Progress note (2026-08-03):
- Customer profile fields are implemented in Account customer schema/backend/forms/list/view using shared TextileSelectField UX.
- Profile-based flow toggles are now delivered via operating-policy capabilities exposed to shared auth props, menu capability filtering, and server-side sales validation that blocks job-work-only customer profiles from sales-order flow.
- Costing split behavior is now delivered: customer-owned profile entries enforce conversion-only costing by excluding company material valuation (`material_cost` forced to `0`) while preserving entered material cost for audit in metadata.
- Verification: `php artisan test tests/Feature/Textile/TextileCustomerOperatingProfileAdminTest.php tests/Feature/Textile/TextileCostingAdminTest.php tests/Feature/Textile/TextileOperatingPolicyAdminTest.php` => `5 passed (45 assertions)`.
### Domain 3: Supplier Management (9 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Yarn Suppliers | 🟡 Extend Existing | P1 | `[x]` |
| Chemical Suppliers | 🟡 Extend Existing | P1 | `[x]` |
| Spare Part Suppliers | 🟡 Extend Existing | P2 | `[x]` |
| Processing Vendors | 🟡 Extend Existing | P2 | `[x]` |
| Dyeing Vendors | 🟡 Extend Existing | P2 | `[x]` |
| Transport Vendors | 🔵 Modify Existing | P2 | `[x]` |
| Vendor Rating | 🆕 New Module Required | P2 | `[ ]` |
| Vendor Performance | 🆕 New Module Required | P2 | `[ ]` |
| Job Workers | 🔵 Modify Existing | P2 | `[x]` |

Progress note (2026-08-03):
- Vendor CRUD now supports supplier classification and filtering through the shared Account vendor workflow, covering yarn supplier usage first without introducing a separate supplier UI.
- Verification: `php artisan test tests/Feature/Account/VendorSupplierTypeTest.php` => `1 passed (18 assertions)`.
- Supplier Management now reuses the same vendor workflow for yarn, chemical, spare-part, processing, dyeing, transport, and job-worker classifications.
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
| Product Variants | 🔵 Modify Existing | P2 | `[~]` |
| Product Specifications | ✅ Already Available | P1 | `[x]` |
| Product Images | 🔵 Modify Existing | P2 | `[~]` |
| Product Documents | 🟡 Extend Existing | P2 | `[~]` |

Progress note (2026-08-03):
- ProductService item taxonomy now supports textile product classifications through the shared item workflow, including yarn, fabric, grey fabric, finished fabric, chemical, packing material, spare part, and accessory labels.
- Verification: `php artisan test tests/Feature/ProductService/ProductTypeTest.php` => `1 passed (20 assertions)`.
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
| Cone Number | 🆕 New Module Required | P1 | `[ ]` |
| Cone Weight | 🆕 New Module Required | P1 | `[ ]` |
| Net Weight | 🔵 Modify Existing | P1 | `[x]` |
| Gross Weight | 🔵 Modify Existing | P1 | `[x]` |
| Moisture | 🟡 Extend Existing | P2 | `[x]` |
| Quality Grade | ✅ Already Available | P1 | `[x]` |
| Yarn Cost | 🔵 Modify Existing | P2 | `[x]` |
| Yarn Barcode | 🆕 New Module Required | P2 | `[ ]` |
| Yarn QR Code | 🆕 New Module Required | P2 | `[ ]` |

Progress note (2026-08-03):
- TextileCore specifications now carry yarn attributes alongside the existing fabric dimensions, reusing the shared TextileCore master-data workflow.
- Verification: `php artisan test tests/Feature/Textile/TextileSpecificationAdminTest.php` => `1 passed (11 assertions)`.
### Domain 6: Purchase (8 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Purchase Requisition | ✅ Already Available | P1 | `[x]` |
| RFQ | 🟡 Extend Existing | P2 | `[~]` |
| Purchase Order | ✅ Already Available | P1 | `[x]` |
| Goods Receipt (GRN) | ✅ Already Available | P1 | `[x]` |
| Purchase Invoice | 🟡 Extend Existing | P1 | `[x]` |
| Purchase Return | ✅ Already Available | P1 | `[x]` |
| Supplier QC | ✅ Already Available | P1 | `[x]` |
| Supplier Claims | 🆕 New Module Required | P2 | `[ ]` |

Progress note (2026-08-03):
- Purchase invoices and purchase returns now reuse the shared vendor supplier classification in their list filters and summary columns, so yarn/chemical/processing vendors can be segmented without a separate purchase taxonomy.
- Verification: `php artisan test tests/Feature/Purchase/PurchaseInvoiceVendorTypeTest.php` => `1 passed (36 assertions)`.

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
| Chemical Consumption | 🆕 New Module Required | P1 | `[ ]` |
| Beam Creation | 🟡 Extend Existing | P1 | `[x]` |
| Beam Inspection | 🟡 Extend Existing | P2 | `[~]` |
| Beam Cost | 🔵 Modify Existing | P2 | `[~]` |

- Sizing Recipe slice now supports create-from-warp-production flow in Textile Manufacturing (web + API + shared UX), with tenant isolation verification extended in TextileManufacturingAdminTest.
- Beam Creation slice now supports create-from-sizing-recipe flow in Textile Manufacturing (web + API + shared UX), with tenant isolation verification extended in TextileManufacturingAdminTest.
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
| RPM | 🔵 Modify Existing | P2 | `[~]` |
| Width | 🔵 Modify Existing | P1 | `[~]` |
| Shed | 🔵 Modify Existing | P2 | `[~]` |
| Status | 🟡 Extend Existing | P2 | `[~]` |
| Running | 🟡 Extend Existing | P2 | `[~]` |
| Idle | 🟡 Extend Existing | P2 | `[~]` |
| Breakdown | 🆕 New Module Required | P2 | `[ ]` |
| Maintenance | 🆕 New Module Required | P2 | `[ ]` |
| Operator Assignment | 🟡 Extend Existing | P2 | `[~]` |

- Loom Management slice now includes loom master registration in Textile Manufacturing (web + API + shared UX), with tenant isolation verified in TextileManufacturingAdminTest.
- Master Setup now includes separate CRUD for Source Types and Machine Types, and workflow forms consume select options sourced from Source Type and Unit Conversion masters (with Machine Type master in Loom Management), verified across textile admin tests.
### Domain 12: Production Planning (6 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Production Calendar | 🆕 New Module Required | P2 | `[ ]` |
| Capacity Planning | 🆕 New Module Required | P2 | `[ ]` |
| Shift Planning | 🆕 New Module Required | P2 | `[ ]` |
| Machine Planning | 🟡 Extend Existing | P1 | `[~]` |
| Material Planning | 🆕 New Module Required | P1 | `[ ]` |
| Production Order | ✅ Already Available | P1 | `[x]` |
| Production Schedule | 🆕 New Module Required | P2 | `[ ]` |
### Domain 13: Weaving Production (8 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Daily Production | ✅ Already Available | P1 | `[x]` |
| Shift Production | 🔵 Modify Existing | P1 | `[~]` |
| Takha Entry | 🆕 New Module Required | P2 | `[ ]` |
| Roll Generation | ✅ Already Available | P1 | `[x]` |
| Loom Efficiency | 🟡 Extend Existing | P2 | `[~]` |
| Operator Efficiency | 🟡 Extend Existing | P2 | `[~]` |
| Machine Downtime | 🆕 New Module Required | P2 | `[ ]` |
| Waste | ✅ Already Available | P1 | `[x]` |
| Production Cost | 🔵 Modify Existing | P2 | `[~]` |
### Domain 14: Grey Fabric (6 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Roll Number | 🔵 Modify Existing | P1 | `[~]` |
| Roll Barcode | 🆕 New Module Required | P2 | `[ ]` |
| Roll QR Code | 🆕 New Module Required | P2 | `[ ]` |
| Roll Weight | 🔵 Modify Existing | P1 | `[~]` |
| Roll Length | 🔵 Modify Existing | P1 | `[~]` |
| GSM | 🔵 Modify Existing | P2 | `[~]` |
| Width | 🔵 Modify Existing | P1 | `[~]` |
| Defects | 🟡 Extend Existing | P2 | `[~]` |
| Grade | 🔵 Modify Existing | P1 | `[~]` |
| Warehouse | ✅ Already Available | P1 | `[x]` |
| Roll History | 🟡 Extend Existing | P2 | `[~]` |
### Domain 15: Processing (9 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Internal Processing | 🟡 Extend Existing | P2 | `[~]` |
| Job Work | ✅ Already Available | P1 | `[x]` |
| Dyeing | 🔵 Modify Existing | P2 | `[~]` |
| Printing | 🆕 New Module Required | P2 | `[ ]` |
| Bleaching | 🆕 New Module Required | P2 | `[ ]` |
| Calendaring | 🆕 New Module Required | P2 | `[ ]` |
| Compacting | 🆕 New Module Required | P2 | `[ ]` |
| Finishing | 🟡 Extend Existing | P2 | `[~]` |
| Recipe | ✅ Already Available | P1 | `[x]` |
| Shade Card | 🆕 New Module Required | P2 | `[ ]` |
| Batch | ✅ Already Available | P1 | `[x]` |
| Process Cost | 🔵 Modify Existing | P2 | `[~]` |
### Domain 16: Quality (8 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Incoming QC | ✅ Already Available | P1 | `[x]` |
| Process QC | 🟡 Extend Existing | P2 | `[~]` |
| Final QC | 🟡 Extend Existing | P1 | `[x]` |
| Defect Library | 🆕 New Module Required | P2 | `[ ]` |
| Shade Matching | 🆕 New Module Required | P2 | `[ ]` |
| Fabric Inspection | 🟡 Extend Existing | P2 | `[~]` |
| Hold | 🟡 Extend Existing | P1 | `[x]` |
| Reject | 🟡 Extend Existing | P2 | `[~]` |
| Pass | 🟡 Extend Existing | P1 | `[x]` |
| Rework | ✅ Already Available | P1 | `[x]` |
| Quality Certificates | 🆕 New Module Required | P2 | `[ ]` |
### Domain 17: Packing (5 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Roll Packing | 🆕 New Module Required | P2 | `[ ]` |
| Bundle Packing | 🆕 New Module Required | P2 | `[ ]` |
| Bale Packing | 🆕 New Module Required | P2 | `[ ]` |
| Labels | 🆕 New Module Required | P2 | `[ ]` |
| Barcode Labels | 🟡 Extend Existing | P2 | `[~]` |
| QR Labels | 🟡 Extend Existing | P2 | `[~]` |
| Packing Material | 🟡 Extend Existing | P2 | `[~]` |
| Weight | 🟡 Extend Existing | P2 | `[~]` |
### Domain 18: Dispatch (7 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Dispatch Planning | 🟡 Extend Existing | P2 | `[~]` |
| Delivery Challan | ✅ Already Available | P1 | `[x]` |
| Truck | 🟡 Extend Existing | P2 | `[~]` |
| Container | 🆕 New Module Required | P2 | `[ ]` |
| Driver | 🆕 New Module Required | P2 | `[ ]` |
| Vehicle | 🟡 Extend Existing | P2 | `[~]` |
| LR Number | 🆕 New Module Required | P2 | `[ ]` |
| E-Way Bill | 🆕 New Module Required | P3 | `[ ]` |
| Freight | 🔵 Modify Existing | P2 | `[~]` |
| POD | ✅ Already Available | P1 | `[x]` |
| Dispatch Tracking | 🆕 New Module Required | P3 | `[ ]` |
### Domain 19: Transport (5 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Own Vehicles | 🔵 Modify Existing | P2 | `[~]` |
| Transport Vendors | 🟡 Extend Existing | P2 | `[~]` |
| Drivers | 🟡 Extend Existing | P2 | `[~]` |
| Routes | 🆕 New Module Required | P2 | `[ ]` |
| Fuel | 🆕 New Module Required | P3 | `[ ]` |
| Freight Cost | 🔵 Modify Existing | P2 | `[~]` |
| Vehicle Maintenance | 🆕 New Module Required | P3 | `[ ]` |
### Domain 20: Maintenance (6 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Preventive Maintenance | 🆕 New Module Required | P2 | `[ ]` |
| Breakdown | 🆕 New Module Required | P2 | `[ ]` |
| Service Schedule | 🆕 New Module Required | P2 | `[ ]` |
| Spare Parts | 🟡 Extend Existing | P2 | `[~]` |
| Machine History | 🟡 Extend Existing | P2 | `[~]` |
| Downtime | 🟡 Extend Existing | P2 | `[~]` |
| Maintenance Cost | 🔵 Modify Existing | P2 | `[~]` |
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
| Cost Per Meter | 🔵 Modify Existing | P2 | `[~]` |
| Cost Per Roll | 🔵 Modify Existing | P2 | `[~]` |
| Machine Cost | 🔵 Modify Existing | P2 | `[~]` |
| Power Cost | 🆕 New Module Required | P2 | `[ ]` |
| Chemical Cost | 🔵 Modify Existing | P2 | `[~]` |
| Labour Cost | 🔵 Modify Existing | P2 | `[~]` |
| Profitability | 🔵 Modify Existing | P2 | `[~]` |
### Domain 23: Reports (12 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| Production Reports | 🟡 Extend Existing | P2 | `[~]` |
| Loom Reports | 🟡 Extend Existing | P2 | `[~]` |
| Operator Reports | 🟡 Extend Existing | P2 | `[~]` |
| Yarn Consumption | 🟡 Extend Existing | P2 | `[~]` |
| Beam Reports | 🟡 Extend Existing | P2 | `[~]` |
| Grey Fabric Reports | 🟡 Extend Existing | P2 | `[~]` |
| Finished Fabric Reports | 🟡 Extend Existing | P2 | `[~]` |
| Dispatch Reports | 🟡 Extend Existing | P2 | `[~]` |
| Purchase Reports | 🟡 Extend Existing | P2 | `[~]` |
| Sales Reports | 🟡 Extend Existing | P2 | `[~]` |
| Stock Reports | 🟡 Extend Existing | P2 | `[~]` |
| Profit Reports | 🔵 Modify Existing | P2 | `[~]` |
| Machine Efficiency | 🟡 Extend Existing | P2 | `[~]` |
| Waste Analysis | 🟡 Extend Existing | P2 | `[~]` |
| Power Consumption | 🆕 New Module Required | P3 | `[ ]` |
| Daily MIS | 🟡 Extend Existing | P2 | `[~]` |
| Monthly MIS | 🟡 Extend Existing | P2 | `[~]` |
| Annual MIS | 🟡 Extend Existing | P3 | `[~]` |
### Domain 24: Dashboards (9 features)

| Task | Classification | Priority | Status |
|---|---|---:|---|
| CEO Dashboard | 🟡 Extend Existing | P2 | `[~]` |
| Factory Dashboard | 🟡 Extend Existing | P2 | `[~]` |
| Production Dashboard | ✅ Already Available | P1 | `[x]` |
| Purchase Dashboard | 🟡 Extend Existing | P2 | `[~]` |
| Inventory Dashboard | 🟡 Extend Existing | P2 | `[~]` |
| Sales Dashboard | 🟡 Extend Existing | P2 | `[~]` |
| Finance Dashboard | 🟡 Extend Existing | P2 | `[~]` |
| Maintenance Dashboard | 🟡 Extend Existing | P2 | `[~]` |
| HR Dashboard | 🟡 Extend Existing | P2 | `[~]` |
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
