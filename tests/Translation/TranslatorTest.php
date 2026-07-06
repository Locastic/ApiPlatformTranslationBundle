<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Translation;

use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @package UnitTests\TranslationBundle\Translation
 * @covers \Locastic\ApiPlatformTranslationBundle\Translation\Translator
 */
class TranslatorTest extends TestCase
{
    private TranslatorInterface|\PHPUnit\Framework\MockObject\MockObject $translator;
    private \PHPUnit\Framework\MockObject\MockObject|RequestStack $requestStack;
    private string $defaultLocale;
    private array $enabledLocales;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->defaultLocale = 'en';
        $this->enabledLocales = ['en', 'hr', 'fr', 'it'];
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
    }

    /**
     * @test translate
     * @dataProvider provideTranslations
     */
    public function testTranslate(
        $stringToTranslate,
        $parameters,
        $domain,
        $locale,
        $translation
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
     * @dataProvider provideTranslations
     */
    public function testTranslateWithNoLocale(
        $stringToTranslate,
        $parameters,
        $domain,
        $translation
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
     * @dataProvider provideLocales
     */
    public function testLoadCurrentLocale(
        $requestedLocale,
        $expectedLocale
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
     * @dataProvider provideLocalesWithAcceptLanguage
     */
    public function testLoadAcceptedLanguagesHeader(
        $requestedLocale,
        $acceptedLanguage,
        $expectedLocale
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
        yield['hello_world', [], 'messages', 'en', 'Hello world!'];
        yield['hello_world', [], 'messages', 'hr', 'Dobar dan svijete!'];
        yield[
            'dateMessage',
            [],
            'validators',
            'hr',
            'Ova vrijednost nije valjani datum',
        ];
        yield['dateMessage', [], 'validators', 'en', 'This value is not a valid date.'];
    }

    public function provideTranslationsNoLocale(): \Generator
    {
        yield['hello_world', [], 'messages', 'Hello world!'];
        yield['dateMessage', [], 'validators', 'This value is not a valid date.'];
    }

    public function provideLocales(): \Generator
    {
        yield['en', 'en'];
        yield['hr', 'hr'];
        yield['', 'en'];
        yield[null, 'en'];
        yield['nl', 'en']; // Locale not enabled
    }

    public function provideLocalesWithAcceptLanguage(): \Generator
    {
        yield['en', 'fr', 'en'];
        yield['hr', 'de', 'hr'];
        yield['', 'fr', 'fr'];
        yield['de', '', 'en']; // Query param locale not enabled
        yield[null, 'it', 'it'];
        yield['nl', null, 'en']; // Query param locale not enabled
        yield['', '', 'en'];
        yield[null, null, 'en'];
        yield[null, 'fr_FR', 'fr'];
        yield[null, 'es', 'en']; // Accept-Language locale not enabled
    }
}
