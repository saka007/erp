# Functional Design Specification: Indian Textile Manufacturing Business Operations

## Document status and boundary

This Functional Design Specification (FDS) defines the business operating requirements of textile enterprises using Gujarat and Maharashtra textile-cluster practices as the primary reference, with pan-India variations noted where material. It is written for consultants, process owners, control teams, and developers who may never have visited a textile factory.

The document covers textile trading, decentralized powerloom or weaving operations, grey-fabric manufacture and trade, processor and job-work businesses, dyeing, printing, finishing, and vertically integrated composite mills. It describes what the business must control and why. It does not prescribe software, screens, databases, APIs, packages, code, implementation design, or any existing product mapping.

### Design principles

1. **Physical truth precedes commercial truth.** Every commercial claim must reconcile to a physically received, moved, converted, inspected, packed, dispatched, returned, consumed, lost, or scrapped quantity.
2. **Ownership and custody are separate.** Material may belong to one party while lying with another. Both must remain visible throughout job work.
3. **Textiles require multiple units.** Length, weight, pieces, packages, beams, rolls, taka or takha, lots, cones, and bales may coexist. Original and converted quantities must be retained.
4. **Lot identity must survive transformation.** Yarn lots become beams and woven grey lots; grey lots become process lots and finished lots. Splits, merges, substitutions, reprocessing, and remnants require traceability.
5. **Quality is specification-based.** A transaction is not complete merely because quantity moved; quality status and disposition must be explicit.
6. **Yield and loss are stage-specific.** Expected and actual waste, shrinkage, process loss, seconds, and remnants must be distinguished.
7. **No silent correction.** Reversal, amendment, regrade, short receipt, excess, substitution, and retrospective approval require reason and authority.
8. **Regional vocabulary must be preserved.** Local terms are legitimate business language but must have controlled definitions.

## 1. Textile business primer

### 1.1 Material states

- **Fibre:** cotton, polyester staple fibre, viscose, wool, blends, and other feedstock before spinning.
- **Yarn:** continuous strand identified by fibre, blend, count or denier, construction, twist, ply, shade where dyed, and manufacturer lot.
- **Warp:** longitudinal yarns held under tension on a loom. A prepared mass of parallel warp ends wound onto a **beam** feeds weaving.
- **Weft:** yarn inserted across warp during weaving; also called filling or pick yarn.
- **Grey or greige fabric:** fabric in loom-state, not yet wet processed or fully finished. Grey describes processing state, not necessarily colour.
- **Processed fabric:** fabric after one or more pretreatment, dyeing, printing, washing, or finishing operations.
- **Finished fabric:** inspected fabric released to customer specification and saleable presentation.
- **Made-up or garment:** converted product beyond fabric; referenced only where packing or customer specification affects fabric operations.

### 1.2 Core Indian trade terms

- **Takha or taka:** a commercially handled piece or roll of fabric, often folded or rolled, carrying a piece number and measured length. Local spelling and customary nominal length vary. It must never be assumed to equal a fixed number of metres.
- **Challan:** a controlled movement document accompanying goods. It may support sale, transfer, processing, return, sample movement, repair, or job work. A challan is not automatically an invoice.
- **Beam:** flanged cylinder carrying many parallel warp ends. Its identity links yarn issue, preparation parameters, loom loading, production, and residual warp.
- **Lot:** controlled grouping intended to be materially homogeneous or processed together. A purchase lot, yarn lot, dye lot, process lot, inspection lot, and dispatch lot are related but not interchangeable.
- **Job work:** processing undertaken by a party on material owned by another party, generally for conversion charges. The job worker has custody but not ownership unless separately agreed.
- **Party:** common Indian business term for customer, supplier, agent, broker, job worker, or principal; role must be explicit.
- **Bale:** compressed or bundled package, used for fibre, yarn, grey, or finished goods depending on context.
- **Thaan:** North Indian term broadly corresponding to a fabric piece or roll; local usage and standard length vary.
- **Palla or cut piece:** short or balance length separated from a main piece; commercial acceptance depends on agreed minimum length.
- **Loomstate:** fabric as removed from loom before processing.
- **Program:** an approved production or processing instruction for an order, design, quality, shade, route, quantity, and due date.

### 1.3 Measurement and reconciliation convention

Metric units are primary: kilograms for mass, metres for length, centimetres or inches for width where trade practice requires, GSM for grams per square metre, and linear or area-based rates as contracted. Yards, pounds, pounds-count references, denier, tex, Ne, Nm, reed-space terminology, and local package units may coexist.

For any movement or conversion, record as applicable:

- piece or package count;
- gross, tare, and net weight;
- measured length by piece and total;
- declared length where measurement is impractical at receipt;
- width and GSM basis;
- source unit, conversion factor or measured conversion, and rounding rule;
- ownership, custody location, quality status, and reservation status.

A useful reasonableness check for fabric is: theoretical weight in kilograms equals length in metres multiplied by width in metres multiplied by GSM divided by 1,000. It is a control estimate, not a substitute for actual weighing. Selvedge, moisture, finish, stretch, coating, and measurement tension cause variation.

Mass balance for a stage is: opening quantity plus receipts equals good output plus recoverable output plus approved loss plus closing work in process. Length balance must separately explain shrinkage, elongation, cuts, samples, defects removed, and measurement differences. Ownership balance must not mix own stock with principal-owned stock.

## 2. Operating models and organization

### 2.1 Trading house

Buys and sells yarn, grey, processed, or finished fabric without necessarily manufacturing. It may purchase against a customer order, hold stock, nominate external processors, or arrange direct delivery. Controls emphasize back-to-back commitments, brokerage, quality matching, custody in third-party premises, and purchase-to-sale margin.

### 2.2 Decentralized powerloom or weaving enterprise

Operates owned or contracted looms, often using external sizing, warping, twisting, or inspection. Orders and material may move among multiple small units. Controls emphasize beam, loom, shift, weaver, yarn issue and return, pick/reed construction, production length, defects, and contractor reconciliation.

### 2.3 Grey manufacturer or merchant-manufacturer

Procures yarn, arranges weaving internally or externally, receives loomstate fabric, inspects it, and sells grey or sends it for processing. It owns product specification and conversion risk even where operations are decentralized.

### 2.4 Processor or independent job worker

Receives customer-owned grey or semi-processed fabric and performs pretreatment, dyeing, printing, or finishing for processing charges. Controls emphasize principal ownership, inward piece identity, process loss, shade approval, chemical consumption, reprocessing liability, and timely return.

### 2.5 Dyeing, printing, and finishing house

May process own or customer material. Batch dyeing, continuous dyeing, rotary or flatbed printing, digital printing, stentering, calendaring, raising, coating, compacting, and other routes have distinct capacity and quality controls.

### 2.6 Composite mill

Integrates multiple stages, potentially fibre or yarn through finished fabric. Internal transfers replace some external challans, but accountability between cost centres, quality gates, intermediate inventory, and transfer pricing remains necessary.

### 2.7 Typical actors and segregation

Actors include proprietor or board, commercial head, merchandiser, sales coordinator, planner, purchase team, yarn store, chemical store, grey warehouse, finished warehouse, production manager, beam and loom supervisors, process-house manager, laboratory, quality control, packing, dispatch, job-work coordinator, engineering, utilities, safety, human resources, time office, costing, accounts, tax and compliance, security, weighbridge, transporters, brokers, agents, customers, suppliers, principals, and job workers.

No single actor should independently create a party, set commercial terms, acknowledge receipt, accept quality, approve a claim, and authorize payment for the same transaction. Small enterprises may combine roles but must use proprietor review and documented exception controls.

## 3. Master governance and common controls

### Objective and purpose

Create controlled definitions used consistently from enquiry through settlement so quantities, qualities, ownership, prices, routes, and obligations are not reinterpreted at each department.

### Actors and master data

Data stewards are nominated for parties, products and qualities, yarn, designs and shades, recipes and routes, equipment, warehouses, tax classifications, commercial terms, quality standards, and workforce rates. Approval owners include commercial, technical, quality, finance, and compliance heads.

Essential master domains are:

