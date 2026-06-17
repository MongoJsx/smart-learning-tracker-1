<?php

$rootDir = dirname(__DIR__);
$publicDir = __DIR__;

$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$path = $uri;
if (strpos($path, '?') !== false) {
    $parts = explode('?', $path, 2);
    $path = $parts[0];
}

$indexPos = strpos($path, 'index.php');
if ($indexPos !== false) {
    $path = substr($path, $indexPos + strlen('index.php'));
}

$path = '/' . ltrim($path, '/');
$apiPos = strpos($path, '/api');

if ($apiPos !== false) {
    $path = substr($path, $apiPos);
}

if (strpos($path, '/api') === 0) {
    $useLegacy = false;
    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID < 80200) {
        $useLegacy = true;
    }

    $envPath = $rootDir . DIRECTORY_SEPARATOR . '.env';
    if (file_exists($envPath)) {
        $parsedEnv = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if (is_array($parsedEnv) && isset($parsedEnv['LEGACY_API'])) {
            $flag = strtolower(trim((string) $parsedEnv['LEGACY_API']));
            if ($flag === 'true' || $flag === '1') {
                $useLegacy = true;
            }
        }
    }

    if (!$useLegacy && file_exists($rootDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php')) {
        require $rootDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $app = require $rootDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $request = Illuminate\Http\Request::capture();
        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);
        exit;
    }

    $legacyApi = $rootDir . DIRECTORY_SEPARATOR . 'compat' . DIRECTORY_SEPARATOR . 'legacy_api.php';
    if (!file_exists($legacyApi)) {
        $legacyApi = $rootDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'legacy_api.php';
    }
    require $legacyApi;
    exit;
}

$adminMap = [
    'admin' => $rootDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin.php',
    'admin-login' => $rootDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin_login.php',
    'admin-logout' => $rootDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin_logout.php',
    'admin.php' => $rootDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin.php',
    'admin_login.php' => $rootDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin_login.php',
    'admin_logout.php' => $rootDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin_logout.php',
    'admin_api.php' => $rootDir . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin_api.php',
];

if (preg_match('#/(admin|admin-login|admin-logout|admin\.php|admin_login\.php|admin_logout\.php|admin_api\.php)(?:/)?$#', $path, $matches)) {
    $key = $matches[1];
    if (isset($adminMap[$key]) && file_exists($adminMap[$key])) {
        require $adminMap[$key];
        exit;
    }
}

$legacyHtml = $publicDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'index.html';
$indexHtml = $publicDir . DIRECTORY_SEPARATOR . 'index.html';

if (file_exists($legacyHtml)) {
    $file = $legacyHtml;
} elseif (file_exists($indexHtml)) {
    $file = $indexHtml;
} else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'index.html not found';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
$content = @file_get_contents($file);
if ($content === false) {
    http_response_code(500);
    echo 'failed to read index file';
    exit;
}

// Ensure relative asset paths in built SPA resolve correctly even on nested routes
if ($file === $legacyHtml && stripos($content, '<base ') === false) {
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/public/index.php';
    $publicPath = str_replace('\\', '/', dirname($scriptName));
    $publicPath = rtrim($publicPath, '/');
    if ($publicPath === '' || $publicPath === '.') {
        $publicPath = '/public';
    }
    $appBase = $publicPath . '/app/';
    $baseTag = '<base href="' . htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') . '">';

    // Normalize built absolute URLs from other environments (e.g. /xxxx/public/app/...) to current app base
    $content = preg_replace(
        '#(src|href)="/[^"]*/public/app/#i',
        '$1="' . $appBase,
        $content
    );

    if (stripos($content, '</head>') !== false) {
        $content = preg_replace('/<\/head>/i', "    {$baseTag}\n</head>", $content, 1);
    } else {
        $content = $baseTag . "\n" . $content;
    }
}

