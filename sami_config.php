<?php

use Sami\Sami;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(sys_get_temp_dir() . '/spark-framework/lib')
;

return new Sami($iterator, [
    'title' => 'Spark Framework API',
    'build_dir' => __DIR__ . '/_site/api',
    'cache_dir' => __DIR__ . '/_build/api_cache',
    'default_opened_level' => 2
]);

