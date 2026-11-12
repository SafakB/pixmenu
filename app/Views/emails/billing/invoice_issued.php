<?php
/**
 * Yeni fatura bildirimi.
 * Parametreler: subject, restaurantName, panelUrl, billing, invoice
 */
ob_start();
?>
<p>PixMenu aboneliğiniz için yeni bir fatura düzenlendi.</p>
<?= view('emails/billing/_invoice', ['invoice' => $invoice], ['saveData' => false]) ?>
<p>Ödemenizi son ödeme tarihine kadar yapmanızı rica ederiz.</p>
<?php
echo view('emails/billing/layout', [
    'subject'        => $subject,
    'restaurantName' => $restaurantName,
    'panelUrl'       => $panelUrl,
    'billing'        => $billing,
    'body'           => (string) ob_get_clean(),
], ['saveData' => false]);
