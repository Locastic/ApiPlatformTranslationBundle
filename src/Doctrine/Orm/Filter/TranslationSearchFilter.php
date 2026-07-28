<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Locastic\ApiPlatformTranslationBundle\Translation\Translator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Searches translated fields in the translation of the resolved request
 * locale only; a core SearchFilter on `translations.title` matches against
 * every locale instead.
 *
 * Translated fields are declared by their bare name with the same strategies
 * the core SearchFilter supports (`exact`, `partial`, `start`, `end`,
 * `word_start`, each optionally prefixed with `i` for case-insensitive
 * matching); omitting the properties argument enables every translated field
 * with the `exact` strategy:
 *
 *     #[ApiFilter(TranslationSearchFilter::class, properties: ['title' => 'ipartial', 'summary' => 'exact'])]
 *
 * Syntax: `?title=foo`, or `?title[]=foo&title[]=bar` for multiple values.
 *
 * @experimental
 */
final class TranslationSearchFilter extends AbstractTranslationFilter
{
    public const STRATEGY_EXACT = 'exact';
    public const STRATEGY_PARTIAL = 'partial';
    public const STRATEGY_START = 'start';
    public const STRATEGY_END = 'end';
    public const STRATEGY_WORD_START = 'word_start';

    /**
     * @param array<string, string|null>|null $properties translated field name to matching strategy
     */
    public function __construct(
        Translator $translator,
        ManagerRegistry $managerRegistry,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($translator, $managerRegistry, $logger, $properties, $nameConverter);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->getTranslatedProperties($resourceClass) as $property => $strategy) {
            $propertyName = $this->normalizePropertyName($property);
            $baseDescription = [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'strategy' => \is_string($strategy) ? $strategy : self::STRATEGY_EXACT,
            ];

            $description[$propertyName] = $baseDescription + ['is_collection' => false];
            $description[$propertyName.'[]'] = $baseDescription + ['is_collection' => true];
        }

        return $description;
    }

    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (null === $value
            || !$this->isPropertyEnabled($property, $resourceClass)
            || !$this->isTranslatedField($property, $resourceClass)
        ) {
            return;
        }

        $values = array_values(array_filter((array) $value, is_scalar(...)));
        if ([] === $values) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new \InvalidArgumentException(\sprintf('At least one value is required, multiple values should be in "%1$s[]=firstvalue&%1$s[]=secondvalue" format', $property)),
            ]);

            return;
        }

        $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;
        if (!\is_string($strategy)) {
            $strategy = self::STRATEGY_EXACT;
        }

        $caseSensitive = true;
        if (str_starts_with($strategy, 'i')) {
            $strategy = substr($strategy, 1);
            $caseSensitive = false;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $joinAlias = $this->joinCurrentLocaleTranslations($queryBuilder, $queryNameGenerator, $rootAlias);

        $this->addWhereByStrategy($strategy, $queryBuilder, $queryNameGenerator, $joinAlias, $property, array_map(strval(...), $values), $caseSensitive);
    }

    /**
     * @param list<string> $values
     *
     * @throws \InvalidArgumentException if the strategy does not exist
     */
    private function addWhereByStrategy(string $strategy, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $alias, string $field, array $values, bool $caseSensitive): void
    {
        $wrapCase = $caseSensitive
            ? static fn (string $expr): string => $expr
            : static fn (string $expr): string => \sprintf('LOWER(%s)', $expr);
        $aliasedField = \sprintf('%s.%s', $alias, $field);
        $valueParameter = ':'.$queryNameGenerator->generateParameterName($field);

        if (self::STRATEGY_EXACT === $strategy) {
            if (1 === \count($values)) {
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq($wrapCase($aliasedField), $wrapCase($valueParameter)))
                    ->setParameter($valueParameter, $values[0]);

                return;
            }

            $queryBuilder
                ->andWhere($queryBuilder->expr()->in($wrapCase($aliasedField), $valueParameter))
                ->setParameter($valueParameter, $caseSensitive ? $values : array_map(strtolower(...), $values));

            return;
        }

        $ors = [];
        $parameters = [];
        foreach ($values as $key => $value) {
            $keyValueParameter = \sprintf('%s_%s', $valueParameter, $key);
            $parameters[$keyValueParameter] = $caseSensitive ? $value : strtolower($value);

            $ors[] = match ($strategy) {
                self::STRATEGY_PARTIAL => $queryBuilder->expr()->like(
                    $wrapCase($aliasedField),
                    $wrapCase((string) $queryBuilder->expr()->concat("'%'", $keyValueParameter, "'%'")),
                ),
                self::STRATEGY_START => $queryBuilder->expr()->like(
                    $wrapCase($aliasedField),
                    $wrapCase((string) $queryBuilder->expr()->concat($keyValueParameter, "'%'")),
                ),
                self::STRATEGY_END => $queryBuilder->expr()->like(
                    $wrapCase($aliasedField),
                    $wrapCase((string) $queryBuilder->expr()->concat("'%'", $keyValueParameter)),
                ),
                self::STRATEGY_WORD_START => $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like(
                        $wrapCase($aliasedField),
                        $wrapCase((string) $queryBuilder->expr()->concat($keyValueParameter, "'%'")),
                    ),
                    $queryBuilder->expr()->like(
                        $wrapCase($aliasedField),
                        $wrapCase((string) $queryBuilder->expr()->concat("'% '", $keyValueParameter, "'%'")),
                    ),
                ),
                default => throw new \InvalidArgumentException(\sprintf('Strategy "%s" does not exist.', $strategy)),
            };
        }

        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$ors));
        foreach ($parameters as $parameterName => $parameterValue) {
            $queryBuilder->setParameter($parameterName, $parameterValue);
        }
    }
}
