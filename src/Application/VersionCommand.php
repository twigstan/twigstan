<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Composer\InstalledVersions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'version')]
class VersionCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf(
            'TwigStan %s, Twig %s, PHPStan %s',
            InstalledVersions::getPrettyVersion('twigstan/twigstan'),
            InstalledVersions::getPrettyVersion('twig/twig'),
            InstalledVersions::getPrettyVersion('phpstan/phpstan'),
        ));

        return Command::SUCCESS;
    }
}
