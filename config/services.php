<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Extension\TranslationsEagerLoadingExtension;
use Locastic\ApiPlatformTranslationBundle\EventListener\AssignLocaleListener;
use Locastic\ApiPlatformTranslationBundle\Serializer\TranslatableItemDenormalizer;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('locastic_api_platform_translation.translation.translator', Translator::class)
        ->args([
            service('translator'),
            service('request_stack'),
            param('kernel.default_locale'),
            param('locastic_api_platform_translation.enabled_locales'),
            param('locastic_api_platform_translation.locale_resolution'),
        ]);

    $services->alias(Translator::class, 'locastic_api_platform_translation.translation.translator');

    $services->set('locastic_api_platform_translation.listener.assign_locale', AssignLocaleListener::class)
        ->args([
            service('locastic_api_platform_translation.translation.translator'),
            param('locastic_api_platform_translation.fallback_locale'),
            param('locastic_api_platform_translation.auto_create_translations'),
        ])
        ->tag('doctrine.event_listener', ['event' => 'postLoad'])
        ->tag('doctrine.event_listener', ['event' => 'prePersist']);

    // Serializer: in-place, merge denormalization of nested translations
    $services->set('locastic_api_platform_translation.serializer.translatable_denormalizer', TranslatableItemDenormalizer::class)
        ->tag('serializer.normalizer', ['priority' => 100]);

    $services->alias(TranslatableItemDenormalizer::class, 'locastic_api_platform_translation.serializer.translatable_denormalizer');

    // Doctrine ORM: eager loading of translations (the interface lives in
    // api-platform/doctrine-orm, which api-platform/symfony does not require)
    if (interface_exists(QueryCollectionExtensionInterface::class)) {
        $services->set('locastic_api_platform_translation.doctrine.orm.query_extension.translations_eager_loading', TranslationsEagerLoadingExtension::class)
            ->args([
                param('locastic_api_platform_translation.eager_load_translations'),
            ])
            ->tag('api_platform.doctrine.orm.query_extension.collection', ['priority' => -18])
            ->tag('api_platform.doctrine.orm.query_extension.item', ['priority' => -8]);

        $services->alias(TranslationsEagerLoadingExtension::class, 'locastic_api_platform_translation.doctrine.orm.query_extension.translations_eager_loading');
    }

    // Filters
    $services->set('locastic_api_platform_translation.filter.translation_groups')
        ->parent('api_platform.serializer.group_filter')
        ->args([
            'groups',
            false,
            ['translations'],
        ])
        ->tag('api_platform.filter', ['id' => 'translation.groups']);
};
