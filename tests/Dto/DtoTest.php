<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Tests\Dto;

use PHPUnit\Framework\TestCase;
use SightPress\Plugin\Core\Dto\ContentRef;
use SightPress\Plugin\Core\Dto\Finding;
use SightPress\Plugin\Core\Dto\LintSubject;
use SightPress\Plugin\Core\Dto\MediaCredit;
use SightPress\Plugin\Core\Dto\MediaRef;
use SightPress\Plugin\Core\Dto\NotificationRequest;
use SightPress\Plugin\Core\Dto\RateLimitResult;
use SightPress\Plugin\Core\Dto\Recipients;
use SightPress\Plugin\Core\Dto\Severity;
use SightPress\Plugin\Core\Dto\TransactionalMail;
use SightPress\Plugin\Core\Dto\WidgetRef;
use SightPress\Plugin\Core\Value\ContentId;
use SightPress\Plugin\Core\Value\Locale;
use SightPress\Plugin\Core\Value\MediaId;
use SightPress\Plugin\Core\Value\SiteId;
use SightPress\Plugin\Core\Value\UserId;
use SightPress\Plugin\Core\Value\WidgetId;

final class DtoTest extends TestCase
{
    public function testOnlyErrorsBlockPublishing(): void
    {
        self::assertTrue(Severity::Error->blocksPublishing());
        self::assertFalse(Severity::Warning->blocksPublishing());
        self::assertFalse(Severity::Info->blocksPublishing());
    }

    public function testFinding(): void
    {
        $finding = new Finding('x.rule', Severity::Warning, 'Add a heading.', 'line 3');

        self::assertSame('x.rule', $finding->ruleId);
        self::assertSame('line 3', $finding->location);
    }

    public function testFindingNeedsARuleId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Finding('', Severity::Error, 'm');
    }

    public function testMediaCredit(): void
    {
        $credit = new MediaCredit('Ana', 'Example', 'CC-BY-4.0');

        self::assertTrue($credit->creditRequired);
        self::assertFalse($credit->editorialOnly);
    }

    public function testMediaCreditNeedsCreatorAndLicence(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaCredit(' ', 'Example', 'CC-BY-4.0');
    }

    public function testRecipients(): void
    {
        $user = UserId::generate();

        self::assertTrue(Recipients::byCapability('form-builder.read')->isByCapability());
        self::assertTrue(Recipients::concernedUser($user)->isConcernedUser());
        self::assertSame([$user->toString()], Recipients::users([$user])->values);
        self::assertFalse(Recipients::users([$user])->isByCapability());
    }

    public function testNotificationRequest(): void
    {
        $request = new NotificationRequest('form-builder.submitted', SiteId::generate(), ['form' => 'x'], Recipients::byCapability('form-builder.read'));

        self::assertSame('form-builder.submitted', $request->type);
    }

    public function testNotificationRequestNeedsAType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new NotificationRequest('', SiteId::generate(), [], Recipients::byCapability('c'));
    }

    public function testReadModelsHoldTheirData(): void
    {
        $site = SiteId::generate();
        $locale = Locale::fromString('pt-BR');
        $content = new ContentRef(ContentId::generate(), $site, 'page', $locale, 'Sobre', '/pt-br/sobre', true);
        $media = new MediaRef(MediaId::generate(), $site, 'image/png', $locale, 'Um porto', null);
        $widget = new WidgetRef(WidgetId::generate(), $site, 'text', $locale, 'Rodapé');
        $subject = new LintSubject($site, $content->id, 'page', $locale, 'Sobre', '# Sobre', '<h1>Sobre</h1>');
        $mail = new TransactionalMail('a@example.org', $locale, 'mail/x', 'x.subject', ['a' => 1]);
        $limit = new RateLimitResult(true, 4);

        self::assertTrue($content->published);
        self::assertSame('image/png', $media->mimeType);
        self::assertSame('text', $widget->type);
        self::assertSame('page', $subject->type);
        self::assertSame(['a' => 1], $mail->context);
        self::assertNull($limit->retryAfter);
    }
}
