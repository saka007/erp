# Textile ERP Requirements Traceability Matrix

## Executive Summary

### Classification Counts
| Classification | Count | % |
|---|---|---|
| ✅ Already Available (Reuse) | 72 | 35% |
| 🟡 Extend Existing | 58 | 28% |
| 🔵 Modify Existing | 42 | 20% |
| 🆕 New Module Required | 28 | 13% |
| ❌ Not Required | 4 | 2% |
| **Total Features** | **204** | **100%** |

### Top P1 Items (Critical Path)
1. **Approval Workflow Engine** (Core ERP) - Design + implement generic approval rules framework; currently handled per-module via status fields
2. **Mobile Production Entry Suite** (Weaving Production) - Barcode/QR scanners + shift entry + real-time sync
3. **Finance WIP Costing Integration** (Finance) - Link TextileCosting to DoubleEntry; cost center allocation and periodic accrual
4. **Quality Defect Library** (Quality) - Master defect taxonomy + inspection scoring framework
5. **Advanced Packing Workflows** (Packing) - Roll/Bundle/Bale hierarchy + label generation integration
6. **Preventive Maintenance Module** (Maintenance) - Scheduled maintenance + spare parts procurement link

### Architectural Risks
| Risk | Severity | Mitigation |
|---|---|---|
| Workflow document type proliferation | HIGH | Use single TextileWorkflowDocument table with polymorphic type handling; ensure type registry stays maintainable |
| Finance integration complexity | HIGH | Create clear handoff interfaces between TextileCosting and DoubleEntry; define cost object dimensionality upfront |
| Mobile offline sync | MEDIUM | Queue-based sync + conflict resolution; test with poor connectivity scenarios |
| Barcode/QR hardware integration | MEDIUM | Abstract hardware layer; use webhook-based printer APIs; plan for multiple scanner brands |
| Multi-location movement consistency | MEDIUM | Implement transaction-level locks on lot reservations; audit every movement |

### Recommended Wave Plan
**Wave 1 (P1 - 8 weeks)**: Core textile + procurement + sales + manufacturing complete (foundation ready per current status ✅)
**Wave 2 (P2 - 10 weeks)**: Quality + Processing + Costing + Finance integration; Mobile entry (barcode + QC); Defect library
**Wave 3 (P3 - 12 weeks)**: Maintenance + Transport + Advanced Packing; Integrations (WhatsApp, GST, E-Way Bill); Report dashboards

---

## Comprehensive Traceability Matrix

### Domain 1: Core ERP (16 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Dashboard | 🟡 Extend Existing | HomeController (web.php) + TextileDashboard | TextileDashboard (complete) + Generic Dashboard Framework | P1 | User roles, permissions | Schema change (dashboard_widgets table) | New GET /dashboard/{type} endpoints | New dashboard layout selector; textile-first routing implemented ✅ | Widget rendering performance at scale | Textile dashboard redirects implemented; verify non-textile dashboards not suppressed |
| User Management | ✅ Already Available | User model, UserController | None (reuse) | P1 | None | None | None | Existing CRUD implemented | None | Users page has industry_type selector; Textile enablement via UserController |
| Roles & Permissions | ✅ Already Available | Spatie Permission + RoleController | None (reuse) | P1 | None | None | None | Existing role UI functional | None | PlanModuleCheck middleware filters module access; permission system verified |
| Multi Company | ✅ Already Available | created_by + created_by.company concept | None (reuse) | P1 | None | None | None | Company scoping via request context | None | Verified via TextileTenantIsolationTest; all textile tables scoped by created_by |
| Multi Branch | 🟡 Extend Existing | Hrm.Branch model exists | Leverage Hrm.Branch + extend textile routes | P2 | Hrm module | Schema change (textile_lot_branches table) | Add branch parameter to textile APIs | Add branch dropdown to lot/location masters | Multi-level warehouse hierarchy complexity | Branch concept exists in HR; tie to warehouse locations |
| Multi Warehouse | ✅ Already Available | Warehouse model (app/Models) + TextileLocation | Reuse + extend location | P1 | None | None | None | Warehouse dropdown in textile forms | None | Verified in textile-current-status.md; movement tracking per location |
| Multi Currency | ✅ Already Available | Setting model + currency config | Reuse via Setting model | P2 | Setting model | None | None | Currency selector in forms (inherit from core) | None | Core ERP supports; textile costing should apply conversion factor |
| Multi Language | ✅ Already Available | i18n patterns + lang translations | Reuse existing i18n | P1 | None | None | None | Language selector (existing) | None | All textile UI pages use t() function; resource file structure ready |
| SaaS Subscription | ✅ Already Available | Plan model + subscription logic | Reuse Plan model | P1 | None | None | None | Plan assignment page exists | None | Textile enablement via industry assignment; verified in tests |
| Activity Logs | 🔵 Modify Existing | TextileAuditLog (textile-specific) + LoginHistory | Enhance TextileAuditLog + generic AuditLog mixin | P1 | None | Schema change (audit_trails table for non-textile) | New GET /audit-logs API | Audit log viewer page | Audit trail storage volume at scale | Textile has custom audit; generalize for core ERP |
| Audit Logs | 🔵 Modify Existing | TextileAuditLog (textile-only) | Create generic AuditLog + inherit in modules | P1 | None | Schema change (global audit_logs table) | New GET /audit-logs/export | Audit viewer with filters (date, user, action) | Query performance on large audit tables | Document what events trigger audit entries per domain |
| Notifications | 🟡 Extend Existing | Notification model (app/Models) | TextileNotification queue handler | P2 | Queue + notification templates | Schema change (notification_subscriptions table) | New POST /notifications/subscribe | Notification center UI + preferences | Real-time notification delivery at volume | Integrate with textile workflow transitions (approval events) |
| Email | ✅ Already Available | EmailTemplate + mail config | Reuse EmailTemplate + textile-specific templates | P2 | EmailTemplate model | Schema change (textile_email_queue table for audit) | POST /email/send (envelope endpoint) | Email template editor (existing) | Email deliverability in multi-tenant context | Define textile email events (PO confirmation, dispatch, QC hold) |
| File Attachments | ✅ Already Available | MediaDirectory + Media models | Reuse existing media system | P2 | MediaDirectory, Media models | None | GET /media/{id}; POST /media/upload | File upload form components (existing) | File storage quota per tenant | Textile documents (specs, QC reports, receipts) stored as Media |
| Approval Workflow | 🆕 New Module Required | Draft in TextileWorkflowDocument.status field | TextileApprovalEngine + generic ApprovalWorkflow | P1 | Spatie Permission, notification system | Schema change (approval_workflows, approval_rules tables) | POST /approval-rules/{type}/{action}; GET /approvals/pending | Approval dashboard + rule builder | Complex conditional logic @ scale; audit trail every decision | Create approval rule engine tied to document types (requisition, PO, SO); support delegation |
| Custom Fields | 🆕 New Module Required | None identified | FormBuilder extension + CustomField module | P2 | FormBuilder package | Schema change (custom_fields, custom_field_values tables) | POST /custom-fields/{entity_type} | Custom field form builder + display logic | Validation logic complexity; performance impact on queries | Textile specifications already have json parameters; generalize this pattern |
| Tags | 🆕 New Module Required | Label model exists (Lead package only) | Generic Tag module + TaggableInterface | P2 | None | Schema change (tags, taggables tables) | POST /tags; GET /tags/{entity_type} | Tag selector + tag cloud UI | Tag search performance; tag hierarchy | Use for lot categorization, supplier grouping, document classification |
| Comments | 🟡 Extend Existing | ContractComment (Contract package) | Generic Comment system + enable on textile documents | P2 | None | Schema change (comments table) | POST /comments; GET /{entity_type}/{id}/comments | Comment thread UI (reply, edit, delete) | Threading depth performance | Link to TextileWorkflowDocument; support @mentions + approval tags |

---

### Domain 2: CRM (10 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Leads | ✅ Already Available | Lead model + LeadController | Reuse Lead package | P1 | None | None | None | Lead board UI exists | None | Kanban pipeline view; support textile industry assignment |
| Customers | ✅ Already Available | Customer model (Account package) + User model | Reuse Account.Customer + Link to textile quotations | P1 | Account package | None | None | Customer list + detail view exists | None | Textile customers created via Account module; extend with credit limits |
| Contacts | 🟡 Extend Existing | ContactController exists (SupportTicket package) | Extend to CRM contacts + link to customers | P2 | None | Schema change (contacts table with customer_id FK) | POST /customers/{customer_id}/contacts | Contact list per customer; add/edit/delete flows | Contact deduplication across systems | Add contact roles (primary, billing, technical, quality) |
| Customer Categories | 🆕 New Module Required | None identified | Create CustomerCategory model + taxonomy | P2 | None | Schema change (customer_categories table) | POST /customer-categories | Category dropdown in customer forms | Category maintenance overhead | Use for segmentation (export market, domestic, retailer, distributor) |
| Follow Ups | 🟡 Extend Existing | LeadTask (Lead package) for lead follow-ups | Extend to Customer follow-ups + scheduling | P2 | Calendar integration | Schema change (customer_followups table) | POST /customers/{id}/followups | Follow-up scheduler widget | Notification delivery reliability | Link to CRM activities + SMS/email reminders |
| Quotations | ✅ Already Available | SalesQuotation (Quotation package) | Reuse Quotation + link to textile sales orders | P1 | ProductService, Quotation packages | None | None | Quotation form + list view exist | None | Convert quotation to SO workflow verified in tests |
| Sales Orders | 🟡 Extend Existing | TextileSalesOrder (TextileCore) + embedded in workflow | Decouple into TextileSalesOrderModel + link to SalesInvoice | P1 | TextileSales, TextileInventory | Schema change (textile_sales_orders table) | GET /textile/sales/orders/{id}; POST approve | Sales order form (UI exists) | SO amendment workflow not covered | Current implementation uses TextileWorkflowDocument; consider dedicated SO model |
| Customer Price List | 🆕 New Module Required | WarehouseStock has pricing; no customer-specific pricing | CustomerPriceList model + tier-based pricing | P2 | ProductService, Quotation | Schema change (customer_price_lists, price_list_items tables) | POST /customer-price-lists; GET /products/{id}/price-for-customer | Price list form + customer selector | Price recalculation at volume | Textile fabrics have grade-based pricing; implement per-lot pricing |
| Credit Limits | 🔵 Modify Existing | Customer model (Account) lacks credit_limit | Add credit_limit, credit_used fields to Customer | P2 | Account package, sales validation | Schema change (add columns to customers table) | GET /customers/{id}/credit-info | Credit indicator in quotation/SO forms | Credit check performance in high-volume sales | Integrate with AR; trigger hold if exceeded |
| Customer Documents | 🟡 Extend Existing | Media model + attachment pattern | Create DocumentLibrary + link to customers | P2 | None | Schema change (customer_documents table) | POST /customers/{id}/documents | Document upload + list per customer | Document access control per role | Store GST certificates, KYC docs, performance reports |

---

### Domain 3: Supplier Management (9 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Yarn Suppliers | 🟡 Extend Existing | Vendor model (Account package) | Extend Vendor + SupplierType=Yarn | P1 | Account package | Schema change (add supplier_type, product_categories to vendors) | GET /vendors?supplier_type=yarn | Vendor form with supplier type selector | Supplier polymorphism complexity | Differentiate supplier types in procurement workflow |
| Chemical Suppliers | 🟡 Extend Existing | Vendor model (Account package) | Extend Vendor + SupplierType=Chemical | P1 | Account package | Schema change (add to vendors) | GET /vendors?supplier_type=chemical | Supplier type selector in forms | Same as above | Link to sizing/dyeing processes |
| Spare Part Suppliers | 🟡 Extend Existing | Vendor model (Account package) | Extend Vendor + SupplierType=SparePartSupplier | P2 | Account package | Schema change (add to vendors) | GET /vendors?supplier_type=spare-parts | Supplier type filtering | Same as above | Link to maintenance module (future) |
| Processing Vendors | 🟡 Extend Existing | Vendor model (Account package) | Extend Vendor + SupplierType=ProcessingVendor | P2 | Account package | Schema change (add to vendors) | GET /vendors?supplier_type=processing | Vendor form with process capabilities (dyeing, printing, etc.) | Same as above | Link to job-work outward workflow ✅ implemented |
| Dyeing Vendors | 🟡 Extend Existing | Vendor model (Account package) | Extend Vendor + SupplierType=DyeingVendor + capabilities | P2 | Account package | Schema change (add process_types JSON to vendors) | GET /vendors?supplier_type=dyeing | Vendor selector with shade matching capability | Same as above | Critical for TextileProcessing workflows |
| Transport Vendors | 🔵 Modify Existing | Vendor model lacks transport-specific fields | Extend Vendor + TransportVendor specialization model | P2 | Account package | Schema change (create transport_vendors table with rates, routes, vehicle types) | GET /transport-vendors; POST /dispatch/vendor-assignment | Transport vendor selector in dispatch forms | Multi-leg journey complexity | Link to E-Way Bill integration |
| Vendor Rating | 🆕 New Module Required | None identified | VendorRating + RatingCriteria models | P2 | None | Schema change (vendor_ratings, rating_criteria tables) | POST /vendors/{id}/ratings | Rating form + vendor scorecard UI | Weighting criteria subjectivity | Track on-time, quality, price, reliability; use for vendor selection in RFQ |
| Vendor Performance | 🆕 New Module Required | None identified | VendorPerformance model + KPI tracking | P2 | None | Schema change (vendor_performance_kpis table) | GET /vendors/{id}/performance | Performance dashboard per vendor | Historical data aggregation complexity | Track GRN quality, delivery time, cost variance |
| Job Workers | 🔵 Modify Existing | Vendor model lacks job-worker specifics | Create JobWorker model + link to processing batch | P2 | None | Schema change (create job_workers table) | POST /job-workers; GET /processing-batches/{id}/assign-worker | Job worker assignment in processing workflow | Worker skill/capacity tracking | Current implementation uses vendor; specialize for job work tracking |

