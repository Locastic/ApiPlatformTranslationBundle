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
     * Left-joins the translations of the resolved request locale and returns
     * the join alias, reusing the join when another translation filter already
     * added it to the query. The join is constrained and never selected: the
     * eager-loading extension knows to leave conditioned joins alone, so the
     * hydrated collections stay complete.
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
     * @return ClassMetadata<object>|null
     */
    private function getTranslationMetadata(string $resourceClass): ?ClassMetadata
    {
        if (!is_a($resourceClass, TranslatableInterface::class, true)) {
            return null;
        }

        $manager = $this->managerRegistry?->getManagerForClass($resourceClass);
        if (null === $manager) {
            return null;
        }

        $metadata = $manager->getClassMetadata($resourceClass);
        if (!$metadata->hasAssociation('translations')) {
            return null;
        }

        return $manager->getClassMetadata($metadata->getAssociationTargetClass('translations'));
    }
}
