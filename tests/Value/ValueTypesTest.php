<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Value;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Value\ContentId;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\MediaId;
use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\Slug;
use SightPress\Plugin\Core\Value\UserId;
use SightPress\Plugin\Core\Value\WidgetId;

final class ValueTypesTest extends TestCase
{
    public function testIdsAreUlids(): void
    {
        $id = SiteId::generate();

        self::assertSame(26, \strlen($id->toString()));
        self::assertTrue($id->equals(SiteId::fromString($id->toString())));
        self::assertSame($id->toString(), (string) $id);
    }

    public function testIdsOfDifferentTypesAreNeverEqual(): void
    {
        $site = SiteId::generate();
        $content = ContentId::fromString($site->toString());

        self::assertFalse($site->equals($content));
    }

    public function testInvalidIdIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MediaId::fromString('not-a-ulid');
    }

    public function testEveryIdTypeGenerates(): void
    {
        foreach ([ContentId::generate(), MediaId::generate(), WidgetId::generate(), UserId::generate()] as $id) {
            self::assertSame(26, \strlen($id->toString()));
        }
    }

    /** @return iterable<string, array{string, string, ?string, string}> */
    public static function locales(): iterable
    {
        yield 'british english' => ['en-GB', 'en', 'GB', 'ltr'];
        yield 'latin american spanish' => ['es-419', 'es', '419', 'ltr'];
        yield 'hebrew' => ['he-IL', 'he', 'IL', 'rtl'];
        yield 'arabic' => ['ar-SA', 'ar', 'SA', 'rtl'];
        yield 'no region' => ['pt', 'pt', null, 'ltr'];
        yield 'script' => ['zh-Hant-TW', 'zh', 'TW', 'ltr'];
    }

    #[DataProvider('locales')]
    public function testLocale(string $code, string $language, ?string $region, string $direction): void
    {
        $locale = Locale::fromString($code);

        self::assertSame($language, $locale->language());
        self::assertSame($region, $locale->region());
        self::assertSame($direction, $locale->direction());
        self::assertSame('rtl' === $direction, $locale->isRtl());
        self::assertSame($code, (string) $locale);
        self::assertTrue($locale->equals(Locale::fromString($code)));
    }

    #[DataProvider('invalidLocales')]
    public function testInvalidLocaleIsRefused(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Locale::fromString($code);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLocales(): iterable
    {
        yield 'underscore' => ['pt_BR'];
        yield 'lowercase region' => ['pt-br'];
        yield 'empty' => [''];
        yield 'long' => ['english'];
    }

    public function testThirteenLaunchLocales(): void
    {
        self::assertCount(13, Locale::LAUNCH);
        foreach (Locale::LAUNCH as $code) {
            self::assertTrue(Locale::fromString($code)->isLaunchLocale());
        }
        self::assertFalse(Locale::fromString('ja-JP')->isLaunchLocale());
    }

    public function testCatalogueNames(): void
    {
        self::assertSame(
            ['en', 'en_US', 'pt', 'pt_BR', 'es', 'es_419', 'fr', 'fr_CA', 'it', 'de', 'de_CH', 'he', 'ar'],
            Locale::launchCatalogues(),
        );
        self::assertSame('pt_BR', Locale::fromString('pt-BR')->toSymfony());
        self::assertSame('pt', Locale::fromString('pt')->catalogue());
    }

    public function testSlug(): void
    {
        self::assertSame('about-us', Slug::fromString('about-us')->toString());
        self::assertSame('über-uns', Slug::fromString('Über-uns')->toString());
        self::assertSame('关于我们', (string) Slug::fromString('关于我们'));
        self::assertTrue(Slug::fromString('a')->equals(Slug::fromString('A')));
    }

    #[DataProvider('invalidSlugs')]
    public function testInvalidSlugIsRefused(string $slug): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Slug::fromString($slug);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['about us'];
        yield 'double hyphen' => ['about--us'];
        yield 'leading hyphen' => ['-about'];
        yield 'slash' => ['a/b'];
        yield 'too long' => [str_repeat('a', 191)];
    }
}
