<?php
/**
 * Vade hatırlatması.
 * Parametreler: subject, restaurantName, panelUrl, billing, invoice
 */
ob_start();
?>
<p>Aşağıdaki faturanızın son ödeme tarihi yaklaşıyor.</p>
<?= view('emails/billing/_invoice', ['invoice' => $invoice], ['saveData' => false]) ?>
<p>Ödemeyi yaptıysanız bu e-postayı dikkate almayınız.</p>
<?php
echo view('emails/billing/layout', [
    'subject'        => $subject,
    'restaurantName' => $restaurantName,
    'panelUrl'       => $panelUrl,
    'billing'        => $billing,
    'body'           => (string) ob_get_clean(),
], ['saveData' => false]);
