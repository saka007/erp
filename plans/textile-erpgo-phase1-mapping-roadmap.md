# Textile FDS to ERPGo Phase 1 Mapping and Implementation Roadmap

## 1. Purpose, authority, and scope

This document is the second-stage, no-code analysis comparing the completed textile operating requirements in [`plans/textile-business-fds.md`](plans/textile-business-fds.md:1) with the supplied ERPGo Phase 1 findings. It classifies each material textile workflow or capability as **REUSE**, **EXTEND**, **MODIFY**, or **NEW MODULE**, then defines a package-native implementation roadmap.

The classification describes the target treatment, not the amount of work:

- **REUSE** — retain the existing capability and contract; add only configuration, permission assignment, or textile references outside its core behavior.
- **EXTEND** — retain the existing capability as the system of record, adding textile attributes, links, workflow states, dimensions, events, or UI surfaces.
- **MODIFY** — change an existing shared behavior or source of truth because the current behavior is unsafe or semantically insufficient; preserve backward compatibility through projections, adapters, or controlled migration.
- **NEW MODULE** — introduce a new Workdo package/domain because no existing capability can represent the required textile identity, control, or workflow without corrupting an existing model.

This is a planning artifact only. It does not prescribe source edits, database implementation syntax, or screen-level code. The existing application architecture observed in the Phase 1 findings remains authoritative: Laravel, Inertia, Workdo packages, dynamic package discovery, SaaS module activation, shared-table tenancy, permissions, events, package-local pages, menus, buttons, fields, settings, and the existing Account, DoubleEntry, Hrm, ProductService, and Quotation capabilities.

## 2. Executive direction

ERPGo is a viable foundation for textile manufacturing, trading, weaving, processing, job work, and composite operations only if its basic aggregate inventory and unsafe posting paths are not treated as the textile source of truth. Existing shared masters and finance/HR capabilities should be retained. Textile physical truth should be implemented as append-only movement, lot, roll, genealogy, quality, and WIP domains with aggregate stock and financial balances treated as controlled projections.

The implementation order is therefore:

1. Harden tenancy, authorization, posting, locking, idempotency, numbering, audit, and reversal behavior.
2. Establish textile shared masters and an immutable inventory subledger.
3. Add controlled procurement, sales-order, receipt, inspection, packing, and dispatch flows.
4. Add weaving and beam execution.
5. Add processing, recipes, job work, quality, and claims.
6. Integrate actual costing and dimensional accounting.
7. Add maintenance, shop-floor APIs, analytics, and scaled rollout.

No production rollout should depend on the current aggregate-only stock mutation, current-price purchase-price COGS, hard-coded account codes, or inconsistent report tenant filters.

## 3. Baseline architecture to preserve

### 3.1 Package and activation model

Textile functionality should be delivered as independently discoverable Workdo packages. Package registration remains centralized in [`app/Providers/PackageServiceProvider.php`](app/Providers/PackageServiceProvider.php:1); package activation and ordering remain governed by [`app/Classes/Module.php`](app/Classes/Module.php:1); subscription access remains enforced through [`app/Http/Middleware/PlanModuleCheck.php`](app/Http/Middleware/PlanModuleCheck.php:1). Textile packages must not replace root routing, root Inertia composition, or the SaaS plan model.

Each package should provide its own migrations, models, requests, policies or authorization services, controllers, routes, Inertia pages, menu declarations, permission declarations, settings, seeders, print views, API routes where needed, and event listeners. Package-local UI must use the existing dynamic discovery mechanism for pages, menus, buttons, fields, and settings. Module activation must be checked at route, service, listener, and scheduled-job boundaries so a disabled package cannot mutate another domain.

### 3.2 Tenancy and actor convention

Every new transactional and master table must carry `created_by` as the company/tenant identifier and `creator_id` as the creating actor, matching the existing shared-table convention. This convention is represented in existing package migrations and models such as [`packages/workdo/Contract/src/Database/Migrations/2025_09_25_033514_create_contracts_table.php`](packages/workdo/Contract/src/Database/Migrations/2025_09_25_033514_create_contracts_table.php:1) and [`packages/workdo/Contract/src/Models/Contract.php`](packages/workdo/Contract/src/Models/Contract.php:1).

Because there is no universal tenant global scope or consistent policy layer, every query, route-bound lookup, relationship traversal, posting service, report join, event listener, API endpoint, export, print action, and background job must explicitly carry and validate the tenant context. `created_by` is necessary but is not sufficient by itself: branch, site, warehouse, location, ownership, and operational dimensions must also be validated where relevant.

### 3.3 Existing capability boundaries

- **ProductService** remains the commercial item identity. Textile packages attach profiles, specifications, units, and genealogy references; they must not create an unrelated duplicate product master.
- **Account** and **DoubleEntry** remain the financial system of record for chart of accounts, journals, AR/AP, taxes, payments, and financial reporting after hardening and extension.
- **Hrm** remains the employee, branch, attendance, shift, and payroll foundation. Textile packages add skills, machine qualification, accepted-output links, and piece-rate or incentive attributes.
- **Quotation** is the preferred canonical commercial proposal base because it already supports revision and duplication. Root `SalesProposal` and the Quotation domain must be rationalized before textile sales-order work begins.
- Existing aggregate warehouse stock remains available as a compatibility projection only after its mutation paths are redirected to the textile movement service or a safe shared stock service.

