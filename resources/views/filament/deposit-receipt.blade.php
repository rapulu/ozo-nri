<div style="font-family: ui-sans-serif, system-ui, sans-serif; color: #111827;">
    <div style="text-align: center; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 12px;">
        <div style="font-size: 20px; font-weight: 800; letter-spacing: 0.05em;">NZE NA OZO ASSOCIATION</div>
        <div style="font-size: 12px; color: #6b7280;">PAYMENT RECEIPT</div>
    </div>

    <div style="display: flex; justify-content: space-between; gap: 16px; margin-bottom: 12px; font-size: 13px;">
        <div>
            <div style="color: #6b7280; font-size: 11px;">RECEIVED FROM</div>
            <div style="font-weight: 700;">{{ $member->full_name }}</div>
            <div style="color: #6b7280;">{{ $member->phone ?? $member->email }}</div>
        </div>
        <div style="text-align: right;">
            <div style="color: #6b7280; font-size: 11px;">REFERENCE</div>
            <div style="font-weight: 700;">{{ $deposit->reference ?? '—' }}</div>
            <div style="color: #6b7280;">{{ $deposit->paid_at?->format('d M Y') }}</div>
        </div>
    </div>

    <div style="background: #f3f4f6; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 13px; color: #6b7280;">Amount deposited</span>
        <span style="font-size: 26px; font-weight: 800;">₦{{ number_format((float) $deposit->amount, 0) }}</span>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 12px;">
        <thead>
            <tr style="color: #6b7280; font-size: 11px; text-align: left;">
                <th style="padding: 6px 8px; border-bottom: 1px solid #e5e7eb;">Applied to</th>
                <th style="padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: right;">Amount (₦)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($splits as $split)
                <tr>
                    <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6;">{{ $split['label'] }}</td>
                    <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6; text-align: right;">{{ number_format($split['amount'], 0) }}</td>
                </tr>
            @endforeach
            @if((float) $deposit->opening_applied > 0)
                <tr>
                    <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6;">Opening balance</td>
                    <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6; text-align: right;">{{ number_format((float) $deposit->opening_applied, 0) }}</td>
                </tr>
            @endif
            @if((float) $deposit->credit_added > 0)
                <tr>
                    <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6;">Kept as credit</td>
                    <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6; text-align: right;">{{ number_format((float) $deposit->credit_added, 0) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div style="display: flex; gap: 12px; font-size: 13px; margin-bottom: 12px;">
        <div style="flex: 1; background: #fef2f2; border-radius: 8px; padding: 8px 12px;">
            <div style="color: #6b7280; font-size: 11px;">Arrear</div>
            <div style="font-weight: 700;">{{ $deposit->outstanding_before === null ? '—' : '₦'.number_format((float) $deposit->outstanding_before, 0) }}</div>
            @php $arrearDate = $previousDepositDate ?? $lastArrearDate ?? null; @endphp
            <div style="color: #6b7280; font-size: 11px;">as of {{ $arrearDate ? \Carbon\Carbon::parse($arrearDate)->format('d M Y') : '—' }}</div>
        </div>
        <div style="flex: 1; background: #ecfdf5; border-radius: 8px; padding: 8px 12px;">
            <div style="color: #6b7280; font-size: 11px;">Outstanding</div>
            <div style="font-weight: 700;">{{ $deposit->outstanding_after === null ? '—' : '₦'.number_format((float) $deposit->outstanding_after, 0) }}</div>
            <div style="color: #6b7280; font-size: 11px;">as of {{ $deposit->paid_at?->format('d M Y') ?? '—' }}</div>
        </div>
        <div style="flex: 1; background: #f3f4f6; border-radius: 8px; padding: 8px 12px;">
            <div style="color: #6b7280; font-size: 11px;">Via / reason</div>
            <div style="font-weight: 700;">{{ $deposit->payment_method }} · {{ $deposit->reason ?? '—' }}</div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; font-size: 11px; color: #6b7280;">
        <span>Recorded by {{ $deposit->recorder?->name ?? '—' }}</span>
        <span>Secretary signature: ___________________</span>
    </div>
</div>
