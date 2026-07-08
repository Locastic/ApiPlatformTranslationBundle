<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Translation;

use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\LocaleAwareTranslatorInterface;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \Locastic\ApiPlatformTranslationBundle\Translation\Translator
 */
class TranslatorTest extends TestCase
{
    private (TranslatorInterface&LocaleAwareInterface)|\PHPUnit\Framework\MockObject\MockObject $translator;
    private \PHPUnit\Framework\MockObject\MockObject|RequestStack $requestStack;
    private string $defaultLocale;
    /** @var list<string> */
    private array $enabledLocales;

    protected function setUp(): void
    {
        $this->defaultLocale = 'en';
        $this->enabledLocales = ['en', 'hr', 'fr', 'it'];
        $this->translator = $this->createMock(LocaleAwareTranslatorInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
    }

    /**
     * @test translate
     *
     * @dataProvider provideTranslations
     *
     * @param array<string, mixed> $parameters
     */
    public function testTranslate(
        string $stringToTranslate,
        array $parameters,
        string $domain,
        string $locale,
        string $translation,
    ): void {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale, $this->enabledLocales);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with($stringToTranslate, $parameters, $domain, $locale)
            ->willReturn($translation);

        $actualTranslatedString = $translator->trans($stringToTranslate, [], $domain, $locale);
        $this->assertSame($translation, $actualTranslatedString);
    }

    /**
     * @test translate
     *
     * @dataProvider provideTranslations
     *
     * @param array<string, mixed> $parameters
     */
    public function testTranslateWithNoLocale(
        string $stringToTranslate,
        array $parameters,
        string $domain,
        string $translation,
    ): void {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale, $this->enabledLocales);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with($stringToTranslate, $parameters, $domain, $this->defaultLocale)
            ->willReturn($translation);

