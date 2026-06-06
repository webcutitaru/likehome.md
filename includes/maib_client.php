<?php

declare(strict_types=1);

use MaibEcomm\MaibCheckoutSdk\MaibCheckoutApiRequest;
use MaibEcomm\MaibCheckoutSdk\MaibCheckoutAuthRequest;

require_once __DIR__ . '/booking_payment.php';

if (!function_exists('lh_maib_configured')) {
    function lh_maib_configured(): bool
    {
        return trim(lh_env('MAIB_CLIENT_ID', '')) !== ''
            && trim(lh_env('MAIB_CLIENT_SECRET', '')) !== ''
            && trim(lh_env('MAIB_SIGNATURE_KEY', '')) !== '';
    }
}

if (!function_exists('lh_maib_api_base_url')) {
    function lh_maib_api_base_url(): ?string
    {
        $base = trim(lh_env('MAIB_API_BASE', ''));
        if ($base === '') {
            return null;
        }

        return rtrim($base, '/') . '/';
    }
}

if (!function_exists('lh_maib_checkout_language')) {
    function lh_maib_checkout_language(?string $locale = null): string
    {
        $locale = $locale ?? (function_exists('lh_current_locale') ? lh_current_locale() : 'ro');
        $map = ['ro' => 'ro', 'en' => 'en', 'ru' => 'ru'];

        return $map[$locale] ?? 'ro';
    }
}

if (!function_exists('lh_maib_access_token')) {
    function lh_maib_access_token(): string
    {
        static $cached = null;
        static $expiresAt = 0;

        if ($cached !== null && time() < $expiresAt - 30) {
            return $cached;
        }

        if (!lh_maib_configured()) {
            throw new RuntimeException('MAIB credentials are not configured.');
        }

        $clientId = trim(lh_env('MAIB_CLIENT_ID', ''));
        $clientSecret = trim(lh_env('MAIB_CLIENT_SECRET', ''));
        $baseUrl = lh_maib_api_base_url();

        $auth = MaibCheckoutAuthRequest::create($baseUrl);
        $result = $auth->generateToken($clientId, $clientSecret);

        $token = is_object($result)
            ? (string) ($result->accessToken ?? '')
            : (string) ($result['accessToken'] ?? '');

        if ($token === '') {
            throw new RuntimeException('MAIB token response missing accessToken.');
        }

        $expiresIn = is_object($result)
            ? (int) ($result->expiresIn ?? 300)
            : (int) ($result['expiresIn'] ?? 300);

        $cached = $token;
        $expiresAt = time() + max(60, $expiresIn);

        return $cached;
    }
}

if (!function_exists('lh_maib_create_checkout')) {
    /**
     * @param array<string, mixed> $payload
     * @return object{checkoutId: string, checkoutUrl: string}
     */
    function lh_maib_create_checkout(array $payload): object
    {
        $token = lh_maib_access_token();
        $baseUrl = lh_maib_api_base_url();
        $api = MaibCheckoutApiRequest::create($baseUrl);
        $response = $api->createCheckout($payload, $token);

        if (!isset($response->ok) || !$response->ok || !isset($response->result)) {
            $msg = 'MAIB createCheckout failed';
            if (isset($response->errors[0])) {
                $err = $response->errors[0];
                $msg .= ': ' . ($err->errorMessage ?? '') . ' (' . ($err->errorCode ?? '') . ')';
            }
            throw new RuntimeException($msg);
        }

        $checkoutId = (string) ($response->result->checkoutId ?? '');
        $checkoutUrl = (string) ($response->result->checkoutUrl ?? '');
        if ($checkoutId === '' || $checkoutUrl === '') {
            throw new RuntimeException('MAIB createCheckout missing checkoutId or checkoutUrl.');
        }

        return (object) [
            'checkoutId' => $checkoutId,
            'checkoutUrl' => $checkoutUrl,
        ];
    }
}

if (!function_exists('lh_maib_get_checkout')) {
    function lh_maib_get_checkout(string $checkoutId): object
    {
        $token = lh_maib_access_token();
        $baseUrl = lh_maib_api_base_url();
        $api = MaibCheckoutApiRequest::create($baseUrl);
        $response = $api->getCheckout($checkoutId, $token);

        if (!isset($response->ok) || !$response->ok || !isset($response->result)) {
            throw new RuntimeException('MAIB getCheckout failed for ' . $checkoutId);
        }

        return $response->result;
    }
}

if (!function_exists('lh_maib_refund_payment')) {
    /**
     * @return object Refund result from maib API
     */
    function lh_maib_refund_payment(string $paymentId, ?float $amount = null, ?string $reason = null): object
    {
        $token = lh_maib_access_token();
        $baseUrl = lh_maib_api_base_url();
        $api = MaibCheckoutApiRequest::create($baseUrl);

        $data = ['payId' => $paymentId];
        if ($amount !== null) {
            $data['amount'] = round($amount, 2);
        }
        if ($reason !== null && trim($reason) !== '') {
            $data['reason'] = trim($reason);
        }

        $response = $api->refund($data, $token);
        if (!isset($response->ok) || !$response->ok) {
            $msg = 'MAIB refund failed';
            if (isset($response->errors[0])) {
                $err = $response->errors[0];
                $msg .= ': ' . ($err->errorMessage ?? '') . ' (' . ($err->errorCode ?? '') . ')';
            }
            throw new RuntimeException($msg);
        }

        return $response->result ?? (object) [];
    }
}

if (!function_exists('lh_maib_verify_callback_signature')) {
    function lh_maib_verify_callback_signature(string $rawBody, ?string $xSignature, ?string $xTimestamp): bool
    {
        $key = trim(lh_env('MAIB_SIGNATURE_KEY', ''));
        if ($key === '' || $xSignature === null || $xTimestamp === null || $xSignature === '' || $xTimestamp === '') {
            return false;
        }

        $receivedSig = str_starts_with($xSignature, 'sha256=')
            ? substr($xSignature, 7)
            : $xSignature;

        $message = $rawBody . '.' . $xTimestamp;
        $expectedSig = base64_encode(hash_hmac('sha256', $message, $key, true));

        return hash_equals($expectedSig, $receivedSig);
    }
}

if (!function_exists('lh_maib_payment_urls')) {
    /**
     * @return array{callbackUrl: string, successUrl: string, failUrl: string}
     */
    function lh_maib_payment_urls(?string $locale = null): array
    {
        $locale = $locale ?? lh_default_locale();
        $success = lh_absolute_locale_url('booking-payment-success.php', $locale);
        $fail = lh_absolute_locale_url('booking-payment-failed.php', $locale);
        $callback = lh_absolute_url('ajax/maib_callback.php');

        return [
            'callbackUrl' => $callback,
            'successUrl' => $success,
            'failUrl' => $fail,
        ];
    }
}
