# Textile SaaS Governance Matrix (Entitlement + Self-Serve Activation)

## Objective
Define a scalable control model where:
- Superadmin controls entitlement, billing boundaries, and sensitive-risk gates.
- Company can self-activate eligible modules as they grow.
- Operational mapping supports multi-select business models over time.

## Core Principle
Use a hybrid governance model:
- Central control for what a tenant is allowed to use.
- Tenant self-service for what they choose to turn on now.

## Roles and Responsibilities

| Area | Superadmin | Company Owner/Admin | Staff |
|---|---|---|---|
| Plan purchase and renewal | Final control | View | No |
| Entitlement assignment (allowed modules/features) | Final control | View | No |
| Self-activation of entitled modules | Optional override / lock | Yes | No |
| Operating model mapping (single/multi-select) | Can edit any tenant | Yes (own tenant) | No |
| Finance posting controls | Final control for high-risk | Request/operate if allowed | No |
| Export/compliance gates | Final control for policy | Operate within policy | No |
| Daily operational data entry | No | Yes | Yes (permission-based) |
| Deactivation with active dependency checks | Policy owner | Request/execute if safe | No |
| Audit and logs | Full visibility | Own tenant visibility | No |

## Module Lifecycle States
Each module for each tenant should have one state:
- `not_entitled`: tenant cannot see/enable module.
- `entitled_disabled`: tenant may enable when ready.
- `active`: module enabled and usable.
- `suspended`: temporarily blocked (billing/compliance/abuse).
- `retired`: no new activity, read-only historical access.

## Business Profile Mapping (Multi-select)
A tenant may choose one or more profile tags:
- `full_package_buyer`
- `job_work_weaving`
- `processing_only`
- `trader_distributor_bulk`
- `export_compliance`

### Mapping rules
- Store as multi-select with effective dates, not a single mutable string.
- Keep history of profile transitions.
- Recompute capabilities from selected profiles + active modules.
- Enforce profile gates in service layer (already started in sales flow).

## Decision Framework: What is Tenant Self-Serve vs Superadmin-Only

### Tenant self-serve (recommended)
- Enable/disable standard operational modules already entitled by plan.
- Update operating model mappings for own tenant.
- Configure low-risk masters and workflows.

### Superadmin-only (recommended)
- Grant/revoke module entitlement.
- Unlock restricted/high-risk modules (finance posting, export document gates, payroll, legal/compliance-critical flows).
- Override locks due to billing/compliance events.

### Optional approval mode
For specific modules, support `requires_superadmin_approval = true`:
- Tenant can request activation.
- Superadmin approves/rejects with comment.
- Decision logged in audit trail.

## Safety Guardrails
- Deactivation dependency checks (cannot disable if open docs/transactions exist unless moved to retired mode).
- Effective-dated change policy for mapping updates.
- No hard deletes for configuration that affects historical transactions.
- Tenant boundary checks on all read/write operations.
- Full audit events:
  - who changed
  - what changed
  - old value/new value
  - reason/comment
  - timestamp

## Suggested Permission Keys
- `manage-company-modules` (company owner/admin self-activation)
- `manage-operating-model` (company mapping changes)
- `approve-module-requests` (superadmin)
- `manage-module-entitlements` (superadmin)
- `manage-compliance-gates` (superadmin or delegated compliance admin)

## Suggested Data Model (Minimal)

### `tenant_module_entitlements`
- `id`
- `tenant_id`
- `module_key`
- `is_entitled` (bool)
- `requires_approval` (bool)
- `set_by`
- `set_at`

### `tenant_module_activations`
- `id`
- `tenant_id`
- `module_key`
- `state` (`entitled_disabled|active|suspended|retired`)
- `effective_from`
- `effective_to` (nullable)
- `changed_by`
- `change_reason` (nullable)

### `tenant_operating_profiles`
- `id`
- `tenant_id`
- `profile_key`
- `is_active`
- `effective_from`
- `effective_to` (nullable)
- `changed_by`
- `change_reason` (nullable)

### `tenant_governance_audits`
- `id`
- `tenant_id`
- `action`
- `entity_type`
- `entity_key`
- `old_payload` (json)
- `new_payload` (json)
- `changed_by`
- `changed_at`

## UX Recommendation
- Company UI page: `Master Setup -> Operating Model`
  - Multi-select profile chips/checklist.
  - Real-time impact summary: enabled capabilities and blocked flows.
- Company UI page: `Settings -> Modules`
  - Show entitled modules and activation state.
  - Show lock badge for superadmin-restricted modules.
- Superadmin UI page:
  - Entitlement matrix by tenant.
  - Activation request queue.
  - Compliance and billing suspension controls.

## Rollout Plan (Incremental)
1. Keep current textile operating-policy page and add multi-select profile storage.
2. Add tenant module activation page for company owner/admin.
3. Add superadmin entitlement + approval panel.
4. Add dependency-safe deactivation checks.
5. Add governance audit logging and reports.

## Recommendation Summary
Do not bind everything to superadmin.
Adopt boundary-based governance:
- Superadmin controls entitlement and sensitive risk gates.
- Company self-serves activation and mapping inside entitlement.
This gives growth flexibility to small businesses while preserving platform safety and monetization control.
