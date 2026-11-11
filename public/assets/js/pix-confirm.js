/**
 * PixMenu — onaylı form gönderimi (SweetAlert2).
 *
 * Kullanım: onay istenen POST formuna öznitelik ekle:
 *   data-confirm="Lezzet Sofrası silinecek."          (zorunlu; açıklama metni)
 *   data-confirm-title="Emin misiniz?"                (ops.)
 *   data-confirm-button="Evet, sil"                   (ops.; varsayılan "Evet, devam et")
 *   data-confirm-variant="danger|warning|primary"     (ops.; varsayılan danger)
 *
 * Form normal POST olarak kalır; yalnızca gönderim onaya bağlanır.
 * SweetAlert2 yüklenemezse tarayıcının confirm() penceresine düşer.
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.pixConfirmed === '1') return;

        e.preventDefault();

        var text = form.dataset.confirm || '';
        var title = form.dataset.confirmTitle || 'Emin misiniz?';
        var button = form.dataset.confirmButton || 'Evet, devam et';
        var variant = form.dataset.confirmVariant || 'danger';

        var proceed = function () {
            form.dataset.pixConfirmed = '1';
            // submit() submit olayını tetiklemez; requestSubmit varsa validasyon da çalışır.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        };

        if (typeof window.Swal === 'undefined') {
            if (window.confirm(title + (text ? '\n\n' + text : ''))) proceed();
            return;
        }

        window.Swal.fire({
            title: title,
            text: text,
            icon: variant === 'primary' ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: button,
            cancelButtonText: 'Vazgeç',
            buttonsStyling: false,
            focusCancel: true,
            customClass: {
                confirmButton: 'btn btn-' + variant + ' w-xs me-2 mt-2',
                cancelButton: 'btn btn-light w-xs mt-2'
            }
        }).then(function (result) {
            if (result.isConfirmed) proceed();
        });
    });
})();
