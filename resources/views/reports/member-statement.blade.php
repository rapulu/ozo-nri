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

<table class="records">
<thead>
<tr>
<th>S/N</th>
<th>Condolence</th>
<th>Date Announced</th>
<th>Expected (₦)</th>
<th>Paid (₦)</th>
<th>Balance (₦)</th>
<th>Status</th>
<th>Payment Date(s)</th>
</tr>
</thead>
<tbody>
@foreach($levies as $i => $levy)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $levy->condolence?->title ?? '—' }}</td>
<td>{{ $levy->condolence?->date_announced?->format('d/m/Y') ?? '—' }}</td>
<td class="right">{{ number_format((float) $levy->amount_expected, 2) }}</td>
<td class="right">{{ number_format((float) $levy->amount_paid, 2) }}</td>
<td class="right">{{ number_format((float) $levy->amount_expected - (float) $levy->amount_paid, 2) }}</td>
<td>{{ ucfirst($levy->status) }}</td>
<td>
@foreach($levy->payments as $p)
₦{{ number_format((float) $p->amount, 2) }} on {{ $p->paid_at?->format('d/m/Y') }} ({{ $p->payment_method }})@if(!$loop->last)<br>@endif
@endforeach
@if($levy->payments->isEmpty()) — @endif
</td>
</tr>
@endforeach
</tbody>
</table>

@if(($arrears ?? collect())->isNotEmpty())
<h3 style="margin: 14px 0 4px 0;">General arrears</h3>
<table class="records">
<thead>
<tr>
<th>S/N</th>
<th>Title</th>
<th>Reason</th>
<th>Due date</th>
<th>Expected (₦)</th>
<th>Paid (₦)</th>
<th>Balance (₦)</th>
<th>Status</th>
<th>Payment Date(s)</th>
</tr>
</thead>
<tbody>
@foreach($arrears as $i => $arrear)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $arrear->title }}</td>
<td>{{ \App\Enums\ArrearReason::options()[$arrear->reason] ?? $arrear->reason }}</td>
<td>{{ $arrear->due_date?->format('d/m/Y') ?? '—' }}</td>
<td class="right">{{ number_format((float) $arrear->amount_expected, 2) }}</td>
<td class="right">{{ number_format((float) $arrear->amount_paid, 2) }}</td>
<td class="right">{{ number_format((float) $arrear->amount_expected - (float) $arrear->amount_paid, 2) }}</td>
<td>{{ ucfirst($arrear->status) }}</td>
<td>
@foreach($arrear->payments as $p)
₦{{ number_format((float) $p->amount, 2) }} on {{ $p->paid_at?->format('d/m/Y') }} ({{ $p->payment_method }})@if(!$loop->last)<br>@endif
@endforeach
@if($arrear->payments->isEmpty()) — @endif
</td>
</tr>
@endforeach
</tbody>
</table>
@endif

<div class="totals">
<table>
<tr><td><strong>Total expected</strong></td><td class="right">₦{{ number_format($expected, 2) }}</td></tr>
<tr><td><strong>Total paid</strong></td><td class="right">₦{{ number_format($paid, 2) }}</td></tr>
<tr><td><strong>Outstanding</strong></td><td class="right">₦{{ number_format($outstanding, 2) }}</td></tr>
</table>
</div>

<div class="footer">
<p>Secretary signature: ___________________________ &nbsp;&nbsp; Date: ____________</p>
<p>Nze na Ozo Association – computer generated statement</p>
</div>
</body>
</html>