## 4. Capability classification matrix

The matrix covers every major workflow and capability represented by the FDS chapters, cross-functional controls, operating variants, and Phase 1 findings. A classification is followed by the target package boundary and the reason it is not treated as a simpler category.

| FDS workflow/capability | Decision | Target boundary | Why |
|---|---|---|---|
| Users, clients, vendors, staff, parties, and party roles | **REUSE** | Core ERPGo users/party model | Existing party and role records already follow SaaS ownership conventions. Textile roles such as principal, job worker, broker, transporter, and processor are role assignments or extensions, not a second party master. |
| Roles, permissions, approval segregation, and print permissions | **EXTEND** | Shared permissions plus textile packages | Retain manage, manage-any, manage-own, view, create, edit, delete, and print permissions. Add textile approve, release, issue, receive, inspect, reserve, post, reverse, adjust, close, and override permissions. |
| SaaS plans, package activation, dynamic pages, menus, buttons, fields, and settings | **REUSE** | Workdo framework | The existing dynamic package architecture is the required delivery mechanism. Textile packages must be activated and subscription-gated using existing framework behavior. |
| Product/item commercial identity | **EXTEND** | ProductService plus TextileCore | ProductService is reusable as the item identity. Add textile family, material state, composition, count/denier, construction, width, GSM, shade, package type, technical attributes, and effective-dated specification profiles. |
| Textile quality, design, colourway, shade, recipe, route, and unit masters | **NEW MODULE** | TextileCore | These governed technical masters do not exist as a coherent domain. Names alone are unsafe; effective dating, approval, duplicate control, and historical snapshots are required. |
| Units and contextual conversions | **EXTEND** | ProductService plus TextileCore conversion service | Existing item units can remain, but textile requires kg, cone, bag, beam, metre, yard, roll, piece, bundle, bale, dual quantities, precision, effective dates, tolerance, and process-specific conversion rules. |
| Warehouses and basic locations | **EXTEND** | Warehouse capability plus TextileCore/Inventory location model | Keep existing warehouses, adding branch/site, store type, zone, rack/bin, quarantine, WIP, process-house, job-worker, dispatch, rejected, scrap, and in-transit classifications. |
| Aggregate warehouse stock | **MODIFY** | Shared stock projection fed by TextileInventory | The current product/warehouse quantity is insufficient and unsafe. Preserve it only as a locked, reconciled projection. Eliminate unlocked increments, silent missing-row behavior, negative stock, duplicate rows, and destructive balance mutation. |
| Immutable stock movement ledger | **NEW MODULE** | TextileInventory | No existing ledger records every receipt, issue, transfer, reservation, status change, adjustment, output, loss, reversal, ownership, custody, lot, and dual quantity. Append-only movements become physical truth. |
| Lots, ownership, custody, statuses, reservations, and cycle counts | **NEW MODULE** | TextileInventory | Aggregate stock cannot separate own, customer-owned, consignment, WIP, quarantine, rejected, rework, seconds, packed, dispatched, and job-work-out stock. |
| Yarn lots, cones, packages, weights, and supplier-lot performance | **NEW MODULE** | TextileInventory | Lot identity, cone/package count, gross/tare/net weight, count, blend, shade, manufacturer lot, issue, return, moisture concern, and lot genealogy are absent. |
| Beams and warp genealogy | **NEW MODULE** | TextileManufacturing with TextileInventory integration | Beam identity must link yarn lots, ends, length, creel, sizing recipe, residual yarn, loom loading, and downstream grey rolls. Existing stock cannot represent this transformation. |
| Fabric rolls, taka/thaan/palla, piece identity, and barcode/QR | **NEW MODULE** | TextileInventory | Roll-level identity requires measured and declared length, weight, width, GSM, defects, grade, owner, quality status, location, splits, joins, and durable genealogy. |
| Enquiry, sample development, lab dip, strike-off, and customer approval | **EXTEND** | Quotation plus TextileSales, TextileCore, and TextileQuality | Quotation can retain commercial revision and duplication. Textile-specific samples, approvals, technical snapshots, and quality evidence must be linked without turning a quote into production execution. |
| Quotation and SalesProposal domains | **MODIFY** | Quotation canonical domain; compatibility adapter for SalesProposal | The domains substantially overlap. Select Quotation as canonical, map or freeze duplicate SalesProposal behavior, preserve legacy references, and prevent two independent proposal-to-invoice pipelines. |
| Accepted quotation/order acknowledgement | **NEW MODULE** | TextileSales | Textile execution requires a committed sales order with tolerances, schedules, specifications, shade control, reservations, amendments, and closure; current accepted quotation conversion to draft invoice is insufficient. |
| Sales order programs and production allocations | **NEW MODULE** | TextileSales and TextileManufacturing | Order-wise split by quality, design, shade, delivery, route, owner, and planned quantity requires a controlled execution commitment. |
| Customer credit, pricing, tax, payments, returns, and claims handoff | **EXTEND** | Existing sales invoice, Account, DoubleEntry, TextileSales/Quality | Reuse existing commercial and financial features, but add source links, snapshots, transactional posting, tenant checks, dimensional references, idempotency, and textile return/claim evidence. |
| Purchase requisition, indent, purchase order, schedules, and rate contracts | **NEW MODULE** | TextileProcurement | Invoice-first procurement cannot drive material requirements, approvals, expected deliveries, technical specifications, or pending-order controls. |
| Gate entry, weighbridge, receipt, GRN, quarantine, and incoming QC | **NEW MODULE** | TextileProcurement and TextileQuality | Textile receipts need independent package arithmetic, gross/tare/net weight, supplier and mill lots, quality release, rejection, and payable matching before posting. |
| Purchase invoice matching and supplier returns | **EXTEND** | Existing PurchaseInvoice plus TextileProcurement/Inventory/Quality | Preserve tax, payment, and accounting behavior, but require GRN, accepted quantity, lot/quality status, source references, tenant authorization, atomic posting, and idempotent journals. |
| Demand, material, capacity, and production planning | **NEW MODULE** | TextileManufacturing | No existing ERPGo capability represents route-specific material requirements, reservations, vessel/loom constraints, capacity calendars, beam plans, or frozen-plan overrides. |
| BOM, routing, recipe, standard loss, and output versions | **NEW MODULE** | TextileCore and TextileManufacturing/Processing | Textile routes and losses are versioned, stage-specific, and approval-controlled; they cannot be safely encoded as generic product quantities. |
| Warping, winding, doubling, twisting, sizing, and beam preparation | **NEW MODULE** | TextileManufacturing | Requires yarn issue/return, ends, recipes, beam cards, size consumption, residuals, waste, and quality gates. |
| Loom master, allocation, beam loading, and qualification | **NEW MODULE** | TextileManufacturing plus Hrm | HR branches do not model loom number, type, width, RPM, capacity, status, beam assignment, operator skill, or maintenance state. |
| Shift-wise weaving production and grey roll generation | **NEW MODULE** | TextileManufacturing and TextileInventory | Counter readings, picks/metres, accepted output, defects, breakages, downtime, operator, waste, and unique roll genealogy are absent. |
| Grey receipt, inspection, four-point/grade rules, mending, and release | **NEW MODULE** | TextileQuality and TextileInventory | Existing product stock has no piece-level measurement, declared-versus-inspected quantity, defect map, grade, hold, ownership, or release control. |
| Internal transfers and stage handoffs | **MODIFY** | TextileInventory movement service | Existing transfer balance mutation is race-prone, clamps source to zero, adds destination quantity, and deletes by backward mutation. Replace with locked append-only transfer and compensating reversal while retaining a familiar transfer UI where useful. |
| Internal and external job work | **NEW MODULE** | TextileProcessing plus TextileInventory/Quality/Procurement | Job work needs ownership/custody separation, outward and inward challans, acknowledgement, process route, loss, residuals, rejected/reprocessed output, charges, claims, and closure. |
| Dyeing, printing, finishing, recipes, batches, and process parameters | **NEW MODULE** | TextileProcessing and TextileCore | No existing capability represents shade standards, lab-to-bulk recipes, chemical batches, machine loading, parameters, actual consumption, process loss, and reprocessing. |
| Laboratory, shade standards, retained samples, and colour kitchen | **NEW MODULE** | TextileQuality and TextileProcessing | Independent shade and performance evidence needs versioned standards, approval, calibration, recipe weighing, and bulk correlation. |
| Shared inspection, QC, holds, nonconformance, concessions, rework, and claims | **NEW MODULE** | TextileQuality | A reusable textile QC domain must serve incoming, weaving, grey, process, final, supplier, job-worker, and customer quality events. |
| Finished inspection, packing, bundles/bales, labels, and package genealogy | **NEW MODULE** | TextileQuality, TextileInventory, TextileSales | Released rolls become customer-specific packs and bales with net/gross weight, piece list, shade grouping, labels, and ancestry. |
| Dispatch, challan, gate pass, transporter, vehicle, LR/GR, e-way references, and POD | **EXTEND** | TextileSales plus parties and existing invoice | Existing parties and invoices are reusable, but dispatch execution, allocation, package picking, movement evidence, transport, POD, and invoice/challan separation require a textile extension. |
| Sales and purchase returns | **EXTEND** | Existing returns plus TextileInventory/Quality | Retain commercial return and tax behavior, but extend it with source roll/lot/package identity, quarantine, inspection, disposition, append-only reversal movements, atomic posting, and idempotency. |
| Accounting, AR/AP, tax, banking, and payments | **EXTEND** | Account and DoubleEntry | The financial foundation is reusable, but extend and harden posting so it is atomic, tenant-authorized, idempotent, lock-safe, and dimension-aware. Replace fixed account codes with tenant posting profiles. |
| Financial and operational journal source references | **MODIFY** | DoubleEntry integration boundary | Existing indexed references are not sufficient for tenant-aware idempotency. Enforce unique source type/reference/action per tenant and preserve operational source links. |
| Account balances and opening balances | **MODIFY** | DoubleEntry | Unlocked stored balances and non-idempotent updates are unsafe. Use locked projections or calculate from immutable journal lines, with controlled rebuild and reconciliation. |
| COGS and inventory valuation | **MODIFY** | TextileCosting integrated with DoubleEntry | Current purchase-price COGS cannot represent actual traceable cost, WIP, conversion, process loss, job work, waste recovery, or cost history. TextileCosting becomes the operational subledger and posts configured valuation results to GL. |
| Branch, warehouse, cost center, machine, batch, beam, roll, and order dimensions | **NEW MODULE** | TextileCore/Costing plus DoubleEntry dimension integration | Branch is currently only an HR organizational master, and warehouse identity is only a description in transfer accounting. Operational and financial dimensions must be first-class references. |
| Textile costing, WIP, yield, variance, and order margin | **NEW MODULE** | TextileCosting | Product purchase price is not a textile cost model. Costing must accumulate material, conversion, chemicals, job work, labor, power, overhead, waste recovery, and variance by order/stage/beam/batch/roll. |
| Employees, branches, attendance, shifts, and payroll | **REUSE** | Hrm | Retain existing HR identity, branches, attendance, shifts, and payroll. Textile packages consume these records through validated references. |
| Operator skills, machine qualification, piece rates, and quality-linked earnings | **EXTEND** | Hrm plus TextileManufacturing | Add effective-dated skills, machine authorization, accepted-output links, shift/loom assignments, and production incentive inputs without duplicating employees. |
| Maintenance, breakdowns, preventive work, spares, meters, calibration, and production impact | **NEW MODULE** | TextileMaintenance | Textile assets and downtime control are not covered by the supplied reusable foundation. Maintenance must integrate with loom, process machine, utility, quality, and production records. |
| Utilities, environment, safety, and statutory operational registers | **NEW MODULE** | Future TextileCompliance package integrated with TextileMaintenance/Quality | The FDS requires energy, water, effluent, safety, permits, incidents, and compliance evidence. These are outside the confirmed ERPGo capabilities and require a dedicated package, staged behind explicit scope approval rather than hidden in production tables. |
| Audit trail, reversal reasons, approvals, corrections, and outbox | **NEW MODULE** | Shared audit/control service, consumed by all textile packages | No universal audit trail exists. Posted documents, movements, QC decisions, genealogy changes, approvals, reversals, and event delivery require durable evidence. |
| Numbering and document sequences | **MODIFY** | Shared numbering service | Existing last-number-plus-one generation is race-prone. Add tenant/document-series scope, serialized allocation, database uniqueness, gap policy, and legacy reference preservation. |
| APIs for receiving, issuing, QC, scanning, loom entry, and dispatch | **EXTEND** | Textile package-local API routes | Follow the package-local Laravel API pattern and existing activation/permission conventions. Add idempotency, device/user authorization, tenant context, rescan handling, and intermittent-connectivity semantics. |
| Dashboards, reports, exports, print layouts, and alerts | **EXTEND** | TextileAnalytics and package-local read services | Reuse Inertia controllers, permission gates, JSON filters, and print conventions. Add textile read models and reconcile every KPI to source documents and movements. |

