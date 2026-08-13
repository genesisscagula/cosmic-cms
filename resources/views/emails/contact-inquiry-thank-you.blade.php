<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Thanks for contacting {{ $website->name }}</title>
</head>
<body style="margin:0;background:#f4f7f6;font-family:Arial,Helvetica,sans-serif;color:#17211d;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f6;">
<tr><td align="center" style="padding:36px 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dfe9e4;border-radius:22px;overflow:hidden;box-shadow:0 14px 45px rgba(17,38,28,.08);">
<tr><td style="padding:34px 34px 28px;background:#123d2d;color:#ffffff;text-align:center;">
    <div style="display:inline-block;width:48px;height:48px;line-height:48px;border-radius:50%;background:#1f8a5e;color:#ffffff;font-size:24px;font-weight:800;margin-bottom:16px;">✓</div>
    <div style="font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#9fe0c2;">Message received</div>
    <h1 style="margin:10px 0 7px;font-size:28px;line-height:1.25;font-weight:800;">Thanks, {{ $contact['name'] }}.</h1>
    <p style="margin:0;color:#d9eee4;font-size:14px;line-height:1.65;">Your inquiry has reached {{ $website->name }} successfully.</p>
</td></tr>
<tr><td style="padding:30px 34px;">
    <p style="margin:0 0 20px;font-size:15px;line-height:1.75;color:#44574d;">We’ve received your message and the team can now review your details. They’ll get back to you as soon as they can.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7faf8;border:1px solid #e1ebe6;border-radius:16px;margin-bottom:22px;">
        <tr><td style="padding:20px 22px;">
            <div style="font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#6c7d74;margin-bottom:9px;">Your message</div>
            <div style="font-size:14px;line-height:1.75;color:#26372f;white-space:pre-wrap;">{{ $contact['message'] }}</div>
        </td></tr>
    </table>

    @if(!empty($fields))
        <div style="font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#6c7d74;margin-bottom:10px;">Details submitted</div>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;border:1px solid #e1ebe6;border-radius:14px;overflow:hidden;margin-bottom:24px;">
            @foreach($fields as $label => $value)
                <tr>
                    <td style="padding:11px 13px;border-bottom:1px solid #edf2ef;background:#f8faf9;width:36%;font-size:12px;font-weight:800;color:#52655b;">{{ ucwords(str_replace('_', ' ', $label)) }}</td>
                    <td style="padding:11px 13px;border-bottom:1px solid #edf2ef;font-size:13px;color:#25372e;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin:0;font-size:13px;line-height:1.7;color:#66776e;">Keep this email as confirmation that your inquiry was submitted successfully.</p>
</td></tr>
<tr><td style="padding:20px 34px;background:#fafcfb;border-top:1px solid #edf2ef;text-align:center;">
    <div style="font-size:13px;font-weight:800;color:#35483e;">{{ $website->name }}</div>
    <div style="margin-top:5px;font-size:11px;color:#9aa69f;">Contact acknowledgement · Powered by Cosmic CMS</div>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
