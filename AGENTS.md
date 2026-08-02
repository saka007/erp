# AGENTS.md

This repository is a Laravel + Inertia + Workdo package-based SaaS application. The current priority is to evolve it into a reusable multi-tenant platform with industry modules while preserving the existing core architecture.

## Project goals
- Preserve the existing platform and reuse its strong foundation.
- Add industry capabilities as package-based modules instead of rewriting the core app.
- Keep the design reusable so the same architecture can support multiple verticals such as textile, diamond, or basic inventory.
- Protect tenant isolation, auditability, approvals, and safe posting.

## Current implementation focus
The textile roadmap should be followed first as the initial vertical, while keeping the architecture generic enough for future industries.

Priority modules:
- TextileCore
- TextileInventory
- TextileProcurement
- TextileSales
- TextileManufacturing

## Working rules for future agents
- Read this file first before starting work.
- Read the roadmap in plans/textile-erpgo-phase1-mapping-roadmap.md before making changes.
- Prefer package-based extension under packages/workdo rather than injecting large amounts of logic into the core app.
- Reuse existing shared capabilities such as ProductService, Quotation, Account, DoubleEntry, and Hrm instead of duplicating business masters.
- Keep finance downstream of operational textile truth where appropriate.
- Respect tenant/company scoping on all reads and writes.
- Follow the roadmap in small vertical slices rather than trying to implement everything at once.
- Frontend textile pages must follow shared UX format: reuse shared components under resources/js/components/textile and keep standard page flow as KPI cards -> forms -> list/tables.
- Use Inventory and Procurement textile pages as baseline reference for layout and behavior consistency when implementing new workflow screens.
- Menu and submenu are mandatory for every delivered slice: no feature is complete until its sidebar placement, submenu link, and route target are added and verifiable in UI.
- Type/unit/machine/source-style fields must not be free-text in workflow forms when they represent controlled values; deliver and wire domain master CRUD (under textile setup) and consume them via select controls.

## Resume guidance
1. Review the roadmap and identify the next unchecked task.
2. Implement one slice end-to-end before moving to the next.
3. Verify the change with the relevant command or test before claiming completion.
4. Update the roadmap checkbox only after the work is actually completed and verified.

## Roadmap checkbox rule
- Use [ ] for pending work.
- Use [x] only after the task is implemented and verified.
- Do not mark a checkbox done just because work was started, planned, or discussed.
- When marking a task complete, add a note with the verification evidence if possible.
