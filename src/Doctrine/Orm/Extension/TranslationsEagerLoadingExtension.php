<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;

/**
 * Fetch-joins the translations of translatable resources, so reading translated
 * fields issues one query per collection instead of one per entity per locale.
 *
 * All locales are joined, not only the current one: a locale-constrained fetch
 * join would mark the collection initialized with a subset, breaking
 * fallback-locale reads and the `translations` serialization group.
 *
 * @experimental
 */
final class TranslationsEagerLoadingExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly bool $enabled = true)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->joinTranslations($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    /**
     * @param array<string, mixed> $identifiers
     */
    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->joinTranslations($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function joinTranslations(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if (!$this->enabled || !is_a($resourceClass, TranslatableInterface::class, true)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $rootAlias || $this->hasFetchJoin($queryBuilder, $rootAlias)) {
            return;
        }

        $joinAlias = $queryNameGenerator->generateJoinAlias('translations');
        $queryBuilder
            ->leftJoin(\sprintf('%s.translations', $rootAlias), $joinAlias)
            ->addSelect($joinAlias);
    }

    /**
     * Only an unconstrained, selected join counts: a filter may join translations
     * WITH a locale condition, and selecting that alias would hydrate a partial
     * collection, so such joins are ignored and a dedicated one is added.
     */
    private function hasFetchJoin(QueryBuilder $queryBuilder, string $rootAlias): bool
    {
        $selected = [];
        foreach ($queryBuilder->getDQLPart('select') as $select) {
            foreach ($select->getParts() as $part) {
                if (\is_string($part)) {
                    $selected[] = $part;
                }
            }
        }

        /** @var array<string, Join[]> $joinParts */
        $joinParts = $queryBuilder->getDQLPart('join');
        foreach ($joinParts[$rootAlias] ?? [] as $join) {
            if (\sprintf('%s.translations', $rootAlias) === $join->getJoin()
                && null === $join->getCondition()
                && \in_array($join->getAlias(), $selected, true)
            ) {
                return true;
            }
        }

        return false;
    }
}
