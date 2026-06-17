<?php

$legacyApi = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'compat' . DIRECTORY_SEPARATOR . 'legacy_api.php';
if (!file_exists($legacyApi)) {
    $legacyApi = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'legacy_api.php';
}
require_once $legacyApi;
