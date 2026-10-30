# Verdeco Cost Control — Manufacturing Cost Accounting Formulas & Methodology

This document details the cost accounting formulas, standard costing logic, variance breakdown equations, and allocation methods implemented across the Verdeco Cost Control system. It serves as the official methodology reference for the corporate controller, plant cost accountants, and external audit teams.

---

## 1. System-Wide Accounting Foundations

### 1.1 Precision Arithmetic
To guarantee absolute financial integrity, all calculations are executed using arbitrary-precision decimal mathematics via PHP's `bcmath` extension (`DecimalMath` service). Floating-point arithmetic (`float`) is strictly prohibited.
- Currency totals: 2 decimal places (`DECIMAL(18, 2)`).
- Weights ($kg$): 4 decimal places (`DECIMAL(18, 4)`).
- Unit rates and variances ($\$/kg$): 6 decimal places (`DECIMAL(18, 6)`).

### 1.2 Metric vs. Imperial Conversion
All weights are stored internally in **Kilograms ($kg$)**. For US operations plants, display conversion to **US Pounds ($lb$)** uses the exact statutory constant:
$$\text{Weight}_{lb} = \text{Weight}_{kg} \times 2.20462$$
$$\text{Cost per lb} = \frac{\text{Cost per kg}}{2.20462}$$

### 1.3 Price Snapshotting & Immutability
When a batch is run or closed, active material prices, labor wage tables, and utility tariff schedules are frozen onto the batch record. Subsequent changes to master rate tables never alter historical closed runs.

---

## 2. Direct Batch Costing Formulas

### 2.1 Direct Material Cost & Mass Balance
Verdeco processes purchased hot-washed clear and colored PET flake.
- **Gross Input**: Total flake weighed into feed hoppers:
  $$\text{Input Kg} = \sum_{i=1}^{n} \text{Input Kg}_i$$
- **Direct Material Cost**:
  $$\text{Material Cost} = \sum_{i=1}^{n} \left( \text{Input Kg}_i \times \text{Price Snapshot}_i \right)$$
- **Yield Percentage**:
  $$\text{Yield \%} = \left( \frac{\text{Good Output Kg}}{\text{Total Input Kg}} \right) \times 100$$
- **By-Product & Recovery Credit**: Downgrade and off-spec pellets sold to secondary non-food markets generate a recovery credit that offsets net production cost:
  $$\text{Recovery Credit} = (\text{Downgrade Kg} \times P_{downgrade}) + (\text{Offspec Kg} \times P_{offspec})$$

### 2.2 Direct Labor Cost
Captures direct extrusion line operators and packaging technicians:
- **Regular Labor**:
  $$\text{Regular Labor Cost} = \text{Regular Hours} \times \text{Base Wage Rate}$$
- **Overtime Labor**:
  $$\text{Overtime Labor Cost} = \text{Overtime Hours} \times \text{Base Wage Rate} \times \text{Overtime Multiplier}$$
- **Total Direct Labor Cost**:
  $$\text{Labor Cost} = \text{Regular Labor Cost} + \text{Overtime Labor Cost}$$
- **Overtime Premium Cost (Isolated)**:
  $$\text{OT Premium} = \text{Overtime Hours} \times \text{Base Wage Rate} \times (\text{Overtime Multiplier} - 1.0)$$

### 2.3 Utilities Cost & Energy Intensity
Captures electrical consumption from heavy extrusion drives, melt pumps, pelletizer water systems, and SSP heating elements:
- **Utility Cost**:
  $$\text{Utility Cost} = \text{Utility kWh} \times \text{Electricity Tariff Snapshot}$$
- **Energy Intensity Metric**:
  $$\text{Energy Intensity (kWh/kg)} = \frac{\text{Utility kWh}}{\text{Good Output Kg}}$$

### 2.4 Net Batch Cost & Unit Cost
$$\text{Total Direct Cost} = \text{Material Cost} + \text{Labor Cost} + \text{Utility Cost} + \text{Allocated Overhead} + \text{Allocated Incident Cash}$$
$$\text{Net Batch Cost} = \text{Total Direct Cost} - \text{Recovery Credit}$$
$$\text{Cost per Kg} = \frac{\text{Net Batch Cost}}{\text{Good Output Kg}}$$

---

## 3. Overhead Allocation Methodologies

Plants operate with shared fixed and indirect manufacturing overhead pools (plant management salaries, quality lab testing supplies, factory insurance, facility depreciation). Overhead is absorbed onto batches using one of four configurable basis rules:

### 3.1 Proportional to Output Weight (`by_kg` / `per_kg`)
$$\text{Batch Overhead} = \text{Daily Pool} \times \left( \frac{\text{Batch Good Output Kg}}{\sum \text{Daily Output Kg}} \right)$$