if ($file === $legacyHtml) {
    $removeAddSubjectScript = <<<'HTML'
<script>
(function () {
    function improveDifficultyLayout() {
        var labels = document.querySelectorAll('h1, h2, h3, h4, div, p, span, label');
        for (var i = 0; i < labels.length; i++) {
            var label = labels[i];
            var text = (label.textContent || '').replace(/\s+/g, ' ').trim();
            if (text !== 'ระดับความยาก') {
                continue;
            }

            var card = label.closest('section, article, div');
            if (!card) {
                continue;
            }

            var groups = card.querySelectorAll('div, nav');
            for (var g = 0; g < groups.length; g++) {
                var group = groups[g];
                var options = group.querySelectorAll('button, [role="button"]');
                if (options.length < 3) {
                    continue;
                }

                var matchCount = 0;
                for (var o = 0; o < options.length; o++) {
                    var optionText = (options[o].textContent || '').replace(/\s+/g, ' ').trim();
                    if (optionText === 'ง่าย' || optionText === 'ปานกลาง' || optionText === 'มาก') {
                        matchCount++;
                    }
                }
                if (matchCount < 3) {
                    continue;
                }

                group.style.display = 'flex';
                group.style.gap = '8px';
                group.style.flexWrap = 'nowrap';
                group.style.justifyContent = 'space-between';
                group.style.alignItems = 'center';

                for (var j = 0; j < options.length; j++) {
                    var btn = options[j];
                    var btnText = (btn.textContent || '').replace(/\s+/g, ' ').trim();
                    if (btnText === 'ปานกลาง') {
                        btn.textContent = 'กลาง';
                    }
                    btn.style.flex = '1 1 0';
                    btn.style.minWidth = '72px';
                    btn.style.whiteSpace = 'nowrap';
                    btn.style.textAlign = 'center';
                    btn.style.paddingLeft = '10px';
                    btn.style.paddingRight = '10px';
                }
            }
        }
    }

    function removeAddSubjectButton() {
        var path = (window.location.pathname || '').toLowerCase();
        if (path.indexOf('/calendar') === -1) {
            return;
        }

        // Remove any direct "เพิ่มวิชา" action on calendar page.
        var allActions = document.querySelectorAll('button, a');
        for (var k = 0; k < allActions.length; k++) {
            var actionNode = allActions[k];
            var actionLabel = (actionNode.textContent || '').replace(/\s+/g, ' ').trim();
            if (actionLabel === 'เพิ่มวิชา' || actionLabel.indexOf('เพิ่มวิชา') !== -1) {
                actionNode.remove();
            }
        }

        var titleNodes = document.querySelectorAll('h1, h2, h3, h4, div, p, span');
        for (var i = 0; i < titleNodes.length; i++) {
            var titleNode = titleNodes[i];
            var titleText = (titleNode.textContent || '').replace(/\s+/g, ' ').trim();
            if (titleText !== 'จัดการรายวิชาในระบบ') {
                continue;
            }

            var container = titleNode.closest('section, article, div');
            if (!container) {
                continue;
            }

            var actionNodes = container.querySelectorAll('button, a');
            for (var j = 0; j < actionNodes.length; j++) {
                var action = actionNodes[j];
                var actionText = (action.textContent || '').replace(/\s+/g, ' ').trim();
                if (actionText === 'เพิ่มวิชา' || actionText.indexOf('เพิ่มวิชา') !== -1) {
                    action.remove();
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            removeAddSubjectButton();
            improveDifficultyLayout();
        });
    } else {
        removeAddSubjectButton();
        improveDifficultyLayout();
    }

    var observer = new MutationObserver(function () {
        removeAddSubjectButton();
        improveDifficultyLayout();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
</script>
HTML;

    if (stripos($content, '</body>') !== false) {
        $content = preg_replace('/<\/body>/i', $removeAddSubjectScript . "\n</body>", $content, 1);
    } else {
        $content .= "\n" . $removeAddSubjectScript;
    }
}

echo $content;
