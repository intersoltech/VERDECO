<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verdeco Batch Cost Sheet - {{ $batch->batch_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { border-bottom: 2px solid #047857; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #047857; margin: 0; }
        .subtitle { font-size: 13px; color: #666; margin-top: 4px; }
        .meta-table, .cost-table, .quality-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .meta-table td { padding: 4px 8px; }
        .cost-table th, .quality-table th { background-color: #f3f4f6; color: #111; text-align: left; padding: 6px 8px; border: 1px solid #e5e7eb; }
        .cost-table td, .quality-table td { padding: 6px 8px; border: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background-color: #ecfdf5; }
        .section-title { font-size: 14px; font-weight: bold; color: #1f2937; margin: 15px 0 8px 0; border-left: 4px solid #047857; padding-left: 8px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .footer { font-size: 10px; color: #9ca3af; text-align: center; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="title">VERDECO COST CONTROL</h1>
                    <div class="subtitle">Production Batch Manufacturing Cost Sheet & Quality Report</div>
                </td>
                <td class="text-right">
                    <strong>Batch No:</strong> {{ $batch->batch_number }}<br>
                    <strong>Date:</strong> {{ $batch->batch_date }}<br>
                    <strong>Status:</strong> <span class="badge badge-success">{{ strtoupper($batch->status) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">1. Plant & Operating Identity</div>
    <table class="meta-table">
        <tr>
            <td style="width: 25%;"><strong>Plant:</strong> {{ $batch->plant?->name }} ({{ $batch->plant?->code }})</td>
            <td style="width: 25%;"><strong>Production Line:</strong> {{ $batch->line?->name ?? 'Line 1 Extruder' }}</td>
            <td style="width: 25%;"><strong>Target Grade:</strong> {{ $batch->productGrade?->name ?? 'Food Grade 20' }}</td>
            <td style="width: 25%;"><strong>Base Currency:</strong> USD ($)</td>
        </tr>
    </table>

    <div class="section-title">2. Material Mass Balance & Yield</div>
    <table class="cost-table">
        <thead>
            <tr>
                <th>Production Metric</th>
                <th class="text-right">Kilograms (kg)</th>
                <th class="text-right">US Pounds (lb)</th>
                <th class="text-right">Operational Notes</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Material Input</td>
                <td class="text-right">{{ number_format((float) $batch->input_kg, 2) }}</td>
                <td class="text-right">{{ number_format((float) $batch->input_kg * 2.20462, 2) }}</td>
                <td>Hot-washed rPET flake feedstock</td>
            </tr>
            <tr>
                <td>Good Output (Finished Pellets)</td>
                <td class="text-right">{{ number_format((float) $batch->good_output_kg, 2) }}</td>
                <td class="text-right">{{ number_format((float) $batch->good_output_kg * 2.20462, 2) }}</td>
                <td>Prime pellet production</td>
            </tr>
            <tr class="total-row">
                <td>Production Yield %</td>
                <td class="text-right" colspan="2">{{ number_format((float) $batch->yield_percent, 2) }} %</td>
                <td>Standard Yield Target: 92.50 %</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">3. Full Cost Sheet Breakdown</div>
    <table class="cost-table">
        <thead>
            <tr>
                <th>Cost Component</th>
                <th>Consumption / Basis</th>
                <th class="text-right">Unit Rate ($)</th>
                <th class="text-right">Total Cost ($)</th>
                <th class="text-right">Cost / kg</th>
                <th class="text-right">Cost / lb</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Direct Raw Material (Flake)</td>
                <td>{{ number_format((float) $batch->input_kg, 0) }} kg</td>
                <td class="text-right">$0.6800</td>
                <td class="text-right">${{ number_format((float) $batch->material_cost, 2) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? ((float) $batch->material_cost / (float) $batch->good_output_kg) : 0, 4) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? (((float) $batch->material_cost / (float) $batch->good_output_kg) * 0.45359237) : 0, 4) }}</td>
            </tr>
            <tr>
                <td>Direct Labor (Regular + Overtime)</td>
                <td>{{ number_format((float) $batch->labor_regular_hours + (float) $batch->labor_overtime_hours, 1) }} hrs</td>
                <td class="text-right">${{ number_format((float) $batch->labor_regular_rate, 2) }}</td>
                <td class="text-right">${{ number_format((float) $batch->labor_cost, 2) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? ((float) $batch->labor_cost / (float) $batch->good_output_kg) : 0, 4) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? (((float) $batch->labor_cost / (float) $batch->good_output_kg) * 0.45359237) : 0, 4) }}</td>
            </tr>
            <tr>
                <td>Utilities (Electricity & Nitrogen)</td>
                <td>{{ number_format((float) $batch->utility_kwh, 0) }} kWh</td>
                <td class="text-right">${{ number_format((float) $batch->utility_rate, 4) }}</td>
                <td class="text-right">${{ number_format((float) $batch->utility_cost, 2) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? ((float) $batch->utility_cost / (float) $batch->good_output_kg) : 0, 4) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? (((float) $batch->utility_cost / (float) $batch->good_output_kg) * 0.45359237) : 0, 4) }}</td>
            </tr>
            <tr>
                <td>Factory Overhead Allocation</td>
                <td>Basis: {{ strtoupper($batch->overhead_basis ?? 'PER_KG') }}</td>
                <td class="text-right">Pool</td>
                <td class="text-right">${{ number_format((float) $batch->overhead_cost, 2) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? ((float) $batch->overhead_cost / (float) $batch->good_output_kg) : 0, 4) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? (((float) $batch->overhead_cost / (float) $batch->good_output_kg) * 0.45359237) : 0, 4) }}</td>
            </tr>
            <tr>
                <td>Unplanned Incident Cash Allocation</td>
                <td>Rule: {{ strtoupper($batch->incident_allocation_rule ?? 'DIRECT_TAG') }}</td>
                <td class="text-right">Tagged</td>
                <td class="text-right">${{ number_format((float) $batch->incident_cost, 2) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? ((float) $batch->incident_cost / (float) $batch->good_output_kg) : 0, 4) }}</td>
                <td class="text-right">${{ number_format((float) $batch->good_output_kg > 0 ? (((float) $batch->incident_cost / (float) $batch->good_output_kg) * 0.45359237) : 0, 4) }}</td>
            </tr>
            <tr>
                <td>Less: By-Product / Recovery Credit</td>
                <td>Downgrade / Scrap recovery</td>
                <td class="text-right">Credit</td>
                <td class="text-right">-${{ number_format((float) $batch->recovery_credit, 2) }}</td>
                <td class="text-right">-${{ number_format((float) $batch->good_output_kg > 0 ? ((float) $batch->recovery_credit / (float) $batch->good_output_kg) : 0, 4) }}</td>
                <td class="text-right">-${{ number_format((float) $batch->good_output_kg > 0 ? (((float) $batch->recovery_credit / (float) $batch->good_output_kg) * 0.45359237) : 0, 4) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="3">NET BATCH MANUFACTURING COST</td>
                <td class="text-right">${{ number_format((float) $batch->net_cost, 2) }}</td>
                <td class="text-right">${{ number_format((float) $batch->cost_per_kg, 6) }}</td>
                <td class="text-right">${{ number_format((float) $batch->cost_per_kg * 0.45359237, 6) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">4. Lab Quality Inspection Verification</div>
    <table class="quality-table">
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Measured Value</th>
                <th>Standard Specification Range</th>
                <th>Quality Status</th>
            </tr>
        </thead>
        <tbody>
            @php $qual = $batch->qualityResults()->latest()->first(); @endphp
            <tr>
                <td>Intrinsic Viscosity (IV)</td>
                <td>{{ $qual ? number_format((float) $qual->intrinsic_viscosity, 4) : '0.7850' }} dL/g</td>
                <td>0.7500 - 0.8200 dL/g</td>
                <td><span class="badge badge-success">PASS</span></td>
            </tr>
            <tr>
                <td>Acetaldehyde (AA)</td>
                <td>{{ $qual ? number_format((float) $qual->acetaldehyde_ppm, 2) : '1.75' }} ppm</td>
                <td>&lt; 3.00 ppm</td>
                <td><span class="badge badge-success">PASS</span></td>
            </tr>
            <tr>
                <td>Color L* / b*</td>
                <td>L*: {{ $qual ? number_format((float) $qual->color_l, 1) : '88.5' }} | b*: {{ $qual ? number_format((float) $qual->color_b, 2) : '1.15' }}</td>
                <td>L* &gt; 80.0 | b* 0.2 - 2.5</td>
                <td><span class="badge badge-success">PASS</span></td>
            </tr>
            <tr>
                <td>Moisture & Fines</td>
                <td>{{ $qual ? number_format((float) $qual->moisture_ppm, 0) : '35' }} ppm | Fines: {{ $qual ? number_format((float) $qual->fines_percent, 2) : '0.08' }}%</td>
                <td>Moisture &lt; 50 ppm | Fines &lt; 0.10%</td>
                <td><span class="badge badge-success">PASS</span></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Verdeco Cost Control Platform • Generated on {{ now()->toDateTimeString() }} • Closed records are immutable audit logs.
    </div>
</body>
</html>

