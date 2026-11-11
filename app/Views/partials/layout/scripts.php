<?php
/**
 * Ortak çekirdek script'ler. Layout bunun ardından renderSection('js') ve en sonda app.js basar.
 * Not: temanın plugins.js dosyası kullanılmıyor (CDN'den toastify + göreli yollarla
 * choices/flatpickr yüklüyordu). Bu kütüphaneler gereken sayfanın js section'ında yüklenir.
 * SweetAlert2 + pix-confirm.js her admin/panel sayfasında yüklü: form[data-confirm] onay ister.
 * (SweetAlert2 CSS'i layout'ların <head>'inde.)
 */
?>
<script src="<?= asset_url('libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset_url('libs/simplebar/simplebar.min.js') ?>"></script>
<script src="<?= asset_url('libs/node-waves/waves.min.js') ?>"></script>
<script src="<?= asset_url('libs/feather-icons/feather.min.js') ?>"></script>
<script src="<?= asset_url('libs/sweetalert2/sweetalert2.min.js') ?>"></script>
<script src="<?= asset_url('js/pix-confirm.js') ?>"></script>
