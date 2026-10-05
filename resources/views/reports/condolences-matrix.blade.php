<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Condolence Levies Matrix – {{ $statusFilter === 'open' ? 'Open' : 'All' }}</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #111; }
.header { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #111; padding-bottom: 6px; }
.header h1 { margin: 0; font-size: 15px; }
.header p { margin: 2px 0; font-size: 8.5px; }
.legend { margin: 6px 0; font-size: 7.5px; }
table.matrix { width: 100%; border-collapse: collapse; }
table.matrix th, table.matrix td { border: 1px solid #444; padding: 2px 3px; text-align: center; vertical-align: top; }
table.matrix th { background: #f0f0f0; }
table.matrix th.group { font-size: 7.5px; }
table.matrix th.sub { font-size: 7px; background: #f9fafb; }
table.matrix td.member { text-align: left; }
.paid { color: #065f46; }
.unpaid { color: #991b1b; }
.partial { color: #92400e; }
.small { font-size: 6.5px; color: #333; }
.arrears { background: #fffbeb; }
.received { background: #ecfdf5; }
.owing { background: #fef2f2; }
.totals-row td, .totals-row th { background: #e5e7eb; font-weight: bold; }
.footer { margin-top: 10px; font-size: 8px; text-align: center; color: #555; }
.continued { text-align: center; font-size: 8.5px; color: #555; margin: 0 0 6px 0; }
h2 { font-size: 11px; margin: 0 0 4px 0; text-align: center; }
</style>
</head>
<body>
@if($showHeader ?? true)
<div class="header">
<h1>NZE NA OZO ASSOCIATION</h1>
<p>General Condolence Levies Matrix — {{ $statusFilter === 'open' ? 'Open condolences' : 'All condolences' }}</p>
<p>Active members in rows &times; condolence areas in columns (Arrears | Received | Outstanding) &nbsp;|&nbsp; Generated {{ now()->format('d M Y H:i') }} ({{ $rangeLabel ?? '' }})</p>
</div>

<div class="legend">
<strong>How to read:</strong> each deceased member has three columns —
<strong>Arrears + date</strong> (amount levied and date announced),
<strong>Received + date</strong> (amount paid and date(s) received),
<strong>Outstanding + date</strong> (balance left and due date).
Update any figure per member inside that condolence's list in the secretary panel.
</div>
@else
<p class="continued">Condolence Levies Matrix — {{ $rangeLabel ?? '' }} (continued)</p>
@endif

<table class="matrix">
<thead>
<tr>
<th rowspan="2" style="width:20px;">S/N</th>
<th rowspan="2" style="text-align:left;">Active member</th>
@foreach($condolences as $c)
<th class="group" colspan="3">
{{ $c->deceasedMember?->full_name ?? $c->title }}<br>
<span class="small">₦{{ number_format((float) $c->amount_per_member, 0) }} &middot; {{ ucfirst($c->status) }}</span>
</th>
@endforeach
<th class="group" colspan="3">Member totals</th>
</tr>
<tr>
@foreach($condolences as $c)
<th class="sub arrears">Arrears<br><span class="small">ann. {{ $c->date_announced?->format('d/m/y') }}</span></th>
<th class="sub received">Received<br><span class="small">+ date</span></th>
<th class="sub owing">Outstanding<br><span class="small">due {{ $c->due_date?->format('d/m/y') ?? '—' }}</span></th>
@endforeach
<th class="sub">Tot. arrears</th>
<th class="sub">Tot. received</th>
<th class="sub">Tot. outstanding</th>
</tr>
</thead>
<tbody>
@foreach($members as $i => $m)
<tr>
<td>{{ ($startIndex ?? 0) + $i + 1 }}</td>
<td class="member">{{ $m->full_name }}<br><span class="small">{{ $m->phone ?? '' }}</span></td>
@foreach($condolences as $c)
@php
$levy = $levyMap->get($m->id.'_'.$c->id);
$paid = $levy ? (float) $levy->amount_paid : 0.0;
$expected = $levy ? (float) $levy->amount_expected : 0.0;
$bal = $expected - $paid;
$dates = $levy ? $levy->payments->pluck('paid_at')->filter()->map(fn ($d) => $d->format('d/m/y'))->unique()->join(', ') : '';
@endphp
@if(!$levy)
<td class="arrears">—</td>
<td class="received">—</td>
<td class="owing">—</td>
@else
<td class="arrears">₦{{ number_format($expected, 0) }}<br><span class="small">{{ $c->date_announced?->format('d/m/y') }}</span></td>
<td class="received">
@if($paid > 0)
<span class="{{ $bal <= 0 ? 'paid' : 'partial' }}"><strong>₦{{ number_format($paid, 0) }}</strong><br><span class="small">{{ $dates }}</span></span>
@else
<span class="unpaid">₦0</span>
@endif
</td>
<td class="owing">
@if($levy->status === 'exempted')
<span class="small">Exempt</span>
@elseif($bal <= 0)
₦0
@else
<span class="unpaid"><strong>₦{{ number_format($bal, 0) }}</strong><br><span class="small">{{ $c->due_date?->format('d/m/y') ?? '—' }}</span></span>
@endif
</td>
@endif
@endforeach
<td><strong>₦{{ number_format($perMember[$m->id]['expected'] ?? 0, 0) }}</strong></td>
<td><strong>₦{{ number_format($perMember[$m->id]['paid'] ?? 0, 0) }}</strong></td>
<td><strong>₦{{ number_format($perMember[$m->id]['outstanding'] ?? 0, 0) }}</strong></td>
</tr>
@endforeach
</tbody>
@if($showTotals ?? true)
<tfoot>
<tr class="totals-row">
<th colspan="2" style="text-align:right;">Arrears per condolence</th>
@foreach($condolences as $c)
<td colspan="3">₦{{ number_format($perCondolence[$c->id]['expected'] ?? 0, 0) }}</td>
@endforeach
<td colspan="3">₦{{ number_format($grandExpected, 0) }}</td>
</tr>
<tr class="totals-row">
<th colspan="2" style="text-align:right;">Received per condolence</th>
@foreach($condolences as $c)
<td colspan="3">₦{{ number_format($perCondolence[$c->id]['collected'] ?? 0, 0) }}</td>
@endforeach
<td colspan="3">₦{{ number_format($grandCollected, 0) }}</td>
</tr>
<tr class="totals-row">
<th colspan="2" style="text-align:right;">Outstanding per condolence</th>
@foreach($condolences as $c)
<td colspan="3">₦{{ number_format($perCondolence[$c->id]['outstanding'] ?? 0, 0) }}</td>
@endforeach
<td colspan="3">₦{{ number_format($grandOutstanding, 0) }}</td>
</tr>
</tfoot>
@endif
</table>

@if($showSignature ?? true)
<div class="footer">
<p>Secretary signature: ___________________________ &nbsp;&nbsp; Date: ____________</p>
<p>{{ $totalMembers ?? $members->count() }} active members &times; {{ $condolences->count() }} condolence area(s) — computer generated matrix</p>
</div>
@endif
</body>
</html>
