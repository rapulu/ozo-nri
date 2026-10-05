<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Condolence Record – {{ $condolence->title }}</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
.header { text-align: center; margin-bottom: 12px; border-bottom: 2px solid #111; padding-bottom: 8px; }
.header h1 { margin: 0; font-size: 20px; }
.header p { margin: 2px 0; }
.meta { margin: 10px 0; }
.meta table { width: 100%; border-collapse: collapse; }
.meta td { padding: 4px 6px; }
table.records { width: 100%; border-collapse: collapse; margin-top: 10px; }
table.records th, table.records td { border: 1px solid #444; padding: 5px 6px; text-align: left; }
table.records th { background: #f0f0f0; }
.right { text-align: right; }
.totals { margin-top: 10px; width: 50%; margin-left: auto; }
.totals table { width: 100%; border-collapse: collapse; }
.totals td { border: 1px solid #444; padding: 5px 6px; }
.footer { margin-top: 18px; font-size: 10px; text-align: center; color: #555; }
.badge { display: inline-block; padding: 2px 6px; border: 1px solid #444; border-radius: 4px; }
</style>
</head>
<body>
<div class="header">
<h1>NZE NA OZO ASSOCIATION</h1>
<p>Condolence Levy Record</p>
</div>

<h2 style="margin:0 0 6px 0;">{{ $condolence->title }}</h2>
<div class="meta">
<table>
<tr>
<td><strong>Deceased:</strong> {{ $condolence->deceasedMember?->full_name ?? '—' }}</td>
<td><strong>Amount per member:</strong> ₦{{ number_format((float) $condolence->amount_per_member, 2) }}</td>
</tr>
<tr>
<td><strong>Date announced:</strong> {{ $condolence->date_announced?->format('d M Y') }}</td>
<td><strong>Due date:</strong> {{ $condolence->due_date?->format('d M Y') ?? '—' }}</td>
</tr>
<tr>
<td><strong>Status:</strong> {{ ucfirst($condolence->status) }}</td>
<td><strong>Generated:</strong> {{ now()->format('d M Y H:i') }} ({{ $rangeLabel ?? '' }})</td>
</tr>
</table>
@if($condolence->description)
<p><strong>Note:</strong> {{ $condolence->description }}</p>
@endif
</div>

<table class="records">
<thead>
<tr>
<th>S/N</th>
<th>Member (Title + Name)</th>
<th>Phone</th>
<th>Expected (₦)</th>
<th>Paid (₦)</th>
<th>Balance (₦)</th>
<th>Status</th>
<th>Date(s) Paid</th>
</tr>
</thead>
<tbody>
@foreach($levies as $i => $levy)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $levy->member?->full_name ?? '—' }}</td>
<td>{{ $levy->member?->phone ?? '—' }}</td>
<td class="right">{{ number_format((float) $levy->amount_expected, 2) }}</td>
<td class="right">{{ number_format((float) $levy->amount_paid, 2) }}</td>
<td class="right">{{ number_format((float) $levy->amount_expected - (float) $levy->amount_paid, 2) }}</td>
<td>{{ ucfirst($levy->status) }}</td>
<td>{{ $levy->payments->pluck('paid_at')->map(fn ($d) => $d?->format('d/m/Y'))->filter()->join(', ') ?: '—' }}</td>
</tr>
@endforeach
</tbody>
</table>

<div class="totals">
<table>
<tr><td><strong>Total expected</strong></td><td class="right">₦{{ number_format($expected, 2) }}</td></tr>
<tr><td><strong>Total collected</strong></td><td class="right">₦{{ number_format($collected, 2) }}</td></tr>
<tr><td><strong>Outstanding</strong></td><td class="right">₦{{ number_format($outstanding, 2) }}</td></tr>
<tr><td><strong>Paid members</strong></td><td class="right">{{ $levies->where('status', 'paid')->count() }} / {{ $levies->count() }}</td></tr>
</table>
</div>

<div class="footer">
<p>Secretary signature: ___________________________ &nbsp;&nbsp; Date: ____________</p>
<p>Nze na Ozo Association – computer generated record</p>
</div>
</body>
</html>