- legal party, location, role, credit terms, broker, tax registration, transport instruction, approved status, and related-party status;
- fibre, yarn count or denier, composition, blend tolerance, ply, twist, shade, spinning method, package, manufacturer and lot attributes;
- fabric quality: construction, weave, ends and picks, reed and pick basis, width at defined stage, GSM, composition, selvedge, permissible defects, shrinkage and finish;
- design, colourway, shade standard, print repeat, screen or engraving reference, artwork ownership and version;
- process route, operation sequence, standard loss, standard output, capacity basis, sampling and hold points;
- machine, loom, beam, vessel, stenter chamber, printing machine, inspection table, utility meter, capacity, constraints and calibration status;
- location and sublocation including own, rented, consignment, job-worker, quarantine, rejected, scrap and in-transit custody;
- units and conversions with context, effective date, precision and allowable tolerance;
- commercial terms: rate basis, taxes where applicable, packing, freight, brokerage, quality claim, quantity tolerance, delivery, payment and ownership transfer;
- defect, reason, loss, rejection, rework, downtime, hazard, claim and approval codes.

### Governance workflow and documents

A department raises a signed master request with source evidence. The steward checks duplication and naming, the technical or commercial owner validates attributes, finance or compliance validates legal implications, and an authorized person approves activation. Amendment preserves prior effective values. Dormant or blocked records remain historically identifiable.

Documents and registers include master request, change register, approved vendor or job-worker list, approved quality catalogue, route and recipe standard, rate circular, authorization matrix, unit-conversion register, and blocked-master register.

### Rules, validations, exceptions, and approvals

- Names alone do not identify textile quality; minimum technical attributes are mandatory.
- Customer aliases and supplier trade names map to one legal party without losing dispatch naming needs.
- A quality, shade, recipe, route, or piece-rate revision has an effective date and approver.
- Width must state stage and measurement condition; loom width cannot silently substitute finished width.
- Standard loss and tolerance are route- and material-specific, not universal percentages.
- Duplicate design, shade, party, and yarn-lot identifiers are prevented or formally cross-referenced.
- Emergency temporary definitions expire unless regularized.

### Problems, best practices, notifications, reports, and KPIs

Common failures are duplicate qualities, verbal rate changes, confused units, wrong party role, reused shade names, and obsolete recipes. Best practice is one accountable steward per domain, periodic duplicate review, and sample or swatch linkage to written standards. Notify affected departments before effective changes and escalate use of blocked or expired definitions. Reports include pending requests, changes by period, duplicates, inactive masters used in open commitments, and missing attributes. KPIs include first-time-right requests, approval aging, duplicate rate, and transactions requiring master override.

Dependencies and integration: every operational chapter depends on this governance; commercial commitments cannot be planned, purchased, produced, tested, costed, or invoiced consistently without it.

## 4. Commercial lifecycle: enquiry to settlement

### Objective and purpose

Convert market demand into technically feasible, profitable, creditworthy, and deliverable commitments, then close quantity, quality, logistics, claim, and payment obligations.

### Actors and data

Customer, broker or agent, sales, merchandising, development, planning, quality, costing, credit control, production, purchase, dispatch and accounts participate. Required references include customer, quality, design, colourway, finish, width, GSM, composition, quantity, tolerance, rate basis, delivery schedule, destination, packing, test standard, inspection terms, sample approval, tax status, payment and claim terms.

### Transactions and end-to-end workflow

1. **Enquiry:** capture requirement and ambiguities; register sample, swatch, artwork, or counter sample received.
2. **Feasibility:** technical teams determine achievable construction, route, shade, finish, yield, minimum lot, machine compatibility, testing and development need.
3. **Cost and quotation:** estimate material, conversion, processing, loss, packing, freight, commission, finance exposure and margin. State validity and assumptions.
4. **Sample development:** create lab dip, strike-off, handloom, sample yardage, finish trial, or counter sample as relevant. Record iterations and customer comments.
5. **Order acceptance:** compare customer order with final offer. Resolve deviations before acknowledgement. Credit and capacity approvals precede commitment.
6. **Order program:** split by quality, design, shade, delivery, route and ownership. Reserve stock or initiate procurement and production.
7. **Execution monitoring:** review material readiness, production, external job work, quality holds, packing and dispatch against due date.
8. **Dispatch and commercial document:** release only approved goods; invoice or job-charge document follows the legal substance. Generate e-way bill where legally and operationally required.
9. **Post-delivery:** obtain proof of delivery, resolve shortage or quality claims, process authorized return or allowance, collect payment, settle broker commission, and close order only after all balances are explained.

### Inputs, outputs, documents, and registers

Inputs include enquiry, physical sample, specification, forecast and customer order. Outputs include approved specification, quote, order acknowledgement, development approvals, production program, dispatch instruction, invoice, delivery proof and closure note. Registers include enquiry, quotation, sample, order amendment, pending order, customer approval, dispatch commitment, claim, return, credit note, collection and commission.

### Rules and validations

- Order acknowledgement must state whether quantity tolerance applies per shade, line, shipment, or total order.
- Rate basis must identify metre, kilogram, piece, area, or lump-sum service and the chargeable measured stage.
- Broker commission requires named broker, basis, trigger and treatment of returns or bad debt.
- A sample approval does not replace written construction and performance requirements.
- Changes after production begins require impact assessment for raw material, WIP, obsolete stock, delivery and price.
- Direct dispatch from supplier or job worker requires independent quantity and quality evidence and clear ownership transfer.
- Credit override, below-floor margin, excess quantity, substitute quality, late delivery, and nonconforming release require authorized approval.

### Exceptions and common problems

Handle cancellation, partial acceptance, split destinations, short closing, excess production, customer-supplied material, development failure, shade-wise imbalance, rejected delivery, transit damage, delayed approval and disputed claim. Frequent problems include selling by an ambiguous trade name, accepting impossible finished width, excluding processing shrinkage, and promising delivery before route capacity is checked.

### Best practices, notifications, reports, and KPIs

Use a signed specification sheet, retain reference samples under controlled conditions, freeze approval cut-offs, and run daily risk review by order. Notify sales of feasibility gaps, planning of amendments, quality of special standards, finance of credit exposure, and customer of approval or delivery risks. Escalate overdue customer approvals and milestones based on promised date.

Reports include order book, contribution by order, sample conversion, delivery risk, amendment impact, excess and short quantity, claims, returns, overdue receivables and broker settlement. KPIs include enquiry conversion, sample approval cycle, order acknowledgement accuracy, on-time-in-full delivery, gross contribution, claim rate, return rate and collection days.

Integration: drives planning, procurement, production, laboratory, quality, dispatch, costing and receivables.

## 5. Demand, material, capacity and production planning

### Objective and purpose

Translate orders and forecasts into feasible material, machine, labour, external-capacity and dispatch plans while protecting traceability and due dates.

### Actors, data, transactions, and workflow

Sales, merchandising, planner, production heads, purchase, stores, lab, quality, maintenance and job-work coordination use order priorities, route, standard consumption and loss, stock status, reservations, machine capability, calendars, changeover, batch size, labour and utility constraints.

Planning proceeds from demand consolidation to net requirement, route selection, lot-sizing, capacity loading, material reservation, production or job-work program release, daily sequencing, progress feedback, replanning and closure. A frozen near-term plan limits disruptive changes; an authorized override documents the displaced work.

### Documents, rules, exceptions, and controls

Documents include demand plan, material requirement, shortage report, capacity plan, beam plan, loom plan, dyeing and finishing plan, printing design schedule, job-work plan, maintenance block, dispatch plan and daily production meeting record.

- Do not count quarantine, rejected, customer-owned, or reserved stock as freely available.
- Plan length backward from required finished length using route-specific shrinkage, cut and rejection allowances; independently validate expected weight.
- Dyeing lots respect vessel loading range; printing respects design and colour change sequence; weaving respects beam and loom compatibility.
- Combine orders only where ownership, quality, shade standard, traceability and commercial terms permit.
- Separate forecast commitment from firm order and record deallocation authority.

Exceptions include urgent order insertion, yarn or chemical shortage, machine breakdown, power restriction, delayed approval, capacity overload, abnormal loss and subcontractor failure. Common problems are optimistic capacity, hidden WIP, repeated priority changes, and treating all metres as equivalent.

Best practices include constraint-based daily planning, visible shortage ownership, campaign planning to reduce cleaning and shade change, and actual-versus-standard feedback. Notify owners on shortage, milestone slippage, overloaded resources and approval dependency. Reports cover load versus capacity, material readiness, WIP aging, order milestone, subcontract exposure and plan adherence. KPIs include plan adherence, schedule stability, capacity utilization, WIP days, shortage incidents and forecast accuracy.

## 6. Procurement, inward logistics and stores

### Objective and purpose

