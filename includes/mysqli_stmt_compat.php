<?php

declare(strict_types=1);

/**
 * Fetch one row as associative array from an executed mysqli_stmt.
 * Hosts without mysqlnd do not provide mysqli_stmt_get_result(); this path uses bind_result.
 */
if (!function_exists('lh_mysqli_stmt_fetch_assoc')) {
    function lh_mysqli_stmt_fetch_assoc(mysqli_stmt $stmt): ?array
    {
        if (function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            if ($result === false) {
                return null;
            }
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);

            return $row === false ? null : $row;
        }

        mysqli_stmt_store_result($stmt);
        $meta = mysqli_stmt_result_metadata($stmt);
        if ($meta === false) {
            return null;
        }

        $row = [];
        $bind = [];
        while ($field = mysqli_fetch_field($meta)) {
            $row[$field->name] = null;
            $bind[] = &$row[$field->name];
        }
        mysqli_free_result($meta);

        if ($bind === []) {
            return null;
        }

        call_user_func_array([$stmt, 'bind_result'], $bind);

        if (!mysqli_stmt_fetch($stmt)) {
            return null;
        }

        $out = [];
        foreach ($row as $k => $v) {
            $out[$k] = $v;
        }

        return $out;
    }
}
