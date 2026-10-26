<?php
/**
 * Backward compatibility redirector for mobile apps or bookmarks pointing to root login.php
 */
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: website/login.php" . $qs);
exit;
