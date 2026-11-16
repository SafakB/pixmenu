<?php
/**
 * Panel uyarı bandı (SubscriptionService::notice()).
 * Parametreler: notice (array|null), isOwner (bool)
 *
 * Deneme / deneme bitti / abonelik yok durumunda owner'a "Paket seç" (self-servis geçiş) gösterilir.
 */
if (empty($notice)) {
    return;
}

$owner      = ! empty($isOwner);
$choosePlan = $notice['type'] === 'trial'
    || ($notice['type'] === 'suspended' && in_array($notice['reason'] ?? '', ['trial_expired', 'no_subscription'], true));

switch ($notice['type']) {
    case 'suspended':
        $icon = 'ri-forbid-2-line';
        $text = 'Aboneliğiniz askıda (' . lang('App.subscription.reason.' . $notice['reason']) . '). Menünüz yayında değil ve içerik düzenleme kapalı.';
        $text .= $owner ? '' : ' Lütfen restoran sahibinizle iletişime geçin.';
        break;

    case 'overdue':
        $icon = 'ri-error-warning-line';
        $text = 'Vadesi geçmiş ' . $notice['count'] . ' faturanız var. Ödeme yapılmazsa aboneliğiniz askıya alınacak.';
        break;

    default:
        $icon = 'ri-time-line';
        $text = $notice['days'] > 0
            ? 'Deneme sürenizin bitmesine ' . $notice['days'] . ' gün kaldı.'
            : 'Deneme süreniz bugün sona eriyor.';
        $text .= ' Kesintisiz kullanım için ücretli bir pakete geçin.';
}
?>
<div class="alert alert-<?= esc($notice['level'], 'attr') ?> alert-label-icon label-arrow d-flex align-items-center flex-wrap gap-2" role="alert">
    <i class="<?= esc($icon, 'attr') ?> label-icon"></i>
    <span class="flex-grow-1"><?= esc($text) ?></span>
    <?php if ($owner && $choosePlan): ?>
        <a href="<?= site_url('panel/subscription/plans') ?>" class="btn btn-sm btn-<?= esc($notice['level'], 'attr') ?>"><i class="ri-vip-crown-2-line align-bottom me-1"></i>Paket seç</a>
    <?php elseif ($owner): ?>
        <a href="<?= site_url('panel/subscription') ?>" class="btn btn-sm btn-<?= esc($notice['level'], 'attr') ?>">Aboneliğim</a>
    <?php endif ?>
</div>