Obtain conforming yarn, grey, dyes, chemicals, auxiliaries, packing, spares, fuels and services at required time and total cost; safeguard stock and supplier accountability.

### Actors and master data

User department, purchase, approved supplier, broker, transporter, gate security, weighbridge, stores, laboratory, quality, accounts and compliance participate. Data includes approved source, specification, manufacturer, rate and unit, lead time, minimum quantity, tax and freight terms, shelf life, hazard class, storage condition and inspection plan.

### Workflow and transactions

1. Requirement or indent identifies purpose, required date, specification and stock context.
2. Purchase obtains quotations or applies contract, completes technical and commercial comparison, negotiates, and secures authority.
3. Purchase order defines quantity tolerance, quality, manufacturer or lot restrictions, packing, delivery, documents and rejection terms.
4. Gate records arrival, seal and vehicle condition. Weighbridge records gross and tare where relevant.
5. Stores counts packages and records declared quantity; quality samples without contaminating or mixing lots.
6. Accepted stock moves from quarantine to usable custody; rejected or conditionally accepted material is segregated.
7. Invoice matching compares order, receipt, quality acceptance, agreed charges and returns before payment.
8. Shortage, excess, damage, quality rejection, debit, replacement and return close explicitly.

### Stores controls by category

**Yarn:** retain manufacturer lot, count, blend, shade, package count, gross/net weight, cone or hank condition and moisture concern. Do not mix lots for warp or visible products without technical approval. Record issue, return, sweep waste, hard waste, damaged cones and empty packing where recoverable.

**Grey and fabric:** piece-level identity, length, width, weight, defects, ownership and quality status are essential. Avoid direct floor stacking; protect from water, oil, dust and mix-up.

**Dyes and chemicals:** retain batch, manufacture and expiry, concentration or strength, hazardous classification, storage compatibility, first-expiry-first-out, opened-container control, and test or certificate references. Segregate acids, alkalis, oxidizers, reducers and flammables appropriately.

**Spares and consumables:** classify criticality, equipment applicability, minimum and maximum holding, repairable or returnable nature, and warranty.

### Documents, registers, approvals, and validations

Documents include indent, request for quotation, comparison, purchase order, amendment, gate entry, weighbridge slip, supplier challan, receipt note, inspection or test report, rejection note, material return, debit note support and invoice-match record. Registers include open orders, overdue supply, quarantine, shelf life, nonmoving stock, rejected custody, supplier claims and returnables.

Validations include approved source, order tolerance, matching unit, manufacturer lot, package arithmetic, gross less tare equals net, expiry suitability, mandatory certificate and independent receipt. Unordered or emergency receipt requires designated acceptance and retrospective order control; it must not become routine.

### Problems, best practices, notifications, reports, and KPIs

Common failures are count mismatch, mixed yarn lots, duplicate receipt, unrecorded direct delivery, moisture-related weight dispute, expired chemicals and unidentified remnants. Use sealed samples for contentious materials, periodic physical count, cycle counting by risk, bin discipline, and aging review. Notify purchase and production of rejection or shortage; escalate shelf-life, negative balance, blocked stock use and overdue replacement.

Reports cover open purchase commitments, supplier delivery, quality acceptance, price variance, inventory aging, slow and nonmoving stock, stock discrepancy, shelf-life risk and supplier claims. KPIs include on-time-in-full supply, incoming rejection, purchase price variance, inventory turns, stock accuracy, emergency purchases and supplier claim closure.

Integration: planning creates need; quality releases stock; stores issue to production or job work; accounts settles only reconciled obligations.

## 7. Yarn preparation: winding, doubling, twisting, warping and sizing

### Objective and purpose

Prepare yarn into a loom-ready warp and suitable weft while preserving yarn-lot integrity and achieving required strength, package, tension and construction.

### Actors and data

Planner, yarn store, preparation supervisor, operators, sizing kitchen, laboratory, quality, maintenance and external preparator use yarn specification, beam plan, end count, warp length, creel pattern, colour sequence, size recipe, stretch, add-on, speed and allowable breaks.

### Transactions and workflow

Yarn is issued by lot and weight to winding, doubling or twisting if required. Usable packages proceed to warping according to end plan. Sectional or direct warping creates a warper beam; sizing applies prepared size to improve abrasion resistance and strength, dries yarn, and winds the weaver beam. Drawing-in or tying-in places ends through drop wires, healds and reed. Prepared beams are inspected, weighed or length-assessed, identified, transferred to loom shed, and residual yarn and waste are returned and classified.

External preparation follows principal-to-job-worker challan, acknowledged receipt, operation report, prepared-beam return, residual yarn return, waste declaration, service charge and reconciliation.

### Inputs, outputs, documents, registers, and quality controls

Inputs: yarn, size chemicals, beam shell, plan and recipe. Outputs: loom-ready beam, prepared weft, residual yarn, recoverable and nonrecoverable waste. Documents include yarn issue, preparation program, warping sheet, sizing batch sheet, recipe record, beam card, quality check, breakdown record, return note and waste record. Registers track beam history, yarn lot usage, size consumption, breakage, waste and external balances.

Validate yarn lot and count, number of ends, pattern, beam shell identity, planned versus actual length, size recipe issue and return weights. Monitor size add-on, moisture, stretch, yarn strength, elongation, hairiness and end breaks. Recipe deviation, mixed lot, short beam, damaged flange and excessive breaks require hold and disposition.

### Problems, best practices, notifications, reports, and KPIs

Common issues are wrong end count, crossed ends, uneven tension, over or under sizing, migration, hard size, mixed yarn lots, excessive waste and untraceable residuals. Use first-beam approval, calibrated length and tension control, recipe discipline, beam-card attachment and waste segregation. Alert weaving to short or risk beams and planning to preparation shortfall. KPIs include preparation yield, size add-on consistency, end breaks per unit length, beam rejection, waste percentage, preparation plan adherence and loom performance attributable to preparation.

Dependencies: planning, yarn stores, chemical stores, lab, weaving, maintenance, costing and external job-work reconciliation.

## 8. Weaving and decentralized powerloom control

### Objective and purpose

Convert warp and weft into grey fabric meeting construction, width, appearance and length requirements with accountable machine, beam, shift and worker performance.

### Actors and master data

Weaving manager, loom supervisor, fitter, weaver, helper, quality checker, yarn store, beam section, planner, maintenance and outside weaver use loom capability, fabric construction, weave design, reed, pick density, width, selvedge, warp beam, weft lot, speed, efficiency and defect limits.

### Workflow and transactions

Loom allocation confirms machine suitability. Beam gaiting or knotting loads warp; approved weft is issued. A start-up sample verifies construction and appearance before bulk run. Shift production records beginning and ending counter, stoppage, pieces doffed, weft use, defects and operator. Each takha receives unique piece identity tied to loom, beam, yarn lots, date and shift. Beam exhaustion or style change records residual warp and loom clearance. Grey moves to inspection under transfer count and length; yarn, waste and empty packages return to store.

For outside weaving, issue yarn or beam under challan, record contractor custody, receive grey by piece and weight, record residual yarn and waste, inspect, calculate accepted production and conversion charges, and close shortages or claims. The principal must not treat yarn issue as sale unless ownership actually transfers.

### Documents and registers

Loom plan, beam loading card, weft issue and return, loom production sheet, piece or takha ticket, doffing record, stoppage register, defect patrol, beam-end report, waste record, outside-weaving challan, contractor production statement and contractor reconciliation.

### Rules, validations, approvals, and exceptions

- Only released beam and weft lots may be used; substitution requires technical authorization and traceability.
- Counter length, takha measured length, aggregate transfer length and theoretical weight must be reasonably consistent.
- Piece identity cannot be reused after rejection, splitting or joining; genealogy is retained.
- Construction is checked at start, after intervention and periodically.
- Production credit and piece-rate earning are based on accepted rules, not unchecked loom counter alone.
- Over-picks, under-picks, wrong design, mixed weft, oil stain, temple mark, stop mark, broken end, reed mark and excessive mending require coded disposition.
- Unplanned short piece or palla must be declared, not hidden in a full-length label.

Exceptions include beam damage, loom breakdown, power failure, yarn shortage, construction drift, abnormal warp breaks, contractor delay, lost challan and quantity shortage. Authorization determines reweave, downgrade, mending, acceptance concession, scrap or claim.

### Problems, best practices, notifications, reports, and KPIs