## 5. Canonical commercial and master-data decisions

### 5.1 Commercial domain decision

Quotation is the canonical pre-order commercial domain. TextileSales should extend Quotation with technical specification snapshots, sample approvals, validity, rate basis, tolerance, delivery schedule, customer approval, and order conversion. Root `SalesProposal` should be placed behind a compatibility boundary: existing records remain readable, but new textile proposals must not create a second independent lifecycle. A migration map should relate each legacy proposal to a canonical quotation or explicitly classify it as historical-only.

The canonical lifecycle is:

`enquiry → sample/development → quotation revision → customer approval → sales order → production/allocation → packing/dispatch → invoice → collection/claim/closure`.

An invoice is a settlement document, not a substitute for a sales order, production commitment, delivery schedule, or physical allocation.

### 5.2 Shared master boundaries

The following are shared ERPGo identities with textile-owned extensions:

- parties, users, employees, branches, and roles remain shared;
- ProductService item IDs remain shared commercial identities;
- warehouses remain compatible shared locations, with textile location hierarchy and status extensions;
- accounts, taxes, currencies, payments, and journals remain Account/DoubleEntry records;
- textile specifications, technical qualities, shade standards, recipes, routes, machines, lots, rolls, beams, and inspection definitions belong to textile packages.

No package may copy a party, product, employee, warehouse, or account master merely to avoid a cross-package lookup. Cross-package records must use tenant-validated foreign references and stable source identifiers.

