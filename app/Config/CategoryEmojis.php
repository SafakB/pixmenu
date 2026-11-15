<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Kategoriye seçilebilecek emojiler (görsel hazırlayamayan müşteriler için).
 *
 * Panel formu bu listeyi gruplar halinde gösterir; servis yalnızca buradaki
 * değerleri kabul eder. Windows 10 ve eski telefonlarda da görünsün diye Unicode ≤ 12 ile sınırlıdır
 * (13+ emojiler, ör. 🫕, Windows 10'da boş kutu olarak çıkar).
 * Bir emoji listeden çıkarılırsa DB'deki mevcut değer korunur: form onu ayrıca
 * gösterir, servis değişmeyen değeri yeniden denetlemez; yalnızca yeni seçimler kısıtlıdır.
 */
final class CategoryEmojis extends BaseConfig
{
    /**
     * Grup adı => emojiler.
     *
     * @var array<string, list<string>>
     */
    public array $groups = [
        'Çorba & ana yemek' => ['🍲', '🥘', '🍛', '🍝', '🍜', '🍖', '🍗', '🥩', '🍔', '🍕', '🌭', '🌯', '🌮', '🥙', '🧆', '🍳', '🥟', '🍢', '🍱', '🥪', '🍟'],
        'Deniz ürünleri'    => ['🐟', '🍤', '🦐', '🦑', '🐙', '🦀', '🦞', '🦪', '🍣'],
        'Kahvaltı & fırın'  => ['🥐', '🥖', '🥨', '🥯', '🍞', '🧇', '🥞', '🧀', '🧈', '🥓', '🥚', '🍯'],
        'Salata & sebze'    => ['🥗', '🥑', '🍅', '🥒', '🥕', '🌽', '🥦', '🍆', '🥔', '🧅', '🧄', '🍄', '🌶️', '🥜', '🌰'],
        'Meyveler'          => ['🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝'],
        'Tatlılar'          => ['🍰', '🎂', '🧁', '🥧', '🍮', '🍩', '🍪', '🍫', '🍬', '🍭', '🍨', '🍦', '🍧', '🥮', '🍡', '🥠'],
        'Sıcak içecekler'   => ['☕', '🍵', '🧉'],
        'Soğuk içecekler'   => ['🥤', '🧃', '🥛', '🧊', '💧'],
        'Alkollü içecekler' => ['🍹', '🍸', '🍷', '🍺', '🍻', '🥂', '🍾', '🥃', '🍶'],
        'Diğer'             => ['🍽️', '🍴', '🥡', '🍿', '🥫', '🧂', '🌱', '🔥', '⭐', '👶', '🎉'],
    ];

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_merge(...array_values($this->groups));
    }

    public function isAllowed(string $emoji): bool
    {
        return in_array($emoji, $this->all(), true);
    }
}
