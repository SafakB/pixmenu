<?php
/**
 * Askıya alma bildirimi.
 * Parametreler: subject, restaurantName, panelUrl, billing, reason
 */
ob_start();
?>
<p>Aboneliğiniz askıya alındı: <strong><?= esc(lang('App.subscription.reason.' . $reason)) ?></strong>.</p>
<p>Askı süresince menünüz ziyaretçilere kapalıdır ve panelde yalnızca abonelik sayfalarına erişebilirsiniz. Ödemeniz kaydedildiğinde hizmetiniz otomatik olarak yeniden açılır.</p>
<?php
echo view('emails/billing/layout', [
    'subject'        => $subject,
    'restaurantName' => $restaurantName,
    'panelUrl'       => $panelUrl,
    'billing'        => $billing,
    'body'           => (string) ob_get_clean(),
], ['saveData' => false]);
