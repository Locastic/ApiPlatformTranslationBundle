<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\State;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader\TranslationsLoaderInterface;

/**
 * Decorates the Doctrine ORM collection provider: the translations of the
 * provided page are loaded in a second step, as one query, instead of being
 * fetch-joined into the paginated query. A to-many fetch join makes Doctrine's
 * Paginator count and select the identifiers through the whole joined result.
 *
 * The decorated provider is told through the context that the translations are
 * taken care of, so the eager-loading extension leaves the query alone. A
 * provider that applies the query extensions without this decorator still gets
 * the fetch join from the extension.
 *
 * @experimental
 *
 * @implements ProviderInterface<object>
 */
final class TranslationsCollectionProvider implements ProviderInterface
{
    /**
     * Context flag: the translations of the collection are loaded after the
     * query, the eager-loading extension must not fetch-join them.
     */
    public const TRANSLATIONS_LOADED_AFTER_QUERY = 'locastic_api_platform_translation_translations_loaded_after_query';

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly TranslationsLoaderInterface $translationsLoader,
        private readonly bool $enabled = true,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$this->enabled || !$this->translationsLoader->supports($this->getEntityClass($operation))) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $result = $this->decorated->provide($operation, $uriVariables, [self::TRANSLATIONS_LOADED_AFTER_QUERY => true] + $context);

        if (is_iterable($result)) {
            // A paginator runs its query here and keeps the result, the
            // serializer iterates the same entities afterwards.
            $this->translationsLoader->load($result);
        }

        return $result;
    }

    private function getEntityClass(Operation $operation): string
    {
        $options = $operation->getStateOptions();
        if ($options instanceof Options && null !== $entityClass = $options->getEntityClass()) {
            return $entityClass;
        }

        return (string) $operation->getClass();
    }
}
