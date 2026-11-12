<?php
/**
 * E-postalardaki fatura özeti. Parametre: invoice
 */
?>
<table role="presentation" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:16px 0;">
    <tr><td style="color:#878a99;">Fatura no</td><td><strong><?= esc((string) $invoice['number']) ?></strong></td></tr>
    <tr><td style="color:#878a99;">Açıklama</td><td><?= esc($invoice['description']) ?></td></tr>
    <tr><td style="color:#878a99;">Tutar (KDV dahil)</td><td><strong><?= esc(pix_money($invoice['total'], $invoice['currency_code'])) ?></strong></td></tr>
    <tr><td style="color:#878a99;">Son ödeme tarihi</td><td><?= esc(pix_date($invoice['due_date'])) ?></td></tr>
</table>