## 6. Target Workdo package/domain architecture

| Package | Owns | Consumes | Publishes or reacts to |
|---|---|---|---|
| TextileCore | Textile profiles, specifications, UOM conversions, colours/shades, constructions, route standards, defect and grade catalogs, machine classes, settings | ProductService, warehouses, branches, users | `TextileSpecificationApproved`, `TextileMasterChanged`, `TextileConversionApproved` |
| TextileInventory | Append-only movements, lots, cones, beams, rolls, locations, reservations, statuses, ownership/custody, stock projections, genealogy, counts, adjustments, barcodes | TextileCore, ProductService, Warehouse, sales orders, production, processing, QC | `StockMovementRecorded`, `StockReserved`, `StockReleased`, `GenealogyAdvanced`, `StockReconciled` |
| TextileProcurement | Requisitions, POs, schedules, GRNs, incoming receipts, supplier claims, invoice-match evidence | ProductService, TextileCore, parties, TextileQuality, TextileInventory, PurchaseInvoice | `PurchaseOrderReleased`, `GoodsReceived`, `IncomingQualityReleased`, `SupplierClaimRaised` |
| TextileSales | Canonical quotation extensions, orders, amendments, schedules, allocations, packing, dispatch, challans, POD, customer claims | Quotation, parties, ProductService, TextileCore, Inventory, Quality, existing invoices | `SalesOrderConfirmed`, `AllocationCreated`, `DispatchReleased`, `DeliveryConfirmed`, `CustomerClaimRaised` |
| TextileManufacturing | Demand planning, BOM/routes, plans, warping, sizing, beams, loom allocation, shift production, grey output, waste, downtime | TextileCore, Inventory, Hrm, Maintenance, SalesOrder, Quality, Costing | `ProductionOrderReleased`, `BeamPrepared`, `LoomRunCompleted`, `GreyRollCreated`, `ProductionClosed` |
| TextileProcessing | Internal/subcontract process orders, outward/inward challans, batches, recipes, chemical usage, losses, charges, reprocessing | Inventory, TextileCore, Quality, parties, Procurement, Sales | `ProcessOutwardIssued`, `ProcessBatchStarted`, `ProcessBatchCompleted`, `JobWorkReconciled`, `ReprocessAuthorized` |
| TextileQuality | Inspection plans/results, samples, defects, grades, shade tests, holds/releases, NCRs, concessions, rework, certificates, claims | TextileCore, Inventory, Procurement, Manufacturing, Processing, Sales | `QualityHoldPlaced`, `QualityReleased`, `NonconformanceRaised`, `ConcessionApproved`, `ClaimDispositioned` |
| TextileCosting | Operational cost ledger, WIP, roll cost, posting profiles, variances, waste recovery, dimensions, order margin | Inventory, Manufacturing, Processing, Quality, Sales, Account, DoubleEntry, Hrm | `CostAccumulated`, `WipValuated`, `VarianceClosed`, `FinancialPostingRequested` |
| TextileMaintenance | Machines/assets, plans, breakdowns, work orders, spares, readings, calibration, downtime | Hrm, ProductService, Inventory, Manufacturing, Processing, Quality | `AssetBlocked`, `MaintenanceCompleted`, `DowntimeRecorded`, `CalibrationExpired` |
| TextileAnalytics | Read models, KPI definitions, dashboards, exports, scheduled summaries, alerts, print layouts | Events and source read models from every textile package and Account/DoubleEntry | Report refresh jobs and escalation notifications; it must not become a transactional source of truth. |
| Shared audit/control boundary | Audit entries, approval evidence, outbox, idempotency records, reversal links, numbering | All transactional packages and Account/DoubleEntry | Durable audit and event-delivery state |

