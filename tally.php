<?php

declare(strict_types=1);

use Tally\Configuration;

return Configuration::create()
    ->withPaths([__DIR__ . '/benchmarks'])
    ->withIniEntries(['memory_limit=-1']);
