<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle;

use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class ApiPlatformTranslationBundle extends AbstractBundle
{
    protected string $extensionAlias = 'api_platform_translation';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('enabled_locales')
                    ->info('Locales accepted from the ?locale= query parameter and Accept-Language negotiation. Empty inherits framework.enabled_locales; when both are empty, any locale is accepted.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('fallback_locale')
                    ->info('Locale stamped as the fallback on loaded and persisted translatables. Defaults to kernel.default_locale.')
                    ->defaultNull()
                ->end()
                ->arrayNode('locale_resolution')
                    ->info('Ordered list of sources the request locale is resolved from; the first source producing a locale wins. Remove a source to disable it.')
                    ->performNoDeepMerging()
                    ->enumPrototype()
                        ->values([Translator::RESOLUTION_QUERY_PARAM, Translator::RESOLUTION_ACCEPT_LANGUAGE])
                    ->end()
                    ->defaultValue([Translator::RESOLUTION_QUERY_PARAM, Translator::RESOLUTION_ACCEPT_LANGUAGE])
                    ->validate()
                        ->ifTrue(static fn (array $sources): bool => \count($sources) !== \count(array_unique($sources)))
                        ->thenInvalid('Locale resolution sources must not repeat: %s')
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{enabled_locales: list<string>, fallback_locale: ?string, locale_resolution: list<string>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('locastic_api_platform_translation.enabled_locales', $config['enabled_locales'] ?: '%kernel.enabled_locales%')
            ->set('locastic_api_platform_translation.fallback_locale', $config['fallback_locale'] ?? '%kernel.default_locale%')
            ->set('locastic_api_platform_translation.locale_resolution', $config['locale_resolution']);

        $container->import('../config/services.php');
    }
}
