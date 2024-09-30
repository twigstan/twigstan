<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

return TwigStanConfig::extend(__DIR__ . '/../twigstan.php')
    ->baselineFile(__DIR__ . '/baseline.php');
