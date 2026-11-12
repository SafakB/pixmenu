<?php
/**
 * Abonelik / fatura durum rozeti.
 * Parametreler: kind ('invoice' | 'subscription' | 'reason'), status (string)
 */
$map = [
    'invoice' => [
        'pending' => 'warning', 'overdue' => 'danger', 'paid' => 'success', 'void' => 'secondary',
    ],
    'subscription' => [
        'trialing' => 'info', 'active' => 'success', 'past_due' => 'warning', 'suspended' => 'danger', 'ended' => 'secondary',
    ],
    'reason' => [
        'overdue' => 'danger', 'trial_expired' => 'danger', 'no_subscription' => 'danger', 'manual' => 'dark',
    ],
];
$langKey = ['invoice' => 'invoice.status', 'subscription' => 'subscription.status', 'reason' => 'subscription.reason'][$kind] ?? 'subscription.status';
$color   = $map[$kind][$status] ?? 'secondary';
$label   = lang('App.' . $langKey . '.' . $status);
?>
<span class="badge bg-<?= esc($color, 'attr') ?>-subtle text-<?= esc($color, 'attr') ?>"><?= esc($label) ?></span>
