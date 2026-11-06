<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use SightPress\Plugin\Core\Quality\CsFixerRules;

require __DIR__.'/vendor/autoload.php';

$finder = Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->append([__DIR__.'/bin/sightpress-check-licenses', __DIR__.'/bin/sightpress-check-translations', __DIR__.'/bin/sightpress-lint-twig', __DIR__.'/bin/sightpress-translations-csv']);

return CsFixerRules::config($finder);
