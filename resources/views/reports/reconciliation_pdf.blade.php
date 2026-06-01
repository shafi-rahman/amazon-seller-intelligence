<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 20px; }
    h1 { font-size: 18px; color: #1e40af; margin-bottom: 4px; }
    h2 { font-size: 14px; color: #374151; margin-top: 20px; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
    .meta { font-size: 10px; color: #6b7280; margin-bottom: 16px; }
    .summary-grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .summary-cell { display: table-cell; background: #f3f4f6; padding: 10px; text-align: center; border: 1px solid #e5e7eb; width: 25%; }
    .summary-label { font-size: 10px; color: #6b7280; }
    .summary-value { font-size: 16px; font-weight: bold; color: #111827; }
    table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 8px; }
    th { background: #1e40af; color: white; padding: 6px 8px; text-align: left; font-weight: bold; }
    td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #f9fafb; }
    .amount { text-align: right; }
    .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    .badge-missing { color: #dc2626; font-weight: bold; }
    .badge-matched { color: #059669; }
</style>
</head>
<body>

<h1>ASIP — Reconciliation Report</h1>
<div class="meta">
    Report Type: <strong>{{ ucwords(str_replace('_', ' ', $report->report_type)) }}</strong> &nbsp;|&nbsp;
    Run ID: #{{ $report->reconciliation_run_id }} &nbsp;|&nbsp;
    Generated: {{ now()->format('d M Y H:i') }} IST
</div>

@php
    $reportType = $report->report_type;
    $rows = $data['rows'] ?? [];
@endphp

@if ($reportType === 'summary')
    <div class="summary-grid">
        <div class="summary-cell">
            <div class="summary-label">Total Orders</div>
            <div class="summary-value">{{ number_format($data['total_orders'] ?? 0) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Matched</div>
            <div class="summary-value" style="color:#059669">{{ number_format($data['matched_orders'] ?? 0) }}</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Match Rate</div>
            <div class="summary-value">{{ $data['match_rate_pct'] ?? 0 }}%</div>
        </div>
        <div class="summary-cell">
            <div class="summary-label">Order Value</div>
            <div class="summary-value">₹{{ number_format($data['total_order_value'] ?? 0, 2) }}</div>
        </div>
    </div>
    <table>
        <tr><th>Metric</th><th class="amount">Value</th></tr>
        <tr><td>Total Orders</td><td class="amount">{{ number_format($data['total_orders'] ?? 0) }}</td></tr>
        <tr><td>Matched Orders</td><td class="amount">{{ number_format($data['matched_orders'] ?? 0) }}</td></tr>
        <tr><td>Unmatched Orders</td><td class="amount badge-missing">{{ number_format($data['unmatched_orders'] ?? 0) }}</td></tr>
        <tr><td>Total Order Value</td><td class="amount">₹{{ number_format($data['total_order_value'] ?? 0, 2) }}</td></tr>
        <tr><td>Total Settled</td><td class="amount">₹{{ number_format($data['total_settled'] ?? 0, 2) }}</td></tr>
        <tr><td>Total Bank Credits (Amazon)</td><td class="amount">₹{{ number_format($data['total_bank_credits'] ?? 0, 2) }}</td></tr>
        <tr><td>Missing Settlements</td><td class="amount badge-missing">{{ $data['missing_settlements'] ?? 0 }}</td></tr>
        <tr><td>Missing Bank Credits</td><td class="amount badge-missing">{{ $data['missing_credits'] ?? 0 }}</td></tr>
        <tr><td>GST Mismatches</td><td class="amount">{{ $data['gst_mismatches'] ?? 0 }}</td></tr>
    </table>

@elseif ($reportType === 'missing_settlements')
    <p><strong>{{ $data['count'] ?? 0 }}</strong> orders without a settlement. Total value: <strong>₹{{ number_format($data['total_value'] ?? 0, 2) }}</strong></p>
    <table>
        <tr><th>Order ID</th><th>Date</th><th>Status</th><th>SKU</th><th class="amount">Price (₹)</th><th>Days Since</th><th>Reason</th></tr>
        @foreach (array_slice($rows, 0, 200) as $row)
        <tr>
            <td>{{ $row['amazon_order_id'] }}</td>
            <td>{{ $row['purchase_date'] }}</td>
            <td>{{ $row['order_status'] }}</td>
            <td>{{ $row['sku'] }}</td>
            <td class="amount">{{ number_format($row['item_price'] ?? 0, 2) }}</td>
            <td>{{ $row['days_since_order'] }}</td>
            <td>{{ str_replace('_', ' ', $row['reason'] ?? '') }}</td>
        </tr>
        @endforeach
    </table>
    @if (count($rows) > 200)
        <p style="font-size:10px;color:#6b7280">Showing first 200 of {{ count($rows) }} rows. Export CSV for full data.</p>
    @endif

@elseif ($reportType === 'missing_credits')
    <p><strong>{{ $data['count'] ?? 0 }}</strong> settlement cycles with no bank credit. Total: <strong>₹{{ number_format($data['total_value'] ?? 0, 2) }}</strong></p>
    <table>
        <tr><th>Settlement ID</th><th>Deposit Date</th><th class="amount">Expected (₹)</th><th>Days Since</th><th>Action</th></tr>
        @foreach ($rows as $row)
        <tr>
            <td>{{ $row['settlement_id'] }}</td>
            <td>{{ $row['deposit_date'] }}</td>
            <td class="amount">{{ number_format($row['deposited_amount'] ?? 0, 2) }}</td>
            <td>{{ $row['days_since_deposit'] }}</td>
            <td>{{ $row['action'] }}</td>
        </tr>
        @endforeach
    </table>

@elseif ($reportType === 'gst_mismatch')
    <p><strong>{{ $data['count'] ?? 0 }}</strong> GST mismatches. Total discrepancy: <strong>₹{{ number_format($data['total_mismatch'] ?? 0, 2) }}</strong></p>
    <table>
        <tr><th>Order ID</th><th>Date</th><th class="amount">Order Tax (₹)</th><th class="amount">GST Reported (₹)</th><th class="amount">Difference (₹)</th></tr>
        @foreach ($rows as $row)
        <tr>
            <td>{{ $row['amazon_order_id'] }}</td>
            <td>{{ $row['order_date'] }}</td>
            <td class="amount">{{ number_format($row['order_tax'] ?? 0, 2) }}</td>
            <td class="amount">{{ number_format($row['reported_tax'] ?? 0, 2) }}</td>
            <td class="amount badge-missing">{{ number_format($row['mismatch_amount'] ?? 0, 2) }}</td>
        </tr>
        @endforeach
    </table>

@else
    {{-- Generic fallback for refund_impact, return_impact --}}
    @if (!empty($rows))
    <table>
        <tr>@foreach (array_keys($rows[0]) as $key)<th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>@endforeach</tr>
        @foreach (array_slice($rows, 0, 300) as $row)
        <tr>@foreach ($row as $val)<td>{{ $val }}</td>@endforeach</tr>
        @endforeach
    </table>
    @else
        <p>No data for this report.</p>
    @endif
@endif

<div class="footer">
    Generated by ASIP — Amazon Seller Intelligence Platform &nbsp;|&nbsp; {{ now()->toISOString() }}
</div>
</body>
</html>
