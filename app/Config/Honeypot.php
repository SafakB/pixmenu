<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Honeypot extends BaseConfig
{
    /**
     * Makes Honeypot visible or not to human
     */
    public bool $hidden = true;

    /**
     * Honeypot Label Content
     */
    public string $label = 'Bu alanı boş bırakın';

    /**
     * Honeypot Field Name
     *
     * Tarayıcı otomatik doldurmasının tetiklemeyeceği bir ad (kayıt formu: /kayit rota filtresi).
     */
    public string $name = 'pix_hp';

    /**
     * Honeypot HTML Template
     */
    public string $template = '<label for="{name}">{label}</label><input type="text" id="{name}" name="{name}" value="" autocomplete="off" tabindex="-1">';

    /**
     * Honeypot container
     *
     * If you enabled CSP, you can remove `style="display:none"`.
     */
    public string $container = '<div style="display:none">{template}</div>';

    /**
     * The id attribute for Honeypot container tag
     *
     * Used when CSP is enabled.
     */
    public string $containerId = 'hpc';
}
