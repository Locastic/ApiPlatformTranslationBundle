<?php

declare(strict_types=1);

namespace Locastic\ApiTranslationBundle\Tests\Service;

use Locastic\ApiPlatformTranslationBundle\Service\Translator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class TranslatorTest
 * @package UnitTests\TranslationBundle\Service
 * @covers \Locastic\ApiPlatformTranslationBundle\Service\Translator
 */
class TranslatorTest extends TestCase
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * setUp
     */
    protected function setUp()
    {
        $this->defaultLocale = 'en';
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
    ) {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with($stringToTranslate, $parameters, $domain, $locale)
            ->willReturn($translation);

        $actualTranslatedString = $translator->translate($stringToTranslate, [], $domain, $locale);
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
    ) {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with($stringToTranslate, $parameters, $domain, $this->defaultLocale)
            ->willReturn($translation);

        $actualTranslatedString = $translator->translate($stringToTranslate, [], $domain);
        $this->assertSame($translation, $actualTranslatedString);
    }

    /**
     * @test loadCurrentLocale
     * @dataProvider provideLocales
     */
    public function testLoadCurrentLocale(
        $requestedLocale,
        $expectedLocale
    ) {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale);

        $request = $this->createMock(Request::class);
        $request->query = $this->createMock(ParameterBag::class);
        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $request->query
            ->expects($this->once())
            ->method('get')
            ->with('locale')
            ->willReturn($requestedLocale);

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
    ) {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale);

        $request = $this->createConfiguredMock(Request::class, [
            'getPreferredLanguage' => $acceptedLanguage
        ]);
        $request->query = $this->createMock(ParameterBag::class);
        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $request->query
            ->expects($this->once())
            ->method('get')
            ->with('locale')
            ->willReturn($requestedLocale);

        $actualLocale = $translator->loadCurrentLocale();
        $this->assertSame($expectedLocale, $actualLocale);
    }

    /**
     * @test loadCurrentLocale
     */
    public function testLoadCurrentLocaleWithNoRequest()
    {
        $translator = new Translator($this->translator, $this->requestStack, $this->defaultLocale);

        $request = null;

        $this->requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $actualLocale = $translator->loadCurrentLocale();
        $this->assertSame('en', $actualLocale);
    }

    /**
     * @return \Generator
     */
    public function provideTranslations()
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

    /**
     * @return \Generator
     */
    public function provideTranslationsNoLocale()
    {
        yield['hello_world', [], 'messages', 'Hello world!'];
        yield['dateMessage', [], 'validators', 'This value is not a valid date.'];
    }

    /**
     * @return \Generator
     */
    public function provideLocales()
    {
        yield['en', 'en'];
        yield['hr', 'hr'];
        yield['', 'en'];
        yield[null, 'en'];
    }

    /**
     * @return \Generator
     */
    public function provideLocalesWithAcceptLanguage()
    {
        yield['en', 'fr', 'en'];
        yield['hr', 'de', 'hr'];
        yield['', 'fr', 'fr'];
        yield['de', '', 'de'];
        yield[null, 'it', 'it'];
        yield['nl', null, 'nl'];
        yield['', '', 'en'];
        yield[null, null, 'en'];
    }
}
