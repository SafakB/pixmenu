/**
 * PixMenu — ödeme ekranı (panel/subscription/checkout): card.js kart önizlemesi.
 * Kart alanlarının `name`'i yoktur; değerler hiçbir yere gönderilmez (TODO(PayTR)).
 */
(function () {
    'use strict';

    var form = document.getElementById('checkout-form');

    if (!form || typeof window.Card !== 'function') {
        return;
    }

    new window.Card({
        form: form,
        container: '.card-wrapper',
        formSelectors: {
            numberInput: 'input#card-number',
            expiryInput: 'input#card-expiry',
            cvcInput: 'input#card-cvc',
            nameInput: 'input#card-name'
        },
        placeholders: {
            number: '•••• •••• •••• ••••',
            name: 'AD SOYAD',
            expiry: '••/••',
            cvc: '•••'
        },
        messages: {
            validDate: 'son\nkullanma',
            monthYear: 'ay/yıl'
        },
        masks: { cardNumber: '•' }
    });
})();
