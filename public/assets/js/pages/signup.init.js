/**
 * PixMenu — kayıt sihirbazı (site/signup): Hesap → İşletme → Paket.
 * Tek form; adım geçişinde o adımın alanları tarayıcı doğrulamasıyla (reportValidity) kontrol edilir.
 * Sunucu hatasında `data-start-step` ilk hatalı adımı açar.
 */
(function () {
    'use strict';

    var form = document.getElementById('signup-form');

    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(form.querySelectorAll('.signup-step'));
    var tabs = Array.prototype.slice.call(form.querySelectorAll('[data-step-tab]'));
    var current = 1;
    var reached = parseInt(form.getAttribute('data-start-step'), 10) || 1;

    // Şifre tekrarı: tarayıcı doğrulamasına özel kural.
    var password = form.querySelector('#password');
    var confirm = form.querySelector('#password_confirm');

    function syncConfirm() {
        if (password && confirm) {
            confirm.setCustomValidity(confirm.value !== '' && confirm.value !== password.value ? 'Şifre tekrarı eşleşmiyor.' : '');
        }
    }

    if (password && confirm) {
        password.addEventListener('input', syncConfirm);
        confirm.addEventListener('input', syncConfirm);
    }

    function fieldsOf(step) {
        return Array.prototype.slice.call(steps[step - 1].querySelectorAll('input, select, textarea')).filter(function (el) {
            return !el.disabled && el.type !== 'hidden';
        });
    }

    function show(step, scroll) {
        current = step;
        reached = Math.max(reached, step);

        steps.forEach(function (el) {
            el.classList.toggle('d-none', parseInt(el.getAttribute('data-step'), 10) !== step);
        });
        tabs.forEach(function (tab) {
            var n = parseInt(tab.getAttribute('data-step-tab'), 10);
            tab.classList.toggle('active', n === step);
            tab.classList.toggle('done', n !== step && n <= reached);
            tab.setAttribute('aria-selected', n === step ? 'true' : 'false');
        });

        if (scroll) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function validStep(step, report) {
        syncConfirm();

        var fields = fieldsOf(step);

        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].checkValidity()) {
                if (report) {
                    show(step, false);
                    fields[i].reportValidity();
                }

                return false;
            }
        }

        return true;
    }

    form.querySelectorAll('[data-step-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (validStep(current, true)) {
                show(Math.min(current + 1, steps.length), true);
            }
        });
    });

    form.querySelectorAll('[data-step-prev]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            show(Math.max(current - 1, 1), true);
        });
    });

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = parseInt(tab.getAttribute('data-step-tab'), 10);

            for (var s = current; s < target; s++) {
                if (!validStep(s, true)) {
                    return;
                }
            }

            show(target, true);
        });
    });

    // Paket seçimi: kart vurgusu + gönder düğmesinin etiketi.
    var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="plan_id"]'));
    var submitLabel = form.querySelector('[data-submit-label]');
    var submitBtn = form.querySelector('[data-submit]');
    var paidNote = form.querySelector('[data-paid-note]');

    function syncPlan() {
        var paid = false;

        radios.forEach(function (r) {
            var card = r.closest('.pricing-box');

            if (card) {
                card.classList.toggle('is-selected', r.checked);
            }

            if (r.checked && r.getAttribute('data-paid') === '1') {
                paid = true;
            }
        });

        if (submitLabel && submitBtn) {
            submitLabel.textContent = submitBtn.getAttribute(paid ? 'data-label-paid' : 'data-label-trial');
        }

        if (paidNote) {
            paidNote.classList.toggle('d-none', !paid);
        }
    }

    radios.forEach(function (r) {
        r.addEventListener('change', syncPlan);
    });

    form.addEventListener('submit', function (e) {
        for (var s = 1; s <= steps.length; s++) {
            if (!validStep(s, true)) {
                e.preventDefault();

                return;
            }
        }

        if (submitBtn) {
            submitBtn.disabled = true; // çift gönderimi engelle
        }
    });

    show(Math.min(Math.max(parseInt(form.getAttribute('data-start-step'), 10) || 1, 1), steps.length), false);
    syncPlan();

    var firstInvalid = form.querySelector('.signup-step:not(.d-none) .is-invalid');

    if (firstInvalid && typeof firstInvalid.focus === 'function') {
        firstInvalid.focus();
    }
})();
