<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $order->order_number }}</title></head>
<body style="margin:0;background:#f5f5f7;font-family:Arial,Helvetica,sans-serif;color:#171717">
@php
    $store = $order->website?->name ?: 'Online Store';
    $decimals = (int) data_get(config('cosmic-commerce.currencies.'.$order->currency), 'decimals', 2);
    $scale = 10 ** $decimals;
    $money = fn ($minor) => $order->currency.' '.number_format(((int) $minor) / $scale, $decimals, '.', ',');
    $isMerchant = $kind === 'merchant_new_order';
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 14px"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #e8e8ec">
<tr><td style="padding:28px 30px;background:#111827;color:#fff"><div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.7">{{ $store }}</div><div style="font-size:25px;font-weight:700;margin-top:8px">
@if($kind === 'merchant_new_order') New order received
@elseif($kind === 'fulfilled') Order fulfilled
@elseif($kind === 'tracking') Shipping update
@elseif($kind === 'refund') Refund issued
@else Order confirmed
@endif
</div><div style="font-size:14px;opacity:.75;margin-top:6px">{{ $order->order_number }}</div></td></tr>
<tr><td style="padding:28px 30px">
@if($kind === 'merchant_new_order')
<p style="margin-top:0;line-height:1.6">A new paid order was received from <strong>{{ $order->customer_first_name }} {{ $order->customer_last_name }}</strong> ({{ $order->customer_email }}).</p>
@elseif($kind === 'fulfilled')
<p style="margin-top:0;line-height:1.6">Your order has been marked completed. Thanks for shopping with {{ $store }}.</p>
@elseif($kind === 'tracking')
<p style="margin-top:0;line-height:1.6">There is a shipping update for your order.</p>
@elseif($kind === 'refund')
<p style="margin-top:0;line-height:1.6">A refund of <strong>{{ $money($amountMinor ?? 0) }}</strong> has been issued through PayPal. PayPal/bank posting times may vary.</p>
@else
<p style="margin-top:0;line-height:1.6">Hi {{ $order->customer_first_name }}, your payment was received and your order is confirmed.</p>
@endif

@if($order->tracking_number)
<div style="margin:18px 0;padding:14px 16px;background:#f7f7fa;border-radius:12px"><strong>Tracking</strong><br><span style="color:#555">{{ $order->tracking_carrier ?: 'Carrier' }} · {{ $order->tracking_number }}</span></div>
@endif

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-top:18px">
@foreach($order->items as $item)
<tr><td style="padding:11px 0;border-bottom:1px solid #eee"><strong>{{ $item->title }}</strong>@if($item->option_label)<div style="font-size:12px;color:#777;margin-top:3px">{{ $item->option_label }}</div>@endif</td><td align="right" style="padding:11px 0;border-bottom:1px solid #eee">{{ $item->quantity }} × {{ $money($item->unit_price_minor) }}</td></tr>
@endforeach
</table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:18px;font-size:14px">
<tr><td style="padding:4px 0;color:#666">Subtotal</td><td align="right">{{ $money($order->subtotal_minor) }}</td></tr>
@if((int)($order->discount_minor ?? 0) > 0)<tr><td style="padding:4px 0;color:#047857">Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</td><td align="right" style="color:#047857">-{{ $money($order->discount_minor) }}</td></tr>@endif
<tr><td style="padding:4px 0;color:#666">Shipping</td><td align="right">{{ $money($order->shipping_minor) }}</td></tr>
<tr><td style="padding:4px 0;color:#666">Tax</td><td align="right">{{ $money($order->tax_minor) }}</td></tr>
<tr><td style="padding:10px 0 4px;font-weight:700;font-size:16px">Total</td><td align="right" style="font-weight:700;font-size:16px">{{ $money($order->total_minor) }}</td></tr>
@if((int)$order->refunded_minor > 0)<tr><td style="padding:4px 0;color:#b42318">Refunded</td><td align="right" style="color:#b42318">-{{ $money($order->refunded_minor) }}</td></tr>@endif
</table>
@if($isMerchant && $order->shipping_address)
<div style="margin-top:20px;padding-top:16px;border-top:1px solid #eee;font-size:13px;color:#555"><strong>Ship to</strong><br>{{ data_get($order->shipping_address,'address1') }}<br>{{ data_get($order->shipping_address,'city') }} {{ data_get($order->shipping_address,'region') }} {{ data_get($order->shipping_address,'postal_code') }}<br>{{ data_get($order->shipping_address,'country') }}</div>
@endif
</td></tr>
</table>
</td></tr></table>
</body></html>
