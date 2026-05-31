<?php

declare(strict_types=1);

/**
 * Safe fetch for iCal URLs (SSRF mitigation: HTTPS by default; optional HTTP via ICAL_ALLOW_HTTP; block private IPs).
 *
 * @return array{ok: true, body: string}|array{ok: false, error: string}
 */
function lh_ical_fetch_url(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'error' => 'Empty URL'];
    }

    $allowHttp = in_array(strtolower(lh_env('ICAL_ALLOW_HTTP', '0')), ['1', 'true', 'yes', 'on'], true);

    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme'])) {
        return ['ok' => false, 'error' => 'Invalid URL'];
    }

    $scheme = strtolower((string) $parts['scheme']);
    if ($scheme === 'https') {
        $curlProtocols = CURLPROTO_HTTPS;
        $curlRedirProtocols = CURLPROTO_HTTPS;
    } elseif ($scheme === 'http' && $allowHttp) {
        $curlProtocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $curlRedirProtocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    } else {
        return ['ok' => false, 'error' => $allowHttp ? 'Only HTTP and HTTPS URLs are allowed' : 'Only HTTPS URLs are allowed'];
    }

    $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
    if ($host === '') {
        return ['ok' => false, 'error' => 'Invalid host'];
    }

    if (!lh_ical_host_is_safe($host)) {
        return ['ok' => false, 'error' => 'Host not allowed'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required for iCal import'];
    }

    $maxBytes = (int) lh_env('ICAL_FETCH_MAX_BYTES', '2097152');
    if ($maxBytes < 1024) {
        $maxBytes = 2097152;
    }

    $ch = curl_init($url);
    $buf = '';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_PROTOCOLS => $curlProtocols,
        CURLOPT_REDIR_PROTOCOLS => $curlRedirProtocols,
        CURLOPT_USERAGENT => 'LikeHome-iCalImporter/1.0',
        CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$buf, $maxBytes) {
            $len = strlen($chunk);
            if (strlen($buf) + $len > $maxBytes) {
                return 0;
            }
            $buf .= $chunk;

            return $len;
        },
    ]);

    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($ok === false || $errno !== 0) {
        return ['ok' => false, 'error' => 'Download failed: ' . ($err !== '' ? $err : 'unknown')];
    }

    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => 'HTTP ' . $code];
    }

    if ($buf === '') {
        return ['ok' => false, 'error' => 'Empty response'];
    }

    return ['ok' => true, 'body' => $buf];
}

function lh_ical_is_private_or_reserved_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE);
    }

    return true;
}

function lh_ical_host_is_safe(string $host): bool
{
    if ($host === 'localhost' || substr($host, -10) === '.localhost') {
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return !lh_ical_is_private_or_reserved_ip($host);
    }

    $v4 = @gethostbynamel($host);
    if (is_array($v4)) {
        foreach ($v4 as $ip) {
            if (lh_ical_is_private_or_reserved_ip($ip)) {
                return false;
            }
        }
    }

    if (function_exists('dns_get_record')) {
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $rec) {
                $ip = $rec['ipv6'] ?? '';
                if ($ip !== '' && lh_ical_is_private_or_reserved_ip($ip)) {
                    return false;
                }
            }
        }
    }

    return true;
}