Common failures are inflated counters, duplicate piece numbers, undocumented weft substitution, delayed doffing entry, mixed contractor stock and waste leakage. Use physical piece tickets at doffing, independent grey receipt, daily beam and yarn reconciliation, defect feedback by loom and fitter, and surprise contractor stock verification.

Notify quality and supervisor on recurring critical defect; planning on beam or loom delay; stores on abnormal yarn usage; maintenance on repeat stoppage; commercial on likely short closure. Reports cover loom and shift production, efficiency, stoppage, beam status, yarn consumption, contractor balance, defect map and pending grey receipt. KPIs include loom efficiency, picks or metres per loom shift, warp and weft stop rate, first-quality percentage, yarn-to-grey yield, defect rate, downtime, contractor yield and plan adherence.

## 9. Grey inspection and warehouse

### Objective and purpose

Establish the reliable quantity and quality baseline before grey is sold, processed, or accepted from internal or external weaving.

### Actors, data, workflow, and transactions

Grey receiver, inspection operator, quality supervisor, warehouse, weaving representative, planner and commercial team use fabric specification, point or defect grading rules, standard piece length, width and GSM tolerance, ownership and order allocation.

Receive sealed or counted pieces against transfer or challan. Verify piece identity, count, condition and declared totals. Inspect on a suitable table or machine; measure actual length and width, check construction and GSM at defined frequency, map defects, grade each piece, and determine mending or rejection. Split or join only under controlled identity. Assign lot and location, reserve or release for sale or processing, and maintain ownership and quality status.

### Documents, controls, and outputs

Documents include grey inward, inspection sheet, piece map, grading report, mending note, shortage or excess note, lot formation, warehouse transfer, process issue and grey sale dispatch instruction. Registers include piece stock, loom-wise defects, contractor-wise acceptance, short pieces, held stock, mending, rejected stock, aging and missing pieces.

Outputs are accepted first quality, seconds, mendable, rejected, sample cuts and accurately declared stock. Piece count, piece-level length and aggregate length reconcile. Weight serves as independent reasonableness control. Width condition, including reed or loomstate width, is explicit. Customer-owned and own grey are physically and administratively segregated.

### Business rules, exceptions, and best practices

Never overwrite declared loom length with inspected length without preserving both. Measurement machine calibration, fabric tension and conditioning rules must be consistent. Grade rules are customer or quality specific; a generic grade must not override a stricter order. Sample cutting, defect removal and short-piece creation require authorization and quantity adjustment.

Problems include double counting between loom and warehouse, hidden pallas, oily floor damage, inspection inconsistency and mixed ownership. Use barcode-independent durable physical marking, roll-end seals where warranted, rack location discipline, checker rotation and retained defect examples. Escalate unexplained length or weight variance, critical defects, missing pieces and aging holds.

Reports include piece ledger, grey stock by quality and ownership, inspection yield, defect Pareto, loom and contractor performance, mending pending, aging, reservations and process readiness. KPIs include first-quality yield, inspection throughput, length variance, missing-piece incidence, hold aging and defect recurrence.

Integration: closes weaving output, establishes processor inward baseline, supports grey sales, quality claims, planning and costing.

## 10. Wet-processing lifecycle and process-lot control

### Objective and purpose

Transform grey into stable, reproducible processed fabric through controlled preparation, coloration, printing, washing and finishing while maintaining lot genealogy, recipe compliance, quality and mass balance.

### Common actors, data, documents, and workflow

Processing planner, grey store, batcher, laboratory, colour kitchen, chemical store, machine operators, process quality, maintenance, utilities, inspection, customer or principal and job-work coordinator participate. Data includes fibre blend, construction, design, shade, finish, route, batch size, machine suitability, recipe, parameters, test standard and loss tolerance.

Generic workflow: inward and grey release; piece joining and batch formation; pretreatment; intermediate testing; dyeing or printing; washing and fixation; finishing; final testing and inspection; packing; return or dispatch. Each stage records input pieces, metres and weight; machine; start and end time; parameters; chemical issue and actual addition; samples; output; hold; reprocess; loss; and operator or supervisor.

Common documents are process program, lot or batch card, piece stitching sheet, recipe and chemical requisition, machine operation sheet, lab report, shade approval, process transfer, reprocess authorization, loss statement and lot closure. Registers cover WIP by stage, machine loading, chemical use, recipe deviation, reprocess, shade holds, damages and customer-owned balances.

### 10.1 Pretreatment

**Purpose:** remove size, oils, waxes, natural impurities and contaminants; improve absorbency, whiteness and dimensional readiness.

Operations may include singeing, desizing, scouring, bleaching, mercerizing, heat setting, washing and drying. Inputs are grey fabric, water, steam, chemicals and route instructions. Outputs are prepared fabric, effluent, extracted impurities, samples and loss.

Controls include stitch quality, batch direction, singeing intensity, desizing effectiveness, absorbency, whiteness, residual peroxide or alkali, pH, width, GSM, strength and weight loss. Polyester blends may require heat setting; cotton routes differ. Mercerization requires caustic concentration, tension and washing control.

Exceptions include incomplete desizing, tendering, uneven absorbency, crease, hole, rope mark and excessive weight loss. Rewash or retreatment requires technical authorization because repeat exposure can damage fabric.

### 10.2 Dyeing

**Purpose:** impart uniform, reproducible colour meeting shade, fastness and physical requirements.

Batch dyeing groups compatible pieces within machine loading range. Continuous or semi-continuous dyeing follows pad liquor, expression, dwell, fixation and washing controls. Recipe issuance identifies dye batch strength, chemicals, liquor ratio or pickup, temperature-time profile, pH and addition sequence.

Laboratory approval establishes target, but bulk behaviour must be monitored through first-batch and running samples. Shade is assessed under defined light sources against an approved standard, considering metamerism. Lot mixing after dyeing is prohibited unless shade continuity and customer rules permit.

Validate machine load, material-to-liquor basis, recipe calculation, dye and chemical identity, actual additions, curve, sample decision and final shade. Exceptions are unlevel dyeing, tailing, listing, crease, patch, off-shade, poor fastness and lot-to-lot variation. Decisions include correction, topping, stripping and redyeing, downgrade or concession. Each additional process records expected damage and cost responsibility.

### 10.3 Printing

**Purpose:** apply controlled design and colour placement to prepared or dyed fabric.

The route covers artwork and repeat approval, engraving or screen preparation, strike-off, colour paste preparation, machine setup, blanket or screen checks, registration, bulk printing, drying, ageing or fixation, washing, finishing and inspection. Rotary, flatbed and digital routes have distinct constraints.

Controls include design and colourway version, repeat size, screen sequence, paste batch, viscosity, mesh or engraving reference, fabric face and direction, registration, penetration, colour yield, fixation, fastness and end-to-end continuity. Screen, blanket or nozzle condition is monitored.

Exceptions include misregistration, screen mark, doctor streak, colour splash, pinhole, crease, print paste variation, stop mark, missing colour, faulty repeat and artwork mismatch. Strike-off approval is not permission to use a different bulk substrate or pretreatment without review.

### 10.4 Finishing

**Purpose:** achieve required width, hand feel, dimensional stability, appearance and performance.

Operations may include stentering, drying, heat setting, calendaring, compacting, sanforizing, raising, shearing, sueding, coating, lamination, resin, softener, water or oil repellency, flame-retardant or other functional finishes.

Controls include overfeed, width, temperature, speed, dwell, chamber condition, weft straightening, bow and skew, finish pickup, moisture, GSM, shrinkage, handle, residual formaldehyde or regulated substance where relevant, and functional performance. Width or GSM correction must not sacrifice agreed strength, shrinkage or finish.

Exceptions include over-width, under-width, GSM miss, harsh handle, yellowing, scorch, selvedge damage, bow, skew, poor shrinkage and finish failure. Refinish requires quality and technical approval.

### Cross-stage rules, problems, best practices, alerts, reports, and KPIs

- A process lot lists every input piece and every resulting piece, including splits, joins, cuts and samples.
- Customer-owned material remains separately valued and reconciled even when processed beside own material.
- Recipe substitution, parameter bypass, skipped test or route change requires recorded authority.
- Process loss is approved only after physical and genealogical reconciliation; it is not a balancing plug.
- Reprocessing is separately identified and attributed to cause and responsible stage.

Common problems include WIP without identity, recipe changed verbally, mixed shades, unrecorded cuts, steam or water instability, late testing and unexplained loss. Best practice uses route cards travelling with lots, stage gates, first-lot approval, chemical dispensing checks, standardized light and conditioned testing, and daily WIP aging review.

