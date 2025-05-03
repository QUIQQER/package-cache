<?php

define('QUIQQER_SYSTEM', true);
require dirname(__FILE__, 7) . '/header.php';

/** @var true|false $debug */
$debug = false;

if ($debug) {
    QUI\System\Log::addError('QJO: Script started', [
        'argv' => $argv
    ]);
}

try {
    $Config = QUI::getPackage('quiqqer/cache')->getConfig();
    $qjo = $Config->get('quiqqer_js_optimizer', 'status');
    if ($debug) {
        QUI\System\Log::addError('QJO: Config loaded', [
            'status' => $qjo
        ]);
    }
} catch (QUI\Exception $ex) {
    if ($debug) {
        QUI\System\Log::addError('QJO: Config error', [
            'exception' => $ex->getMessage()
        ]);
    }
    exit(1);
}

$jsFile = $argv[1] ?? null;

if ($debug) {
    QUI\System\Log::addError('QJO: Checking jsFile', [
        'jsFile' => $jsFile
    ]);
}

if (!$jsFile) { // @phpstan-ignore-line
    if ($debug) {
        QUI\System\Log::addError('QJO: No jsFile parameter');
    }
    exit(1);
}

if (!file_exists($jsFile)) {
    if ($debug) {
        QUI\System\Log::addError('QJO: jsFile does not exist', [
            'jsFile' => $jsFile
        ]);
    }
    exit(1);
}

$code = file_get_contents($jsFile);

if (empty($code)) {
    if ($debug) {
        QUI\System\Log::addError('QJO: jsFile is empty', [
            'jsFile' => $jsFile
        ]);
    }
    exit(1);
}

$key = $Config->get('quiqqer_js_optimizer', 'license');
$optimizerUrl = $Config->get('quiqqer_js_optimizer', 'server_url');

if (empty($optimizerUrl)) {
    $optimizerUrl = 'https://js-optimizer.quiqqer.com';
}

if ($debug) {
    QUI\System\Log::addError('QJO: Sending request', [
        'optimizerUrl' => $optimizerUrl,
        'jsFile' => $jsFile
    ]);
}

$responseHeaders = null;
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: text/plain\r\nX-License-Key: $key\r\n",
        'content' => $code
    ]
]);

$fp = fopen($optimizerUrl . '/optimize', 'r', false, $context);

if ($fp === false) {
    QUI\System\Log::addError(
        'QJO: Could not connect to optimizer server',
        ['optimizerUrl' => $optimizerUrl]
    );
    exit(1);
}

$meta = stream_get_meta_data($fp);

if (isset($meta['wrapper_data'])) {
    $responseHeaders = $meta['wrapper_data'];
}

$result = stream_get_contents($fp);
fclose($fp);

if ($debug) {
    QUI\System\Log::addError('QJO: Got response', [
        'headers' => $responseHeaders,
        'result_sample' => substr($result, 0, 200)
    ]);
}

// Check HTTP status code
$statusCode = 200;
if ($responseHeaders) {
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\\d+\\.\\d+ (\\d{3})#', $header, $matches)) {
            $statusCode = (int)$matches[1];
            break;
        }
    }
}

// Check if response is JSON error
$isJson = false;
if (isset($responseHeaders)) {
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Content-Type: application/json') !== false) {
            $isJson = true;
            break;
        }
    }
}

if ($isJson) {
    $json = json_decode($result, true);
    if (isset($json['error'])) {
        $msg = $json['error'];
        if (isset($json['details'])) {
            $msg .= ': ' . $json['details'];
        }
        QUI\System\Log::addError('QJO: Optimizer server error', [
            'msg' => $msg
        ]);
        exit(1);
    }
}

if ($statusCode < 200 || $statusCode >= 300) {
    if ($debug) {
        QUI\System\Log::addError('QJO: Bad HTTP status', [
            'status' => $statusCode
        ]);
    }
    exit(1);
}

if ($debug) {
    QUI\System\Log::addError('QJO: Writing optimized file', [
        'jsFile' => $jsFile
    ]);
}

if (file_exists($jsFile)) { // @phpstan-ignore-line
    file_put_contents($jsFile, $result);
}

if ($debug) {
    QUI\System\Log::addError('QJO: Done', [
        'jsFile' => $jsFile
    ]);
}

exit(0);
