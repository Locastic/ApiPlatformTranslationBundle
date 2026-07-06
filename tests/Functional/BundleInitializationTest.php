<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Functional;

use ApiPlatform\Serializer\Filter\GroupFilter;
use Locastic\ApiPlatformTranslationBundle\EventListener\AssignLocaleListener;
use Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\TestKernel;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Boots a real kernel with FrameworkBundle, DoctrineBundle and ApiPlatformBundle
 * to prove the container compiles and the bundle services are wired, in
 * particular the translation.groups filter, which inherits from an
 * api_platform service definition the unit tests never see.
 */
class BundleInitializationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testContainerCompilesAndRegistersServices(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->assertInstanceOf(
            Translator::class,
            $container->get('locastic_api_platform_translation.translation.translator')
        );
        $this->assertInstanceOf(
            AssignLocaleListener::class,
            $container->get('locastic_api_platform_translation.listener.assign_locale')
        );
        $this->assertInstanceOf(
            GroupFilter::class,
            $container->get('locastic_api_platform_translation.filter.translation_groups')
        );
    }
}
