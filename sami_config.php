<?php

use Sami\Sami;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(sys_get_temp_dir() . '/spark-framework/lib')
;

$config = new Sami($iterator, [
    'title' => 'Spark Framework API',
    'theme' => 'spark',
    'build_dir' => __DIR__ . '/_site/api',
    'cache_dir' => __DIR__ . '/_build/api_cache',
    'default_opened_level' => 2
]);

$config['template_dirs'] = [__DIR__ . '/sami_themes'];

$config['twig'] = $config->share($config->extend('twig', function($twig) {
    $md = new \dflydev\markdown\MarkdownParser;
    $twig->addExtension(new \Aptoma\Twig\Extension\MarkdownExtension($md));

    return $twig;
}));

return $config;

