<?php

declare(strict_types=1);

if (!function_exists('lh_page_content_file')) {
    function lh_page_content_file(string $page, ?string $locale = null): string
    {
        $locale = $locale ?? lh_current_locale();
        $base = dirname(__DIR__) . '/lang/pages/' . $page . '.' . $locale . '.php';
        if (is_readable($base)) {
            return $base;
        }

        $fallback = dirname(__DIR__) . '/lang/pages/' . $page . '.ro.php';

        return is_readable($fallback) ? $fallback : $base;
    }
}

if (!function_exists('lh_page_faq_items')) {
    /** @return list<array{q: string, a: string}> */
    function lh_page_faq_items(?string $locale = null): array
    {
        $file = lh_page_content_file('faq', $locale);
        if (!is_readable($file)) {
            return [];
        }
        /** @var mixed $data */
        $data = require $file;

        return is_array($data) ? $data : [];
    }
}

if (!function_exists('lh_page_sections')) {
    /** @return array<string, mixed> */
    function lh_page_sections(string $page, ?string $locale = null): array
    {
        $file = lh_page_content_file($page, $locale);
        if (!is_readable($file)) {
            return [];
        }
        /** @var mixed $data */
        $data = require $file;

        return is_array($data) ? $data : [];
    }
}

if (!function_exists('lh_page_meta')) {
    /**
     * @return array{title: string, description: string, heading: string, intro: string, label: string}
     */
    function lh_page_meta(string $page, ?string $locale = null): array
    {
        $locale = $locale ?? lh_current_locale();
        $key = 'page.' . $page;
        $defaults = [
            'faq' => [
                'title' => __('page.faq.title'),
                'description' => __('page.faq.description'),
                'heading' => __('page.faq.heading'),
                'intro' => __('page.faq.intro'),
                'label' => __('page.faq.label'),
            ],
            'about' => [
                'title' => __('page.about.title'),
                'description' => __('page.about.description'),
                'heading' => __('page.about.heading'),
                'intro' => '',
                'label' => __('page.about.label'),
            ],
            'contact' => [
                'title' => __('page.contact.title'),
                'description' => __('page.contact.description'),
                'heading' => __('page.contact.heading'),
                'intro' => '',
                'label' => __('page.contact.label'),
            ],
        ];

        return $defaults[$page] ?? [
            'title' => 'Like HOME',
            'description' => '',
            'heading' => '',
            'intro' => '',
            'label' => '',
        ];
    }
}