Notify laboratory on shade drift; maintenance or utilities on parameter instability; customer coordinator on approval delay or likely loss; quality on critical failure; management on repeat reprocessing. Reports include lot location and aging, stage yield, recipe consumption, machine load, shade status, reprocess Pareto, loss, right-first-time and due-date risk. KPIs include right-first-time percentage, processing loss, shade pass rate, reprocess rate, machine utilization, chemical variance, water and energy per kilogram or metre, stage lead time and on-time lot completion.

## 11. Laboratory, colour kitchen and shade control

### Objective and purpose

Translate colour and performance requirements into repeatable laboratory and bulk standards, control chemical preparation, and provide independent evidence for release.

### Actors, data, transactions, and workflow

Customer, merchandising, laboratory technician, colourist, quality, dyeing or printing production, chemical store and colour kitchen use substrate identity, approved standard, light sources, tolerances, dye and chemical batches, recipe version, test method and equipment calibration.

Register physical or digital target; condition samples; prepare lab dips or print strike-offs; test and compare; submit variants with unambiguous codes; record customer selection and comments; freeze approved standard; scale to bulk; check first bulk lot; manage correction; retain approved and bulk reference samples. Colour kitchen receives an authorized recipe, weighs and dissolves ingredients, labels batch, verifies issue to the correct machine and returns or disposes residue safely.

### Documents and controls

Lab request, sample receipt, lab-dip card, strike-off record, recipe trial, approval sheet, standard swatch custody, bulk shade report, fastness report, colour-kitchen batch sheet, weighing check, calibration register and retained-sample register.

Use defined illuminants and assessment conditions. Spectral or instrumental values supplement, not automatically replace, agreed visual assessment. Dye strength and batch variation require correction protocol. Recipe scale-up accounts for substrate, machine, liquor or pickup and process differences. Two-person verification is used for high-impact weighing where practicable.

Exceptions include metamerism, contaminated standard, expired dye, scale error, approval ambiguity, customer changing standard, and lab-to-bulk mismatch. Do not relabel a failed shade as a new approved shade without commercial authorization.

Reports cover pending approvals, iteration count, bulk pass, recipe reproducibility, test failure, dye-batch performance and calibration due. KPIs include first-submission approval, lab-to-bulk right-first-time, approval turnaround, weighing error, shade correction and retained-standard traceability. Escalate approvals blocking production, repeated bulk mismatch, expired calibration and disputed standards.

## 12. Internal and external job work

### Objective and purpose

Control outsourced or inter-unit conversion without losing ownership, custody, material identity, quantity, quality, due date, waste, liability or charge accuracy.

### Actors and master data

Principal, job worker, job-work coordinator, gate, warehouse, quality, planning, transport, accounts and compliance use approved operation, rate basis, expected yield or loss, turnaround, supplied and consumable responsibility, quality standard, scrap ownership, insurance, subcontract permission and claim terms.

### End-to-end workflow

Select approved job worker after capability, capacity, compliance and quality review. Issue a job order or written instruction. Dispatch identified material under challan stating ownership, operation and quantity by all relevant units. Obtain acknowledged receipt and discrepancy report within defined time. Monitor WIP and approvals. Receive converted material, residual material, recoverable waste, samples and process documents. Inspect independently. Reconcile input, accepted output, rejected or reprocess quantity, authorized loss, scrap and outstanding custody. Approve conversion charge only on contract basis and accepted evidence. Close challan lines and statutory job-work obligations where applicable.

Internal job work between sites follows the same physical controls even if no external commercial charge exists.

### Documents, rules, exceptions, and approvals

Documents include job order, outward challan, transporter proof, inward acknowledgment, process report, return challan, receipt and inspection, waste return, shortage statement, claim, rate approval, service bill check and closure certificate. Registers include party-wise custody, challan aging, operation-stage WIP, material overdue, rejected or reprocess pending, scrap and open reconciliation.

- Ownership never changes merely due to physical dispatch for job work.
- Job worker cannot merge principals' lots or subcontract without authorization.
- Rate basis identifies accepted input, accepted output, machine run, kilogram, metre, piece or agreed minimum.
- Normal loss is not automatic entitlement; actual and allowable loss are compared.
- Job worker-added material is identified and quality-approved.
- Direct dispatch from job worker to customer requires principal release, complete traceability and dispatch evidence.
- Shortage adjustment, scrap retention, excess process loss and damage require authorized commercial disposition.

Exceptions include short receipt, excess receipt, mixed pieces, missing lot identity, job worker closure, seizure or accident, delayed return, quality failure, unauthorized subcontract, obsolete material and customer cancellation. Periodic physical confirmation and management escalation are required for aged custody.

Common problems are perpetual open challans, paying on dispatched rather than accepted quantity, hidden subcontracting, disputed moisture or measurement, and treating normal loss as a plug. Best practices include operation-specific agreements, piece-level annexures, acknowledgment deadlines, surprise stock confirmation and no new issue against seriously overdue reconciliation.

Reports cover party custody by age, challan reconciliation, yield, quality, turnaround, rate variance, recoverable waste, claims and dependency concentration. KPIs include on-time return, first-pass acceptance, unexplained shortage, reconciliation aging, conversion cost, subcontractor concentration and claim recovery.

Integration: commercial, planning, stores, every production stage, quality, logistics, costing, accounts and operational GST documentation.

## 13. Finished inspection, packing and dispatch

### Objective and purpose

Release only conforming, correctly measured, traceable and commercially authorized goods; protect them through packing and delivery.

### Actors, data, workflow, and transactions

Final quality, inspection operator, warehouse, merchandising, packing, dispatch, transporter, security, accounts and customer inspector use release specification, grading rules, shade grouping, packing standard, destination, shipment tolerance, marking and transport condition.

Condition fabric where required; inspect every piece or approved sample plan; measure length, width, GSM and defects; test required performance; grade and shade-group; mend or rework if permitted; approve or hold. Fold, roll, bale or carton-pack using protective material, assign package identity and packing list, weigh, reserve to order, pick, verify, load, seal where required, issue challan or invoice and e-way bill where legally required, record vehicle and obtain proof of delivery.

### Inputs, outputs, documents, and registers

Input is process-complete fabric with lot genealogy and test evidence. Outputs are released first quality, seconds, rejected, remnants, samples, packed stock and dispatched goods. Documents include final inspection, test certificate, concession, packing instruction, piece and package list, bale sheet, dispatch authorization, loading checklist, challan, invoice, e-way bill where applicable, lorry receipt and proof of delivery. Registers cover finished stock, quality holds, shade bands, packing material, dispatch, transit and customer returns.

### Rules, validations, exceptions, and approvals

- Package totals reconcile to contained piece count, measured length and net weight.
- Piece label states actual length; nominal length cannot conceal shortage.
- Shade groups and roll sequence are preserved where customer cutting continuity matters.
- Seconds or commercial-grade goods are explicitly marked and approved; labels cannot imply first quality.
- Dispatch quantity, customer, destination, order, packing and vehicle are independently checked.
- No quality-hold material is loaded without concession authority.
- E-way bill and GST documents are generated only according to current legal applicability and actual movement; operational teams must not invent tax treatment.

Exceptions include short shipment, excess pack, damaged packing, missing piece, truck breakdown, wrong destination, transit shortage, refused delivery, customer return and export or customer inspection hold. Returned goods remain segregated pending identity, condition, tax-document and disposition review.

Common problems include metre mismatch between inspection and packing, swapped labels, mixed shade bales, moisture damage and dispatch without test release. Use scan-independent physical count, sealed packing list copy, preloading checklist, dry vehicle inspection and dispatch photographs where risk warrants.

Reports cover released versus held stock, order-ready stock, packing variance, dispatch performance, in-transit aging, proof-of-delivery pending and returns. KPIs include final first-quality yield, packing accuracy, on-time-in-full dispatch, transit damage, measurement claim, return rate and proof-of-delivery turnaround.

## 14. Quality management and claims

### Objective and purpose

Prevent defects, independently verify conformity, manage nonconformance consistently, and turn failure evidence into corrective action.

### Actors and master data

Quality head, incoming, in-process and final inspectors, laboratory, production, commercial, supplier or job worker, customer and management use specification, test method, sampling plan, tolerance, defect severity, grade rule, calibration requirement and disposition authority.

