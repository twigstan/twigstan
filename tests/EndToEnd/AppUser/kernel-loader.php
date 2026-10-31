<?php

declare(strict_types=1);

use TwigStan\EndToEnd\AppUser\AppUserKernel;

$kernel = new AppUserKernel('test', true);
$kernel->boot();

return $kernel;
