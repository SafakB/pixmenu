<?php
/**
 * Faturalama e-postalarının ortak iskeleti. Parametreler: subject, restaurantName, panelUrl, billing (Config\Billing), body (HTML)
 */
?>
<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body style="margin:0;padding:24px;background:#f3f3f9;font-family:Arial,Helvetica,sans-serif;color:#212529;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:6px;">
    <tr><td style="padding:24px 32px;border-bottom:1px solid #e9ebec;font-size:20px;font-weight:bold;color:#405189;">PixMenu</td></tr>
    <tr><td style="padding:24px 32px;font-size:14px;line-height:1.6;">
        <p style="margin-top:0;">Merhaba<?= $restaurantName !== '' ? ', <strong>' . esc($restaurantName) . '</strong> ekibi' : '' ?>,</p>
        <?= $body ?>
        <p style="margin:24px 0;">
            <a href="<?= esc($panelUrl, 'attr') ?>" style="display:inline-block;padding:10px 18px;background:#405189;color:#ffffff;text-decoration:none;border-radius:4px;">Aboneliğimi görüntüle</a>
        </p>
        <?php if ($billing->paymentInstructions !== ''): ?>
            <p style="padding:12px;background:#f3f6f9;border-radius:4px;"><strong>Ödeme bilgisi:</strong><br><?= nl2br(esc($billing->paymentInstructions)) ?></p>
        <?php endif ?>
    </td></tr>
    <tr><td style="padding:16px 32px;border-top:1px solid #e9ebec;font-size:12px;color:#878a99;">Bu e-posta PixMenu abonelik sistemi tarafından otomatik gönderilmiştir.</td></tr>
</table>
</body>
</html>
