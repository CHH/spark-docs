<?php

namespace Bob\BuildConfig;

use FrozenSilex\Freezer;

directoryTask('_site');
directoryTask('_build');

define('SOURCE_DIR', sys_get_temp_dir() . '/spark-framework');
define('SITE_BASE_PATH', '/spark');

directoryTask('_site/assets');

desc('Freezes the Silex App in "app.php" to static HTML');
task('site', ['_site', '_site/assets'], function() {
    $app = require(__DIR__ . '/app.php');

    $app['pipe.css_compressor'] = 'yuglify_css';
    $app['pipe.use_precompiled'] = true;
    $app['pipe.manifest'] = '_site/assets/manifest.json';
    $app['pipe.prefix'] = SITE_BASE_PATH . '/assets';

    $app['pipe']->precompile();

    $app['freezer.destination'] = '_site';

    $app->before(function() use ($app) {
        $app['request_context']->setBaseUrl(SITE_BASE_PATH);
    });

    $freezer = new Freezer($app);
    $freezer->freeze();
});

task('site:server', function() {
    info('Started development server on localhost:3000');
    sh('php -S localhost:3000 -t ./ router.php');
});

task('checkout_source', function() {
    if (is_dir(SOURCE_DIR)) {
        cd(SOURCE_DIR, function() {
            sh('git pull --ff origin master');
        });
    } else {
        sh("git clone git@github.com:CHH/spark " . SOURCE_DIR);
    }

    if (!is_dir(SOURCE_DIR . '/composer.phar')) {
        $in = fopen('http://getcomposer.org/composer.phar', 'rb');
        $out = fopen(SOURCE_DIR . "/composer.phar", "w+");
        stream_copy_to_stream($in, $out);
    }

    cd(SOURCE_DIR, function() {
        php("composer.phar self-update");
        php("composer.phar update --dev");
    });
});

task('dist', ['checkout_source'], function() {
    cd(SOURCE_DIR, function() {
        sh('bob deps dist', ['fail_on_error' => true]);
        copy('spark.phar', __DIR__ . '/_site/spark.phar');
    });
});

desc('Builds Spark\'s Homepage on Github.com');
task('gh-pages', ['docs', 'dist', 'site'], function() {
    $temp = 'spark_ghpages_clone_' . uniqid();
    $site = realpath('_site');

    cd(sys_get_temp_dir(), function() use ($site, $temp) {
        sh(['git', 'clone', '--branch', 'gh-pages', 'git@github.com:CHH/spark', sys_get_temp_dir() . "/$temp"], ['fail_on_error' => true]);
        chdir($temp);

        sh("rsync -avr --delete --exclude=.git $site/ .", ['fail_on_error' => true]);

        sh("git add -A", ['fail_on_error' => true]);
        sh("git commit -m 'Update website'");

        sh('git push git@github.com:CHH/spark gh-pages', ['fail_on_error' => true]);
    });
});

desc('Builds the documentation');
task('docs', ['checkout_source', '_site'], function() {
    php([SOURCE_DIR . "/vendor/bin/sami.php", 'update', 'sami_config.php', '-v']);
});

