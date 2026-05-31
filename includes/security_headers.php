<?php

declare(strict_types=1);

/**
 * Send baseline HTTP security headers (safe defaults for this app’s CDN usage).
 */
function lh_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Adaugă domenii suplimentare în consolă dacă GTM/Ads raportează blocări CSP.
    // Clarity: https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-csp
    // — default-src include *.clarity.ms + c.bing.com (redirecte la pixeli); script-uri suplimentare scripts.clarity.ms
    $csp = implode('; ', [
        "default-src 'self' https://*.clarity.ms https://c.bing.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://fonts.googleapis.com https://www.googletagmanager.com https://www.google-analytics.com https://ssl.google-analytics.com https://www.googleadservices.com https://www.google.com https://www.gstatic.com https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net https://connect.facebook.net https://www.clarity.ms https://scripts.clarity.ms",
        "script-src-attr 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: https: https://*.clarity.ms https://c.bing.com",
        "connect-src 'self' https://cdn.jsdelivr.net https://unpkg.com https://www.google-analytics.com https://region1.google-analytics.com https://analytics.google.com https://www.googletagmanager.com https://stats.g.doubleclick.net https://googleads.g.doubleclick.net https://www.google.com https://pagead2.googlesyndication.com https://www.facebook.com https://graph.facebook.com https://www.clarity.ms https://*.clarity.ms https://c.bing.com",
        "frame-src 'self' https://www.google.com https://maps.google.com https://*.google.com https://www.googletagmanager.com https://www.googleadservices.com https://td.doubleclick.net",
    ]);

    header('Content-Security-Policy: ' . $csp);
}
