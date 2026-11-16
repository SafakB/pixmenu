<?php
/**
 * Self-servis kayıt sonrası hoş geldin e-postası (AccountNotifier::welcome).
 * Parametreler: subject, userName, restaurantName, planName, trialEndsAt|null, invoice|null, payUrl|null,
 *               panelUrl, loginUrl, viaGoogle (bool), email
 */
ob_start();
?>
<p><strong><?= esc($restaurantName) ?></strong> için PixMenu hesabınız oluşturuldu. İlk şubeniz hazır; kategorilerinizi ve ürünlerinizi ekleyerek menünüzü hemen yayına alabilirsiniz.</p>
<p>
    Paketiniz: <strong><?= esc($planName) ?></strong><br>
    <?php if ($trialEndsAt !== null): ?>
        Ücretsiz deneme süreniz <strong><?= esc(pix_date($trialEndsAt)) ?></strong> tarihinde sona erer. Dilediğiniz zaman panelden ücretli bir pakete geçebilirsiniz.
    <?php elseif ($invoice !== null): ?>
        İlk dönem faturanız: <strong><?= esc((string) $invoice['number']) ?></strong> — <?= esc(pix_money($invoice['total'], $invoice['currency_code'])) ?>
        (son ödeme: <?= esc(pix_date($invoice['due_date'])) ?>).
        <?php if ($payUrl !== null): ?><br><a href="<?= esc($payUrl, 'attr') ?>" style="color:#405189;">Ödeme sayfasına git</a><?php endif ?>
    <?php endif ?>
</p>
<p>
    Giriş adresi: <a href="<?= esc($loginUrl, 'attr') ?>" style="color:#405189;"><?= esc($loginUrl) ?></a><br>
    <?= $viaGoogle ? 'Giriş için "Google ile giriş yap" düğmesini kullanın (' . esc($email) . ').' : 'Kullanıcı adınız: ' . esc($email) ?>
</p>
<?php
echo view('emails/layout', [
    'subject'     => $subject,
    'greeting'    => 'Merhaba ' . $userName . ',',
    'body'        => (string) ob_get_clean(),
    'buttonUrl'   => $panelUrl,
    'buttonLabel' => 'Panele git',
], ['saveData' => false]);
