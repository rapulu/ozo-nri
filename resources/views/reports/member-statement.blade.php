<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Member Statement – {{ $member->full_name }}</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
.header { text-align: center; margin-bottom: 12px; border-bottom: 2px solid #111; padding-bottom: 8px; }
.header h1 { margin: 0; font-size: 20px; }
.profile table { width: 100%; border-collapse: collapse; margin: 10px 0; }
.profile td { padding: 4px 6px; border: 1px solid #ccc; }
table.records { width: 100%; border-collapse: collapse; margin-top: 10px; }
table.records th, table.records td { border: 1px solid #444; padding: 5px 6px; text-align: left; }
table.records th { background: #f0f0f0; }
.right { text-align: right; }
.totals { margin-top: 10px; width: 55%; margin-left: auto; }
.totals table { width: 100%; border-collapse: collapse; }
.totals td { border: 1px solid #444; padding: 5px 6px; }
.footer { margin-top: 18px; font-size: 10px; text-align: center; color: #555; }
h3 { margin: 14px 0 4px 0; }
</style>
</head>
<body>
<div class="header">
<h1>NZE NA OZO ASSOCIATION</h1>
<p>Member Payment History / Statement</p>
</div>

<div class="profile">
<table>
<tr><td><strong>Name:</strong> {{ $member->full_name }}</td><td><strong>Email:</strong> {{ $member->email }}</td></tr>
<tr><td><strong>Phone:</strong> {{ $member->phone ?? '—' }}</td><td><strong>Status:</strong> {{ ucfirst($member->status) }}</td></tr>
<tr><td><strong>Date joined:</strong> {{ $member->date_joined?->format('d M Y') ?? '—' }}</td><td><strong>Generated:</strong> {{ now()->format('d M Y H:i') }}</td></tr>
<tr><td colspan="2"><strong>Opening arrears b/f (past debt):</strong> ₦{{ number_format((float) $member->opening_arrears, 2) }}</td></tr>
</table>
</div>

<h3>Payment history (bulk deposits)</h3>
<table class="records">
<thead>
<tr>
<th>S/N</th>
<th>Date received</th>
<th>Deposited (₦)</th>
<th>Method</th>
<th>Reason</th>
<th>Reference</th>
</tr>
</thead>
<tbody>
@forelse($deposits as $i => $deposit)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $deposit->paid_at?->format('d/m/Y') ?? '—' }}</td>
<td class="right">{{ number_format((float) $deposit->amount, 2) }}</td>
<td>{{ \App\Enums\PaymentMethod::options()[$deposit->payment_method] ?? $deposit->payment_method ?? '—' }}</td>
<td>{{ $deposit->reason ? (\App\Enums\ArrearReason::options()[$deposit->reason] ?? $deposit->reason) : '—' }}</td>
<td>{{ $deposit->reference ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="6" style="text-align:center;">No payments recorded yet.</td></tr>
@endforelse
</tbody>
</table>

<div class="totals">
<table>
<tr><td><strong>Total expected</strong></td><td class="right">₦{{ number_format($expected, 2) }}</td></tr>
<tr><td><strong>Total paid</strong></td><td class="right">₦{{ number_format($paid, 2) }}</td></tr>
<tr><td><strong>Outstanding</strong></td><td class="right">₦{{ number_format($outstanding, 2) }}</td></tr>
<tr><td><strong>Credit (owed to member)</strong></td><td class="right">₦{{ number_format($credit, 2) }}</td></tr>
</table>
</div>

<div class="footer">
<p>Secretary signature: ___________________________ &nbsp;&nbsp; Date: ____________</p>
<p>Nze na Ozo Association – computer generated statement</p>
</div>
</body>
</html>
