<?php

/**
 * service worker
 * set the header for the worker, that the worker has sufficient rights
 */

header('service-worker: script');
header('content-type: application/javascript');
header('Service-Worker-Allowed: /');

echo file_get_contents('html.js');
exit;
