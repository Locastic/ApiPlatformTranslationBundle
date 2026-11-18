<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Base of the filters working on translated fields: fields that live on the
 * translation entity, addressed by their bare name (`title`, not
 * `translations.title`) and constrained to the translation row of the locale
 * resolved from the request.
 *
 * The filters rely on a resource having at most one translation per locale,
 * like the `indexBy: 'locale'` mapping of the translations does.
 *
 * @experimental
 */
abstract class AbstractTranslationFilter extends AbstractFilter
{
    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        protected readonly Translator $translator,
        ManagerRegistry $managerRegistry,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    /**
     * The configured properties that are fields of the resource's translation
     * entity; when no properties are configured, every translated field is
     * enabled.
     *
     * @return array<string, mixed> property name to its configured options
     */
    protected function getTranslatedProperties(string $resourceClass): array
    {
        $metadata = $this->getTranslationMetadata($resourceClass);
        if (null === $metadata) {
            return [];
        }

        $properties = $this->properties ?? array_fill_keys($metadata->getFieldNames(), null);

        return array_filter(
            $properties,
            fn (string $property): bool => $this->isTranslatedField($property, $resourceClass),
            \ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Whether the property is a field of the resource's translation entity,
     * excluding its identifier and the `locale` discriminator the filters
     * already constrain.
     */
    protected function isTranslatedField(string $property, string $resourceClass): bool
    {
        $metadata = $this->getTranslationMetadata($resourceClass);

        return null !== $metadata
            && 'locale' !== $property
            && !\in_array($property, $metadata->getIdentifierFieldNames(), true)
            && $metadata->hasField($property);
    }

    /**
     * Left-joins the translation row of the resolved request locale and
     * returns the join alias, reusing the join when it is already in the
     * query. Resources without a translation in that locale stay in the
     * result.
     *
     * The join targets the translation entity itself, not the `translations`
     * association: a to-many association join makes API Platform paginate
     * through Doctrine's fetch-join Paginator, which sorts the whole joined
     * result before it applies the limit. A resource has one translation per
     * locale, so this join never multiplies rows.
     *
     * When the translation entity has no field that points back to the
     * resource (`translations` is not the inverse side of a bidirectional
     * association), there is nothing to join on and the association join of
     * {@see joinCurrentLocaleTranslations()} is used.
     */
    protected function joinCurrentLocaleTranslation(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, string $resourceClass): string
    {
        $translationClass = $this->getTranslationMetadata($resourceClass)?->getName();
        if (null === $translationClass) {
            throw new \InvalidArgumentException(\sprintf('"%s" has no translations.', $resourceClass));
        }

        if (!$this->isTranslationsInverseSide($resourceClass)) {
            return $this->joinCurrentLocaleTranslations($queryBuilder, $queryNameGenerator, $rootAlias);
        }

        $locale = $this->translator->loadCurrentLocale();

        /** @var array<string, Join[]> $joinParts */
        $joinParts = $queryBuilder->getDQLPart('join');
        foreach ($joinParts[$rootAlias] ?? [] as $join) {
            $alias = $join->getAlias();
            if ($translationClass !== $join->getJoin() || Join::LEFT_JOIN !== $join->getJoinType() || null === $alias) {
                continue;
            }

            $pattern = '/^'.preg_quote($this->correlateTranslation($alias, $rootAlias, $resourceClass), '/').' AND '.preg_quote($alias, '/').'\.locale = :(\w+)$/';
            if (1 === preg_match($pattern, (string) $join->getCondition(), $matches)
                && $locale === $queryBuilder->getParameter($matches[1])?->getValue()
            ) {
                return $alias;
            }
        }

        $joinAlias = $queryNameGenerator->generateJoinAlias('translations');
        $parameterName = $queryNameGenerator->generateParameterName('locale');
        $queryBuilder
            ->leftJoin($translationClass, $joinAlias, Join::WITH, \sprintf(
                '%s AND %s.locale = :%s',
                $this->correlateTranslation($joinAlias, $rootAlias, $resourceClass),
                $joinAlias,
                $parameterName,
            ))
            ->setParameter($parameterName, $locale);

        return $joinAlias;
    }

    /**
     * Builds `EXISTS (the translation of the resolved request locale matches
     * the condition)`, for use in a WHERE clause. The callback receives the
     * alias of the translation and returns the DQL condition on it.
     *
     * A subquery keeps to-many joins out of the query, see
     * {@see joinCurrentLocaleTranslation()}; a join on the translation entity
     * is no option here, API Platform's FilterEagerLoadingExtension does not
     * carry such a join into the subquery it moves the WHERE clause to.
     *
     * When the translation entity has no field that points back to the
     * resource, the subquery cannot be tied to it: the condition is then put
     * on the association join of {@see joinCurrentLocaleTranslations()}.
     *
     * @param callable(string): string $condition
     */
    protected function existsCurrentLocaleTranslation(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias, string $resourceClass, callable $condition): string
    {
        $translationClass = $this->getTranslationMetadata($resourceClass)?->getName();
        if (null === $translationClass) {
            throw new \InvalidArgumentException(\sprintf('"%s" has no translations.', $resourceClass));
        }

        if (!$this->isTranslationsInverseSide($resourceClass)) {
            return \sprintf('(%s)', $condition($this->joinCurrentLocaleTranslations($queryBuilder, $queryNameGenerator, $rootAlias)));
        }

        $alias = $queryNameGenerator->generateJoinAlias('translations');
        $parameterName = $queryNameGenerator->generateParameterName('locale');
        $queryBuilder->setParameter($parameterName, $this->translator->loadCurrentLocale());

        return \sprintf(
            'EXISTS (SELECT %1$s FROM %2$s %1$s WHERE %3$s AND %1$s.locale = :%4$s AND (%5$s))',
            $alias,
            $translationClass,
            $this->correlateTranslation($alias, $rootAlias, $resourceClass),
            $parameterName,
            $condition($alias),
        );
    }

    /**
     * Left-joins the translations of the resolved request locale through the
     * `translations` association and returns the join alias, reusing the join
     * when it is already in the query. The join is constrained and never
     * selected: the eager-loading extension knows to leave conditioned joins
     * alone, so the hydrated collections stay complete.
     *
     * API Platform sees a to-many join and paginates through the fetch-join
     * Paginator, which is slow on large collections. Prefer
     * {@see joinCurrentLocaleTranslation()} to order and
     * {@see existsCurrentLocaleTranslation()} to constrain: they use this join
     * only when the translation entity has no field that points back to the
     * resource.
     */
    protected function joinCurrentLocaleTranslations(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $rootAlias): string
    {
        $locale = $this->translator->loadCurrentLocale();

        /** @var array<string, Join[]> $joinParts */
        $joinParts = $queryBuilder->getDQLPart('join');
        foreach ($joinParts[$rootAlias] ?? [] as $join) {
            $alias = $join->getAlias();
            $condition = $join->getCondition();
            if (\sprintf('%s.translations', $rootAlias) !== $join->getJoin()
                || Join::LEFT_JOIN !== $join->getJoinType()
                || null === $alias
                || null === $condition
            ) {
                continue;
            }

            if (1 === preg_match('/^'.preg_quote($alias, '/').'\.locale = :(\w+)$/', (string) $condition, $matches)
                && $locale === $queryBuilder->getParameter($matches[1])?->getValue()
            ) {
                return $alias;
            }
        }

        $joinAlias = $queryNameGenerator->generateJoinAlias('translations');
        $parameterName = $queryNameGenerator->generateParameterName('locale');
        $queryBuilder
            ->leftJoin(\sprintf('%s.translations', $rootAlias), $joinAlias, Join::WITH, \sprintf('%s.locale = :%s', $joinAlias, $parameterName))
            ->setParameter($parameterName, $locale);

        return $joinAlias;
    }

    /**
     * The DQL condition tying a translation alias to its translatable.
     *
     * With a single-field identifier the root alias is written in its
     * `alias.field` form: that is the form API Platform's
     * FilterEagerLoadingExtension re-aliases when it moves the WHERE clause
     * into a subquery.
     */
    private function correlateTranslation(string $translationAlias, string $rootAlias, string $resourceClass): string
    {
        $metadata = $this->getTranslatableMetadata($resourceClass);
        if (null === $metadata) {
            throw new \InvalidArgumentException(\sprintf('"%s" has no translations.', $resourceClass));
        }

        $translatableField = $metadata->getAssociationMappedByTargetField('translations');

        $identifiers = $metadata->getIdentifierFieldNames();
        if (1 === \count($identifiers) && $metadata->hasField($identifiers[0])) {
            return \sprintf('IDENTITY(%s.%s) = %s.%s', $translationAlias, $translatableField, $rootAlias, $identifiers[0]);
        }

        return \sprintf('%s.%s = %s', $translationAlias, $translatableField, $rootAlias);
    }

    /**
     * Whether the translation entity has a field that points back to the
     * resource, which a join on the translation entity and a subquery need to
     * tie a translation to its resource. The documented mapping (OneToMany
     * with `mappedBy`) has it.
     */
    private function isTranslationsInverseSide(string $resourceClass): bool
    {
        return true === $this->getTranslatableMetadata($resourceClass)?->isAssociationInverseSide('translations');
    }

    /**
     * @return ClassMetadata<object>|null
     */
    private function getTranslationMetadata(string $resourceClass): ?ClassMetadata
    {
        $metadata = $this->getTranslatableMetadata($resourceClass);
        if (null === $metadata) {
            return null;
        }

        return $this->managerRegistry?->getManagerForClass($resourceClass)?->getClassMetadata($metadata->getAssociationTargetClass('translations'));
    }

    /**
     * The metadata of the resource, when it is a translatable with a mapped
     * `translations` association.
     *
     * @return ClassMetadata<object>|null
     */
    private function getTranslatableMetadata(string $resourceClass): ?ClassMetadata
    {
        if (!is_a($resourceClass, TranslatableInterface::class, true)) {
            return null;
        }

        $manager = $this->managerRegistry?->getManagerForClass($resourceClass);
        if (null === $manager) {
            return null;
        }

        $metadata = $manager->getClassMetadata($resourceClass);

        return $metadata->hasAssociation('translations') ? $metadata : null;
    }
}
