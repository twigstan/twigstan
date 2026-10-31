<?php

declare(strict_types=1);

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\UX\TwigComponent\Twig\ComponentExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

$loader = new FilesystemLoader(rootPath: __DIR__);
$loader->addPath(__DIR__);
$loader->addPath(__DIR__ . '/EndToEnd', 'EndToEnd');

$environment = new Environment($loader);
$environment->addGlobal('app', new AppVariable());
$environment->addExtension(new FormExtension());
// Registered by the Symfony UX Twig component bundle in applications.
$environment->addExtension(new ComponentExtension()); // @phpstan-ignore new.internalClass
// Twig compiles closures to $this->env->getFunction('name')->getCallable(), see LevelMax/closure.twig
$environment->addFunction(new TwigFunction('closure_function', fn(string $value, int $times = 1): string => str_repeat($value, $times)));
$environment->addFilter(new TwigFilter('closure_filter', fn(string $value): int => strlen($value)));
$environment->addTest(new TwigTest('closure_test', fn(int $value): bool => $value > 0));

return $environment;