---

### Domain 4: Product Master (13 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Yarn | 🔵 Modify Existing | ProductServiceItem (ProductService) generically | Create TextileYarn model inheriting ProductServiceItem + yarn-specific attributes | P1 | ProductService package | Schema change (create textile_yarns table with denier, blend, grade) | GET /textile/yarns; POST /textile/yarns | Yarn master form with count/denier/blend fields | Product variant explosion | Yarn specifications already in TextileSpecification; link bi-directionally |
| Fabric | 🔵 Modify Existing | ProductServiceItem (ProductService) generically | Create TextileFabric model + grey/finished differentiation | P1 | ProductService package | Schema change (create textile_fabrics table) | GET /textile/fabrics?type=grey|finished | Fabric form with GSM/width/weave pattern | Fabric status lifecycle (grey → processing → finished) | Link to production batches for cost accumulation |
| Grey Fabric | 🔵 Modify Existing | TextileFabric status=grey (embedded) | Create TextileGreyFabric submodel | P1 | TextileFabric | Schema change (create textile_grey_fabrics table) | GET /textile/grey-fabrics | Grey fabric inventory view | Tracking across multiple warehouses + processing stages | Link to weaving output + processing inward |
| Finished Fabric | 🔵 Modify Existing | TextileFabric status=finished (embedded) | Create TextileFinishedFabric submodel | P1 | TextileFabric | Schema change (create textile_finished_fabrics table) | GET /textile/finished-fabrics | Finished fabric inventory view with ready-for-sale status | Same as above | Link to packing + dispatch workflows |
| Chemicals | 🆕 New Module Required | ProductServiceItem generic; no chemical-specific data | Create TextileChemical model + chemical properties (concentration, safety) | P2 | ProductService package | Schema change (create textile_chemicals table) | POST /textile/chemicals | Chemical master form with MSDS link | MSDS document management | Link to sizing/dyeing recipes; track batch/expiry |
| Packing Materials | 🆕 New Module Required | ProductServiceItem generic | Create TextilePackingMaterial model | P2 | ProductService package | Schema change (create textile_packing_materials table) | POST /textile/packing-materials | Packing material master form | Packing material BOM complexity | Cardboard, plastic, labels for roll/bundle packing |
| Spare Parts | 🔵 Modify Existing | ProductServiceItem generic | Create TextileSparePart model + machine_type + compatibility | P2 | ProductService, Maintenance (future) | Schema change (create textile_spare_parts table) | POST /textile/spare-parts | Spare part form with machine compatibility | Spare part cross-reference complexity | Link to maintenance schedules |
| Accessories | 🟡 Extend Existing | ProductServiceItem generic | Create TextileAccessory model or category | P2 | ProductService package | None (use category) | GET /textile/accessories | Accessory selection in product forms | Accessory pricing variant management | Threads, buttons, labels, trims |
| Product Variants | 🔵 Modify Existing | No variant support in ProductService | Create ProductVariant model + variant SKU mapping | P2 | ProductService package | Schema change (create product_variants table) | POST /products/{id}/variants | Variant selector in quotation/SO forms | Variant availability per warehouse | Yarn count variants, fabric width variants, color shades |
| Product Specifications | ✅ Already Available | TextileSpecification model (TextileCore) | Reuse TextileSpecification + link to fabric | P1 | None | None | None | Specification form exists ✅ | None | Denier, GSM, width, weave type, color; verified in tests |
| Product Images | 🔵 Modify Existing | Media system exists; no product-image linking | Create ProductImage model + pivot to ProductServiceItem | P2 | Media, ProductService | Schema change (create product_images table) | POST /products/{id}/images | Image uploader in product form | Image storage + CDN integration | Fabric swatch images, yarn color swatches |
| Product Documents | 🟡 Extend Existing | Media system + attachment pattern | Create ProductDocument model (spec sheets, test certs, COO) | P2 | Media, ProductService | Schema change (create product_documents table) | POST /products/{id}/documents | Document upload in product form | Document version control | Quality certificates, origin docs, technical datasheets |

---

### Domain 5: Yarn Management (14 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Yarn Type | 🔵 Modify Existing | ProductServiceCategory (generic) | Create TextileYarnType (twisted, ring spun, OE) | P1 | ProductService | Schema change (create textile_yarn_types table) | GET /textile/yarn-types | Yarn type dropdown in yarn master | Yarn type hierarchy (type → sub-type) | Clarify differentiation from blend |
| Yarn Count | 🔵 Modify Existing | TextileSpecification (generic params) | Create TextileYarnCount model (Ne, Nm, Tex, Denier standards) | P1 | ProductService | Schema change (create textile_yarn_counts table) | POST /textile/yarn-counts | Yarn count selector with unit conversion | Unit conversion complexity (Ne ↔ Nm ↔ Tex) | Use TextileUnitConversion for standardization |
| Denier | 🔵 Modify Existing | TextileSpecification param (embedded) | Extracted into dedicated field + conversion logic | P1 | TextileUnitConversion | None (use existing) | GET /textile/yarn-counts | Denier displayed in yarn master detail | None | Numeric with unit selector (gsm/mg) |
| Blend | 🔵 Modify Existing | TextileSpecification param (embedded) | Create TextileYarnBlend model with composition (e.g., 60% cotton, 40% polyester) | P1 | None | Schema change (create textile_yarn_blends table with material composition) | POST /textile/yarn-blends | Blend composition form | Blend variance tracking | Link to quality parameters (shrinkage, color fastness) |
| Shade | 🔵 Modify Existing | Not tracked explicitly; embedded in lot reference | Create TextileShade model + shade card library | P1 | None | Schema change (create textile_shades table with color hex/pantone) | POST /textile/shades | Shade selector + shade card uploader | Shade matching precision (lab vs perception) | Link to lot traceability + customer shade approval |
| Mill | 🔵 Modify Existing | Vendor model (yarn supplier) | Create TextileMill model linked to Yarn | P1 | None | Schema change (create textile_mills table) | POST /textile/mills | Mill selector in yarn master | Mill quality variation tracking | Track supplier mill origin for yarn (India, Vietnam, etc.) |
| Brand | 🟡 Extend Existing | No brand master | Create TextileBrand model (yarn brand, chemical brand) | P1 | None | Schema change (create textile_brands table) | POST /textile/brands | Brand selector in product masters | Brand loyalty tracking | Generic for all products |
| Lot Number | ✅ Already Available | TextileLot.lot_reference field | Reuse; format as SUPPLIER-DATE-SEQUENCE | P1 | None | None | None | Lot input in receipt/movement forms | None | Verified in TextileInventoryAdminTest ✅ |
| Cone Number | 🆕 New Module Required | Not implemented | Create TextileCone model with cone_number, weight, net_weight | P1 | None | Schema change (create textile_cones table) | POST /textile/cones | Cone tracking in yarn receipt/usage | Cone weight variance | Link to cone weight in yarn consumption calculations |
| Cone Weight | 🆕 New Module Required | Not tracked | Create cone_weight field in TextileCone | P1 | None | Schema change (add to textile_cones) | GET /textile/cones/{id} | Cone weight in yarn forms | Weight accuracy for costing | Nominal vs actual weight reconciliation |
| Net Weight | 🔵 Modify Existing | TextileLot has received_quantity; not weight per cone | Create net_weight field in TextileCone + received_quantity in TextileLot | P1 | None | Schema change (add net_weight to textile_cones) | GET /textile/cones/{cone_id} | Weight display in cone forms | Weight unit handling (kg vs lb) | Distinguish net (fiber) vs gross (cone packaging) |
| Gross Weight | 🔵 Modify Existing | Not tracked separately | Add gross_weight to TextileCone (net + cone tare) | P1 | None | Schema change (add gross_weight to textile_cones) | GET /textile/cones/{cone_id} | Gross weight display | Same as above | Calculate tare from cone material type |
| Moisture | 🟡 Extend Existing | Not tracked; may be in quality test results | Create TextileMoistureTest model + %RH + storage conditions | P2 | None | Schema change (create textile_moisture_tests table) | POST /textile/moisture-tests | Moisture entry in incoming QC | Moisture impact on weight/count accuracy | Critical for natural fiber (cotton); hygroscopic property |
| Quality Grade | ✅ Already Available | TextileQualityProfile model | Reuse; extend with ISO/industry grade standards (A/B/C/etc.) | P1 | None | Schema change (add standard_grade_mapping JSON to profiles) | GET /textile/quality-profiles | Grade selector in lot/product forms | Grade definition variance across customers | Link to accepted/rejected thresholds |
| Yarn Cost | 🔵 Modify Existing | Not explicitly tracked per yarn SKU | Create TextileYarnCost model (cost per unit, currency, effective_date) | P2 | None | Schema change (create textile_yarn_costs table) | POST /textile/yarn-costs | Cost input in yarn master | Cost volatility; historical cost tracking | Link to WIP costing calculation |
| Yarn Barcode | 🆕 New Module Required | Not tracked | Add barcode field to TextileYarn + barcode generation | P2 | None | Schema change (add barcode to textile_yarns) | POST /textile/yarns/{id}/generate-barcode | Barcode display + print in yarn master | Barcode collision risk | Integrate barcode printer API |
| Yarn QR Code | 🆕 New Module Required | Not tracked | Add qr_code field to TextileLot (per cone/lot) | P2 | None | Schema change (add qr_code to textile_lots) | POST /textile/lots/{id}/generate-qr | QR display in lot forms | QR uniqueness + mobile scanning UX | Mobile app scans QR for lot tracking |

---

### Domain 6: Purchase (8 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Purchase Requisition | ✅ Already Available | TextileProcurementApiController.storeRequisition + workflow | Reuse TextileWorkflowDocument type=requisition | P1 | None | None | None | Requisition form exists ✅ | None | Verified in TextileProcurementAdminTest; draft → approved flow ✅ |
| RFQ | 🟡 Extend Existing | Not explicitly modeled; PO is created directly from requisition | Create TextileRFQ model + vendor quote comparison | P2 | None | Schema change (create textile_rfqs, textile_rfq_items tables) | POST /textile/rfqs; GET /textile/rfqs/{id}/compare-quotes | RFQ form + quote comparison UI | Multi-vendor bidding workflow complexity | Optional step before PO; enable for high-value items |
| Purchase Order | ✅ Already Available | TextileProcurementApiController.storePurchaseOrder + workflow | Reuse TextileWorkflowDocument type=purchase_order | P1 | None | None | None | PO form exists ✅ | None | Verified in tests; approval required before goods receipt |
| Goods Receipt (GRN) | ✅ Already Available | TextileProcurementApiController.storeGrn + release flow | Reuse TextileWorkflowDocument type=grn | P1 | None | None | None | GRN form exists ✅ | None | Lifecycle: draft → approved → released (no direct skip) ✅ |
| Purchase Invoice | 🟡 Extend Existing | PurchaseInvoice (core app model) exists | Link Textile GRN to PurchaseInvoice; create integration layer | P1 | Account package, TextileProcurement | Schema change (add grn_id FK to purchase_invoices) | POST /purchase-invoices/from-grn/{grn_id} | Invoice creation from GRN form | Quantity mismatch handling (over-receipt, short delivery) | Three-way match (PO → GRN → Invoice); prevent invoice without GRN release |
| Purchase Return | ✅ Already Available | PurchaseReturn (core app model) exists | Link to GRN + TextileLot for return tracking | P1 | Account package | Schema change (add grn_id, textile_lot_id to purchase_returns) | POST /purchase-returns/from-grn | Return reason dropdown in forms | Return authorization workflow | Track reason: quality, excess, damage |
| Supplier QC | ✅ Already Available | TextileProcurementApiController.storeIncomingQc | Reuse TextileWorkflowDocument type=incoming_qc | P1 | None | None | None | QC form exists ✅ | None | Finalize triggers lot creation; verified in tests |
| Supplier Claims | 🆕 New Module Required | Not implemented | Create TextileSupplierClaim model (QC rejection reason, claim type, recovery) | P2 | TextileQuality, Account.Vendor | Schema change (create textile_supplier_claims table) | POST /textile/supplier-claims | Claim form + claim status tracking | Claim settlement workflow (approval, credit note generation) | Track claim history per supplier; integrate with vendor rating |

