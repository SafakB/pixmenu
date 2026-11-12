<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Abonelik ve faturalama ayarları (docs/ABONELIK_PLANI.md §4).
 * .env ile ezilebilir: billing.graceDays = 10
 */
final class Billing extends BaseConfig
{
    /** Gecikme eşiği: vadeden kaç gün SONRA askıya alınır. */
    public int $graceDays = 7;

    /** Vade = kesim tarihi + bu değer (ilk fatura / paket değişimi). */
    public int $paymentTermDays = 7;

    /** Yenileme faturası dönem bitiminden kaç gün önce kesilir (vade = dönem sonu). */
    public int $invoiceLeadDays = 7;

    /** Vadeye kaç gün kala hatırlatma e-postası. */
    public int $reminderDaysBeforeDue = 3;

    /** Deneme bitişine kaç gün kala hatırlatma e-postası. */
    public int $trialReminderDays = 3;

    /** KDV oranı (%). Fiyatlar KDV hariçtir. */
    public float $taxRate = 20.00;

    /** Fatura numarası öneki: PM-2026-000123 */
    public string $invoicePrefix = 'PM';

    /** Bildirim e-postalarının gizli kopyası (boş = yok). */
    public string $billingEmailBcc = '';

    /** Yeni restorana varsayılan atanan paket kodu. */
    public string $defaultPlanCode = 'demo';

    /** Özel alan adı çözümleme cache süresi (saniye). */
    public int $domainCacheTtl = 120;

    /**
     * Özel alan adı olarak kabul edilmeyen adlar (uygulamanın kendi host'ları ayrıca reddedilir).
     *
     * @var list<string>
     */
    public array $reservedDomains = ['localhost', 'pixmenu.com', 'pixmenu.app'];

    /** Fatura üzerindeki satıcı bilgisi (yazdırılabilir görünüm). */
    public string $sellerName    = 'PixMenu';
    public string $sellerAddress = '';
    public string $sellerTaxInfo = '';

    /** Ödeme bilgisi (fatura ve e-postalarda gösterilir; ör. IBAN). */
    public string $paymentInstructions = '';
}
