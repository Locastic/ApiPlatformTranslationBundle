<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader;

/**
 * Loads the translations of a set of already loaded translatable entities.
 *
 * @experimental
 */
interface TranslationsLoaderInterface
{
    /**
     * Whether the translations of entities of this class can be loaded by
     * {@see load()}.
     */
    public function supports(string $entityClass): bool;

    /**
     * Initializes the translations collections of the given translatable
     * entities, with every locale, in one query per 500 entities. Anything
     * that is not a managed translatable with an uninitialized collection is
     * skipped.
     *
     * @param iterable<mixed> $translatables
     */
    public function load(iterable $translatables): void;
}
