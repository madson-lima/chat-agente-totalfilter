<?php

declare(strict_types=1);

function totalfilterAppBasePath(): string
{
    $configuredPathFile = __DIR__ . '/app-path.php';
    if (is_file($configuredPathFile)) {
        $configured = require $configuredPathFile;
        if (is_string($configured) && $configured !== '') {
            $configured = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), DIRECTORY_SEPARATOR);
            if (is_file($configured . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'bootstrap.php')) {
                return $configured;
            }
        }
    }

    $default = dirname(__DIR__);
    if (is_file($default . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'bootstrap.php')) {
        return $default;
    }

    throw new RuntimeException(
        'Nao foi possivel localizar a base privada da aplicacao. ' .
        'Crie public/app-path.php retornando o caminho absoluto da pasta privada do projeto.'
    );
}
