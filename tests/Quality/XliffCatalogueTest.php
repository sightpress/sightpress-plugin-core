<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Quality;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Quality\XliffCatalogue;

final class XliffCatalogueTest extends TestCase
{
    private function load(string $body): XliffCatalogue
    {
        $file = tempnam(sys_get_temp_dir(), 'sp-xlf');
        self::assertIsString($file);
        file_put_contents($file, '<?xml version="1.0" encoding="utf-8"?><xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2"><file source-language="en-GB" target-language="de-DE" datatype="plaintext" original="demo"><body>'.$body.'</body></file></xliff>');
        try {
            return XliffCatalogue::load($file);
        } finally {
            unlink($file);
        }
    }

    public function testFilesThatAreNotXliffAreRefused(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sp-xlf');
        self::assertIsString($file);
        file_put_contents($file, '<not-xliff');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('is not a readable XLIFF 1.2 catalogue');
            XliffCatalogue::load($file);
        } finally {
            unlink($file);
        }
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        XliffCatalogue::load(sys_get_temp_dir().'/sp-none-'.bin2hex(random_bytes(4)).'.xlf');
    }

    public function testUnitsWithoutTargetOrSourceGetATarget(): void
    {
        $catalogue = $this->load('<trans-unit id="a"><source>A</source></trans-unit><trans-unit id="b"></trans-unit>');

        self::assertNull($catalogue->target('a'));
        self::assertSame('', $catalogue->state('a'));
        self::assertSame('', $catalogue->note('a'));
        $catalogue->set('a', 'A', 'Ä', 'translated');
        $catalogue->set('b', 'B', 'Ö', 'final');

        self::assertStringContainsString('<trans-unit id="a"><source>A</source><target state="translated">Ä</target></trans-unit>', $catalogue->toXml());
        self::assertStringContainsString('<trans-unit id="b"><target state="final">Ö</target></trans-unit>', $catalogue->toXml());
    }

    public function testUnitsAreAppendedToABodyWithoutIndentation(): void
    {
        $catalogue = $this->load('');

        $catalogue->set('new', 'New & "quoted"', '<Neu>', 'new');

        self::assertTrue($catalogue->has('new'));
        self::assertSame(['new'], $catalogue->keys());
        self::assertSame('<Neu>', $catalogue->target('new'));
        self::assertStringContainsString('<body><trans-unit id="new" resname="new">', $catalogue->toXml());
        self::assertStringContainsString('<source>New &amp; "quoted"</source>', $catalogue->toXml());
        self::assertStringContainsString('&lt;Neu&gt;', $catalogue->toXml());
    }

    public function testANewCatalogueMatchesTheCoreLayout(): void
    {
        $catalogue = XliffCatalogue::create('a"b', 'pt-BR');
        $catalogue->set('k', 'Key', 'Chave', 'needs-review-translation');

        self::assertSame(
            "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<xliff xmlns=\"urn:oasis:names:tc:xliff:document:1.2\" version=\"1.2\">\n  <file source-language=\"en-GB\" target-language=\"pt-BR\" datatype=\"plaintext\" original=\"a&quot;b\">\n    <body>\n      <trans-unit id=\"k\" resname=\"k\">\n        <source>Key</source>\n        <target state=\"needs-review-translation\">Chave</target>\n      </trans-unit>\n    </body>\n  </file>\n</xliff>\n",
            $catalogue->toXml(),
        );
    }
}