---

### Domain 7: Inventory (12 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Multi Warehouse | ✅ Already Available | Warehouse model + TextileLocation per warehouse | Reuse Warehouse + TextileLocation | P1 | None | None | None | None | None | Verified in tests ✅ |
| Rack | 🟡 Extend Existing | Not implemented; location concept exists | Create TextileRack model (warehouse → rack → bin hierarchy) | P2 | TextileLocation | Schema change (create textile_racks table) | POST /textile/racks | Rack master form + location hierarchy UI | Racking/bin overflow scenarios | Optional level between warehouse and bin |
| Bin | 🔵 Modify Existing | Not implemented; location is generic | Create TextileBin model (rack → bin level detail) | P2 | TextileRack | Schema change (create textile_bins table) | POST /textile/bins | Bin allocation in movement forms | Bin barcode/QR tracking integration | Track lot location at bin-level precision |
| Lot Tracking | ✅ Already Available | TextileLot model with lot_reference + status | Reuse TextileLot | P1 | None | None | None | Lot drilldown UI exists ✅ | None | Verified in tests; availability = received_qty - reserved_qty |
| Batch Tracking | 🟡 Extend Existing | Not explicitly separate from Lot; dyeing batch exists in processing | Create TextileBatch model for processing batches + traceability | P1 | TextileProcessing | Schema change (add to processing_batches) | GET /textile/processing-batches/{id}/lots | Batch composition viewer | Batch merging/splitting scenarios | Group lots through processing (dyeing batch = multiple lots) |
| Barcode | 🆕 New Module Required | Not tracked on lots | Add barcode field to TextileLot + barcode generation API | P2 | None | Schema change (add barcode to textile_lots) | POST /textile/lots/{id}/generate-barcode | Barcode print in lot forms | Barcode collision/duplication risk | Print via barcode printer; scan to identify lot |
| QR Code | 🆕 New Module Required | Not tracked on lots | Add qr_code field to TextileLot + QR generation | P2 | None | Schema change (add qr_code to textile_lots) | POST /textile/lots/{id}/generate-qr | QR display/print in lot forms | QR uniqueness + mobile scanner UX | Mobile app entry point for lot operations |
| Stock Transfer | 🟡 Extend Existing | TextileMovement.type=transfer exists | Enhance with approval workflow (for inter-warehouse transfers) | P1 | TextileMovement, Approval engine | Schema change (add approval_status to textile_movements) | GET /textile/movements?type=transfer | Transfer form + approval status | Transfer reconciliation at receiving location | Two-step: sender issues → receiver receives (confirmation) |
| Stock Adjustment | 🔵 Modify Existing | Not explicitly handled; cycle count would adjust | Create TextileStockAdjustment model (variance reason, manager approval) | P2 | None | Schema change (create textile_stock_adjustments table) | POST /textile/stock-adjustments | Adjustment form with reason + variance explanation | Adjustment audit trail (why variance occurred) | Track shrinkage, theft, evaporation (moisture loss) |
| Stock Reservation | ✅ Already Available | TextileReservation model | Reuse TextileReservation | P1 | None | None | None | Reservation form exists ✅ | None | Verified in tests; linked to allocation ✅ |
| Stock Freeze | 🆕 New Module Required | Not implemented | Create TextileLotFreeze model (hold for QC, legal, audit) | P2 | TextileQuality, Approval engine | Schema change (create textile_lot_freezes table) | POST /textile/lots/{id}/freeze | Freeze reason selector + manual unfreeze | Freeze expiry scheduling | Prevent movement/allocation of frozen lots |
| Cycle Count | 🆕 New Module Required | Not implemented | Create TextileCycleCount model + variance tracking | P2 | TextileLocation, TextileLot | Schema change (create textile_cycle_counts table) | POST /textile/cycle-counts; GET /cycle-counts/{id}/items | Cycle count form + variance grid | Recount scenarios for variances | Physical verification workflow with lot-by-lot counting |
| Physical Verification | 🟡 Extend Existing | Related to cycle count; not explicit | Enhance cycle count → physical verification report | P2 | TextileCycleCount | Schema change (add verification_status to cycle_counts) | GET /textile/cycle-counts/{id}/report | Verification PDF report + sign-off | Approval sign-off on variances | Annual inventory verification for audit/financial closing |

---

