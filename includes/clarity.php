<?php

declare(strict_types=1);

/**
 * Microsoft Clarity — încărcare directă când CLARITY_PROJECT_ID e setat în .env.
 * Apelat din includes/header.php (după config.php).
 */
if (!function_exists('lh_env')) {
    return;
}

$id = trim(lh_env('CLARITY_PROJECT_ID', ''));
if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
    return;
}

$idJson = json_encode($id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($idJson === false) {
    return;
}
?>
  <!-- Microsoft Clarity -->
  <script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", <?php echo $idJson; ?>);
  </script>
