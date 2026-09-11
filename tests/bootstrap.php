<?php

$autoload = __DIR__.'/../vendor/autoload.php';
if (! is_file($autoload)) {
    $autoload = __DIR__.'/../../tackle/vendor/autoload.php';
}
$loader = require $autoload;
$loader->addPsr4('Grokbot\\', __DIR__.'/../src');