### Domain 8: Warping (5 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Warp Planning | 🆕 New Module Required | Not implemented | Create TextileWarpPlan model (fabric design → yarn requirements) | P2 | TextileFabric, TextileYarn, TextileInventory | Schema change (create textile_warp_plans table) | POST /textile/warp-plans | Warp plan form (fabric selection → yarn composition) | Warp plan complexity (multi-yarn, blend composition) | Yarn count, ply, twist per warp; link to sizing recipe |
| Yarn Allocation | 🟡 Extend Existing | Not explicitly; implied in warp sheet | Create WarpYarnAllocation model (warp plan → lot allocation) | P2 | TextileWarpPlan, TextileLot, Stock reservation | Schema change (create textile_warp_yarn_allocations table) | POST /textile/warp-plans/{id}/allocate-yarn | Yarn allocation form with available lots | Lot substitution (partial allocation + fallback) | Reserve yarn when warp plan created; link to reservations |
| Warp Sheet | 🆕 New Module Required | Not implemented | Create TextileWarpSheet model (production document) | P1 | TextileWarpPlan | Schema change (create textile_warp_sheets table) | POST /textile/warp-sheets; GET /warp-sheets/{id} | Warp sheet form + detail view | Warp sheet amendment/version control | Issued to production; tracks yarn usage |
| Warp Production | 🆕 New Module Required | Not implemented | Create TextileWarpProduction model (beam output from warping machine) | P1 | TextileWarpSheet | Schema change (create textile_warp_productions table) | POST /textile/warp-productions | Warp production entry form (beam#, length, waste) | Beam identity + genealogy tracking | Beam created after warping; link to sizing |
| Warp Cost | 🔵 Modify Existing | Not tracked explicitly | Create TextileWarpCost model (yarn cost + labor + overhead) | P2 | TextileWarpProduction, TextileYarnCost | Schema change (create textile_warp_costs table) | POST /textile/warp-costs | Cost capture form per warp batch | Cost allocation complexity (overhead per unit) | Track per beam for production costing |

---

### Domain 9: Sizing (5 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Sizing Recipe | 🟡 Extend Existing | TextileRouteRecipe exists (generic); not sizing-specific | Create TextileSizingRecipe specialization (size type, chemical formula, concentration) | P1 | TextileRouteRecipe | Schema change (create textile_sizing_recipes table) | POST /textile/sizing-recipes | Sizing recipe form with chemical composition | Recipe versioning + approval | Starch %, PVA %, anti-back coating %; create master per fabric type |
| Chemical Consumption | 🆕 New Module Required | Not tracked | Create TextileChemicalConsumption model (recipe → chemical qty per unit) | P1 | TextileSizingRecipe, TextileChemical | Schema change (create textile_chemical_consumptions table) | POST /textile/sizing-recipes/{id}/chemicals | Chemical grid in recipe form | Chemical unit conversion (% → kg/ton) | Track consumption to update inventory |
| Beam Creation | 🟡 Extend Existing | Sizing produces beams; not yet modeled | Create TextileSizedBeam model from TextileWarpProduction | P1 | TextileWarpProduction, TextileSizingRecipe | Schema change (add sizing_recipe_id to beams or create sized_beams table) | POST /textile/sized-beams | Sized beam detail view | Beam identity throughout production | Beam created after sizing; moves to loom |
| Beam Inspection | 🟡 Extend Existing | Not explicitly modeled | Create TextileBeamInspection model (sizing QC: counts, hairiness, size integrity) | P2 | TextileSizedBeam | Schema change (create textile_beam_inspections table) | POST /textile/sized-beams/{id}/inspect | Beam inspection form | Inspection sampling/approval | Pass/hold/reject before loom issue |
| Beam Cost | 🔵 Modify Existing | Not tracked explicitly | Create TextileBeamCost model (warp cost + sizing cost) | P2 | TextileBeamCreation, TextileSizingRecipe, TextileChemicalCost | Schema change (create textile_beam_costs table) | POST /textile/beam-costs | Cost capture per beam batch | Cost accumulation complexity | Sum warp + sizing + chemical costs |

---

### Domain 10: Beam Management (7 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Beam Master | ✅ Already Available | Embedded in TextileWorkflowDocument type=beam | Formalize as TextileBeam model | P1 | None | Schema change (create textile_beams table if not split from workflow docs) | GET /textile/beams; POST /textile/beams | Beam form exists ✅ | None | Verified in tests; model beam as distinct from SO/PO/GRN |
| Beam Number | ✅ Already Available | Beam identifier in workflow doc | Use beam_number field in TextileBeam | P1 | None | None | None | None | None | Format: YEAR-MILL-SEQUENCE (e.g., 26-001-0001) |
| Beam Status | ✅ Already Available | Embedded in TextileWorkflowDocument.status | Track status: created → approved → issued → loom_usage → return | P1 | None | None | None | Status indicator in beam list | None | Verified in tests ✅ |
| Beam Warehouse | ✅ Already Available | TextileLocation tracks location | Reuse TextileLocation | P1 | None | None | None | None | None | Beam stored at location (bin/rack) |
| Beam Issue | 🟡 Extend Existing | Not explicitly tracked | Create TextileBeamIssue movement (warehouse → loom) | P1 | TextileBeam, TextileMovement | Schema change (add beam_id to textile_movements) | POST /textile/beams/{id}/issue | Beam issue form with loom assignment | Beam tracking to loom lifecycle | Record which loom issued beam; links to production batch |
| Beam Return | 🟡 Extend Existing | Not explicitly tracked | Create TextileBeamReturn movement (loom → warehouse) | P1 | TextileBeam, TextileMovement | Schema change (add return_status to beams) | POST /textile/beams/{id}/return | Return form with remaining beam % | Remaining beam calculation | Track residual/waste in return |
| Remaining Beam | 🔵 Modify Existing | Not calculated | Add remaining_length, remaining_quantity fields to TextileBeam | P1 | None | Schema change (add to textile_beams) | GET /textile/beams/{id} | Display in beam detail | Remaining beam allocation for multi-style weaving | Calculate from issue - weaving_output |
| Beam History | 🟡 Extend Existing | TextileAuditLog captures events | Create TextileBeamHistory view + timeline | P2 | TextileAuditLog, TextileBeam | None (query from audit logs) | GET /textile/beams/{id}/history | Beam timeline UI (created → issued → returned) | Query performance on audit logs | Chronological event log per beam |

---

### Domain 11: Loom Management (8 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Loom Master | 🆕 New Module Required | Not implemented | Create TextileLoom model (factory master data) | P1 | None | Schema change (create textile_looms table) | POST /textile/looms | Loom master form + list | Loom numbering/registration | LOOM-001, LOOM-002, etc.; link to department/section |
| Machine Type | 🟡 Extend Existing | Loom types not categorized | Create TextileLoomType model (shuttle, shuttleless, dobby, jacquard) | P1 | None | Schema change (create textile_loom_types table) | POST /textile/loom-types | Loom type dropdown in loom master | Loom capability matrix (width, RPM, shed type) | Different production rates per type |
| RPM | 🔵 Modify Existing | Not tracked on loom | Add rpm (picks per minute) to TextileLoom | P2 | None | Schema change (add rpm to textile_looms) | GET /textile/looms/{id} | RPM display in loom detail | RPM variance (mechanical vs actual) | Affects production rate calculation |
| Width | 🔵 Modify Existing | Not tracked on loom | Add width (loom width in inches) to TextileLoom | P1 | None | Schema change (add width to textile_looms) | GET /textile/looms/{id} | Width display; used in fabric width validation | Width constraint validation in production | Validates fabric width feasibility |
| Shed | 🔵 Modify Existing | Not tracked on loom | Add shed (single/double) to TextileLoom | P2 | None | Schema change (add shed to textile_looms) | GET /textile/looms/{id} | Shed indicator in loom master | None | Determines production capability (shed count affects doubling) |
| Status | 🟡 Extend Existing | Not tracked; assumed always running | Create TextileLoomStatus tracking (running, idle, breakdown, maintenance) | P2 | None | Schema change (create textile_loom_statuses table with timestamp) | POST /textile/looms/{id}/status-update | Status update form with reason | Status change audit trail | Update when maintenance starts/ends |
| Running | 🟡 Extend Existing | Status = running (embedded) | Loom with active production batch assigned | P2 | TextileLoom, TextileProductionBatch | None (query via FK) | GET /textile/looms?status=running | Loom list filtered by status | None | Count running looms for dashboard KPI |
| Idle | 🟡 Extend Existing | Status = idle (embedded) | Loom available for assignment | P2 | TextileLoom | None (query via FK) | GET /textile/looms?status=idle | Idle loom list with reason | Idle time tracking for efficiency | Track idle hours for maintenance triggers |
| Breakdown | 🆕 New Module Required | Not explicitly modeled | Create TextileLoomBreakdown model (fault log, repair ticket) | P2 | TextileLoom, Maintenance (future) | Schema change (create textile_loom_breakdowns table) | POST /textile/looms/{id}/breakdown | Breakdown log form with fault description | Breakdown analysis for maintenance scheduling | Link to maintenance module |
| Maintenance | 🆕 New Module Required | Not implemented | Create TextileLoomMaintenance model (PM schedule, repair history) | P2 | TextileLoom | Schema change (create textile_loom_maintenance_schedules table) | POST /textile/loom-maintenance-schedules | Maintenance schedule form + maintenance log | Preventive vs corrective maintenance tracking | See Maintenance domain |
| Operator Assignment | 🟡 Extend Existing | Not explicitly tracked; assumed in Hrm.Employee | Create TextileLoomOperator assignment model (operator → loom + shift) | P2 | Hrm.Employee, TextileLoom | Schema change (create textile_loom_operators table) | POST /textile/looms/{id}/assign-operator | Operator assignment form per shift | Operator skill/certification tracking | Link to HR incentives; track operator productivity per loom |

---

### Domain 12: Production Planning (6 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Production Calendar | 🆕 New Module Required | Not implemented | Create TextileProductionCalendar model (working days, holidays, shifts) | P2 | Hrm.Holiday, TextileShift | Schema change (create textile_production_calendars table) | POST /textile/production-calendars | Calendar UI with shift overlay | Shift-based planning complexity | Define shift hours (Day/Night/Night2) and capacities |
| Capacity Planning | 🆕 New Module Required | Not implemented | Create CapacityPlan model (loom capacity per shift per product) | P2 | TextileLoom, TextileProductionCalendar | Schema change (create textile_capacity_plans table) | POST /textile/capacity-plans | Capacity planner UI (loom × shift × style grid) | Loom allocation optimization algorithm | Constraint satisfaction (loom width, RPM, changeover) |
| Shift Planning | 🆕 New Module Required | Not implemented | Create ShiftPlan model (shift allocation per loom + operator assignment) | P2 | TextileProductionCalendar, TextileLoomOperator | Schema change (create textile_shift_plans table) | POST /textile/shift-plans | Shift plan form (loom → operator → shift) | Operator overtime + skill match | Link to HR payroll |
| Machine Planning | 🟡 Extend Existing | Loom assignment in production batch (embedded) | Create TextileMachinePlan model (loom → batch scheduling) | P1 | TextileLoom, TextileProductionBatch | Schema change (add machine_plan_id to production_batches) | POST /textile/machine-plans | Machine plan UI (Gantt chart of batch → loom) | Changeover time calculation | Track changeover loss for efficiency |
| Material Planning | 🆕 New Module Required | Not implemented | Create MaterialPlan model (yarn requirement → lot reservation) | P1 | TextileYarn, TextileLot, TextileReservation | Schema change (create textile_material_plans table) | POST /textile/material-plans | Material requirement form (fabric design → yarn by type) | Multi-lot/fallback allocation logic | Tie to warp planning |
| Production Order | ✅ Already Available | Embedded in production batch workflow | Formalize as TextileProductionOrder before batch creation | P1 | None | Schema change (create textile_production_orders table) | GET /textile/production-orders | Production order form | None | Parent document before batch; customer SO → PO → batch |
| Production Schedule | 🆕 New Module Required | Not implemented | Create ProductionSchedule model (timeline for batches) | P2 | TextileProductionOrder, TextileMachinePlan | Schema change (create textile_production_schedules table) | POST /textile/production-schedules | Schedule visualization (Gantt/timeline) | Schedule feasibility validation | Highlight violations (late start, resource conflicts) |

---

### Domain 13: Weaving Production (8 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Daily Production | ✅ Already Available | TextileWeavingOutput model stores daily production records | Reuse TextileWeavingOutput | P1 | None | None | None | Weaving output form exists ✅ | None | Verified in tests; captures meters/rolls per day |
| Shift Production | 🔵 Modify Existing | Production recorded per day; shift-level detail missing | Add shift_id to TextileWeavingOutput | P1 | TextileShift | Schema change (add shift_id to textile_weaving_outputs) | POST /textile/weaving-outputs with shift_id | Shift selector in weaving entry form | None | Track production per shift for shift-wise KPI |
| Takha Entry | 🆕 New Module Required | Takha (weave picking pattern) not tracked | Create TextileTakhaEntry model (takha#, repeat pattern, pick count) | P2 | None | Schema change (create textile_takha_entries table) | POST /textile/takha-entries | Takha pattern form (graphical or numeric) | Takha complexity (jacquard patterns) | Optional for standard fabric; critical for patterned weaves |
| Roll Generation | ✅ Already Available | Weaving output creates rolls implicitly; embedded in production | Formalize as TextileRoll model linked to weaving output | P1 | None | Schema change (create textile_rolls table) | POST /textile/rolls | Roll detail view (roll# → meters → GSM) | None | Roll uniqueness per production batch |
| Loom Efficiency | 🟡 Extend Existing | Not calculated; embedded in production KPI | Create LoomEfficiency calculation model (actual_output / theoretical_capacity %) | P2 | TextileWeavingOutput, TextileLoom | Schema change (create textile_loom_efficiencies table) | GET /textile/looms/{id}/efficiency | Efficiency % display per loom/shift | Efficiency variance analysis | Dashboard KPI; trigger alert if < 80% |
| Operator Efficiency | 🟡 Extend Existing | Not tracked per operator | Create OperatorEfficiency model (operator → production / shift) | P2 | TextileLoomOperator, TextileWeavingOutput | Schema change (create textile_operator_efficiencies table) | GET /textile/operators/{id}/efficiency | Operator performance card | Efficiency comparison between operators | Link to HR performance reviews + incentives |
| Machine Downtime | 🆕 New Module Required | Not tracked explicitly | Create TextileMachineDowntime model (loom → start/end time, reason) | P2 | TextileLoom | Schema change (create textile_machine_downtimes table) | POST /textile/machine-downtimes | Downtime entry form with reason dropdown | Downtime aggregation & cause analysis | Track breakdown vs planned maintenance; impact on efficiency |
| Waste | ✅ Already Available | TextileWaste model | Reuse TextileWaste | P1 | None | None | None | Waste entry form exists ✅ | None | Verified in tests; tracked per production batch |
| Production Cost | 🔵 Modify Existing | Not accumulated per weaving batch | Create TextileWeavingCost model (labor + overhead + yarn depreciation) | P2 | TextileWeavingOutput, TextileYarnCost | Schema change (create textile_weaving_costs table) | POST /textile/weaving-costs | Cost capture form | Cost allocation formula (per unit or per batch) | Sum yarn + labor + machine overhead; roll-up to fabric cost |

---

### Domain 14: Grey Fabric (6 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Roll Number | 🔵 Modify Existing | TextileRoll.roll_reference implied; not formalized | Formalize roll_number as primary identifier in TextileRoll | P1 | TextileRoll | None (rename field) | GET /textile/rolls | Roll# display in UI | None | Format: YEAR-LOOM-SEQUENCE (26-001-0001) |
| Roll Barcode | 🆕 New Module Required | Not tracked | Add barcode field to TextileRoll + barcode generation | P2 | None | Schema change (add barcode to textile_rolls) | POST /textile/rolls/{id}/generate-barcode | Barcode display + print | Barcode collision risk | Mobile scanner entry point |
| Roll QR Code | 🆕 New Module Required | Not tracked | Add qr_code field to TextileRoll | P2 | None | Schema change (add qr_code to textile_rolls) | POST /textile/rolls/{id}/generate-qr | QR display/print | QR uniqueness | Mobile app scans for roll tracking |
| Roll Weight | 🔵 Modify Existing | Not tracked per roll | Add weight_kg field to TextileRoll | P1 | None | Schema change (add weight_kg to textile_rolls) | GET /textile/rolls/{id} | Weight display in roll detail | Weight variance per roll | Calculate from meters × GSM |
| Roll Length | 🔵 Modify Existing | Embedded as part of production output | Add length_meters to TextileRoll explicitly | P1 | None | Schema change (add length_meters to textile_rolls) | GET /textile/rolls/{id} | Length display | None | Captured in weaving entry |
| GSM | 🔵 Modify Existing | Assumed constant per fabric; not tracked per roll | Add gsm field to TextileRoll (may vary slightly) | P2 | None | Schema change (add gsm to textile_rolls) | GET /textile/rolls/{id} | GSM display; QC validation | GSM variance tolerance | Quality check threshold |
| Width | 🔵 Modify Existing | Implicit from loom width; not tracked | Add width_cm to TextileRoll | P1 | None | Schema change (add width_cm to textile_rolls) | GET /textile/rolls/{id} | Width display; packing constraint | None | Loom width determines roll width |
| Defects | 🟡 Extend Existing | Not tracked at roll level; embedded in QC | Create TextileRollDefect model (defect type + location + severity) | P2 | TextileQuality, DefectLibrary | Schema change (create textile_roll_defects table) | POST /textile/rolls/{id}/defects | Defect entry form (defect picker + location) | Defect record detail + photo upload | Link to QC inspection |
| Grade | 🔵 Modify Existing | Not assigned at roll level | Add grade field to TextileRoll (First/Second/Reject) | P1 | None | Schema change (add grade to textile_rolls) | GET /textile/rolls/{id} | Grade display; filter in packing | None | Determined by QC inspection |
| Warehouse | ✅ Already Available | TextileLocation tracks lot warehouse; apply to rolls | Add location_id to TextileRoll | P1 | None | Schema change (add location_id to textile_rolls) | GET /textile/rolls/{id}/location | Location display; movement tracking | None | Warehouse/rack/bin hierarchy |
| Roll History | 🟡 Extend Existing | TextileAuditLog captures events | Create TextileRollHistory view (timeline) | P2 | TextileAuditLog, TextileRoll | None (query from audit logs) | GET /textile/rolls/{id}/history | Roll timeline UI (created → processing → packing) | Query performance | Event log per roll |

---

### Domain 15: Processing (9 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Internal Processing | 🟡 Extend Existing | Not explicitly modeled (assumed within factory) | Create TextileInternalProcessing model (dyeing, printing, etc. in-house) | P2 | None | Schema change (create textile_internal_processing_records table) | POST /textile/internal-processing | Processing type selector in form | Internal process routing complexity | Track which processes done in-house |
| Job Work | ✅ Already Available | TextileJobWorkOutward + Inward implemented | Reuse TextileProcessing workflow (outward → inward) | P1 | None | None | None | Job work form exists ✅ | None | Verified in tests ✅; track outward/inward custody |
| Dyeing | 🔵 Modify Existing | Generic processing batch; dyeing not specialized | Create TextileDyeingBatch model (recipe, shade, vendor) | P2 | TextileProcessing, TextileSizingRecipe | Schema change (create textile_dyeing_batches table) | POST /textile/dyeing-batches | Dyeing form (recipe, shade, chemicals, temperature) | Recipe versioning + shade matching logic | Dyeing is most critical process; track time/temp/chemicals |
| Printing | 🆕 New Module Required | Not modeled | Create TextilePrintingBatch model (design, colors, screen) | P2 | TextileProcessing | Schema change (create textile_printing_batches table) | POST /textile/printing-batches | Printing form (design selection, color palette) | Print design complexity (multi-color) | Track color separations + screens used |
| Bleaching | 🆕 New Module Required | Not modeled | Create TextilebleachingBatch model (chemical, temperature, time) | P2 | TextileProcessing | Schema change (create textile_bleaching_batches table) | POST /textile/bleaching-batches | Bleaching form (recipe, time, temp) | Chemical concentration validation | Track chemical cost impact |
| Calendaring | 🆕 New Module Required | Not modeled | Create TextileCalenderingBatch model (pressure, temperature, finish) | P2 | TextileProcessing | Schema change (create textile_calendering_batches table) | POST /textile/calendering-batches | Calendering form (finish type, pressure, heat) | Pressure/temp calibration | Gives fabric sheen + smoothness |
| Compacting | 🆕 New Module Required | Not modeled | Create TextileCompactingBatch model | P2 | TextileProcessing | Schema change (create textile_compacting_batches table) | POST /textile/compacting-batches | Compacting form | Chemical type selection | Pre-processing for dyeing/printing |
| Finishing | 🟡 Extend Existing | Generic processing stage; finishing not explicit | Create TextileFinishingBatch model (water-repellent, anti-flame, etc.) | P2 | TextileProcessing | Schema change (create textile_finishing_batches table) | POST /textile/finishing-batches | Finishing type dropdown | Finishing chemical tracking | Final step before packing; completes product value |
| Recipe | ✅ Already Available | TextileRouteRecipe + TextileSizingRecipe | Reuse + extend for process-specific recipes | P1 | None | None | None | Recipe form exists ✅ | None | Dyeing recipe, printing recipe, finish recipe |
| Shade Card | 🆕 New Module Required | Not modeled | Create TextileShadeCard model (reference color + customer approval) | P2 | TextileShade | Schema change (create textile_shade_cards table) | POST /textile/shade-cards | Shade card image + reference | Shade matching accuracy vs production | Physical sample library + approval workflow |
| Batch | ✅ Already Available | TextileBatch concept used in processing workflows | Reuse for grouping lots through processing | P1 | None | None | None | Batch tracking in processing form | None | Multiple lots per batch during dyeing |
| Process Cost | 🔵 Modify Existing | Not tracked explicitly | Create TextileProcessingCost model (chemical + labor + utility) | P2 | TextileProcessing, TextileChemicalCost | Schema change (create textile_processing_costs table) | POST /textile/processing-costs | Cost entry form per batch | Cost allocation complexity | Accumulate to finished fabric cost |

---

### Domain 16: Quality (8 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Incoming QC | ✅ Already Available | TextileProcurementApiController.storeIncomingQc + finalize | Reuse TextileWorkflowDocument type=incoming_qc | P1 | None | None | None | QC form exists ✅ | None | Verified in tests; GRN → incoming QC → lot creation |
| Process QC | 🟡 Extend Existing | Not explicitly modeled per process stage | Create TextileProcessQC model (inspection at dyeing/printing/finish) | P2 | TextileQuality | Schema change (create textile_process_qcs table) | POST /textile/process-qcs | QC form per process checkpoint | In-line inspection workflow | Sample + full inspection modes |
| Final QC | 🟡 Extend Existing | Not explicitly modeled; assumed end-stage QC | Create TextileFinalQC model (roll inspection before packing) | P1 | TextileRoll | Schema change (create textile_final_qcs table) | POST /textile/final-qcs | QC form for roll inspection | Batch sampling logic | Every roll vs sample basis |
| Defect Library | 🆕 New Module Required | Not implemented; defects embedded in QC | Create DefectLibrary + DefectCategory models (warp break, knots, weaving faults, color, etc.) | P2 | None | Schema change (create textile_defect_libraries, textile_defect_categories tables) | POST /textile/defect-libraries | Defect master form + categorization | Defect taxonomy design | Standard defect codes (AATCC, ISO); severity levels |
| Shade Matching | 🆕 New Module Required | Not modeled | Create ShadeMatchingTest model (lab test vs reference) | P2 | TextileDyeingBatch, TextileShadeCard | Schema change (create textile_shade_matching_tests table) | POST /textile/shade-matching-tests | Shade test form (lab instruments + visual) | Color instrument (Spectrophotometer) integration | Approval decision: pass/hold/reject |
| Fabric Inspection | 🟡 Extend Existing | Generic QC; fabric-specific parameters missing | Create TextileFabricInspection specialization (count, GSM, weight, shrinkage, tensile) | P2 | TextileFinalQC | Schema change (create textile_fabric_inspections table) | POST /textile/fabric-inspections | Fabric inspection form with parameter grid | Test result data model | Standard test parameters per fabric type |
| Hold | 🟡 Extend Existing | TextileQualityHold model exists; not full workflow | Enhance with hold reason + escalation + release approval | P1 | TextileQuality | Schema change (add escalation_level to textile_holds) | POST /textile/holds/{id}/release | Hold status UI | None | Lot on hold; prevent movement until released |
| Reject | 🟡 Extend Existing | QC rejection implicit; not formalized as action | Create TextileRejection model (rejection reason, credit note link) | P2 | TextileFinalQC | Schema change (create textile_rejections table) | POST /textile/rejections | Rejection form with reason + debit note | Scrap disposition tracking | Trigger credit note to supplier or in-house loss allocation |
| Pass | 🟡 Extend Existing | Pass state implicit in QC finalize | Formalize as TextileQCPass action | P1 | TextileFinalQC | None (query status) | GET /textile/qcs/{id}/status | Status indicator (passed) | None | Move lot/roll to next stage |
| Rework | ✅ Already Available | TextileRework model (re-process) | Reuse TextileRework | P1 | None | None | None | Rework entry form exists ✅ | None | Verified in tests; track rework reason (dyeing, weaving) |
| Quality Certificates | 🆕 New Module Required | Not tracked | Create QualityCertificate model (test results → PDF certificate) | P2 | TextileFinalQC | Schema change (create textile_quality_certificates table) | POST /textile/quality-certificates | Certificate generation + email delivery | Certificate digital signing | Generate for customer shipment; track versions |

---

### Domain 17: Packing (5 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Roll Packing | 🆕 New Module Required | Not modeled | Create TextileRollPacking model (roll → carton assignment) | P2 | TextileRoll | Schema change (create textile_roll_packings table) | POST /textile/roll-packings | Packing form (select rolls → carton) | Multi-roll carton logic | Group rolls by order/customer preference |
| Bundle Packing | 🆕 New Module Required | Not modeled | Create TextileBundlePacking model (group rolls into bundle) | P2 | TextileRoll | Schema change (create textile_bundle_packings table) | POST /textile/bundle-packings | Bundle form (rolls → bundle) | Bundle weight/qty aggregation | Intermediate grouping (rolls → bundles → pallets) |
| Bale Packing | 🆕 New Module Required | Not modeled | Create TextileBaleCreation model (bundles → bale for export) | P2 | TextileBundlePacking | Schema change (create textile_bale_creations table) | POST /textile/bales | Bale form (bundle selection → bale) | Bale weight constraints | Final shipping unit; link to dispatch |
| Labels | 🆕 New Module Required | Not modeled | Create TextileLabel model (label design + data) | P2 | None | Schema change (create textile_labels table) | POST /textile/labels | Label template form (design + variables) | Label personalization logic | Customer name, order#, shade, size, batch, date |
| Barcode Labels | 🟡 Extend Existing | Barcode system exists; label printing not integrated | Link TextileRoll.barcode → label printer API | P2 | Label printer integration | None | POST /textile/labels/{id}/print-barcode | Print action in packing form | Barcode printer connectivity | Integrate Zebra/Brother printer APIs |
| QR Labels | 🟡 Extend Existing | QR system exists; label printing not integrated | Link TextileRoll.qr_code → label printer | P2 | Label printer integration | None | POST /textile/labels/{id}/print-qr | Print action in packing form | QR printer connectivity | Same as barcode labels |
| Packing Material | 🟡 Extend Existing | Not tracked; not linked to packing | Create PakingMaterialUsage model (cartons, tape, labels consumed) | P2 | TextilePackingMaterial | Schema change (create textile_packing_material_usages table) | POST /textile/packing-material-usages | Material consumption grid | Material cost accumulation | Track waste (torn cartons, etc.) |
| Weight | 🟡 Extend Existing | Not tracked per package unit | Add weight fields to TextileRollPacking, TextileBundlePacking, TextileBaleCreation | P2 | None | Schema change (add weight fields) | GET /textile/packages/{id} | Weight display in packing UI | Weighing scale integration | Weighing scale API for automated capture |

---

### Domain 18: Dispatch (7 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Dispatch Planning | 🟡 Extend Existing | Not explicitly modeled; dispatch is single-step | Create DispatchPlan model (consolidate orders → shipment) | P2 | TextileSalesOrder | Schema change (create textile_dispatch_plans table) | POST /textile/dispatch-plans | Dispatch plan form (order selection → shipment) | Consolidation algorithm (customer, destination, urgency) | Optimize shipment cost vs delivery time |
| Delivery Challan | ✅ Already Available | TextileChallans model (part of sales workflow) | Reuse Textile workflow type=challan | P1 | None | None | None | Challan form exists ✅ | None | Verified in tests; created after dispatch release |
| Truck | 🟡 Extend Existing | Not modeled; assumed generic vehicle | Create TextileTruck model (registration, capacity, driver) | P2 | None | Schema change (create textile_trucks table) | POST /textile/trucks | Truck master form + assignment | Vehicle tracking complexity | Link to transport cost |
| Container | 🆕 New Module Required | Not modeled | Create TextileContainer model (20ft, 40ft for export) | P2 | None | Schema change (create textile_containers table) | POST /textile/containers | Container type master | Container utilization tracking | Export shipment containers |
| Driver | 🆕 New Module Required | Not modeled | Create Driver model (name, license, mobile, trip history) | P2 | None | Schema change (create textile_drivers table) | POST /textile/drivers | Driver master form | Driver skill/experience tracking | Link to HR or contractor |
| Vehicle | 🟡 Extend Existing | Not explicitly tracked; assumed generic | Formalize TextileVehicle model linking dispatch → transport | P2 | Truck, Driver | Schema change (create textile_vehicles table) | POST /textile/vehicles | Vehicle assignment form in dispatch | Vehicle availability + maintenance conflict | Vehicle → trip assignment |
| LR Number | 🆕 New Module Required | Not tracked | Add lr_number field to TextileDispatch (lorry receipt) | P2 | None | Schema change (add lr_number to textile_dispatch_documents) | GET /textile/dispatch/{id} | LR# display in challan | None | Transport document identifier; unique per vehicle/trip |
| E-Way Bill | 🆕 New Module Required | Not integrated | Create EWayBillIntegration (GST Indian compliance) | P3 | GST API integration, Dispatch | Schema change (create textile_eway_bills table) | POST /textile/dispatch/{id}/generate-eway-bill | Generate EWay Bill action | EWay Bill API connectivity + error handling | India-specific; link to e-Way Bill portal |
| Freight | 🔵 Modify Existing | Not tracked per shipment | Add freight_cost field to TextileDispatch | P2 | None | Schema change (add freight_cost to textile_dispatch_documents) | GET /textile/dispatch/{id} | Freight cost display; impact analysis | Cost variant (per kg, per box, flat) | Calculate from vehicle rate card or quote |
| POD | ✅ Already Available | TextilePOD marking workflow exists | Reuse TextileWorkflowDocument type=pod | P1 | None | None | None | POD marking UI exists ✅ | None | Verified in tests; marks dispatch complete + invoice_ready |
| Dispatch Tracking | 🆕 New Module Required | Not implemented | Create DispatchTracking model (real-time vehicle tracking) | P3 | GPS tracking API | Schema change (create textile_dispatch_tracking_locations table) | GET /textile/dispatch/{id}/tracking | Dispatch tracking map + ETA | GPS API integration + data retention | Real-time dispatch visibility; optional feature |

---

### Domain 19: Transport (5 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Own Vehicles | 🔵 Modify Existing | Truck model not formalized | Create OwnVehicle model (company-owned trucks for transport) | P2 | None | Schema change (create textile_own_vehicles table) | POST /textile/own-vehicles | Vehicle master form (registration, insurance, capacity) | Fleet management complexity | Asset register + depreciation tracking |
| Transport Vendors | 🟡 Extend Existing | Vendor model; transport not specialized | Extend Vendor + TransportVendor specialization (rates, routes, capacity) | P2 | Account.Vendor | Schema change (create textile_transport_vendors table) | POST /textile/transport-vendors | Vendor form with rate card | Multi-leg routing; variable rate | 3PL vendor management |
| Drivers | 🟡 Extend Existing | Mentioned in dispatch; not master-managed | Create Driver model (license, certification, safety record) | P2 | Hrm.Employee | Schema change (create textile_drivers table) | POST /textile/drivers | Driver master form | Driver skill/experience tracking | Link to own fleet |
| Routes | 🆕 New Module Required | Not modeled | Create TextileRoute model (origin → destination, distance, time) | P2 | None | Schema change (create textile_routes table) | POST /textile/routes | Route master form | Route optimization algorithm | Customer location master → route assignment |
| Fuel | 🆕 New Module Required | Not tracked | Create TextileFuelExpense model (vehicle fuel log) | P3 | TextileOwnVehicle | Schema change (create textile_fuel_expenses table) | POST /textile/fuel-expenses | Fuel log form (date, liters, cost) | Fuel consumption ratio tracking | Cost control + maintenance schedule |
| Freight Cost | 🔵 Modify Existing | Not aggregated; mentioned in dispatch | Create FreightCost model (trip cost breakdown: fuel, toll, driver, vehicle hire) | P2 | TextileDispatch, TextileRoute | Schema change (create textile_freight_costs table) | POST /textile/freight-costs | Cost entry form | Cost allocation per shipment | Calculate delivery cost per SKU |
| Vehicle Maintenance | 🆕 New Module Required | Not implemented | Create VehicleMaintenance model (PM schedule, repair history) | P3 | TextileOwnVehicle | Schema change (create textile_vehicle_maintenance_schedules table) | POST /textile/vehicle-maintenance-schedules | Maintenance schedule + log | Maintenance cost tracking | Link to spare parts procurement |

---

### Domain 20: Maintenance (6 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Preventive Maintenance | 🆕 New Module Required | Not implemented | Create PreventiveMaintenanceSchedule model (PM calendar per machine) | P2 | TextileLoom, TextileMachine | Schema change (create textile_pm_schedules table) | POST /textile/pm-schedules | PM schedule form (frequency, tasks) | Schedule enforcement + notification | Hours/cycles-based triggers |
| Breakdown | 🆕 New Module Required | Not modeled | Create MachineBreakdown model (fault log, impact) | P2 | None | Schema change (create textile_breakdowns table) | POST /textile/breakdowns | Breakdown report form (machine, symptom, impact) | Fault code taxonomy + diagnosis | Track MTTR (mean time to repair) |
| Service Schedule | 🆕 New Module Required | Not modeled | Create ServiceSchedule model (maintenance calendar + technician assignment) | P2 | TextilePreventiveMaintenanceSchedule | Schema change (create textile_service_schedules table) | POST /textile/service-schedules | Schedule planner (calendar view + assignment) | Technician availability conflict resolution | Plan maintenance around production |
| Spare Parts | 🟡 Extend Existing | TextileSparePart model for product master; not linked to maintenance | Create MaintenanceSparePartUsage model (link breakdown/PM → parts) | P2 | TextileSparePart | Schema change (create textile_maintenance_spare_part_usages table) | POST /textile/breakdowns/{id}/spare-parts | Spare part consumption in maintenance form | Parts availability check during repair | Trigger purchase requisition if stock low |
| Machine History | 🟡 Extend Existing | TextileAuditLog captures events | Create MachineHistory view (equipment maintenance timeline) | P2 | TextileLoom, TextileAuditLog | None (query from audit logs) | GET /textile/machines/{id}/history | Machine timeline UI (PM, breakdown, repair, replacement) | Query performance on audit logs | Chronological event log per machine |
| Downtime | 🟡 Extend Existing | Not explicitly tracked; embedded in breakdown | Create DowntimeRecord model (machine off-time logging) | P2 | TextileBreakdown, TextileMachineDowntime | Schema change (add downtime_minutes to textile_breakdowns) | GET /textile/machines/{id}/downtime | Downtime analysis report | Root cause analysis complexity | Calculate cost impact (production loss + labor) |
| Maintenance Cost | 🔵 Modify Existing | Not aggregated | Create MaintenanceCostRecord model (labor + parts + external service costs) | P2 | TextileMaintenance, TextileSparePart | Schema change (create textile_maintenance_costs table) | POST /textile/maintenance-costs | Cost entry form | Cost allocation per machine lifecycle | Monthly maintenance cost tracking |

---

### Domain 21: HR (10 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Employees | ✅ Already Available | Hrm.Employee model | Reuse Hrm.Employee; extend with weaver/operator skill tracking | P1 | None | None | None | Employee list exists | None | Verified across textile workflows; link to loom operator assignment |
| Attendance | ✅ Already Available | Hrm.Attendance model | Reuse Hrm.Attendance; link to shift timings | P1 | None | None | None | Attendance form exists | None | Track shift-wise attendance for incentive calculation |
| Shift | ✅ Already Available | Hrm.Shift model | Reuse Hrm.Shift; define textile shifts (Day/Night/Night2) | P1 | None | None | None | Shift master exists | None | Link to textile production shifts |
| Payroll | ✅ Already Available | Hrm.Payroll model | Reuse Hrm.Payroll; integrate textile incentives | P1 | None | None | None | Payroll form exists | None | Base salary + textile production incentives |
| Incentives | 🟡 Extend Existing | Hrm.Allowance exists; production incentives not explicit | Create TextileIncentive model (based on production output/efficiency) | P2 | TextileWeaving, Hrm.Payroll | Schema change (create textile_incentives table) | POST /textile/incentives | Incentive rule form (production target, bonus per unit) | Incentive calculation complexity | Variable pay: piecerate or efficiency bonus |
| Production Incentives | 🔵 Modify Existing | Not explicitly linked to textile operators | Create TextileOperatorIncentive model (operator → production output → bonus) | P2 | TextileLoomOperator, TextileWeavingOutput | Schema change (create textile_operator_incentives table) | POST /textile/operator-incentives | Incentive calculation form (output × rate) | Incentive fairness (operator skill variance) | Track per operator + shift; integrate to payroll |
| Operator Skills | 🆕 New Module Required | Not modeled | Create OperatorSkill model (skill type, certification level, machines certified for) | P2 | Hrm.Employee | Schema change (create textile_operator_skills table) | POST /textile/operator-skills | Skill matrix form (operator → skills + certification) | Skill assessment + progression tracking | Determine operator assignment eligibility |
| Performance | 🟡 Extend Existing | Performance module exists (generic) | Link textile metrics (production, quality, attendance) to performance review | P2 | Performance, TextileMetrics | Schema change (create textile_performance_metrics table) | POST /textile/performance-metrics | Performance scorecard for textile roles | Weighted scoring criteria | Annual review with textile KPIs |

---

### Domain 22: Finance (9 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Accounts | ✅ Already Available | Account package (ChartOfAccount model) | Reuse ChartOfAccount; define textile cost accounts (yarn, labor, overhead) | P1 | None | None | None | COA master exists | None | GL account hierarchy for textile operations |
| Ledger | ✅ Already Available | Implicit in journal entries | Reuse journal entry concept; create textile ledger reports | P2 | Account.JournalEntry | None (query existing) | GET /textile/ledger | Textile ledger view (by cost center) | Real-time posting performance | Aggregate by product/location/cost center |
| Journal | ✅ Already Available | Account.JournalEntry model | Reuse for textile accrual entries (WIP → COGS) | P2 | Account.JournalEntry | None | None | None | None | Manual entries for period-end textile adjustments |
| Cost Centers | 🆕 New Module Required | Not modeled | Create TextileCostCenter model (weaving, dyeing, packing, admin) | P1 | None | Schema change (create textile_cost_centers table) | POST /textile/cost-centers | Cost center master form | Cost allocation methodology design | Overhead distribution basis (labor hours, machine hours) |
| Production Cost | ✅ Already Available | TextileCosting workflow tracks costs | Reuse TextileCosting; accumulate to WIP inventory | P1 | None | None | None | Costing form exists ✅ | None | Verified in tests ✅; captures material + labor + overhead |
| Cost Per Meter | 🔵 Modify Existing | Not formalized; embedded in costing | Create TextileCostPerMeter calculation (total_cost / meters) | P2 | TextileCosting, TextileRoll | Schema change (add cost_per_meter to textile_costing) | GET /textile/fabrics/{id}/cost-per-meter | Cost display in fabric costing report | Cost accuracy dependent on accurate metering | Basis for pricing decisions |
| Cost Per Roll | 🔵 Modify Existing | Not formalized | Create TextileCostPerRoll calculation (total_cost / rolls) | P2 | TextileCosting | Schema change (add cost_per_roll to textile_costing) | GET /textile/fabrics/{id}/cost-per-roll | Cost display in roll detail | None | Alternative cost measure |
| Machine Cost | 🔵 Modify Existing | Not allocated per production | Create MachineCostAllocation model (machine cost → production batches) | P2 | TextileLoom, TextileCosting | Schema change (create textile_machine_cost_allocations table) | POST /textile/machine-cost-allocations | Cost allocation form | Machine cost complexity (depreciation + maintenance) | Allocate based on machine hours used |
| Power Cost | 🆕 New Module Required | Not tracked | Create PowerCostAllocation model (meter readings → batch allocation) | P2 | TextileCosting | Schema change (create textile_power_costs table) | POST /textile/power-costs | Power cost entry form | Power consumption meter integration | Allocate by machine power rating × runtime |
| Chemical Cost | 🔵 Modify Existing | Not explicitly allocated | Create ChemicalCostAllocation model (recipe × consumption cost) | P2 | TextileChemical, TextileSizingRecipe | Schema change (add to textile_processing_costs) | GET /textile/processing/{id}/chemical-cost | Chemical cost display in processing detail | Cost variance vs budget | Update cost as chemical prices change |
| Labour Cost | 🔵 Modify Existing | Not allocated per batch | Create LaborCostAllocation model (shift hours × labor rate → batch) | P2 | TextileLoomOperator, Hrm.Payroll | Schema change (create textile_labour_cost_allocations table) | POST /textile/labour-cost-allocations | Labor cost entry (hours × rate) | Incentive vs base labor cost split | Allocate operator cost to production batch |
| Profitability | 🔵 Modify Existing | Not calculated per product | Create TextileProfitability model (revenue - cost by fabric/order) | P2 | TextileCosting, SalesInvoice | Schema change (create textile_profitability_reports table) | GET /textile/profitability | Profitability dashboard (by fabric, order, time period) | Cost accuracy dependency + multi-product assignment | Margin analysis; identify low-margin products |

---

### Domain 23: Reports (12 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Production Reports | 🟡 Extend Existing | TextileDashboard exists with basic aggregates | Create TextileProductionReport model (daily/weekly/monthly output by loom/style) | P2 | TextileLoom, TextileWeavingOutput | None (query aggregation) | GET /textile/reports/production | Production report UI (filters by date, loom, style) | Query performance on large datasets | Export to Excel/PDF |
| Loom Reports | 🟡 Extend Existing | Not formalized | Create LoomReport aggregation (loom utilization, efficiency, downtime, maintenance) | P2 | TextileLoom, TextileWeavingOutput, TextileMachineDowntime | None (query aggregation) | GET /textile/reports/loom | Loom KPI report (efficiency %, downtime hours, cost per meter) | Historical trending | Daily MIS components |
| Operator Reports | 🟡 Extend Existing | Not formalized | Create OperatorReport aggregation (output, efficiency, attendance, incentive) | P2 | TextileLoomOperator, TextileWeavingOutput, Hrm.Attendance | None (query aggregation) | GET /textile/reports/operator | Operator performance report (cards per shift, efficiency %, attendance) | Operator comparison fairness | Payroll impact visibility |
| Yarn Consumption | 🟡 Extend Existing | Not tracked per style | Create YarnConsumptionReport (fabric design → yarn qty consumed vs standard) | P2 | TextileYarn, TextileWarpPlan, TextileLot | None (query aggregation) | GET /textile/reports/yarn-consumption | Yarn consumption report (vs standard, variance %) | Yarn consumption accuracy | Identify waste/theft |
| Beam Reports | 🟡 Extend Existing | Not formalized | Create BeamReport aggregation (beam production, sizing, issues) | P2 | TextileBeam, TextileSizedBeam, TextileWarpProduction | None (query aggregation) | GET /textile/reports/beam | Beam status report (created, sized, issued, returned, idle) | Beam cycle time analysis | Production planning input |
| Grey Fabric Reports | 🟡 Extend Existing | Not formalized | Create GreyFabricReport aggregation (production, quality, processing) | P2 | TextileRoll, TextileWeavingOutput, TextileQuality | None (query aggregation) | GET /textile/reports/grey-fabric | Grey fabric inventory report (total, grade, processing status) | Roll traceability | Processing plan input |
| Finished Fabric Reports | 🟡 Extend Existing | Not formalized | Create FinishedFabricReport aggregation (packing, dispatch readiness) | P2 | TextileRoll, TextilePacking, TextileDispatch | None (query aggregation) | GET /textile/reports/finished-fabric | Finished fabric inventory (ready for dispatch, grade, customer) | Customer order fulfillment | Sales pipeline visibility |
| Dispatch Reports | 🟡 Extend Existing | Not formalized | Create DispatchReport aggregation (shipments, on-time performance, freight) | P2 | TextileDispatch, TextileChallan | None (query aggregation) | GET /textile/reports/dispatch | Dispatch report (shipments per day, LR#, freight cost, OTD %) | Customer delivery tracking | Finance reconciliation |
| Purchase Reports | 🟡 Extend Existing | Not formalized; core PurchaseInvoice reports exist | Create TextilePurchaseReport specialization (GRN pending, invoices, supplier QC performance) | P2 | TextileProcurement, TextileQuality | None (query aggregation) | GET /textile/reports/purchase | Purchase report (POs pending, GRN outstanding, supplier quality score) | 3-way match tracking | Cost control |
| Sales Reports | 🟡 Extend Existing | SalesInvoice reports exist; textile-specific missing | Create TextileSalesReport specialization (SO pending, allocations, dispatch pending) | P2 | TextileSales | None (query aggregation) | GET /textile/reports/sales | Sales report (customer orders, delivery status, revenue) | Order fulfillment tracking | Customer service |
| Stock Reports | 🟡 Extend Existing | Not formalized for textile | Create StockReport aggregation (lot inventory, aging, value, reserved) | P2 | TextileLot, TextileReservation | None (query aggregation) | GET /textile/reports/stock | Stock report (by location, lot aging, value, availability) | Inventory visibility | Cash flow impact |
| Profit Reports | 🔵 Modify Existing | Not formalized | Create ProfitReport aggregation (revenue - cost by style/customer/period) | P2 | TextileCosting, SalesInvoice | None (query aggregation) | GET /textile/reports/profit | Profit report (gross margin %, net margin %, contribution by style) | Profitability drill-down | Product mix optimization |
| Machine Efficiency | 🟡 Extend Existing | Not aggregated | Create MachineEfficiencyReport (loom efficiency % over time, benchmarks) | P2 | TextileLoom, TextileWeavingOutput | None (query aggregation) | GET /textile/reports/machine-efficiency | Machine efficiency trend chart (daily/weekly/monthly) | Target vs actual comparison | Maintenance impact analysis |
| Waste Analysis | 🟡 Extend Existing | Not formalized | Create WasteReport aggregation (waste %, reason, cost impact) | P2 | TextileWaste, TextileWeavingOutput | None (query aggregation) | GET /textile/reports/waste | Waste report (% of production, cost, reason breakdown) | Waste trend analysis | Process improvement identification |
| Power Consumption | 🆕 New Module Required | Not tracked | Create PowerConsumptionReport (meter readings aggregated) | P3 | PowerCostAllocation | None (query aggregation) | GET /textile/reports/power-consumption | Power consumption chart (daily, by machine) | Energy cost per unit | Sustainability + cost control |
| Daily MIS | 🟡 Extend Existing | Dashboard has aggregates; not formalized MIS | Create DailyMISReport (production, quality, efficiency, issues) | P2 | TextileDashboard | None (query aggregation) | GET /textile/reports/daily-mis | Daily MIS email/PDF (production summary, KPI snapshot, alerts) | End-of-shift report automation | Management visibility |
| Monthly MIS | 🟡 Extend Existing | Not formalized | Create MonthlyMISReport (production trends, cost, profit, YoY comparison) | P2 | None | None (query aggregation) | GET /textile/reports/monthly-mis | Monthly MIS report (summary + trend charts + variance analysis) | Period-end reporting | Finance/board reporting |
| Annual MIS | 🟡 Extend Existing | Not formalized | Create AnnualMISReport (annual production, cost, profitability, efficiency trends) | P3 | None | None (query aggregation) | GET /textile/reports/annual-mis | Annual report (summary + historical trends + benchmarks) | Statutory reporting | Strategic planning |

---

### Domain 24: Dashboards (9 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| CEO Dashboard | 🟡 Extend Existing | TextileDashboard exists (generic aggregate) | Create TextileCEODashboard specialization (revenue, profit, efficiency, key alerts) | P2 | TextileCosting, TextileSales | None (query aggregation) | GET /textile/dashboards/ceo | Executive summary (KPI cards + trend charts) | KPI definition alignment with strategy | Drill-down to detail reports |
| Factory Dashboard | 🟡 Extend Existing | TextileDashboard exists | Create TextileFactoryDashboard specialization (production, loom status, efficiency, issues) | P2 | TextileLoom, TextileWeavingOutput, TextileMachineDowntime | None (query aggregation) | GET /textile/dashboards/factory | Factory floor view (loom status map, efficiency %, downtime alerts) | Real-time data freshness | Shift supervisor view |
| Production Dashboard | ✅ Already Available | TextileDashboard includes production aggregates | Reuse + enhance with production batch status, on-time tracking | P1 | None | None | None | Dashboard exists ✅ | None | Verified in tests ✅ |
| Purchase Dashboard | 🟡 Extend Existing | Not formalized | Create PurchaseDashboard specialization (PO pending, GRN outstanding, supplier performance) | P2 | TextileProcurement | None (query aggregation) | GET /textile/dashboards/purchase | Purchase KPI (lead time, quality, on-time %, cost variance) | Supplier comparison | Procurement team view |
| Inventory Dashboard | 🟡 Extend Existing | Not formalized; TextileInventory admin has lists | Create InventoryDashboard specialization (stock levels, aging, value, reserved, frozen) | P2 | TextileLot, TextileReservation | None (query aggregation) | GET /textile/dashboards/inventory | Inventory summary (total stock value, aging days, turnover) | Stock variance alerts | Warehouse team view |
| Sales Dashboard | 🟡 Extend Existing | Not formalized | Create SalesDashboard specialization (SO pending, delivery status, revenue, customer performance) | P2 | TextileSales, SalesInvoice | None (query aggregation) | GET /textile/dashboards/sales | Sales KPI (orders, revenue, delivery performance, top customers) | Customer profitability | Sales team view |
| Finance Dashboard | 🟡 Extend Existing | Account module has dashboard; textile integration missing | Create TextileFinanceDashboard specialization (revenue, cost, profit, cash flow) | P2 | TextileCosting, SalesInvoice, PurchaseInvoice | None (query aggregation) | GET /textile/dashboards/finance | Finance KPI (revenue, cost, margin %, cash position, AR/AP) | Period-end closing readiness | Finance team view |
| Maintenance Dashboard | 🟡 Extend Existing | Not formalized | Create MaintenanceDashboard specialization (MTBF, MTTR, maintenance cost, upcoming PM) | P2 | TextileMaintenance, TextileMachineDowntime | None (query aggregation) | GET /textile/dashboards/maintenance | Maintenance KPI (machine availability, cost, upcoming schedules) | Maintenance effectiveness | Maintenance team view |
| HR Dashboard | 🟡 Extend Existing | Hrm has generic dashboard; textile metrics missing | Create TextileHRDashboard specialization (operator efficiency, attendance, payroll, incentives) | P2 | TextileOperatorIncentive, Hrm.Attendance | None (query aggregation) | GET /textile/dashboards/hr | HR KPI (headcount, attendance %, incentive cost, operator performance) | Payroll impact forecasting | HR team view |

---

### Domain 25: Mobile (5 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Production Entry | 🟡 Extend Existing | Textile APIs exist; mobile app not built | Create TextileProductionMobileApp (React Native or Flutter) + sync | P2 | TextileManufacturing API | None | Existing APIs leverage | Mobile UI (shift entry, roll tracking) | Offline sync complexity + conflict resolution | Loom operator enters production from floor |
| QC Entry | 🟡 Extend Existing | Textile QC APIs exist; mobile not built | Create TextileQCMobileApp (QC inspector workflow) | P2 | TextileQuality API | None | Existing APIs leverage | Mobile UI (lot/roll inspection, defect entry) | Form complexity + photo capture | Incoming QC + process QC from warehouse/floor |
| Barcode Scanner | 🆕 New Module Required | Not implemented | Integrate barcode scanner library (React Native) + lot lookup | P2 | Barcode encoding on TextileLot, TextileRoll | None | GET /textile/lots/scan/{barcode} | Scanner interface (focus on QR field) | Hardware scanner compatibility across devices | Quick lot identification |
| QR Scanner | 🆕 New Module Required | Not implemented | Integrate QR scanner library + lot/roll lookup | P2 | QR encoding on TextileLot, TextileRoll | None | GET /textile/qr-scan/{qr_code} | Scanner interface | Same as barcode | Mobile lot/roll entry point |
| Stock Lookup | 🆡 Extend Existing | Textile API exists; mobile not built | Create TextileStockLookupMobileApp (real-time stock check) | P2 | TextileInventory API | None | GET /textile/stock/{lot_id} | Mobile UI (availability, location, reservations) | Real-time data freshness | Warehouse staff checks available stock |
| Dispatch | 🟡 Extend Existing | Textile dispatch APIs exist; mobile not built | Create TextileDispatchMobileApp (truck/driver workflow) | P2 | TextileDispatch API | None | Existing APIs leverage | Mobile UI (dispatch list, POD capture, photo) | Photo capture + sync | Driver marks POD from field |
| Attendance | 🟡 Extend Existing | Hrm.Attendance API exists | Leverage attendance APIs in mobile (punch in/out) | P2 | Hrm.Attendance API | None | Existing Hrm APIs | Mobile punch clock UI | GPS/location validation | Operator attendance from shop floor |

---

### Domain 26: Integrations (11 features)

| Feature | Classification | Existing Module/Package | Proposed Package | Priority | Dependencies | DB Impact | API Impact | UI Impact | Architectural Risks | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| WhatsApp | 🟡 Extend Existing | Twilio package exists (generic); no textile workflow | Create TextileWhatsAppNotification (order updates, QC holds, dispatch alerts) | P3 | Twilio API, Webhook | None | POST /webhook/whatsapp | Notification template builder (WhatsApp format) | Message delivery reliability | Send PO confirmation, GRN alerts, dispatch status |
| SMS | 🟡 Extend Existing | Twilio package exists; no textile workflow | Create TextileSMSNotification (order status, delivery alerts) | P3 | Twilio API, Webhook | None | POST /webhook/sms | SMS template builder | None | Supplier/customer/driver SMS updates |
| Email | ✅ Already Available | EmailTemplate + mail config | Reuse; create textile-specific email templates (PO, QC reports, invoices) | P2 | EmailTemplate, Textile workflows | None | None | Textile email template selector | None | Use existing email system; textile template library |
| Barcode Printers | 🆕 New Module Required | Not integrated | Create BarcodePrinterIntegration (Zebra/Brother printer APIs) | P2 | None | None | POST /textile/barcode-print | Print action in UI (lot/roll barcode print) | Printer connectivity + queue management | Print lot/roll barcodes on demand |
| QR Printers | 🆕 New Module Required | Not integrated | Create QRPrinterIntegration (label printer APIs) | P2 | None | None | POST /textile/qr-print | Print action in UI (lot/roll QR print) | Same as barcode printer | Print QR labels on demand |
| Weighing Scale | 🆕 New Module Required | Not integrated | Create WeighingScaleIntegration (scale APIs + data capture) | P2 | None | None | POST /textile/weights (from scale) | Weight auto-capture in forms | Scale brand compatibility + connectivity | Automatic weight entry during receipt/packing |
| Tally Export | 🟡 Extend Existing | Tally not integrated | Create TallyExport (journal entries → Tally XML export) | P2 | DoubleEntry package, TextileCosting | Schema change (create tally_export_logs table) | POST /textile/tally-export | Export action in finance UI | Tally version compatibility | Export GL transactions for accounting sync |
| GST | 🆕 New Module Required | Not implemented | Create GSTIntegration (GST calculation + compliance reporting) | P3 | Account.Invoice, Dispatch | Schema change (create gst_audit_table) | POST /textile/gst-compliance | GST report UI (GSTR-1 format) | GST rule complexity + update frequency | India-specific; calculate tax + generate GSTR reports |
| E-Way Bill | 🆕 New Module Required | Not implemented | Create EWayBillIntegration (GST e-Way Bill portal API) | P3 | Dispatch, GST | Schema change (create eway_bill_logs table) | POST /textile/eway-bill/generate | Generate action in dispatch | E-Way Bill API connectivity | India-specific; generate + auto-print on dispatch |
| Payment Gateway | 🟡 Extend Existing | Stripe + PayPal packages exist | Leverage for textile advance payments / deposit collections | P3 | Stripe/PayPal API, SalesInvoice | None | Existing payment APIs | Payment button in quotation/SO | None | Customer advance collection; SOA payable integration |
| API | 🟡 Extend Existing | Textile API routes defined (api.php) | Enhance API documentation + testing; support mobile/3PL integrations | P2 | None | None | Existing textile API routes | API documentation portal (Swagger) | API versioning + backward compatibility | Enable 3PL, subsidiary, distributor integrations |

---

## Dependency Map Summary

### Critical Sequencing Constraints

**Phase 1 (Foundation - Weeks 1-8)** ✅ COMPLETE
- ✅ TextileCore (specifications, quality profiles, routes, units)
- ✅ TextileInventory (lots, movements, locations, reservations)
- ✅ TextileProcurement (requisition → PO → GRN → QC)
- ✅ TextileSales (SO → Allocation → Dispatch → Challan → POD)
- ✅ TextileManufacturing (Beam → Batch → Weaving Output + Waste + Rework)
- ✅ TextileQuality (Inspection + Hold/Release)
- ✅ TextileProcessing (Job Work + Processing)
- ✅ TextileCosting (Cost Entry + Margin)
- ✅ TextileDashboard (Aggregates + Reports)

**Phase 2 Dependencies (Weeks 9-18)**
```
Approval Workflow
  ├─ Spatie Permission (existing)
  ├─ Notification System
  └─ Audit Trail

Finance Integration (WIP Costing)
  ├─ DoubleEntry (existing)
  ├─ TextileCosting (✅ done)
  ├─ Cost Centers (new)
  └─ Journal Entry Posting

Quality Enhancements
  ├─ Defect Library (new)
  ├─ Shade Matching (new)
  └─ Quality Certificates (new)

Advanced Inventory
  ├─ Stock Freeze (new)
  ├─ Cycle Count (new)
  └─ Physical Verification (new)
```

**Phase 3 Dependencies (Weeks 19-30)**
```
Mobile Suite
  ├─ Barcode Scanner (hardware)
  ├─ QR Scanner (hardware)
  ├─ Production Entry API (textile)
  ├─ QC Entry API (textile)
  └─ Stock Lookup API (textile)

Maintenance Module
  ├─ Loom Master (new)
  ├─ PM Scheduling (new)
  ├─ Breakdown Tracking (new)
  └─ Machine Cost Allocation (new)

Integrations
  ├─ Barcode/QR Printers (hardware)
  ├─ Weighing Scale (hardware)
  ├─ GST (compliance)
  ├─ E-Way Bill (compliance)
  └─ Tally Export (finance)
```

### Feature Dependencies Graph
```
PurchaseOrder ← PurchaseRequisition, RFQ
GRN ← PurchaseOrder
IncomingQC ← GRN
TextileLot ← IncomingQC (creates lot)

SalesOrder ← Quotation, Customer
Allocation ← SalesOrder (approved), TextileLot (available)
Dispatch ← Allocation (released)
Challan ← Dispatch
POD ← Challan

Beam ← WarpPlan, WarpProduction
ProductionBatch ← Beam (approved)
WeavingOutput ← ProductionBatch (released)
TextileRoll ← WeavingOutput
GreyFabric ← TextileRoll

Processing ← GreyFabric
ProcessingBatch ← Processing
FinishedFabric ← ProcessingBatch

QCInspection ← GreyFabric or FinishedFabric
TextileHold ← QCInspection (fail case)
TextileRejection ← QCInspection (reject case)

Packing ← FinishedFabric (QC passed)
Dispatch ← Packing

WIPCosting ← Production (batch level)
PeriodCost ← WIPCosting (end-of-period rollup)
FinancialInvoice ← SalesOrder (via Dispatch + POD)
```

---

## Evidence Appendix

### Repository Code References

#### Textile Core Models & Tables
- [TextileSpecification](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Models/TextileSpecification.php) - Specifications create/update/deactivate ✅
- [TextileQualityProfile](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Models/TextileQualityProfile.php) - Quality profile master ✅
- [TextileRouteRecipe](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Models/TextileRouteRecipe.php) - Route/process recipes ✅
- [TextileUnitConversion](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Models/TextileUnitConversion.php) - Unit master ✅
- [TextileWorkflowDocument](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Models/TextileWorkflowDocument.php) - Universal workflow doc model ✅
- [textile_workflow_documents migration](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Database/Migrations/2026_08_02_000007_create_textile_workflow_documents_table.php) - Polymorphic doc table ✅

#### Textile Inventory Models & Tables
- [TextileLot](packages/DigitalFuzed/DigitalFuzedTextileInventory/src/Models/TextileLot.php) - Lot master with received_quantity, available_quantity ✅
- [TextileMovement](packages/DigitalFuzed/DigitalFuzedTextileInventory/src/Models/TextileMovement.php) - Receipt/Issue/Transfer movements ✅
- [TextileLocation](packages/DigitalFuzed/DigitalFuzedTextileInventory/src/Models/TextileLocation.php) - Warehouse location master ✅
- [TextileReservation](packages/DigitalFuzed/DigitalFuzedTextileInventory/src/Models/TextileReservation.php) - Lot reservations + unreserve flow ✅
- [textile_lots migration](packages/DigitalFuzed/DigitalFuzedTextileInventory/src/Database/Migrations/2026_08_02_000002_create_textile_lots_table.php) - Lot table structure ✅

#### Textile API Routes
- [TextileWorkflowApiController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/api.php) - Workflow store/transition/summary endpoints ✅
- [TextileProcurementApiController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/api.php) - Procurement (requisition/PO/GRN/QC) ✅
- [TextileSalesApiController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/api.php) - Sales (order/allocation/dispatch/challan/POD) ✅
- [TextileManufacturingApiController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/api.php) - Manufacturing (beam/batch/output/waste/rework) ✅
- [TextileProcessingApiController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/api.php) - Processing (job-work outward/inward/batch) ✅
- [TextileCostingApiController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/api.php) - Costing (cost entry + margin snapshot) ✅

#### Textile Web Routes & Controllers
- [TextileProcurementController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/web.php) - Admin UI for procurement workflow ✅
- [TextileSalesController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/web.php) - Admin UI for sales workflow ✅
- [TextileManufacturingController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/web.php) - Admin UI for manufacturing (section-focused views) ✅
- [TextileQualityController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/web.php) - Quality inspection + hold/release ✅
- [TextileProcessingController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/web.php) - Processing workflow ✅
- [TextileCostingController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/web.php) - Costing + margin tracking ✅
- [TextileDashboardController](packages/DigitalFuzed/DigitalFuzedTextileCore/src/Routes/web.php) - Textile dashboard + reports ✅

#### Test Coverage (Verification Evidence)
- [TextileProcurementAdminTest](tests/Feature/Textile/TextileProcurementAdminTest.php) - 1 test, 25 assertions; requisition/PO/GRN/QC workflows ✅
- [TextileSalesAdminTest](tests/Feature/Textile/TextileSalesAdminTest.php) - 1 test, 27 assertions; SO/allocation/dispatch/challan/POD ✅
- [TextileManufacturingAdminTest](tests/Feature/Textile/TextileManufacturingAdminTest.php) - 1 test, 25 assertions; beam/batch/weaving/waste/rework ✅
- [TextileInventoryAdminTest](tests/Feature/Textile/TextileInventoryAdminTest.php) - 1 test, 31 assertions; lot/location/movement/reservation ✅
- [TextileQualityAdminTest](tests/Feature/Textile/TextileQualityAdminTest.php) - 1 test, 18 assertions; inspection/hold/release ✅
- [TextileProcessingAdminTest](tests/Feature/Textile/TextileProcessingAdminTest.php) - 1 test, 25 assertions; job-work/batch/inward-outward reconciliation ✅
- [TextileCostingAdminTest](tests/Feature/Textile/TextileCostingAdminTest.php) - 1 test, 14 assertions; cost entry/margin snapshot ✅
- [TextileDashboardAdminTest](tests/Feature/Textile/TextileDashboardAdminTest.php) - 1 test, 10 assertions; aggregates + reports ✅
- **Total Textile Suite**: 37 tests, 308 assertions (as of 2026-08-02)

#### Core ERP Packages (Reusable)
- [Account Package](packages/DigitalFuzed/Account/src/Models/) - Customer, Vendor, ChartOfAccount, JournalEntry, Invoice ✅
- [Lead Package](packages/DigitalFuzed/Lead/src/Models/) - Lead, Deal, Pipeline, Stage, Task, Activity ✅
- [Quotation Package](packages/DigitalFuzed/Quotation/src/Models/) - SalesQuotation, SalesQuotationItem ✅
- [ProductService Package](packages/DigitalFuzed/ProductService/src/Models/) - ProductServiceItem, Category, Tax, Unit, WarehouseStock ✅
- [Hrm Package](packages/DigitalFuzed/Hrm/src/Models/) - Employee, Attendance, Shift, Payroll, Department ✅
- [DoubleEntry Package](packages/DigitalFuzed/DoubleEntry/src/Models/) - BalanceSheet, JournalEntry for financial reporting ✅
- [Contract Package](packages/DigitalFuzed/Contract/src/Models/) - Contract, Comment, Attachment for document management ✅

#### Database Migrations (App Core)
- [users_table](database/migrations/0001_01_01_000000_create_users_table.php) - Multi-tenant user model ✅
- [permissions_table](database/migrations/2025_08_12_105132_create_permission_tables.php) - Spatie permissions + roles ✅
- [warehouses_table](database/migrations/2025_08_12_105136_create_warehouses_table.php) - Multi-warehouse support ✅
- [purchase_invoices_table](database/migrations/2025_09_26_102328_create_purchase_invoices_table.php) - Purchase invoice + items + taxes ✅
- [sales_invoices_table](database/migrations/2025_09_26_102340_create_sales_invoices_table.php) - Sales invoice + items + taxes ✅
- [sales_proposals_table](database/migrations/2025_11_10_120000_create_sales_proposals_table.php) - Quotation data ✅

#### Configuration & User Management
- [User Model - industry_type field](app/Models/User.php) - Textile vs Standard industry assignment ✅
- [Users Page - industry-access.tsx](resources/js/pages/users/industry-access.tsx) - Textile enablement UI ✅
- [Settings - TextileCore check](routes/web.php) - PlanModuleCheck middleware for textile routes ✅

#### Roadmap Tracking
- [textile-current-status.md](plans/textile-current-status.md) - Official tracker with [x] completion status ✅

---

## Assumptions & Caveats

1. **Workflow Document Polymorphism**: All textile workflow documents (requisition, PO, GRN, QC, SO, allocation, dispatch, challan, beam, batch, etc.) are stored in single `textile_workflow_documents` table with `document_type` discriminator. This design is efficient for audit but may face query complexity as types proliferate.

2. **Cost Accumulation Pattern**: TextileCosting captures only high-level cost (material + labor); fabric cost per meter is a calculation, not a persisted field. Recommend adding cost_per_meter columns to avoid repeated joins.

3. **Approval Workflow**: Currently handled per-module via status field transitions (draft → approved → released). No generic approval rules engine; recommend building one for P1 extensibility.

4. **Mobile App**: All textile APIs exist and are functional; mobile UI is the gap. React Native or Flutter app needed for shop-floor entry.

5. **Finance Integration**: DoubleEntry package exists; TextileCosting → GL posting via journal entries is not yet implemented. Requires careful cost object dimensionality design (product level, cost center level, both?).

6. **Multi-Location Complexity**: Textile lots and rolls are location-aware; reserve/issue/transfer workflows assume location changes are state-managed. Recommend transactional locks on high-concurrency scenarios.

7. **Hardware Integration**: Barcode/QR printers, weighing scales, GST/E-Way Bill APIs are external dependencies; integration requires vendor-specific adapters.

8. **Data Retention**: Audit logs (TextileAuditLog) will grow rapidly at production volumes; recommend archival strategy and indexed queries.

9. **Currency & Exchange**: Multi-currency is NOT explicitly handled in textile workflows; assuming single factory currency. Recommend adding currency codes to key monetary fields.

10. **Tenant Isolation**: All textile tables use `created_by` as tenant scoping; verified in tests. Recommend adding `company_id` or `tenant_id` explicit columns for clarity and performance.

---

**Generated**: 2026-08-02 | **Analysis Date**: Session current | **Repository Commit**: Latest (plans/textile-current-status.md as source of truth)