### 6.1 Event integration rules

Events are integration contracts, not a substitute for atomic domain state. A publisher must write its business state, movement/journal request, audit record, idempotency record, and outbox entry in one transaction. A listener must verify the tenant, source type, source ID, event version, activation status, and idempotency key before applying effects.

Examples of required flows:

1. `SalesOrderConfirmed` causes reservation requests in TextileInventory and material/capacity demand in TextileManufacturing.
2. `GoodsReceived` creates controlled receipt movements, starts incoming QC, and holds payable matching until release or approved conditional acceptance.
3. `IncomingQualityReleased` changes stock status and makes accepted lots available to planning; rejection creates quarantine/rejection movements and a supplier claim reference.
4. `ProductionOrderReleased` reserves eligible yarn/grey, creates planned genealogy, and publishes requirements to procurement for shortages.
5. `BeamPrepared` records consumption and output genealogy, makes the beam available only after quality release, and updates TextileCosting.
6. `GreyRollCreated` creates a roll movement and inspection requirement; `QualityReleased` determines sale/process availability.
7. `ProcessOutwardIssued` transfers custody without changing ownership; `ProcessBatchCompleted` records output, loss, residuals, charges, and inspection requirements.
8. `DispatchReleased` requires released, allocated, owned or authorized customer stock; it creates packing/dispatch movements and hands the invoice source reference to the existing invoice domain.
9. `CostAccumulated` and `WipValuated` request configured DoubleEntry postings with operational dimensions and unique source references.
10. `QualityHoldPlaced`, `AssetBlocked`, and overdue custody events create alerts but cannot silently mutate commercial or financial truth.

## 7. Cross-cutting hardening prerequisites

### 7.1 Tenancy and authorization

