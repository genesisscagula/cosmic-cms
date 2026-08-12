<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f4f7f6;font-family:Manrope,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f6;padding:28px 14px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dce7e2;border-radius:18px;overflow:hidden;">
<tr><td style="background:#071b14;padding:24px 30px;color:#ffffff;"><div style="font-size:12px;font-weight:800;letter-spacing:.18em;color:#7ce9ba;">COSMIC CMS</div><div style="margin-top:7px;font-size:22px;font-weight:800;">New trial lead</div></td></tr>
<tr><td style="padding:30px;">
<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#475569;">A visitor saved a Cosmic CMS trial website.</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f8faf9;border:1px solid #e2e8e5;border-radius:12px;">
<tr><td style="padding:13px 16px;font-size:12px;font-weight:800;color:#64748b;width:150px;">Visitor email</td><td style="padding:13px 16px;font-size:14px;font-weight:700;">{{ $trial->email }}</td></tr>
<tr><td style="padding:13px 16px;border-top:1px solid #e2e8e5;font-size:12px;font-weight:800;color:#64748b;">Website</td><td style="padding:13px 16px;border-top:1px solid #e2e8e5;font-size:14px;">{{ $trial->business_name ?: ($trial->page?->website?->name ?? 'Trial website') }}</td></tr>
<tr><td style="padding:13px 16px;border-top:1px solid #e2e8e5;font-size:12px;font-weight:800;color:#64748b;">Industry</td><td style="padding:13px 16px;border-top:1px solid #e2e8e5;font-size:14px;">{{ $trial->industry ?: '—' }}</td></tr>
<tr><td style="padding:13px 16px;border-top:1px solid #e2e8e5;font-size:12px;font-weight:800;color:#64748b;">Captured</td><td style="padding:13px 16px;border-top:1px solid #e2e8e5;font-size:14px;">{{ optional($trial->email_captured_at)->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td></tr>
</table>
</td></tr></table>
</td></tr></table>
</body></html>
