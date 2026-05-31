<?php

declare(strict_types=1);

use Mailjet\Client as MailjetClient;
use Mailjet\Resources;

if (!function_exists('lh_booking_resolve_admin_notification_email')) {
    function lh_booking_resolve_admin_notification_email(): string
    {
        if (defined('ADMIN_NOTIFICATION_EMAIL') && filter_var(ADMIN_NOTIFICATION_EMAIL, FILTER_VALIDATE_EMAIL)) {
            return ADMIN_NOTIFICATION_EMAIL;
        }
        if (!empty($_SERVER['SERVER_ADMIN']) && filter_var((string) $_SERVER['SERVER_ADMIN'], FILTER_VALIDATE_EMAIL)) {
            return (string) $_SERVER['SERVER_ADMIN'];
        }

        return '';
    }
}

if (!function_exists('lh_booking_mail_display_name')) {
    function lh_booking_mail_display_name(): string
    {
        return 'LikeHome | Daily rental';
    }
}

if (!function_exists('lh_booking_mail_from_header')) {
    /**
     * RFC 2047 encoded display name + envelope address for booking emails.
     */
    function lh_booking_mail_from_header(): ?string
    {
        if (!defined('BOOKING_MAIL_FROM')) {
            return null;
        }
        $addr = trim((string) BOOKING_MAIL_FROM);
        if ($addr === '' || !filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $encoded = '=?UTF-8?B?' . base64_encode(lh_booking_mail_display_name()) . '?=';

        return $encoded . ' <' . $addr . '>';
    }
}

if (!function_exists('lh_booking_send_via_mailjet')) {
    /**
     * @throws RuntimeException
     */
    function lh_booking_send_via_mailjet(string $to, string $subject, string $message, string $replyTo): void
    {
        if (!defined('MAILJET_API_KEY') || !defined('MAILJET_API_SECRET')) {
            throw new RuntimeException('Mailjet constants not defined (load config.php first).');
        }

        $fromAddr = defined('BOOKING_MAIL_FROM') ? trim((string) BOOKING_MAIL_FROM) : '';
        if ($fromAddr === '' || !filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('BOOKING_MAIL_FROM is missing or invalid.');
        }

        $mj = new MailjetClient((string) MAILJET_API_KEY, (string) MAILJET_API_SECRET, true, ['version' => 'v3.1']);

        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $fromAddr,
                        'Name'  => lh_booking_mail_display_name(),
                    ],
                    'To' => [
                        ['Email' => $to],
                    ],
                    'Subject'  => $subject,
                    'TextPart' => $message,
                ],
            ],
        ];

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $body['Messages'][0]['ReplyTo'] = ['Email' => $replyTo];
        }

        $response = $mj->post(Resources::$Email, ['body' => $body]);

        if (!$response->success()) {
            $status = $response->getStatus();
            $data   = json_encode($response->getData());
            throw new RuntimeException("Mailjet send failed (HTTP {$status}): {$data}");
        }
    }
}

if (!function_exists('send_booking_notification')) {
    function send_booking_notification(string $to, string $subject, string $message, string $replyTo = ''): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (defined('MAILJET_READY') && MAILJET_READY) {
            try {
                lh_booking_send_via_mailjet($to, $subject, $message, $replyTo);

                return true;
            } catch (Throwable $e) {
                error_log('booking_mail Mailjet error: ' . $e->getMessage());

                return false;
            }
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $from = lh_booking_mail_from_header();
        if ($from !== null) {
            $headers[] = 'From: ' . $from;
        }

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, implode("\r\n", $headers));
    }
}

if (!function_exists('send_telegram_notification')) {
    function send_telegram_notification(string $botToken, string $chatId, string $message): bool
    {
        if ($botToken === '' || $chatId === '' || $message === '') {
            return false;
        }

        $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            error_log('telegram_send: HTTP/request failed (no response body)');
            return false;
        }

        $data = json_decode($result, true);
        if (!is_array($data)) {
            error_log('telegram_send: invalid JSON response');
            return false;
        }

        if (!empty($data['ok'])) {
            return true;
        }

        $code = isset($data['error_code']) ? (string) $data['error_code'] : '?';
        $desc = isset($data['description']) ? (string) $data['description'] : '(no description)';
        error_log('telegram_send: API error_code=' . $code . ' description=' . $desc);

        return false;
    }
}
