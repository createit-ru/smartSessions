<?php
/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */

$success = true;
$src = MODX_CORE_PATH . "components/smartsessions/model/_include_handler.php";
$dst = MODX_CORE_PATH . "model/modx/smartsessionhandler.class.php";

if ($transport->xpdo) {
    $modx =& $transport->xpdo;
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            if (file_exists($src)) {
                if (!copy($src, $dst)) {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, '[smartSessions] Could not copy file ' . $src . ' to ' . $dst);
                    $success = false;
                }
            } else {
                $modx->log(xPDO::LOG_LEVEL_ERROR, '[smartSessions] Could not find file ' . $src);
                $success = false;
            }
            break;
        case xPDOTransport::ACTION_UNINSTALL:
            // Вернем значение "по-умолчанию" для настройки session_handler_class
            $sessionHandlerClass = $modx->getOption('session_handler_class');
            if ($sessionHandlerClass == 'smartSessionHandler') {
                $setting = $modx->getObject('modSystemSetting', 'session_handler_class');
                if ($setting) {
                    $modx->setOption('session_handler_class', 'modSessionHandler');
                    $setting->set('value', 'modSessionHandler');
                    $setting->save();

                    // Очистим кеш
                    $cacheRefreshOptions = [
                        'system_settings' => []
                    ];
                    $modx->cacheManager->refresh($cacheRefreshOptions);
                }
            }
            // 2. Удалим файл $dst
            if (file_exists($dst)) {
                unlink($dst);
            }
            break;
    }
}
return $success;