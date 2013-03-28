<?php

namespace SparkGuides;

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Guide
{
    public $file;
    public $contents;
    public $title;
    public $name;
    public $excerpt;

    protected $markdown;

    function __construct($file)
    {
        $this->file = $file;
        $this->contents = file_get_contents($this->file);

        $this->name = basename($file, '.md');

        if (preg_match('/^#(.+)$/m', $this->contents, $matches)) {
            $this->title = trim($matches[1]);
        }

        if (preg_match('/---\n(.+)\n---/m', $this->contents, $matches)) {
            $this->contents = str_replace($matches[0], '', $this->contents);

            foreach (Yaml::parse($matches[1]) as $key => $value) {
                $this->$key = $value;
            }
        }

        $this->markdown = new \Sundown($this->contents, [
            'with_toc_data' => true
        ]);
    }

    public function getToc()
    {
        return $this->markdown->toTOC();
    }

    public function getHtml()
    {
        return $this->markdown->toHTML();
    }
}

function find_guides($directory)
{
    $finder = Finder::create()->name('*.md')->in($directory);
    $guides = [];

    foreach ($finder as $file) {
        $guide = new Guide($file->getRealpath());
        $guides[] = $guide;
    }

    return $guides;
}

$app = new Application;

$app['debug'] = true;
$app['guides.path'] = __DIR__ . '/guides';

$app->register(new \Silex\Provider\UrlGeneratorServiceProvider);

$app->register(new TwigServiceProvider, [
    'twig.path' => __DIR__ . '/views',
    'twig.options' => [
        'cache' => __DIR__ . '/_build/twig_cache'
    ]
]);

$app->register(new \Pipe\Silex\PipeServiceProvider, ['pipe.root' => __DIR__ . '/assets']);

$app['pipe.load_path'] = $app->extend('pipe.load_path', function($loadPath) {
    $loadPath[] = __DIR__ . "/assets/components";
    return $loadPath;
});

$app['markdown'] = $app->share(function() use ($app) {
    return new \Sundown\Markdown(new \Sundown\Render\HTML);
});

$app['pipe.precompile'] = ['screen.less'];
$app['pipe.precompile_directory'] = __DIR__ . '/_site/assets';

$app->get('/', function() use ($app) {
    return $app['twig']->render('index.html');
})->bind('homepage');

$app->get('/guides', function() use ($app) {
    return $app['twig']->render('guides/index.html', [
        'index' => find_guides($app['guides.path'])
    ]);
})->bind('guides');

$app->get('/guides/{guide}', function($guide) use ($app) {
    $file = "{$app['guides.path']}/$guide.md";
    $guide = new Guide($file);

    return $app['twig']->render('guides/guide.html', [
        'guide' => $guide
    ]);
})->bind('guide_show');

# Fallback route to serve static pages
$app->get('/{page}', function($page) use ($app) {
    return $app['twig']->render($page);
})->assert('page', '.+')->bind('page_show');

return $app;

