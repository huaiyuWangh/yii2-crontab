<?php
$params = array_merge(
    // require(YII_FRAMEWORK . 'common/config/params.php'),
    require(YII_FRAMEWORK . 'common/config/params-local.php'),
    // require(__DIR__ . '/params.php')
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'console\controllers',
    'aliases' => [
        '@console' => 'console/',
    ],
    'params' => $params,
];
