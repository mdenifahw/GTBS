<?php
/**
 * Root Router - GT Barcode System
 * Mendeteksi environment secara cerdas:
 * - Jika di Vercel: redirect ke /login.php atau load langsung
 * - Jika di Local (Laragon/Nginx subfolder): redirect ke base_url . /website/login.php
 */

$isVercel = (getenv('VERCEL') || !empty($_ENV['VERCEL']));
if ($isVercel) {
    header("Location: /login.php");
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = rtrim(dirname($scriptName), '/\\');
$target = ($baseDir ? $baseDir : '') . '/website/login.php';

header("Location: " . $target);
exit;
