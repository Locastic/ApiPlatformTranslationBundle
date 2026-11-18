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
 * Orders a collection by translated fields, using the value of the
 * translation of the resolved request locale; a core OrderFilter on
 * `translations.title` sorts by an arbitrary locale instead.
 *
 * Translated fields are declared by their bare name, optionally with a
 * default direction applied when the query parameter gives none; omitting
 * the properties argument enables every translated field:
 *
 *     #[ApiFilter(TranslationOrderFilter::class, properties: ['title' => 'ASC', 'summary'])]
 *
 * Syntax: `?order[title]=asc|desc`. Resources without a translation in the
 * current locale keep their database null ordering.
 *
 * @experimental
 */
final class TranslationOrderFilter extends AbstractTranslationFilter
{
    public const DIRECTION_ASC = 'ASC';
    public const DIRECTION_DESC = 'DESC';

    /**
     * @param array<string, string|array{default_direction?: string}|null>|null $properties translated field name to default direction
     */
    public function __construct(
        Translator $translator,
        ManagerRegistry $managerRegistry,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
        private readonly string $orderParameterName = 'order',
    ) {
        if (null !== $properties) {
            // shorthand for the default direction
            $properties = array_map(
                static fn ($options) => \is_string($options) ? ['default_direction' => $options] : $options,
                $properties,
            );
        }

        parent::__construct($translator, $managerRegistry, $logger, $properties, $nameConverter);
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $orderings = $context['filters'][$this->orderParameterName] ?? null;
        if (!\is_array($orderings)) {
            return;
        }

        foreach ($orderings as $property => $direction) {
            $this->filterProperty($this->denormalizePropertyName($property), $direction, $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->getTranslatedProperties($resourceClass) as $property => $options) {
            $propertyName = $this->normalizePropertyName($property);
            $schema = ['type' => 'string', 'enum' => ['asc', 'desc']];
            if (\is_string($default = $options['default_direction'] ?? null)) {
                $schema = ['type' => 'string', 'default' => strtolower($default), 'enum' => ['asc', 'desc']];
            }

            $description[\sprintf('%s[%s]', $this->orderParameterName, $propertyName)] = [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'schema' => $schema,
            ];
        }

        return $description;
    }

    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$this->isPropertyEnabled($property, $resourceClass) || !$this->isTranslatedField($property, $resourceClass)) {
            return;
        }

        $direction = $this->normalizeDirection($value, $property);
        if (null === $direction) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $joinAlias = $this->joinCurrentLocaleTranslation($queryBuilder, $queryNameGenerator, $rootAlias, $resourceClass);

        $queryBuilder->addOrderBy(\sprintf('%s.%s', $joinAlias, $property), $direction);
    }

    private function normalizeDirection(mixed $value, string $property): ?string
    {
        if ((null === $value || '' === $value) && \is_string($default = $this->properties[$property]['default_direction'] ?? null)) {
            $value = $default;
        }

        if (!\is_string($value)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new \InvalidArgumentException(\sprintf('Invalid direction value for "%s" property', $property)),
            ]);

            return null;
        }

        $direction = strtoupper($value);

        return \in_array($direction, [self::DIRECTION_ASC, self::DIRECTION_DESC], true) ? $direction : null;
    }
}