Before textile transactions are enabled, define a shared tenant-context checklist and apply it to list queries, route binding, nested relations, posting, reversal, reports, exports, APIs, events, queued jobs, and scheduled summaries. Posting must verify both permission and `created_by`; route-model authorization must reject foreign-tenant records before any state or stock mutation. Every child reference must be checked against the same tenant, including product, party, warehouse, account, order, lot, stock row, and source document.

### 7.2 Atomic posting and idempotency

Posting must lock the source document and all affected movement/cost rows, validate lifecycle state and available status, perform movement and journal effects, write audit/outbox records, and transition the source state in one database transaction. A tenant-aware idempotency key and unique source/action constraint must prevent repeated effects from retries, duplicate clicks, synchronous listener re-entry, queued delivery, and device resubmission.

### 7.3 Inventory safety

The detailed movement ledger must be append-only. Transfers, returns, corrections, status changes, and reversals create new compensating records; they do not delete or mutate history. Available quantity must be computed by item, location, lot/roll, owner, status, and UOM. Negative stock is rejected except through an explicitly permissioned adjustment workflow with reason, evidence, approval, and audit. A database uniqueness rule must prevent duplicate aggregate projection rows within the required tenant/item/location dimensions.

### 7.4 Financial safety

DoubleEntry must receive configurable tenant posting profiles rather than fixed account codes. Journal lines must carry or reference branch, warehouse, cost center, order, process batch, machine/loom, beam, roll, and operational source dimensions. Stored account balances must be locked projections rebuilt from journal lines, or calculated from immutable posted lines. Financial reports must constrain every joined table and detail lookup by tenant ownership.

### 7.5 Numbering and audit

Shared numbering must allocate sequences under serialization and enforce tenant/document-series uniqueness. Gaps, retries, cancelled numbers, and legacy references require a documented policy. The audit/control boundary must record who, tenant, timestamp, action, source, previous state, new state, reason, approval, event key, and reversal link for every material transaction.

## 8. Phased implementation roadmap and dependencies

### Phase 0 — Decision gates and foundation hardening

**Outputs:** canonical Quotation decision; tenant authorization standard; atomic posting contract; idempotency and numbering design; audit/outbox contract; stock and journal safety policy; dimensional accounting decision.

**Dependencies:** none; this phase is a prerequisite for all physical or financial textile transactions.

**Validation gate:** cross-tenant route and posting tests fail closed; concurrent invoice, transfer, return, journal, and number-generation tests produce one effect; no destructive reversal path remains; report detail queries are tenant-isolated.

### Phase 1 — TextileCore and TextileInventory

**Outputs:** textile item profiles, specifications, UOM conversions, lots, cones, locations, statuses, reservations, movements, stock projections, rolls, genealogy, opening balance controls, barcode/QR identity, and cycle-count workflow.

**Dependencies:** Phase 0, ProductService, Warehouse, shared permissions, module activation, and tenant conventions.

**Validation gate:** detailed movement totals reconcile to projections and legacy opening balances; negative stock is blocked; dual quantities and ownership/custody are preserved; a yarn lot can produce a beam and roll genealogy skeleton.

### Phase 2 — Procurement and sales control

**Outputs:** requisitions, POs, schedules, GRNs, incoming QC, purchase matching, canonical sales orders, amendments, delivery schedules, allocations, packing, challans, dispatch, POD, and invoice handoff.

**Dependencies:** TextileCore, TextileInventory, TextileQuality baseline, Quotation consolidation, hardened PurchaseInvoice/SalesInvoice, parties, Account/DoubleEntry.

**Validation gate:** supplier-to-GRN-to-QC-to-stock-to-invoice and order-to-allocation-to-pack-to-dispatch-to-invoice reconciliations balance by quantity, status, ownership, and tenant.

### Phase 3 — Weaving manufacturing

**Outputs:** demand and capacity plans, BOM/routes, warping, sizing, beams, loom allocation, shift production, roll creation, defects, waste, downtime, and production closure.

**Dependencies:** Phase 2 orders and material availability, TextileCore, TextileInventory, Hrm, and initial Quality.

**Validation gate:** every accepted grey roll traces to a loom run, beam, yarn lots, operators, shift, input/return/waste, and approved quality result; plan-versus-actual and mass/length balance are reproducible.

### Phase 4 — Processing, job work, and quality

**Outputs:** lab dips, shade standards, recipes, process routes, internal/subcontract batches, outward/inward challans, loss and residual reconciliation, reprocessing, finished inspection, claims, concessions, and certificates.

**Dependencies:** Phase 3 roll identity and quality, TextileInventory genealogy, TextileCore recipes/routes, parties, and procurement charge matching.

**Validation gate:** customer-owned material remains unvalued but quantitatively reconciled; every process lot lists inputs and outputs; loss is approved only after genealogy and quantity reconciliation; job-worker bills match accepted output and contract basis.

### Phase 5 — Costing and finance integration

**Outputs:** operational cost ledger, WIP valuation, actual roll cost, posting profiles, cost centers/dimensions, waste recovery, variance analysis, order margin, COGS handoff, and GL reconciliation.

