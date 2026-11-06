<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The bin scripts run from vendor/bin in another project. When this package
 * is a symlinked path repository, __DIR__ points into the sibling checkout,
 * so they must load the autoloader that Composer's bin proxy names.
 */
final class BinScriptsTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function scripts(): iterable
    {
        yield 'licences' => ['sightpress-check-licenses'];
        yield 'translations' => ['sightpress-check-translations'];
        yield 'twig' => ['sightpress-lint-twig'];
        yield 'translations csv' => ['sightpress-translations-csv'];
    }

    #[DataProvider('scripts')]
    public function testTheProjectAutoloaderNamedByComposerIsUsedFirst(string $script): void
    {
        $dir = sys_get_temp_dir().'/sightpress-bin-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $autoload = $dir.'/autoload.php';
        file_put_contents($autoload, "<?php\nfwrite(STDERR, \"project autoloader\\n\");\nrequire ".var_export(\dirname(__DIR__, 2).'/vendor/autoload.php', true).";\n");

        try {
            // What Composer's vendor/bin proxy does before it includes the script.
            $proxy = \sprintf('$GLOBALS["_composer_autoload_path"] = %s; $argv = [%s]; include %s;', var_export($autoload, true), var_export($script, true), var_export(\dirname(__DIR__, 2).'/bin/'.$script, true));
            $process = proc_open([\PHP_BINARY, '-r', $proxy], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
            self::assertIsResource($process);
            $output = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            proc_close($process);

            self::assertStringStartsWith('project autoloader', $errors);
            self::assertStringNotContainsString('not found', $output.$errors);
        } finally {
            @unlink($autoload);
            @rmdir($dir);
        }
    }
}