### Workflow and transactions

Plan control points from incoming through final dispatch. Inspect or test and record actual result against requirement. Accepted material is released; nonconforming material is held and physically segregated. Review determines rework, reprocess, return, replacement, downgrade, concession, scrap or claim. Identify root cause, corrective and preventive action, responsibility and effectiveness check. Customer complaints link to original lot, pieces, production history and retained samples.

### Documents, registers, rules, and exceptions

Documents include inspection plan, test report, hold tag, nonconformance report, deviation or concession, rework instruction, root-cause record, supplier corrective action, customer complaint, claim settlement and calibration record. Registers include holds, failures, concessions, claims, corrective action, instruments and audit findings.

Critical characteristics and safety or regulatory failures cannot be averaged away. Sample acceptance applies only under approved sampling rules. Production cannot self-release its own disputed failure without quality authority. Concession identifies exact quantity, defect, customer or internal authority and commercial effect. Retest follows defined method and cannot continue until a passing result appears.

Exceptions include disputed test method, damaged retained sample, instrument failure, latent defect, mixed lot and customer standard change. Use an independent accredited laboratory where contract or dispute requires.

Common problems are subjective grading, unauthorized release, repeated rework, weak root cause and claim settlement without quantity proof. Best practice is defect libraries, calibrated instruments, layered audits, cross-functional daily review and effectiveness verification.

Reports include defect Pareto by source, hold aging, right-first-time, cost of poor quality, supplier or job-worker rating, claims and corrective-action overdue. KPIs include incoming rejection, process right-first-time, first-quality yield, reprocess, customer claim per dispatched quantity, cost of poor quality, closure aging and recurrence. Critical failures trigger immediate stop and containment; overdue holds and corrective actions escalate by age and risk.

## 15. Costing, commercial accounting and operational statutory documents

### Objective and purpose

Establish realistic product and order economics, value physical flows consistently, verify conversion obligations, and support compliant movement and billing without allowing accounting assumptions to distort factory truth.

### Actors and data

Costing, commercial, planning, production, stores, quality, job-work coordination, accounts and authorized tax personnel use material standards, actual issue and return, yields, conversion rates, machine or process rates, labour, utility drivers, packing, freight, commission, overhead policy, taxes and claim terms.

### Cost lifecycle

Pre-cost an enquiry using expected route and loss; freeze order estimate on acceptance; accumulate actual yarn, grey, dyes, chemicals, packing, internal conversion, external job work, labour, utility and attributable overhead; recognize waste or by-product consistently; compare estimate to actual; explain rate, usage, yield, mix, quality and capacity variances; close after all WIP, subcontract balances and claims settle.

Trading margin separately identifies purchase price, processing, freight, brokerage, discount, claims, finance exposure and selling realization. Job-worker profitability distinguishes customer-owned material from worker-owned consumables and conversion revenue.

### Documents, controls, and rules

Documents include cost sheet, rate approval, material variance, process-cost statement, job-work bill verification, freight and commission support, claim or debit support, stock valuation review and order closure. Reports include order and quality profitability, stage cost, loss cost, reprocessing cost, job-worker comparison, inventory and WIP valuation, margin bridge and slow-stock provision.

- Standard and actual cost remain distinguishable.
- Customer-owned material is quantitatively controlled but not valued as owned inventory.
- Free issue, sample, seconds, scrap and abnormal loss receive explicit disposition.
- Conversion bill quantity agrees with the contracted rate basis and accepted physical quantity.
- GST classification, place or time of supply, job-work treatment, invoice, debit or credit note, and input credit decisions belong to authorized tax policy.
- E-way bill operational details must agree with actual consignor, consignee, goods, value basis, vehicle and movement; cancellations or vehicle changes are controlled.
- Challan, invoice and goods movement are cross-referenced but not treated as identical documents.

Exceptions include provisional rates, retrospective price settlement, related-party transfer, free-of-charge processing, customer debit, abnormal loss, obsolete stock and disputed job-work bill. Approval levels depend on financial and control impact.

Common problems are ignoring shrinkage in quotations, valuing customer stock as own, paying unreconciled job work, hiding reprocessing in standard cost and tax documents inconsistent with movement. Best practices include monthly physical-to-cost reconciliation, order closure discipline, variance ownership and specialist tax review.

KPIs include estimate-to-actual variance, contribution, material yield variance, conversion variance, reprocessing cost, inventory and WIP days, job-work balance aging, claim recovery and margin leakage. Escalate negative margin, unreconciled high-value custody, abnormal loss, obsolete WIP and statutory document mismatch.

## 16. Workforce, attendance and piece rate

### Objective and purpose

Deploy competent labour safely, record attendance and output fairly, calculate time or piece earnings transparently, and prevent production incentives from overriding quality.

### Actors and data

Human resources, time office, contractor, department supervisor, worker, quality, safety and payroll use identity, skill, grade, shift, department, contractor, wage or piece-rate agreement, machine assignment, accepted-output rule, overtime authorization and statutory eligibility.

### Workflow and controls

Verify worker and contractor compliance before deployment. Record entry, attendance, shift and machine or operation assignment. Supervisor records actual job and output; quality determines accepted, reworked or rejected quantity under published earning rules. Downtime, changeover, training, helper allocation and team sharing are coded. Authorized rates calculate gross earning, adjustments and deductions; worker receives comprehensible statement and dispute route.

Documents include muster, shift roster, skill matrix, deployment, production ticket, piece-rate sheet, downtime, overtime approval, contractor bill verification, training and grievance record. Registers cover attendance, overtime, skill authorization, piece output, rejection, accidents and contractor workers.

Rates are effective-dated and jointly approved by production, HR and finance. Loom counter or gross metres alone do not determine earnings where quality acceptance applies. Rejection deduction must follow lawful and communicated policy, not arbitrary supervisor action. No worker operates equipment without required competency and safety induction. Overtime and weekly-rest practices comply with applicable law and establishment conditions.

Exceptions include machine breakdown, shared loom, trainee output, absent reliever, rework responsibility, disputed rejection and emergency overtime. Common problems are proxy attendance, inflated output, undocumented rate changes and incentive causing concealed defects. Best practices combine productivity and quality, conduct worker acknowledgment, reconcile production to payroll and rotate sensitive verification.

Reports include manpower plan versus actual, attendance, overtime, output and earning by operation, quality-adjusted productivity, absenteeism, turnover, skill gaps and contractor deployment. KPIs include labour productivity, attendance, overtime ratio, rejection-adjusted earning, absenteeism, turnover, training compliance and payroll-to-production reconciliation. Escalate unsafe understaffing, excessive overtime, rate disputes and attendance-output anomalies.

## 17. Utilities and environmental operations

### Objective and purpose

Provide reliable electricity, steam, water, compressed air, thermic fluid, gas or fuel, refrigeration and effluent treatment at required quality and cost while controlling environmental obligations.

### Actors and data

Utility manager, boiler and electrical operators, water treatment, effluent treatment plant, production, maintenance, safety, laboratory and compliance use capacity, pressure, temperature, flow, water quality, fuel quality, energy tariff, discharge norms, consent conditions and meter hierarchy.

### Workflow, documents, and controls

Plan demand by production schedule; start and monitor equipment; record generation, purchase, distribution and consumption; test water and effluent; respond to excursions; maintain treatment chemicals and sludge; reconcile main meters with departmental meters; investigate abnormal use.

Documents include shift log, meter reading, boiler log, water-quality test, fuel receipt and issue, power interruption, effluent-treatment log, discharge or reuse record, sludge disposal evidence and statutory inspection. Registers cover consumption, peak demand, breakdown, emissions, calibration and compliance samples.

Utilities must be measured on consistent boundaries. Production cannot bypass treatment or environmental controls to meet output. Meter failure uses approved estimation and prompt repair. Boiler, pressure system and electrical operation require competent authorized personnel.

Exceptions include grid outage, low gas pressure, boiler trip, water shortage, treatment failure, effluent excursion and emergency generator use. Trigger production curtailment where safe quality cannot be maintained.

Common problems are unmetered loss, condensate waste, variable water hardness, steam leakage, compressed-air leaks and dilution used to mask effluent noncompliance. Best practice includes departmental energy review, condensate recovery, preventive testing, emergency response and water reuse consistent with product quality.

Reports include energy and water balance, consumption by stage, peak demand, fuel efficiency, condensate recovery, effluent volume and quality, downtime and compliance. KPIs include kWh, steam, fuel and water per kilogram or metre; power factor; utility availability; treatment compliance; reuse percentage; and utility cost. Escalate legal-limit excursion immediately and intensity variance by threshold.

