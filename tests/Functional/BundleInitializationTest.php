<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Functional;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Serializer\Filter\GroupFilter;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Extension\TranslationsEagerLoadingExtension;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader\TranslationsLoader;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\State\TranslationsCollectionProvider;
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
        $this->assertInstanceOf(
            TranslationsEagerLoadingExtension::class,
            $container->get('locastic_api_platform_translation.doctrine.orm.query_extension.translations_eager_loading')
        );
    }

    public function testRegistersTheTranslationsLoader(): void
    {
        self::bootKernel();

        $this->assertInstanceOf(
            TranslationsLoader::class,
            static::getContainer()->get('locastic_api_platform_translation.doctrine.orm.translations_loader')
        );
        // without the Doctrine ORM provider of API Platform there is nothing to decorate
        $this->assertFalse(static::getContainer()->has('locastic_api_platform_translation.doctrine.orm.state.translations_collection_provider'));
    }

    public function testDecoratesTheDoctrineOrmCollectionProvider(): void
    {
        $kernel = new TestKernel('test', true, [], [], true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');

            $this->assertInstanceOf(
                TranslationsCollectionProvider::class,
                $container->get('api_platform.doctrine.orm.state.collection_provider')
            );
            $this->assertInstanceOf(
                ItemProvider::class,
                $container->get('api_platform.doctrine.orm.state.item_provider')
            );
        } finally {
            $kernel->shutdown();
        }
    }
}
