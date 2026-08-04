<!doctype html>
<html>
<body style="font-family:Arial,sans-serif;color:#172033;line-height:1.6">
    <h2>Payment received</h2>
    <p>Hi {{ $billingTransaction->user->name }},</p>
    <p>Your {{ ucfirst($billingTransaction->type) }} payment for the <strong>{{ ucfirst($billingTransaction->paymentOrder?->product_key ?? 'Cosmic CMS') }}</strong> plan was successful.</p>
    <p>
        <strong>Amount:</strong> {{ $billingTransaction->currency }} {{ number_format($billingTransaction->amount_minor / 100, 2) }}<br>
        <strong>Credits added:</strong> {{ number_format($billingTransaction->credits_granted) }}<br>
        <strong>Transaction:</strong> {{ $billingTransaction->external_id }}<br>
        <strong>Date:</strong> {{ $billingTransaction->occurred_at->format('M j, Y g:i A') }}
    </p>
    <p>Thank you for using Cosmic CMS.</p>
</body>
</html>
