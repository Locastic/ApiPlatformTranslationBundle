<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Functional;

use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\TestKernel;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Boots a real kernel to prove the api_platform_translation configuration tree
 * is processed and wired into the container parameters and services.
 */
class ConfigurationTest extends KernelTestCase
{
    /** @var array<string, mixed> */
    private static array $translationConfig = [];

    /** @var array<string, mixed> */
    private static array $frameworkConfig = [];

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, self::$translationConfig, self::$frameworkConfig);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::$translationConfig = [];
        self::$frameworkConfig = [];
    }

    public function testDefaultsWithBareFramework(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->assertSame([], $container->getParameter('locastic_api_platform_translation.enabled_locales'));
        $this->assertSame('en', $container->getParameter('locastic_api_platform_translation.fallback_locale'));
        $this->assertSame(
            [Translator::RESOLUTION_QUERY_PARAM, Translator::RESOLUTION_ACCEPT_LANGUAGE],
            $container->getParameter('locastic_api_platform_translation.locale_resolution')
        );
    }

    public function testDefaultsInheritFrameworkLocales(): void
    {
        self::$frameworkConfig = [
            'default_locale' => 'fr',
            'enabled_locales' => ['en', 'fr'],
        ];
        self::bootKernel();
        $container = static::getContainer();

        $this->assertSame(['en', 'fr'], $container->getParameter('locastic_api_platform_translation.enabled_locales'));
        $this->assertSame('fr', $container->getParameter('locastic_api_platform_translation.fallback_locale'));
    }

    public function testBundleConfigurationOverridesFramework(): void
    {
        self::$frameworkConfig = [
            'default_locale' => 'fr',
            'enabled_locales' => ['en', 'fr'],
        ];
        self::$translationConfig = [
            'enabled_locales' => ['de', 'it'],
            'fallback_locale' => 'de',
            'locale_resolution' => [Translator::RESOLUTION_ACCEPT_LANGUAGE],
        ];
        self::bootKernel();
        $container = static::getContainer();

        $this->assertSame(['de', 'it'], $container->getParameter('locastic_api_platform_translation.enabled_locales'));
        $this->assertSame('de', $container->getParameter('locastic_api_platform_translation.fallback_locale'));
        $this->assertSame(
            [Translator::RESOLUTION_ACCEPT_LANGUAGE],
            $container->getParameter('locastic_api_platform_translation.locale_resolution')
        );
    }

    public function testTranslatorFollowsConfiguredResolutionOrder(): void
    {
        self::$translationConfig = [
            'enabled_locales' => ['en', 'de'],
            'locale_resolution' => [Translator::RESOLUTION_ACCEPT_LANGUAGE],
        ];
        self::bootKernel();
        $container = static::getContainer();

        $requestStack = $container->get(RequestStack::class);
        $requestStack->push(new Request(['locale' => 'en'], [], [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'de']));

        $translator = $container->get('locastic_api_platform_translation.translation.translator');

        // Accept-Language wins: the query parameter source is not configured.
        $this->assertSame('de', $translator->loadCurrentLocale());
    }

    public function testUnknownResolutionSourceIsRejected(): void
    {
        self::$translationConfig = [
            'locale_resolution' => ['cookie'],
        ];

        $this->expectException(InvalidConfigurationException::class);

        self::bootKernel();
    }

    public function testDuplicateResolutionSourcesAreRejected(): void
    {
        self::$translationConfig = [
            'locale_resolution' => [Translator::RESOLUTION_QUERY_PARAM, Translator::RESOLUTION_QUERY_PARAM],
        ];

        $this->expectException(InvalidConfigurationException::class);

        self::bootKernel();
    }
}
