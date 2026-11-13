/**
 * PixMenu — dosya seçilince görsel önizleme.
 *
 * Kullanım:
 *   <input type="file" data-preview="#image-preview" data-preview-placeholder="#image-placeholder">
 *   <img id="image-preview" class="d-none" ...>
 *   <div id="image-placeholder">…</div>   (ops.; görsel seçilince gizlenir)
 *
 * Sayfada data-preview'li input yoksa hiçbir şey yapmaz.
 */
(function () {
    'use strict';

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('input[type=file][data-preview]')) return;
        if (!input.files || !input.files[0]) return;

        var img = document.querySelector(input.dataset.preview);
        if (!img) return;
        img.src = URL.createObjectURL(input.files[0]);
        img.classList.remove('d-none');

        if (input.dataset.previewPlaceholder) {
            var placeholder = document.querySelector(input.dataset.previewPlaceholder);
            if (placeholder) placeholder.classList.add('d-none');
        }
    });
})();
