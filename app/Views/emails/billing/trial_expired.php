<?php
/**
 * Deneme süresi sona erdi bildirimi.
 * Parametreler: subject, restaurantName, panelUrl, billing, reason
 */
ob_start();
?>
<p>Ücretsiz deneme süreniz sona erdi ve hesabınız askıya alındı.</p>
<p>Menünüzü yeniden yayına almak için ücretli bir pakete geçmek üzere bizimle iletişime geçin. Verileriniz silinmez.</p>
<?php
echo view('emails/billing/layout', [
    'subject'        => $subject,
    'restaurantName' => $restaurantName,
    'panelUrl'       => $panelUrl,
    'billing'        => $billing,
    'body'           => (string) ob_get_clean(),
], ['saveData' => false]);
