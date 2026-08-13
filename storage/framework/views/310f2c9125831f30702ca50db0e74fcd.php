<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>New inquiry from <?php echo e($website->name); ?></title>
</head>
<body style="margin:0;background:#f4f7f6;font-family:Arial,Helvetica,sans-serif;color:#17211d;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f6;">
<tr><td align="center" style="padding:36px 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dfe9e4;border-radius:22px;overflow:hidden;box-shadow:0 14px 45px rgba(17,38,28,.08);">
<tr><td style="padding:30px 34px;background:#123d2d;color:#ffffff;">
    <div style="font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#9fe0c2;">New website inquiry</div>
    <h1 style="margin:10px 0 5px;font-size:27px;line-height:1.25;font-weight:800;">A new lead just came in</h1>
    <p style="margin:0;color:#d9eee4;font-size:14px;line-height:1.6;"><?php echo e($website->name); ?> received a new contact form submission.</p>
</td></tr>
<tr><td style="padding:30px 34px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:22px;background:#f7faf8;border:1px solid #e1ebe6;border-radius:16px;">
        <tr><td style="padding:20px 22px;">
            <div style="font-size:19px;font-weight:800;color:#17211d;"><?php echo e($contact['name']); ?></div>
            <div style="margin-top:7px;font-size:14px;line-height:1.7;color:#5c6d64;">
                <a href="mailto:<?php echo e($contact['email']); ?>" style="color:#167a52;text-decoration:none;font-weight:700;"><?php echo e($contact['email']); ?></a>
                <?php if(!empty($contact['phone'])): ?><span style="color:#b2beb8;"> &nbsp;•&nbsp; </span><?php echo e($contact['phone']); ?><?php endif; ?>
            </div>
        </td></tr>
    </table>

    <div style="font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#6c7d74;margin-bottom:9px;">Message</div>
    <div style="font-size:15px;line-height:1.75;color:#26372f;background:#ffffff;border-left:4px solid #2e9d6f;padding:3px 0 3px 16px;margin-bottom:24px;white-space:pre-wrap;"><?php echo e($contact['message']); ?></div>

    <?php if(!empty($fields)): ?>
        <div style="font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#6c7d74;margin-bottom:10px;">Additional details</div>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;border:1px solid #e1ebe6;border-radius:14px;overflow:hidden;margin-bottom:26px;">
            <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td style="padding:12px 14px;border-bottom:1px solid #edf2ef;background:#f8faf9;width:34%;font-size:12px;font-weight:800;color:#52655b;"><?php echo e(ucwords(str_replace('_', ' ', $label))); ?></td>
                    <td style="padding:12px 14px;border-bottom:1px solid #edf2ef;font-size:13px;color:#25372e;"><?php echo e($value); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    <?php endif; ?>

    <a href="mailto:<?php echo e($contact['email']); ?>?subject=<?php echo e(rawurlencode('Re: Your inquiry to '.$website->name)); ?>" style="display:inline-block;background:#1f8a5e;color:#ffffff;text-decoration:none;font-size:14px;font-weight:800;padding:13px 20px;border-radius:11px;">Reply to <?php echo e($contact['name']); ?></a>

    <p style="margin:26px 0 0;font-size:12px;line-height:1.6;color:#8a9991;">Submission #<?php echo e($submission->id); ?> · Received <?php echo e(optional($submission->received_at)->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A')); ?></p>
</td></tr>
<tr><td style="padding:18px 34px;background:#fafcfb;border-top:1px solid #edf2ef;font-size:11px;color:#96a39c;">Delivered securely by Cosmic CMS.</td></tr>
</table>
</td></tr></table>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\my-custom-cms\resources\views/emails/contact-inquiry-owner.blade.php ENDPATH**/ ?>