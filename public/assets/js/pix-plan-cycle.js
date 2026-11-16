/**
 * PixMenu — paket kartlarında aylık/yıllık geçişi (partials/billing/plan_card).
 *
 * Sözleşme:
 *   .plan-nav [data-cycle="monthly|yearly"]  geçiş düğmeleri (aktif olan `.active`)
 *   .price-monthly / .price-yearly           fiyat blokları (d-none ile gizlenir)
 *   input[data-cycle-input]                  seçili dönemi taşıyan (gizli) alanlar — pix-confirm
 *                                            requestSubmit() submitter göndermediği için düğme değeri yerine
 *   a[data-cycle-href]                       href'indeki `cycle=` parametresi güncellenir
 * Değişimde document üzerinde `pix:cycle` olayı yayınlanır (detail.cycle).
 */
(function () {
    'use strict';

    var buttons = Array.prototype.slice.call(document.querySelectorAll('.plan-nav [data-cycle]'));

    if (buttons.length === 0) {
        return;
    }

    function each(selector, fn) {
        Array.prototype.forEach.call(document.querySelectorAll(selector), fn);
    }

    function apply(cycle) {
        var yearly = cycle === 'yearly';

        buttons.forEach(function (b) {
            var on = b.getAttribute('data-cycle') === cycle;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        each('.price-monthly', function (el) { el.classList.toggle('d-none', yearly); });
        each('.price-yearly', function (el) { el.classList.toggle('d-none', !yearly); });
        each('input[data-cycle-input]', function (el) { el.value = cycle; });
        each('a[data-cycle-href]', function (a) {
            try {
                var url = new URL(a.getAttribute('href'), window.location.href);
                url.searchParams.set('cycle', cycle);
                a.setAttribute('href', url.pathname + url.search + url.hash);
            } catch (e) { /* geçersiz href: dokunma */ }
        });

        document.dispatchEvent(new CustomEvent('pix:cycle', { detail: { cycle: cycle } }));
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            apply(btn.getAttribute('data-cycle'));
        });
    });

    var active = buttons.filter(function (b) { return b.classList.contains('active'); })[0] || buttons[0];
    apply(active.getAttribute('data-cycle'));
})();