        $actualTranslatedString = $translator->trans($stringToTranslate, [], $domain);
        $this->assertSame($translation, $actualTranslatedString);
    }

    /**
     * @test loadCurrentLocale
     *
     * @dataProvider provideLocales
     */
    public function testLoadCurrentLocale(
        ?string $requestedLocale,
        string $expectedLocale,
    ): void {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale, $this->enabledLocales);

        $request = new Request(['locale' => $requestedLocale]);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $actualLocale = $translator->loadCurrentLocale();
        $this->assertSame($expectedLocale, $actualLocale);
    }

    /**
     * @test loadCurrentLocale
     *
     * @dataProvider provideLocalesWithAcceptLanguage
     */
    public function testLoadAcceptedLanguagesHeader(
        ?string $requestedLocale,
        ?string $acceptedLanguage,
        string $expectedLocale,
    ): void {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale, $this->enabledLocales);

        $request = new Request(['locale' => $requestedLocale], [], [], [], [], ['HTTP_ACCEPT_LANGUAGE' => $acceptedLanguage]);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $actualLocale = $translator->loadCurrentLocale();
        $this->assertSame($expectedLocale, $actualLocale);
    }

    /**
     * @test loadCurrentLocale
     */
    public function testFallbackIsDefaultLocaleEvenWhenNotFirstEnabledLocale(): void
    {
        $translator = new Translator($this->translator, $this->requestStack, 'en', ['hr', 'en', 'fr']);

        $request = new Request([], [], [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'es']);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->assertSame('en', $translator->loadCurrentLocale());
    }

    /**
     * @test loadCurrentLocale
     */
    public function testAnyLocaleAcceptedWhenEnabledLocalesNotConfigured(): void
    {
        $translator = new Translator($this->translator, $this->requestStack, 'en');

        $request = new Request(['locale' => 'nl']);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->assertSame('nl', $translator->loadCurrentLocale());
    }

    /**
     * @test loadCurrentLocale
     *
     * @dataProvider provideResolutionOrders
     *
     * @param list<string> $localeResolution
     */
    public function testConfigurableResolutionOrder(
        array $localeResolution,
        ?string $requestedLocale,
        ?string $acceptedLanguage,
        string $expectedLocale,
    ): void {
        $translator = new Translator(
            $this->translator,
            $this->requestStack,
            $this->defaultLocale,
            $this->enabledLocales,
            $localeResolution,
        );

        $server = null === $acceptedLanguage ? [] : ['HTTP_ACCEPT_LANGUAGE' => $acceptedLanguage];
        $request = new Request(['locale' => $requestedLocale], [], [], [], [], $server);

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->assertSame($expectedLocale, $translator->loadCurrentLocale());
    }

    /**
     * @test loadCurrentLocale
     */
    public function testUnknownResolutionSourceThrows(): void
    {
        $translator = new Translator(
            $this->translator,
            $this->requestStack,
            $this->defaultLocale,
            $this->enabledLocales,
            ['cookie'],
        );

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown locale resolution source "cookie".');

        $translator->loadCurrentLocale();
    }

    /**
     * @test loadCurrentLocale
     */
    public function testLoadCurrentLocaleWithNoRequest(): void
    {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale, $this->enabledLocales);

        $request = null;

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $actualLocale = $translator->loadCurrentLocale();
        $this->assertSame('en', $actualLocale);
    }

    public function provideTranslations(): \Generator
    {
        yield ['hello_world', [], 'messages', 'en', 'Hello world!'];
        yield ['hello_world', [], 'messages', 'hr', 'Dobar dan svijete!'];
        yield [
            'dateMessage',
            [],
            'validators',
            'hr',
            'Ova vrijednost nije valjani datum',
        ];
        yield ['dateMessage', [], 'validators', 'en', 'This value is not a valid date.'];
    }

    public function provideTranslationsNoLocale(): \Generator
    {
        yield ['hello_world', [], 'messages', 'Hello world!'];
        yield ['dateMessage', [], 'validators', 'This value is not a valid date.'];
    }

    public function provideLocales(): \Generator
    {
        yield ['en', 'en'];
        yield ['hr', 'hr'];
        yield ['', 'en'];
        yield [null, 'en'];
        yield ['nl', 'en']; // Locale not enabled
    }

    public static function provideResolutionOrders(): \Generator
    {
        $header = Translator::RESOLUTION_ACCEPT_LANGUAGE;
        $query = Translator::RESOLUTION_QUERY_PARAM;

        yield 'header-first order prefers the header' => [[$header, $query], 'hr', 'fr', 'fr'];
        yield 'header-first order falls through to the query param without a header' => [[$header, $query], 'hr', null, 'hr'];
        yield 'header-first order pins the default on a non-enabled header locale' => [[$header, $query], 'hr', 'es', 'en'];
        yield 'query-param-only ignores the header' => [[$query], null, 'fr', 'en'];
        yield 'query-param-only still resolves the query param' => [[$query], 'hr', 'fr', 'hr'];
        yield 'header-only ignores the query param' => [[$header], 'hr', 'fr', 'fr'];
        yield 'empty resolution always yields the default locale' => [[], 'hr', 'fr', 'en'];
    }

    public function provideLocalesWithAcceptLanguage(): \Generator
    {
        yield ['en', 'fr', 'en'];
        yield ['hr', 'de', 'hr'];
        yield ['', 'fr', 'fr'];
        yield ['de', '', 'en']; // Query param locale not enabled
        yield [null, 'it', 'it'];
        yield ['nl', null, 'en']; // Query param locale not enabled
        yield ['', '', 'en'];
        yield [null, null, 'en'];
        yield [null, 'fr_FR', 'fr'];
        yield [null, 'es', 'en']; // Accept-Language locale not enabled
        yield [null, 'it;q=0.4, fr;q=0.9', 'fr']; // Quality values respected
        yield [null, 'fr;q=0.3, it;q=0.9', 'it'];
    }
}
