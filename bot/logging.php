<?php
namespace CluebotNG;

function create_logger() {
    $level = Config::$log_level;
    $path = Config::$log_path;

    $levels = \Monolog\Logger::getLevels();
    if (!array_key_exists($level, $levels)) {
        throw new \Exception("invalid log level: $level");
    }
    $level = $levels[$level];

    if (substr($path, 0, 6) === 'php://') {
        $handler = new \Monolog\Handler\StreamHandler($path, $level);
    } else {
        $handler = new \Monolog\Handler\RotatingFileHandler($path, 2, $level, true, 0600, false);
    }

    $logger = new \Monolog\Logger('cluebotng');

    $logger->pushHandler($handler);
    return $logger;
}

$logger = create_logger();
