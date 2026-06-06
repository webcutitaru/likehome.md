<?php

declare(strict_types=1);

require_once __DIR__ . '/company_legal.php';

if (!function_exists('lh_nav_current_script')) {
    function lh_nav_current_script(): string
    {
        return basename($_SERVER['SCRIPT_NAME'] ?? '');
    }
}

if (!function_exists('lh_nav_is_current')) {
    function lh_nav_is_current(string $navFile): bool
    {
        $cur = lh_nav_current_script();
        if ($navFile === 'properties.php' && $cur === 'property-details.php') {
            return true;
        }

        return $cur === $navFile;
    }
}

if (!function_exists('lh_site_contact_email')) {
    function lh_site_contact_email(): string
    {
        return 'contact@likehome.md';
    }
}

if (!function_exists('lh_site_contact_city')) {
    function lh_site_contact_city(): string
    {
        return 'Chișinău';
    }
}

if (!function_exists('lh_site_nav_items')) {
    /**
     * @return list<array{label: string, href: string, file: string}>
     */
    function lh_site_nav_items(): array
    {
        return [
            ['label' => __('nav.home'), 'href' => lh_locale_url(), 'file' => 'index.php'],
            ['label' => __('nav.properties'), 'href' => lh_locale_url('properties.php'), 'file' => 'properties.php'],
            ['label' => __('nav.faq'), 'href' => lh_locale_url('faq.php'), 'file' => 'faq.php'],
            ['label' => __('nav.about'), 'href' => lh_locale_url('about.php'), 'file' => 'about.php'],
            ['label' => __('nav.contact'), 'href' => lh_locale_url('contact.php'), 'file' => 'contact.php'],
        ];
    }
}