### 3.2 Proportional to Input Weight (`by_input_kg`)
$$\text{Batch Overhead} = \text{Daily Pool} \times \left( \frac{\text{Batch Input Kg}}{\sum \text{Daily Input Kg}} \right)$$

### 3.3 Proportional to Line Operating Hours (`by_hours`)
$$\text{Batch Overhead} = \text{Daily Pool} \times \left( \frac{\text{Batch Hours}}{\sum \text{Daily Line Hours}} \right)$$

### 3.4 Equal Distribution (`equal`)
$$\text{Batch Overhead} = \frac{\text{Daily Pool}}{N_{\text{closed batches}}}$$

### 3.5 Fixed Overhead Volume Under-Absorption
When plant output falls below budgeted nameplate capacity, fixed overhead cannot be fully absorbed by production:
$$\text{Standard Overhead Absorbed} = \text{Actual Good Output Kg} \times \text{Standard Overhead Rate per Kg}$$
$$\text{Under-Absorption Volume Variance} = \text{Budgeted Monthly Fixed Overhead} - \text{Standard Overhead Absorbed}$$
A positive variance indicates unfavorable under-absorption (capacity idle penalty).

---

## 4. Unplanned Incident Cost Module

Incidents (screen blockages, melt-pump seizure, die contamination, off-spec pellet rework, power flicker) capture two strictly separated financial metrics:

### 4.1 Cash Cost (Hard Money Out-of-Pocket)
Real dollars spent immediately to rectify the incident:
- Emergency replacement parts and filter media.
- Third-party contractor emergency labor.
- Overtime labor dedicated specifically to clean-up or rework.
- Hazardous or scrap disposal fees.

### 4.2 Opportunity Cost (Lost Contribution Margin)
Economic loss due to lost plant capacity, displayed in management reports but **never** blended into GAAP product cost:
$$\text{Lost Output Kg} = \text{Downtime Hours} \times \text{Rated Line Kg/Hour}$$
$$\text{Contribution Margin per Kg} = \text{Average Selling Price} - \text{Standard Variable Cost per Kg}$$
$$\text{Opportunity Cost} = \text{Lost Output Kg} \times \text{Contribution Margin per Kg}$$

---

## 5. Variance Analysis & Standard-to-Actual Bridge

Verdeco decomposes the total difference between planned standard cost and actual realized manufacturing cost into seven discrete operational variance components:

### 5.1 Material Price Variance ($MPV$)
Measures the impact of paying more or less for PET flake feedstock than standard:
$$MPV = (\text{Actual Price per Kg} - \text{Standard Price per Kg}) \times \text{Actual Flake Input Kg}$$

### 5.2 Material Usage / Yield Variance ($MUV$)
Measures the financial impact of yield loss (fines, screen purge waste, startup scrap) relative to standard yield expectation:
$$\text{Standard Allowed Input Kg} = \frac{\text{Actual Good Output Kg}}{\text{Standard Yield \%}}$$
$$MUV = (\text{Actual Flake Input Kg} - \text{Standard Allowed Input Kg}) \times \text{Standard Price per Kg}$$

### 5.3 Labor Rate Variance ($LRV$)
Measures shift wage rate differentials:
$$LRV = (\text{Actual Base Wage Rate} - \text{Standard Labor Rate}) \times \text{Actual Labor Hours}$$

### 5.4 Labor Efficiency & Overtime Variance ($LEV$)
Measures labor productivity and unplanned overtime premium:
$$\text{Standard Allowed Hours} = \text{Actual Good Output Kg} \times \left( \frac{\text{Standard Hours per Ton}}{1000} \right)$$
$$LEV = (\text{Actual Total Labor Hours} - \text{Standard Allowed Hours}) \times \text{Standard Labor Rate} + \text{OT Premium}$$

### 5.5 Utility Efficiency Variance ($UEV$)
Measures energy intensity drifts above or below baseline standard:
$$UEV = (\text{Actual kWh per Kg} - \text{Standard kWh per Kg}) \times \text{Actual Good Output Kg} \times \text{Standard Electricity Rate}$$

### 5.6 Overhead Volume Variance ($OVV$)
$$OVV = (\text{Budgeted Monthly Output Kg} - \text{Actual Monthly Output Kg}) \times \text{Standard Overhead Rate per Kg}$$

### 5.7 Unplanned Incident Variance ($IV$)
Total cash cost incurred from approved unplanned cost incidents:
$$IV = \sum \text{Allocated Incident Cash Costs}$$

### 5.8 Complete Standard-to-Actual Cost Reconciliation
$$\text{Actual Manufacturing Cost} = \text{Standard Cost} + MPV + MUV + LRV + LEV + UEV + OVV + IV$$
This forms the mathematical basis for the **Plant Standard-to-Actual Waterfall Bridge Widget**.

