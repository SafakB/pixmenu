/**
 * PixMenu — tanıtım sitesi (layouts/landing): yapışkan navbar, mobil menünün bağlantı
 * tıklamasında kapanması, sayfa başına dön düğmesi. (Velzon landing.init.js'in sade hâli;
 * Swiper/sayaç/onclick kullanılmaz.)
 */
(function () {
    'use strict';

    var navbar = document.getElementById('navbar');
    var backTop = document.getElementById('back-to-top');

    function onScroll() {
        var y = window.scrollY || document.documentElement.scrollTop;

        if (navbar) {
            navbar.classList.toggle('is-sticky', y >= 50);
        }

        if (backTop) {
            backTop.style.display = y > 100 ? 'block' : 'none';
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (backTop) {
        backTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    var menu = document.getElementById('navbarSupportedContent');

    if (menu && window.bootstrap) {
        menu.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (menu.classList.contains('show')) {
                    window.bootstrap.Collapse.getOrCreateInstance(menu).hide();
                }
            });
        });
    }
})();
