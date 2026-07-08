<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @deprecated since locastic/api-platform-translation-bundle 2.1, to be removed in 3.0;
 *             the bundle extends AbstractBundle and registers its extension itself
 */
class ApiPlatformTranslationExtension extends Extension
{
    public function __construct()
    {
        trigger_deprecation(
            'locastic/api-platform-translation-bundle',
            '2.1',
            'The "%s" class is deprecated and will be removed in 3.0, the bundle registers its own extension through AbstractBundle.',
            self::class,
        );
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setParameter('locastic_api_platform_translation.enabled_locales', '%kernel.enabled_locales%');
        $container->setParameter('locastic_api_platform_translation.fallback_locale', '%kernel.default_locale%');
        $container->setParameter('locastic_api_platform_translation.locale_resolution', ['query_param', 'accept_language']);

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'config'));
        $loader->load('services.php');
    }

    public function getAlias(): string
    {
        return 'api_platform_translation';
    }
}
