<?php
/**
 * Vadesi geçmiş fatura bildirimi.
 * Parametreler: subject, restaurantName, panelUrl, billing, invoice, graceDays
 */
ob_start();
?>
<p>Aşağıdaki faturanızın son ödeme tarihi geçti.</p>
<?= view('emails/billing/_invoice', ['invoice' => $invoice], ['saveData' => false]) ?>
<p style="color:#f06548;">Ödeme vade tarihinden itibaren <strong><?= esc((string) $graceDays) ?> gün</strong> içinde yapılmazsa aboneliğiniz askıya alınacak ve menünüz yayından kalkacaktır.</p>
<?php
echo view('emails/billing/layout', [
    'subject'        => $subject,
    'restaurantName' => $restaurantName,
    'panelUrl'       => $panelUrl,
    'billing'        => $billing,
    'body'           => (string) ob_get_clean(),
], ['saveData' => false]);
