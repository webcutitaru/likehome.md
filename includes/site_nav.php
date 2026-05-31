<?php

declare(strict_types=1);

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
            ['label' => 'Acasă', 'href' => lh_public_url(), 'file' => 'index.php'],
            ['label' => 'Proprietăți', 'href' => lh_public_url('properties.php'), 'file' => 'properties.php'],
            ['label' => 'Întrebări frecvente', 'href' => lh_public_url('faq.php'), 'file' => 'faq.php'],
            ['label' => 'Despre noi', 'href' => lh_public_url('about.php'), 'file' => 'about.php'],
            ['label' => 'Contact', 'href' => lh_public_url('contact.php'), 'file' => 'contact.php'],
        ];
    }
}
