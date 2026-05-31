<?php

declare(strict_types=1);

if (!function_exists('lh_rate_limit_exceeded')) {
    /**
     * Sliding window rate limit using a temp-dir file per bucket key.
     *
     * @return true if the caller should block this request (limit exceeded)
     */
    function lh_rate_limit_exceeded(string $bucketKey, int $maxAttempts, int $windowSeconds): bool
    {
        if ($maxAttempts < 1 || $windowSeconds < 1) {
            return false;
        }

        $dir = sys_get_temp_dir() . '/likehome_rate';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }

        $file = $dir . '/' . hash('sha256', $bucketKey) . '.json';
        $now = time();
        $state = ['count' => 0, 'window_start' => $now];

        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);

            return false;
        }

        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $state = array_merge($state, $decoded);
            }
        }

        $start = (int) ($state['window_start'] ?? $now);
        $count = (int) ($state['count'] ?? 0);

        if ($now - $start >= $windowSeconds) {
            $count = 1;
            $start = $now;
        } else {
            $count++;
        }

        $payload = json_encode(['count' => $count, 'window_start' => $start]);
        if ($payload === false) {
            $payload = '{"count":1,"window_start":' . $now . '}';
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $payload);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $count > $maxAttempts;
    }
}
