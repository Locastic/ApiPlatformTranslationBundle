<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

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
        ])
        ->tag('doctrine.event_listener', ['event' => 'postLoad'])
        ->tag('doctrine.event_listener', ['event' => 'prePersist']);

    // Serializer: in-place, merge denormalization of nested translations
    $services->set('locastic_api_platform_translation.serializer.translatable_denormalizer', TranslatableItemDenormalizer::class)
        ->tag('serializer.normalizer', ['priority' => 100]);

    $services->alias(TranslatableItemDenormalizer::class, 'locastic_api_platform_translation.serializer.translatable_denormalizer');

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
