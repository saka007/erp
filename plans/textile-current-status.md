# Textile ERP Task Tracker

This is the single source of truth for Textile ERP delivery. It replaces the previous Textile FDS, architecture roadmap, and checklist, and clearly separates backend implementation from admin-portal usability.

Last updated: 2026-08-02

## How to read this tracker

| Status | Meaning |
|---|---|
| `[x]` | Fully implemented, visible where required, and verified. |
| `[~]` | Backend foundation exists and is tested, but the required admin UI is not complete. |
| `[ ]` | Not implemented. |

An item cannot be considered a complete user-facing feature until its backend, authorization, admin UI, and focused verification are complete.

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

## TextileInventory

| Task | Backend | Admin UI | Status | Current evidence / gap |
|---|---:|---:|---|---|
| Lots create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile inventory admin test. |
| Lots roll drilldown/edit/deactivate | Yes | Yes | `[x]` | Lot drilldown page plus lot status update/archive controls implemented and verified in Textile inventory admin test. |
| Location master create/list (tenant scoped) | Yes | Yes | `[x]` | Implemented and verified in Textile inventory admin test. |
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
| GRN and incoming QC workflow | Yes | Yes | `[x]` | Procurement screen now supports GRN create/release and incoming QC create/finalize and is verified in Textile procurement admin test. |

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

## Verification evidence

- Textile backend suite: `./vendor/bin/phpunit tests/Feature/Textile --testdox --colors=never`
- Last recorded result: `OK (23 tests, 75 assertions)`.
- Industry assignment: `./vendor/bin/phpunit tests/Feature/Textile/TextileIndustryAssignmentTest.php --testdox --colors=never` passed: `OK (1 test, 5 assertions)`.
- Textile specification admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileSpecificationAdminTest.php --colors=never` passed: `OK (1 test, 11 assertions)`.
- Textile master-data admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileMasterDataAdminTest.php --colors=never` passed: `OK (1 test, 25 assertions)`.
- Textile inventory admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileInventoryAdminTest.php --testdox --colors=never` passed: `OK (1 test, 31 assertions)`.
- Textile procurement admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileProcurementAdminTest.php --colors=never` passed: `OK (1 test, 25 assertions)`.
- Textile sales admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileSalesAdminTest.php --colors=never` passed: `OK (1 test, 27 assertions)`.
- Textile manufacturing admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileManufacturingAdminTest.php --colors=never` passed: `OK (1 test, 25 assertions)`.
- Textile quality admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileQualityAdminTest.php --colors=never` passed: `OK (1 test, 18 assertions)`.
- Textile processing admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileProcessingAdminTest.php --colors=never` passed: `OK (1 test, 25 assertions)`.
- Textile costing admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileCostingAdminTest.php --colors=never` passed: `OK (1 test, 14 assertions)`.
- Textile dashboard admin: `./vendor/bin/phpunit tests/Feature/Textile/TextileDashboardAdminTest.php --colors=never` passed: `OK (1 test, 10 assertions)`.
- Users-page frontend build: `npm run build` passed. The existing Vite chunk-size warning remains non-blocking.

## Delivery order from here

1. Continue deeper costing integrations (WIP stage costing and finance handoff details).
2. Expand dashboard/report coverage and KPI breakdowns.

## Tracker maintenance

- Update this file after each implemented and verified slice.
- Mark a task `[x]` only when its backend, authorization, required admin UI, and focused validation are complete.
- Keep a task `[~]` when only backend work exists.
