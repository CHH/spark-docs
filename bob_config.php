<?php

namespace Bob\BuildConfig;

use FrozenSilex\Freezer;

directoryTask('_site');
directoryTask('_build');

define('SOURCE_DIR', '_build/spark-framework');
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
    println('----> Started development server on localhost:3000');
    sh('php -S localhost:3000 -t ./ router.php');
});

task('checkout_source', function() {
    if (is_dir(SOURCE_DIR . '/.git')) {
        info('----> Updating source...');

        cd(SOURCE_DIR, function() {
            sh('git pull --ff origin master');
        });
    } else {
        info('----> Checking out source...');
        if (is_dir(SOURCE_DIR)) sh(['rm', '-rf', SOURCE_DIR]);

        sh("git clone git@github.com:sparkframework/spark " . SOURCE_DIR);
    }

    info('----> Downloading Composer...');

    if (!is_dir(SOURCE_DIR . '/composer.phar')) {
        $in = fopen('http://getcomposer.org/composer.phar', 'rb');
        $out = fopen(SOURCE_DIR . "/composer.phar", "w+");
        stream_copy_to_stream($in, $out);
    }

    info('----> Updating Dependencies...');

    cd(SOURCE_DIR, function() {
        php("composer.phar self-update");
        php("composer.phar update --dev");
    });
});

task('dist', ['checkout_source'], function() {
    info('----> Building spark.phar');

    cd(SOURCE_DIR, function() {
        if (!is_file('box.phar')) {
            sh('curl -s http://box-project.org/installer.php | php');
        }

        sh('php box.phar build --verbose', ['fail_on_error' => true]);
        copy('spark.phar', __DIR__ . '/_site/spark.phar');
    });

    info('----> Done');
});

desc('Builds Spark\'s Homepage on Github.com');
task('gh-pages', ['docs', 'dist', 'site'], function() {
    if (is_dir('_build/spark-gh-pages/.git')) {
        cd('_build/spark-gh-pages', function() {
            sh('git pull git@github.com:sparkframework/spark gh-pages --ff');
        });
    } else {
        sh(['git', 'clone', '--branch', 'gh-pages', 'git@github.com:sparkframework/spark', "_build/spark-gh-pages"], ['fail_on_error' => true]);
    }

    $site = realpath('_site');

    cd('_build/spark-gh-pages', function() use ($site) {
        sh("rsync -avr --delete --exclude=.git $site/ .", ['fail_on_error' => true]);

        sh("git add -A", ['fail_on_error' => true]);
        sh("git commit -m 'Update website'");

        sh('git push git@github.com:sparkframework/spark gh-pages', ['fail_on_error' => true]);
    });
});

desc('Builds the documentation');
task('docs', ['checkout_source', '_site'], function() {
    info('----> Building API docs using Sami');
    php([SOURCE_DIR . "/vendor/bin/sami.php", 'update', '--force', __DIR__ . '/sami_config.php']);
    info('----> Done');
});

