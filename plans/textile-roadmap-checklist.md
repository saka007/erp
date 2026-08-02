# Textile implementation roadmap checklist

Use this checklist to track progress. A task is only marked complete when the implementation and verification are done.

## Foundation
- [ ] Harden tenant-aware module activation and permission boundaries
- [ ] Add shared audit and numbering service
- [ ] Establish reusable inventory movement primitives

## TextileCore
- [ ] Create textile specification master
- [ ] Create textile quality profile master
- [ ] Create textile route/recipe master
- [ ] Create textile unit/conversion support

## TextileInventory
- [ ] Create textile lot and roll tracking
- [ ] Add location/status handling
- [ ] Implement receipt, issue, transfer, and movement history
- [ ] Add reservation and availability logic

## TextileProcurement
- [ ] Add purchase requisition flow
- [ ] Add purchase order flow
- [ ] Add GRN and incoming QC flow

## TextileSales
- [ ] Add sales order flow
- [ ] Add allocation and dispatch flow
- [ ] Add challan and POD support

## TextileManufacturing
- [ ] Add beam and production batch support
- [ ] Add weaving/grey output flow
- [ ] Add waste and rework handling

## Quality and costing
- [ ] Add inspection and hold/release workflow
- [ ] Add job-work and processing flow
- [ ] Add costing and margin tracking

## Reporting and rollout
- [ ] Add textile dashboards and reports
- [ ] Add API support for operational workflows
- [ ] Validate multi-tenant behavior end to end
