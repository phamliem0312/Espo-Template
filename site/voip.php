<?php
/**LICENSE**/

/* DEPRECATED */
/* Will be removed in future versions */
/* USE: php command.php voip CONNECTOR*/

$sapiName = php_sapi_name();

if (substr($sapiName, 0, 3) != 'cli') {
    die("Cron can be run only via CLI");
}

/*SET UNDEFINED $_SERVER VARIABLES*/
$list = array(
    'REQUEST_METHOD',
    'REMOTE_ADDR',
    'SERVER_NAME',
    'SERVER_PORT',
    'REQUEST_URI',
);
foreach ($list as $name) {
    if (!array_key_exists($name, $_SERVER)) {
        $_SERVER[$name] = '';
    }
} /*END: SET UNDEFINED VARIABLES*/

include "bootstrap.php";

$app = new \Espo\Core\Application();

if (method_exists($app, 'setupSystemUser')) {
    $app->setupSystemUser();
} else {
    $auth = new \Espo\Core\Utils\Auth($app->getContainer());
    $auth->useNoAuth();
}

$arg = $_SERVER['argv'];
if (!isset($arg[1])) {
    throw new \Espo\Core\Exceptions\Error('Voip connector cannot be empty. Please check your command.');
}

$connector = $arg[1];
$voipManager = $app->getContainer()->get('voipManager');
$connectorManager = $voipManager->getConnectorManager($connector);
$connectorManager->startEventListener();