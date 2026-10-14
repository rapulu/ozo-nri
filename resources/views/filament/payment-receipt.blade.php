<div style="font-family: ui-sans-serif, system-ui, sans-serif; color: #111827;">
    <div style="text-align: center; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 12px;">
        <div style="font-size: 20px; font-weight: 800; letter-spacing: 0.05em;">NZE NA OZO ASSOCIATION</div>
        <div style="font-size: 12px; color: #6b7280;">PAYMENT RECORD</div>
    </div>

    <div style="display: flex; justify-content: space-between; gap: 16px; margin-bottom: 12px; font-size: 13px;">
        <div>
            <div style="color: #6b7280; font-size: 11px;">PAID BY</div>
            <div style="font-weight: 700;">{{ $member->full_name }}</div>
            <div style="color: #6b7280;">{{ $member->phone ?? $member->email }}</div>
        </div>
        <div style="text-align: right;">
            <div style="color: #6b7280; font-size: 11px;">REFERENCE</div>
            <div style="font-weight: 700;">{{ $payment->reference ?? '—' }}</div>
            <div style="color: #6b7280;">{{ $payment->paid_at?->format('d M Y') }}</div>
        </div>
    </div>

    <div style="background: #f3f4f6; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 13px; color: #6b7280;">Amount applied</span>
        <span style="font-size: 26px; font-weight: 800;">₦{{ number_format((float) $payment->amount, 0) }}</span>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 12px;">
        <thead>
            <tr style="color: #6b7280; font-size: 11px; text-align: left;">
                <th style="padding: 6px 8px; border-bottom: 1px solid #e5e7eb;">Applied to</th>
                <th style="padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: right;">Balance after (₦)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6;">{{ $appliedTo }}</td>
                <td style="padding: 6px 8px; border-bottom: 1px solid #f3f4f6; text-align: right;">{{ number_format($balanceAfter, 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="display: flex; gap: 12px; font-size: 13px; margin-bottom: 12px;">
        <div style="flex: 1; background: #f3f4f6; border-radius: 8px; padding: 8px 12px;">
            <div style="color: #6b7280; font-size: 11px;">Via / reason</div>
            <div style="font-weight: 700;">{{ $payment->payment_method }} · {{ $payment->reason ?? '—' }}</div>
        </div>
        <div style="flex: 2; background: #f3f4f6; border-radius: 8px; padding: 8px 12px;">
            <div style="color: #6b7280; font-size: 11px;">Note</div>
            <div style="font-weight: 700;">{{ $payment->notes ?? '—' }}</div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; font-size: 11px; color: #6b7280;">
        <span>Recorded by {{ $payment->recorder?->name ?? '—' }}</span>
        <span>Secretary signature: ___________________</span>
    </div>
</div>
