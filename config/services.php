<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Extension\TranslationsEagerLoadingExtension;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationOrderFilter;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationSearchFilter;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader\TranslationsLoader;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader\TranslationsLoaderInterface;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\State\TranslationsCollectionProvider;
use Locastic\ApiPlatformTranslationBundle\EventListener\AssignLocaleListener;
use Locastic\ApiPlatformTranslationBundle\Serializer\TranslatableItemDenormalizer;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

    // Doctrine ORM: eager loading and locale-constrained filters (the base
    // classes live in api-platform/doctrine-orm, which api-platform/symfony
    // does not require)
    if (interface_exists(QueryCollectionExtensionInterface::class)) {
        $services->set('locastic_api_platform_translation.doctrine.orm.query_extension.translations_eager_loading', TranslationsEagerLoadingExtension::class)
            ->args([
                param('locastic_api_platform_translation.eager_load_translations'),
            ])
            ->tag('api_platform.doctrine.orm.query_extension.collection', ['priority' => -18])
            ->tag('api_platform.doctrine.orm.query_extension.item', ['priority' => -8]);

        $services->alias(TranslationsEagerLoadingExtension::class, 'locastic_api_platform_translation.doctrine.orm.query_extension.translations_eager_loading');

        $services->set('locastic_api_platform_translation.doctrine.orm.translations_loader', TranslationsLoader::class)
            ->args([
                service('doctrine'),
            ]);

        $services->alias(TranslationsLoaderInterface::class, 'locastic_api_platform_translation.doctrine.orm.translations_loader');

        // Collections load the translations of the provided page in a second
        // step; the extension above then leaves the collection query alone
        $services->set('locastic_api_platform_translation.doctrine.orm.state.translations_collection_provider', TranslationsCollectionProvider::class)
            ->decorate('api_platform.doctrine.orm.state.collection_provider', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([
                service('.inner'),
                service('locastic_api_platform_translation.doctrine.orm.translations_loader'),
                param('locastic_api_platform_translation.eager_load_translations'),
            ]);

        $services->alias(TranslationsCollectionProvider::class, 'locastic_api_platform_translation.doctrine.orm.state.translations_collection_provider');

        // Abstract parents the #[ApiFilter] attribute and user-defined filter
        // services build upon; children carry the api_platform.filter tag.
        $services->set('locastic_api_platform_translation.doctrine.orm.filter.translation_search', TranslationSearchFilter::class)
            ->abstract()
            ->args([
                service('locastic_api_platform_translation.translation.translator'),
                service('doctrine'),
                service('logger')->ignoreOnInvalid(),
            ]);

        $services->alias(TranslationSearchFilter::class, 'locastic_api_platform_translation.doctrine.orm.filter.translation_search');

        $services->set('locastic_api_platform_translation.doctrine.orm.filter.translation_order', TranslationOrderFilter::class)
            ->abstract()
            ->args([
                service('locastic_api_platform_translation.translation.translator'),
                service('doctrine'),
                service('logger')->ignoreOnInvalid(),
            ]);

        $services->alias(TranslationOrderFilter::class, 'locastic_api_platform_translation.doctrine.orm.filter.translation_order');
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