**Dependencies:** stable movement/genealogy, production/process closure, accepted quality states, hardened DoubleEntry, and approved account mappings.

**Validation gate:** WIP plus inventory plus recognized variance reconciles to GL; COGS follows configured traceable cost flow rather than current purchase price; journals are unique, balanced, tenant-scoped, and dimensioned.

### Phase 6 — Maintenance, APIs, analytics, and scale

**Outputs:** asset and maintenance package, shop-floor/mobile APIs, barcode workflows, dashboards, read models, exports, alerts, performance indexes, retention/archive policy, and operational support tooling.

**Dependencies:** stable source events and transactional identifiers from Phases 1–5; Hrm for people and authorization; agreed device and connectivity requirements.

**Validation gate:** realistic shift-volume, rescan, retry, intermittent-connectivity, and multi-year report tests pass without duplicate movements or material tenant leakage.

## 9. Migration strategy

### 9.1 Preparation

1. Inventory all tenants, users, roles, permissions, active packages, branches, warehouses, products, parties, UOMs, accounts, invoices, returns, transfers, and existing stock rows.
2. Clean tenant ownership, duplicate products, warehouse names, UOM labels, party roles, and chart-of-account mappings.
3. Select the canonical Quotation domain and map existing SalesProposal references without deleting history.
4. Define product profile mappings that preserve ProductService item IDs.
5. Establish opening-date, cut-off, valuation, ownership, quality, and unresolved-balance policies per pilot tenant.

### 9.2 Stock and genealogy conversion

Each opening aggregate balance must become controlled opening movement lines by tenant, branch/site, warehouse/location, item, lot or roll, owner, status, quantity, dual quantity, UOM, and value. Traceable yarn, beam, grey, and finished stock must be physically enumerated. Unresolved stock may use a clearly marked controlled migration lot, but it cannot be treated as fully traceable until cleared.

Legacy aggregate stock remains available only as a comparison projection during reconciliation. Legacy invoice, transfer, and return references must be retained in source-reference fields. No historical movement should be fabricated as if it were an actual shop-floor event; imported opening movements must be identified as migration-origin records.

### 9.3 Cutover

Freeze legacy mutations during final extraction. Load masters, then opening movements, then reservations and open orders, then open procurement/job-work custody, then opening financial mappings. Reconcile detailed stock to legacy aggregate stock and inventory valuation to GL before enabling live posting. Retain a read-only legacy reference path and a signed cutover reconciliation pack.

## 10. Validation and acceptance criteria

### Security and tenancy

- Every query, export, print route, API endpoint, event listener, queued job, and route-bound action is tenant-isolated.
- Child references cannot cross tenants, branches, warehouses, owners, or unauthorized operational dimensions.
- Permission tests distinguish manage-any, manage-own, view, approve, post, reverse, and adjust.

### Transaction integrity

- Repeated, concurrent, retried, and partially failed posting creates exactly one business effect.
- Source documents, movements, journals, projections, audit records, and outbox records commit or roll back together.
- Reversals preserve history and restore stock and accounting exactly.
- Numbering remains unique under concurrency and tenant/document-series scope.

### Inventory and genealogy

- Stock cannot become negative except through authorized adjustment policy.
- Detailed stock equals aggregate projections by item, location, status, owner, and UOM.
- Movement records are immutable and corrections are compensating movements.
- A finished roll traces backward through process batch, grey production, loom/beam, and yarn lots, and forward through packing, dispatch, returns, and claims.
- Length, weight, pieces, packages, and converted quantities retain source values and conversion evidence.

### Quality and operations

- Quarantine, released, reserved, WIP, rejected, rework, seconds, packed, dispatched, and returned statuses control availability.
- Quality holds prevent unauthorized issue, process, packing, or dispatch.
- Mass balance and stage yield explain good output, recoverable output, approved loss, samples, cuts, and unresolved variance.
- Job-work custody reconciles sent, acknowledged, returned, accepted, rejected, residual, waste, authorized loss, and outstanding quantities.

### Finance and reporting

- WIP, inventory, variance, waste recovery, and recognized cost reconcile to GL.
- Posting profiles are tenant-configurable and do not rely on fixed account codes.
- Reports reconcile to source documents and are tenant-filtered at every join and detail lookup.
- Read models refresh idempotently and remain performant at expected multi-year volume.

### Usability and resilience

- Shop-floor entry supports shift volume, duplicate submissions, barcode rescans, intermittent connectivity, and permission-aware offline retry handling.
- Print documents preserve commercial specification snapshots and source references.
- Operational users can explain every exception, hold, adjustment, reversal, and claim from durable evidence.

## 11. Risks, mitigations, and decision gates

