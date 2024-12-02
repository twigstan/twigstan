<?php

declare(strict_types=1);

namespace Tools\Phar;

use Castor\Attribute\AsTask;
use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Build phar for all systems')]
function build(): void
{
    run(['git', 'restore', 'composer.lock', 'composer.json'], context: context()->withWorkingDirectory(__DIR__ . '/../../'));
    run(['composer', 'remove', 'phpstan/phpstan', 'twig/twig', '--no-install'], allowFailure: true, context: context()->withWorkingDirectory(__DIR__ . '/../../'));
    run(['composer', 'install', '--no-dev', '--prefer-dist'], context: context()->withWorkingDirectory(__DIR__ . '/../../'));
    run(['git', 'restore', 'composer.lock', 'composer.json'], context: context()->withWorkingDirectory(__DIR__ . '/../../'));

    // TODO: box-project/box#1423
    run('php -d error_reporting=24575 vendor/bin/box compile -c box.json');
}

#[AsTask(description: 'install dependencies')]
function install(): void
{
    run(['composer', 'install']);
}

#[AsTask(description: 'update dependencies')]
function update(): void
{
    run(['composer', 'update']);
    run(['composer', 'bump']);
}
