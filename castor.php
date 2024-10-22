<?php

declare(strict_types=1);

use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use function Castor\capture;
use function Castor\exit_code;
use function Castor\fs;
use function Castor\io;
use Symfony\Component\Console\Input\InputOption;

#[AsTask(name: 'install', namespace: 'composer')]
function composer_install(): int
{
    return exit_code(['composer', 'install']);
}

#[AsTask(name: 'phpunit', aliases: ['tests', 'test'], ignoreValidationErrors: true)]
function phpunit(
    #[AsRawTokens]
    array $rawTokens = [],
): int {
    if ( ! fs()->exists('vendor')) {
        composer_install();
    }

    return exit_code(['vendor/bin/phpunit', ...$rawTokens]);
}

#[AsTask(name: 'phpstan', aliases: ['stan'], ignoreValidationErrors: true)]
function phpstan(
    #[AsRawTokens]
    array $rawTokens = [],
): int {
    if ( ! fs()->exists('vendor')) {
        composer_install();
    }

    return exit_code(['vendor/bin/phpstan', '--ansi', ...$rawTokens]);
}

#[AsTask(name: 'php-cs-fixer', aliases: ['code-style', 'cs'])]
function phpcsfixer(
    #[AsOption(mode: InputOption::VALUE_NEGATABLE)]
    bool $fix = true,
): int {
    if ( ! fs()->exists('vendor')) {
        composer_install();
    }

    return exit_code(sprintf('vendor/bin/php-cs-fixer %s --diff', $fix ? 'fix' : 'check'));
}

#[AsTask(name: 'editorconfig')]
function editorconfig(): int
{
    if ( ! fs()->exists('vendor')) {
        composer_install();
    }

    return exit_code('vendor/bin/ec');
}

#[AsTask(name: 'dependency-analyzer')]
function dependencies(): int
{
    if ( ! fs()->exists('vendor')) {
        composer_install();
    }

    return exit_code('vendor/bin/composer-dependency-analyser');
}

#[AsTask(name: 'composer-normalize')]
function composer_normalize(): int
{
    if ( ! fs()->exists('vendor')) {
        composer_install();
    }

    return exit_code('composer normalize --diff');
}

#[AsTask(name: 'verify-commits')]
function verify_commits(): int
{
    if (capture('git rev-parse --abbrev-ref HEAD') === 'main') {
        return 0;
    }

    $commits = explode(PHP_EOL, capture('git log --oneline --pretty=format:%s origin/main..'));

    $matchingCommits = [];
    foreach ($commits as $commit) {
        if (str_contains($commit, 'fixup!') || str_contains($commit, 'squash!')) {
            $matchingCommits[] = $commit;
        }
    }

    if ($matchingCommits !== []) {
        io()->error(sprintf("Found fixup! or squash! commits:\n\n%s", implode(PHP_EOL, $matchingCommits)));

        return 1;
    }

    return 0;
}

#[AsTask(name: 'qa')]
function qa(): int
{
    if ( ! fs()->exists('vendor')) {
        composer_install();
    }

    io()->section('Running verify-commits');
    $exitCode = verify_commits();
    if ($exitCode !== 0) {
        return $exitCode;
    }
    io()->success('No issues found');

    io()->section('Running composer-normalize');
    $exitCode = composer_normalize();
    if ($exitCode !== 0) {
        return $exitCode;
    }
    io()->success('No issues found');

    io()->section('Running editorconfig');
    $exitCode = editorconfig();
    if ($exitCode !== 0) {
        return $exitCode;
    }
    io()->success('No issues found');

    io()->section('Running PHP-CS-Fixer');
    $exitCode = phpcsfixer();
    if ($exitCode !== 0) {
        return $exitCode;
    }
    io()->writeln('');
    io()->success('No issues found');

    io()->section('Running PHPUnit');
    $exitCode = phpunit();
    if ($exitCode !== 0) {
        return $exitCode;
    }
    io()->writeln('');
    io()->success('No issues found');

    io()->section('Running PHPStan');
    $exitCode = phpstan();
    if ($exitCode !== 0) {
        return $exitCode;
    }
    io()->success('No issues found');

    io()->section('Running Composer Dependency Analyzer');
    $exitCode = dependencies();
    if ($exitCode !== 0) {
        return $exitCode;
    }
    io()->success('No issues found');
    io()->writeln('');

    return 0;
}
