<?php
/**
 * Deneme bitiş hatırlatması.
 * Parametreler: subject, restaurantName, panelUrl, billing, subscription, daysLeft
 */
ob_start();
?>
<p>Ücretsiz deneme sürenizin bitmesine <strong><?= esc((string) $daysLeft) ?> gün</strong> kaldı (son gün: <?= esc(pix_date($subscription['current_period_end'])) ?>).</p>
<p>Menünüzün kesintisiz yayında kalması için ücretli bir pakete geçmek üzere bizimle iletişime geçin.</p>
<?php
echo view('emails/billing/layout', [
    'subject'        => $subject,
    'restaurantName' => $restaurantName,
    'panelUrl'       => $panelUrl,
    'billing'        => $billing,
    'body'           => (string) ob_get_clean(),
], ['saveData' => false]);
