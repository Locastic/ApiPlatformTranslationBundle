<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\DependencyInjection;

use Locastic\ApiPlatformTranslationBundle\DependencyInjection\ApiPlatformTranslationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @covers \Locastic\ApiPlatformTranslationBundle\DependencyInjection\ApiPlatformTranslationExtension
 *
 * @group legacy
 */
class ApiPlatformTranslationExtensionTest extends TestCase
{
    public function testDeprecatedExtensionStillRegistersServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new ApiPlatformTranslationExtension();

        $extension->load([], $container);

        $this->assertTrue($container->hasDefinition('locastic_api_platform_translation.translation.translator'));
        $this->assertTrue($container->hasDefinition('locastic_api_platform_translation.listener.assign_locale'));
        $this->assertTrue($container->hasDefinition('locastic_api_platform_translation.serializer.translatable_denormalizer'));
        $this->assertTrue($container->hasDefinition('locastic_api_platform_translation.filter.translation_groups'));
        $this->assertSame('%kernel.enabled_locales%', $container->getParameter('locastic_api_platform_translation.enabled_locales'));
        $this->assertSame('api_platform_translation', $extension->getAlias());
    }
}