## 18. Maintenance, engineering and calibration

### Objective and purpose

Maintain equipment capability, safety, accuracy and availability while balancing preventive work with production priorities.

### Actors and data

Production, mechanical and electrical maintenance, instrumentation, utilities, stores, original equipment supplier, contractor, safety and quality use asset identity, hierarchy, criticality, manual, maintenance plan, spare list, lubrication, breakdown history, calibration range and statutory inspection due date.

### Workflow and transactions

Register and critically classify assets. Plan preventive, predictive, lubrication, cleaning, statutory and calibration work. Production hands over equipment in safe condition; maintenance isolates energy, performs work, records parts and findings, tests, and returns equipment. Production and quality verify capability where product quality may be affected. Breakdown work records symptom, response, repair, downtime, root cause and recurrence action.

Documents include work request, maintenance order, permit and isolation, inspection checklist, spare issue and return, breakdown report, calibration certificate, trial acceptance, contractor service report and history card. Registers cover preventive due, breakdown, critical spare, calibration, statutory examination and repeat failure.

No bypass of safety interlock without formal risk control and temporary authorization. Overdue calibration equipment cannot support release unless risk-assessed alternative verification exists. Cannibalization and temporary repair are recorded and time-bound. Capital modification updates operating, safety and maintenance standards.

Exceptions include catastrophic failure, unavailable spare, obsolete machine, emergency bypass and vendor delay. Common problems are firefighting maintenance, no failure coding, wrong spare, hidden micro-stoppages and production refusing planned shutdown. Best practices use criticality-based planning, operator basic care, planned shutdown windows, root-cause analysis and critical-spare review.

Reports include availability, downtime Pareto, maintenance compliance, spare consumption, repeat failures, mean time between failure, mean time to repair, calibration due and maintenance cost. KPIs include equipment availability, preventive compliance, breakdown hours, repeat failure, maintenance cost, critical-spare stockout and calibration compliance. Safety-critical and production-stopping failures escalate immediately; recurring failures escalate after defined recurrence.

## 19. Safety, legal and operational compliance

### Objective and purpose

Prevent injury, fire, chemical exposure, unsafe machinery and environmental harm; maintain evidence that operations meet applicable permissions and duties.

### Actors and data

Occupier and management, safety officer, department heads, workers, contractors, security, medical support, fire team, environmental team and competent external authorities use hazard register, legal register, safety data sheets, permit requirements, emergency plan, training matrix and inspection schedule.

### Workflow and controls

Identify hazards and assess risk before routine work and change. Establish engineering controls, safe procedures and personal protection. Induct employees, visitors and contractors. Control chemical receipt, labelling, storage, dispensing and spill response. Use permit-to-work for hot work, confined space, height, electrical isolation and other high-risk activity. Inspect guards, fire systems, exits and emergency equipment. Report all incidents and near misses, provide response, investigate root cause, correct conditions and verify effectiveness. Conduct drills and management review.

Documents include risk assessment, safety data sheet, training record, permit, lockout or isolation, inspection, incident and near-miss report, medical or exposure record as applicable, drill report, waste manifest and legal compliance register.

Production pressure never authorizes bypass of guards, interlocks, permits or effluent controls. Unlabelled chemical is quarantined. Incompatible chemicals are segregated. Contractor safety obligations are verified, not transferred by contract alone. Emergency changes undergo post-event review.

Exceptions include spill, fire, exposure, serious injury, unsafe contractor act, statutory notice and environmental excursion. Stop work, isolate, assist, notify and preserve evidence according to risk and law.

Common problems are paper-only training, blocked exits, decanted chemical without label, bypassed guards, casual hot work and underreported near misses. Best practices include visible leadership rounds, worker reporting without retaliation, process-specific drills, change management and independent audit.

Reports include leading and lagging indicators, hazards open, permit compliance, training due, incidents, near misses, corrective-action age, fire and equipment inspection and environmental compliance. KPIs include recordable and lost-time incidents, near-miss reporting, closure rate, training compliance, audit findings, spill count and permit violation. Imminent danger and reportable events escalate immediately under the emergency and legal notification matrix.

## 20. Cross-functional controls and interdepartmental integration

### 20.1 Lot genealogy and ownership control

Every quantity has an owner, custodian, location, material state, quality status and source reference. Genealogy supports one-to-many split, many-to-one merge and repeated process, but only under approved compatibility. A piece can change length, weight, grade and package while preserving ancestry.

Own material, customer material, consignment, returnable packing, recoverable waste and scrap are separately identified. Periodic confirmation covers stock at outside parties and outside-party stock on site.

### 20.2 Quantity, weight and length reconciliation

At every handoff, sending and receiving departments agree count and declared quantity; receiving measurement may create a documented variance, not erase the sender's figure. Reconciliation uses:

- yarn: packages and net weight, with moisture or conditioning concern identified;
- beams: beam count, ends and expected or measured length, plus yarn weight where practical;
- grey and finished fabric: piece count, measured length, width and net weight;
- wet processing: input and output by piece, length and weight with stage-specific shrinkage or gain;
- chemicals: issued, returned, actual consumed and residue or disposal;
- job work: sent, acknowledged, returned good, rejected, residual, waste, authorized loss and outstanding.

Tolerance triggers investigation; it does not automatically approve a variance. Loss codes distinguish normal process loss, measurement difference, sample, cut, quality removal, evaporation or moisture change, recoverable waste, damage, theft or unexplained shortage.

### 20.3 Status and hold control

Standard physical statuses are received pending check, quarantine, released, reserved, issued, in process, awaiting approval, held, rejected, rework or reprocess, seconds, scrap, packed, in transit, delivered and returned. Physical segregation and visible identification support status. Aging escalation applies to quarantine, approval, reprocess, rejected and in-transit statuses.

### 20.4 Approval and exception matrix

Authorities are defined by event and impact: commercial deviation, credit, purchase, rate, master amendment, material substitution, recipe or route change, excess loss, quality concession, reprocess, downgrade, scrap, stock adjustment, job-worker shortage, overtime, safety bypass prohibition and claim settlement. The requester cannot approve their own high-risk exception.

### 20.5 Cut-off and physical verification

At period close, freeze or identify movements around cut-off; count selected high-risk and high-value material; confirm goods in transit and third-party custody; reconcile production not yet received, challans not acknowledged, processing lots between stages, packed not dispatched, and returns not dispositioned. Differences receive root cause and approval.

### 20.6 Notification and escalation framework

Immediate escalation: safety event, legal excursion, critical quality defect, ownership mix-up, missing high-value lot, major breakdown, suspected fraud or wrong-customer dispatch.

Time-based escalation: overdue sample approval, purchase, job-work acknowledgment or return, quality hold, WIP stage, maintenance action, customer proof of delivery, claim, reconciliation and collection. Escalations state owner, consequence, required decision and next deadline rather than merely sending reminders.

### 20.7 Cross-functional management reports

Minimum daily views: order risk, material shortage, machine and utility availability, production versus plan, lot WIP and holds, quality exceptions, job-worker overdue and dispatch readiness.

Minimum periodic views: order profitability, stock and WIP aging, physical variance, yield and loss by stage, first-quality and reprocess, supplier and job-worker scorecard, customer claims, energy and water intensity, maintenance reliability, workforce productivity, safety and compliance, and corrective-action aging.

### 20.8 Enterprise KPIs

Use balanced measures to prevent local optimization:

- service: on-time-in-full, order lead time, approval delays and backlog;
- quality: right-first-time, first-quality yield, claims, reprocess and recurrence;
- quantity: stage yield, unexplained shortage, stock accuracy and job-work balance;
- cost: contribution, material and conversion variance, poor-quality cost and waste recovery;
- asset: utilization, availability, plan adherence and WIP days;
- people: productivity, attendance, skill and safety;
- sustainability and compliance: energy, water, effluent, waste and legal closure.

## 21. Operating-model variants

### Trading-only variant

Production chapters become supplier and nominated-processor controls rather than internal departments. Direct delivery is common, but the trader still requires independent specification, ownership-transfer, quantity, quality, claim and margin evidence. Stock may be physically absent yet commercially owned.

### Merchant manufacturer with decentralized weaving

Planning, yarn procurement, beam or yarn issue, contractor custody, grey inspection and contractor reconciliation are central controls. Contractor-wise stock confirmation and lot segregation are more important than internal machine utilization.