| Risk | Consequence | Required mitigation / gate |
|---|---|---|
| Partial posting before status update | Stock/journal/document divergence | Phase 0 atomic transaction, locks, idempotency, rollback tests. |
| Foreign-tenant route-bound posting | Cross-tenant financial and stock mutation | Tenant-scoped binding and explicit authorization before state changes. |
| Aggregate stock retained as source of truth | Lost lot, roll, owner, status, and genealogy truth | TextileInventory ledger must be authoritative; aggregate stock is reconciled projection only. |
| Duplicate stock rows or race-prone mutation | Phantom or negative stock | Unique constraints, row locks, availability service, and concurrency tests. |
| Destructive transfer deletion | Unexplainable historical balances | Append-only compensating movement and reversal workflow. |
| Non-idempotent journals/balances | Duplicate financial effects | Tenant-aware source uniqueness, locked balance projections or rebuildable balances. |
| Current purchase-price COGS | Misstated margin and inventory | TextileCosting subledger and approved valuation policy before financial go-live. |
| Fixed account codes | Inflexible or wrong tenant postings | Configurable posting profiles and account mapping approval. |
| Missing branch/warehouse/cost dimensions | Unusable operational and financial analysis | First-class dimension references and dimension reconciliation. |
| SalesProposal/Quotation duplication | Split commercial truth | Quotation canonicalization decision before TextileSales. |
| No universal audit trail | Weak control evidence and dispute resolution | Shared audit/outbox/control boundary before live textile transactions. |
| Race-prone numbering | Duplicate legal/commercial document numbers | Serialized tenant/document-series allocator and database uniqueness. |
| Inconsistent tenant report filtering | Data leakage and incorrect summaries | Query review, ownership validation, tenant-aware joins, security tests. |
| Overly broad textile package | Coupling and upgrade risk | Cohesive package boundaries and event contracts. |
| Unresolved physical opening stock | False genealogy and valuation confidence | Controlled migration lots, physical enumeration, signed reconciliation. |
| Shop-floor connectivity or adoption failure | Duplicate or missing production events | Idempotent APIs, rescan handling, pilot operation, and supervised parallel run. |

Decision gates are mandatory at the end of Phase 0, after inventory reconciliation, before production, before subcontract processing, before GL costing, and before each site rollout. A failed gate leaves the affected legacy path read-only or limited to controlled transactions; it does not permit an operational workaround that bypasses the ledger or audit trail.

## 12. Assumptions and exclusions

### Assumptions

- The supplied ERPGo Phase 1 findings are the authoritative baseline for current architecture, capabilities, and risks.
- Workdo packages can expose package-local migrations, routes, permissions, Inertia pages, APIs, settings, menus, buttons, and event listeners through the existing discovery model.
- Shared-table tenancy remains the deployment model; a universal tenant scope is not assumed.
- ProductService, Account, DoubleEntry, Hrm, and Quotation remain supported shared foundations.
- Textile businesses may operate trading-only, decentralized weaving, processing/job-work, dyeing/printing/finishing, or composite models; package activation and configuration select applicable workflows.
- Tax treatment, e-way applicability, valuation method, statutory job-work records, and labour compliance require tenant and professional approval.

### Exclusions from this no-code roadmap

- Source code, migrations, controllers, services, UI implementation, API implementation, or configuration changes.
- A decision to implement garment manufacturing beyond fabric operations.
- Replacement of the Laravel/Inertia/Workdo architecture.
- A universal replacement for HR, accounting, parties, ProductService, or Quotation.
- Automatic tax-law conclusions or accounting-policy selection without finance and tax approval.
- Full MES, IoT, machine telemetry, laboratory instrumentation integration, or statutory environmental compliance automation unless separately approved in Phase 6 scope.

## 13. Rollout sequence

1. Select one pilot tenant, one site, one warehouse, one customer-order flow, and one representative yarn-to-roll-to-dispatch flow.
2. Complete Phase 0 hardening and the Quotation/SalesProposal decision without enabling uncontrolled textile posting.
3. Load and reconcile TextileCore and TextileInventory opening data; operate physical stock and legacy aggregate projection in parallel.
4. Enable procurement, GRN, incoming QC, sales orders, allocation, packing, dispatch, and invoice handoff for the pilot.
5. Add weaving with one loom group or contracted weaving flow; validate beam, shift, grey roll, quality, waste, and genealogy.
6. Add one processing route and one job-worker route; validate custody, challan, recipe, loss, reprocess, charge, and claim closure.
7. Enable TextileCosting and financial posting profiles only after operational reconciliations pass.
8. Add maintenance, APIs, dashboards, exports, alerts, and performance hardening.
9. Expand by product family, process route, department, warehouse, branch/site, and tenant using the same gate checklist.
10. Retire legacy mutation paths only after signed reconciliation, user acceptance, support readiness, and rollback evidence.

## 14. Final target state

The target is a package-native textile extension in which ERPGo shared masters, SaaS activation, permissions, events, dynamic UI, Account, DoubleEntry, Hrm, ProductService, and canonical Quotation continue to serve their existing purposes. TextileCore governs technical meaning; TextileInventory records physical truth; Procurement and Sales control obligations; Manufacturing and Processing record transformations; Quality controls release; Costing explains economic truth; Maintenance protects capacity; Analytics reports from reconciled read models; and the shared audit/control boundary preserves accountability.

The essential architectural rule is that commercial and financial documents may trigger textile operations, but neither invoice rows nor aggregate warehouse quantities may stand in for lot identity, roll genealogy, ownership/custody, quality status, stage yield, WIP, or actual textile cost.
