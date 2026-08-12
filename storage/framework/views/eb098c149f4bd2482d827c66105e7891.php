<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo e($regenerated ? 'Your updated Cosmic CMS website is ready' : 'Your Cosmic CMS website is ready'); ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f7f5;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f3f7f5;padding:32px 14px">
<tr>
<td align="center">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border:1px solid #dfe7e3;border-radius:20px;overflow:hidden;box-shadow:0 18px 50px rgba(15,23,42,.08)">
        <tr>
            <td style="background:#07140f;padding:28px 32px">
                <div style="font-size:13px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#6ee7b7">Cosmic CMS</div>
                <div style="margin-top:8px;font-size:12px;color:#94a3b8">AI Website Builder</div>
            </td>
        </tr>
        <tr>
            <td style="padding:34px 32px 30px">
                <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#059669">
                    <?php echo e($regenerated ? 'Your latest version is ready' : 'Your website has been saved'); ?>

                </p>

                <h1 style="margin:0 0 14px;font-size:30px;line-height:1.18;letter-spacing:-.02em;color:#0f172a">
                    <?php echo e($regenerated ? 'Your refreshed website is ready to edit.' : 'Your Cosmic CMS website is ready 🚀'); ?>

                </h1>

                <p style="margin:0 0 22px;font-size:16px;line-height:1.7;color:#475569">
                    <?php if($regenerated): ?>
                        Your regenerated page is saved. Open your private Builder link below to keep refining the latest version.
                    <?php else: ?>
                        We saved your website for <strong><?php echo e($trial->business_name ?: 'your business'); ?></strong>. You can return to the same private Builder anytime while your trial link is active.
                    <?php endif; ?>
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px">
                    <tr>
                        <td style="border-radius:12px;background:#059669">
                            <a href="<?php echo e($url); ?>" style="display:inline-block;padding:14px 22px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800">
                                Continue Editing Your Website →
                            </a>
                        </td>
                    </tr>
                </table>

                <div style="margin:24px 0;padding:18px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#64748b">Private Builder Link</div>
                    <div style="margin-top:8px;font-size:12px;line-height:1.6;word-break:break-all;color:#334155"><?php echo e($url); ?></div>
                    <?php if(!empty($expiryLabel)): ?>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.6;color:#64748b">
                        This private trial link is currently scheduled to remain available until <strong style="color:#334155"><?php echo e($expiryLabel); ?></strong>.
                    </div>
                    <?php endif; ?>
                </div>

                <?php if(!$regenerated): ?>
                <p style="margin:0 0 14px;font-size:14px;line-height:1.7;color:#64748b">
                    Like what you built? Your current design, logo, theme, and page content can move with you when you buy the website.
                </p>

                <p style="margin:0 0 4px">
                    <a href="<?php echo e($pricingUrl); ?>" style="color:#047857;font-size:14px;font-weight:800;text-decoration:none">
                        View plans &amp; buy this website →
                    </a>
                </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td style="padding:22px 32px;border-top:1px solid #e2e8f0;background:#fbfdfc">
                <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8">
                    This email contains a private editing link. Keep it safe and avoid forwarding it to people who should not edit your website.
                </p>
                <p style="margin:8px 0 0;font-size:12px;line-height:1.6;color:#94a3b8">
                    Questions about your website? Reply to this email and keep your private Builder link handy.
                </p>
                <p style="margin:8px 0 0;font-size:12px;color:#94a3b8">
                    © <?php echo e(now()->year); ?> Cosmic CMS
                </p>
            </td>
        </tr>
    </table>
</td>
</tr>
</table>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\my-custom-cms\resources\views/emails/trial-access.blade.php ENDPATH**/ ?>