### Grey fabric seller

Wet processing may be absent. Finished-good quality means approved grey grade, construction, width, length and packing. Commercial claims often arise from defects discovered only after the buyer processes fabric; retained samples and piece genealogy are therefore critical.

### Independent processor or job worker

Most raw fabric is customer-owned. Revenue is conversion charge, while principal material remains quantitatively accountable. Capacity, route, shade approval, process loss, reprocessing liability, chemical consumption and open challan closure dominate. Own and customer orders must never be confused.

### Dyeing or printing house with own trading

The same plant handles own and principal material. Capacity and chemical resources may be shared, but ownership, costing, quality obligations and priority decisions remain separate. Related-party or internal orders cannot invisibly displace committed customer job work.

### Composite mill

Internal transfers replace some external documents but retain sender-receiver acceptance, stage yield, quality gate and cost-centre accountability. The primary risk is hiding inefficiency in aggregate yield; each stage therefore closes separately before enterprise consolidation.

### Make-to-order and make-to-stock

Make-to-order prioritizes customer specification, approval and delivery traceability. Make-to-stock uses approved market qualities, demand policy, stock-aging limits and replenishment triggers. Forecast production must not consume customer-reserved material or obscure speculative exposure.

### Regional and pan-India variation

Gujarat and Maharashtra clusters commonly use terms such as takha, grey, challan, beam, lot, job work and party, with decentralized weaving and processing networks. Elsewhere, thaan may replace takha; yards may remain prominent; market grading, brokerage, standard piece length and settlement customs vary. Local custom may inform a contract but never replace explicit definition of quantity, rate basis, quality, ownership and liability.

## 22. Consolidated exception catalogue

The enterprise must provide defined response and authority for:

- customer amendment, cancellation, delayed approval or refused delivery;
- credit block, price dispute, broker dispute or negative margin;
- supplier short, excess, late, damaged, wrong-lot or failed material;
- mixed ownership, missing lot identity, duplicate piece or unacknowledged challan;
- yarn substitution, beam shortage, wrong construction, loom breakdown or abnormal waste;
- grey length discrepancy, short piece, mending, downgrade or latent defect;
- recipe deviation, shade mismatch, machine overload, process loss, reprocess or sample cut;
- job-worker delay, unauthorized subcontract, shortage, closure or disputed conversion bill;
- failed test, concession, instrument breakdown or retained-sample dispute;
- package mismatch, wrong label, vehicle failure, transit loss, return or proof-of-delivery delay;
- attendance anomaly, rate dispute, skill gap or unsafe overtime;
- power, water, steam, fuel or effluent emergency;
- overdue maintenance, unavailable critical spare, temporary repair or calibration failure;
- accident, spill, fire, legal notice, environmental excursion or suspected fraud.

Each exception record identifies affected material and orders, physical containment, owner and custodian, quantity by relevant units, immediate action, root cause, commercial and quality consequence, approving authority, due date and final closure evidence.

## 23. Glossary

- **Absorbency:** ability of prepared fabric to take up liquid uniformly.
- **Add-on:** material added as a percentage of substrate weight, such as size or finish.
- **Auxiliary:** chemical supporting dyeing, printing, washing or finishing rather than providing primary colour.
- **Beam:** flanged cylinder carrying parallel warp yarns for preparation or weaving.
- **Bow and skew:** distortion of weft lines into curved or angled alignment across fabric.
- **Broker:** intermediary facilitating business, generally earning commission without automatically owning goods.
- **Challan:** document evidencing physical movement for a stated purpose; not inherently a sale invoice.
- **Colourway:** approved combination of colours for a design.
- **Composite mill:** enterprise integrating multiple textile conversion stages under common ownership.
- **Cone:** common yarn package wound on a conical support.
- **Construction:** fabric technical build, commonly including ends, picks, yarn counts, weave, width, composition and GSM.
- **Count:** yarn fineness measure. Systems differ; higher Ne generally means finer cotton yarn, while denier increases with filament thickness.
- **Custody:** physical possession and safeguarding responsibility, distinct from legal ownership.
- **Denier:** grams per 9,000 metres of filament yarn.
- **Desizing:** removal of warp size from woven grey fabric.
- **Doffing:** removal of a completed fabric piece or yarn package from a machine.
- **Ends:** warp threads; ends per inch or centimetre describes warp density.
- **Fastness:** resistance of colour to washing, rubbing, light, perspiration or other exposure.
- **Gaiting:** mounting and setting a warp beam and related warp path on a loom.
- **Grey or greige:** unprocessed loomstate fabric; not a colour description.
- **GSM:** grams per square metre, a fabric mass-per-area measure.
- **Hand or handle:** tactile feel of fabric.
- **Heald or heddle:** element controlling a warp end to form the shed.
- **Job work:** conversion service on material owned by the principal or customer.
- **Lab dip:** small dyed sample offered for shade approval.
- **Listing:** side-to-centre shade variation.
- **Liquor ratio:** process bath volume relative to material weight.
- **Loomstate:** condition of fabric directly after weaving.
- **Lot:** controlled material grouping defined for traceability or joint processing.
- **Mercerizing:** controlled caustic treatment, commonly of cotton, to modify lustre, strength and dye affinity.
- **Metamerism:** two colours matching under one light but differing under another.
- **Palla or cut piece:** short balance or separated piece below normal commercial piece length.
- **Party:** business counterparty; its exact role must be identified.
- **Pick:** one weft insertion; picks per inch or centimetre describes weft density.
- **Piece:** individually identified continuous fabric length; may be a roll, folded takha or thaan.
- **Piece rate:** worker compensation based on defined accepted output or operation.
- **Principal:** owner of material sent to a job worker.
- **Process lot:** compatible fabric grouped for a defined processing route or machine batch.
- **Reed:** comb-like loom element spacing warp ends and beating weft into place.
- **Reprocess:** repeat or additional processing intended to correct a failure.
- **Right-first-time:** output passing required stage without correction or reprocessing.
- **Sanforizing or compacting:** mechanical finishing to control residual shrinkage.
- **Scouring:** removal of natural and added impurities to improve cleanliness and absorbency.
- **Seconds:** downgraded goods not meeting first-quality standard but saleable under explicit grade.
- **Selvedge:** finished longitudinal edge of woven fabric.
- **Shade band:** sequence of representative samples showing within-lot or roll-to-roll shade variation.
- **Shed:** opening formed between warp yarn groups through which weft is inserted.
- **Singeing:** burning projecting fibres from fabric surface.
- **Sizing:** application of protective preparation to warp yarn before weaving.
- **Stenter:** machine that dries or heat-treats fabric under controlled width and overfeed.
- **Strike-off:** small printed sample used to approve design, colour and print effect.
- **Takha or taka:** identified fabric piece or roll in western Indian trade usage; not a fixed length unless contractually defined.
- **Tailing:** progressive shade variation along processing length.
- **Tex:** grams per 1,000 metres of yarn.
- **Thaan:** fabric piece or roll term common in North Indian trade.
- **Warp:** longitudinal yarn system in woven fabric.
- **Warping:** assembling many warp ends in parallel onto a beam.
- **Weft:** crosswise yarn inserted through warp; also called pick or filling.
- **WIP:** work in process physically between raw material and completed output.
- **Yield:** acceptable output relative to input on a clearly stated quantity basis.

## 24. Functional completeness checklist

Before any later solution assessment, business owners must confirm that each applicable module has agreed:

1. objective and business purpose;
2. accountable actors and segregation;
3. governed master data and terminology;
4. transactions and complete workflow;
5. physical and informational inputs and outputs;
6. documents and registers;
7. approvals and authority limits;
8. business rules and validations;
9. exception handling and closure evidence;
10. upstream and downstream dependencies;
11. known problems and preventive best practices;
12. notifications and escalation triggers;
13. operational and management reports;
14. balanced KPIs;
15. interdepartmental handoffs;
16. piece, lot and batch genealogy;
17. quantity, length and weight reconciliation;
18. ownership, custody and quality status reconciliation;
19. operating-model and regional variations;
20. safety, quality, commercial and statutory consequences.

## 25. Explicit stopping point

This document ends with the consultant-first definition of textile manufacturing business operations. It intentionally stops before any Reuse, Extend, Modify, or New analysis and before any comparison with, mapping to, or design for a software product. No software implementation conclusions should be inferred from this FDS.
