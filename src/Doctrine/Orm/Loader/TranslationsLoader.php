<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;

/**
 * Loads translations with a fetch join restricted to the given entities:
 * hydrating a fetch join onto entities that are already managed initializes
 * their uninitialized collections, keyed as the mapping says (`indexBy`).
 *
 * All locales are loaded, like the fetch join of the eager-loading extension:
 * fallback-locale reads and the `translations` serialization group need the
 * complete collection.
 *
 * @experimental
 */
final class TranslationsLoader implements TranslationsLoaderInterface
{
    private const BATCH_SIZE = 500;

    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    public function supports(string $entityClass): bool
    {
        if (!is_a($entityClass, TranslatableInterface::class, true)) {
            return false;
        }

        $manager = $this->managerRegistry->getManagerForClass($entityClass);
        if (!$manager instanceof EntityManagerInterface) {
            return false;
        }

        $metadata = $manager->getClassMetadata($entityClass);

        // WHERE o IN (:entities) needs a single-column identifier
        return $metadata->isCollectionValuedAssociation('translations') && !$metadata->isIdentifierComposite;
    }

    public function load(iterable $translatables): void
    {
        $pending = [];
        foreach ($translatables as $translatable) {
            if (!$translatable instanceof TranslatableInterface) {
                continue;
            }

            $translations = $translatable->getTranslations();
            if (!$translations instanceof PersistentCollection || $translations->isInitialized()) {
                continue;
            }

            $owner = $translations->getOwner();
            if (null === $owner) {
                continue;
            }

            // the class that declares the association: entities of one
            // inheritance tree load together
            $pending[$translations->getMapping()->sourceEntity][spl_object_id($owner)] = $owner;
        }

        foreach ($pending as $entityClass => $entities) {
            if (!$this->supports($entityClass)) {
                continue;
            }

            /** @var EntityManagerInterface $manager */
            $manager = $this->managerRegistry->getManagerForClass($entityClass);
            foreach (array_chunk(array_values($entities), self::BATCH_SIZE) as $batch) {
                $manager->createQueryBuilder()
                    ->select('o', 'translations')
                    ->from($entityClass, 'o')
                    ->leftJoin('o.translations', 'translations')
                    ->where('o IN (:translatables)')
                    ->setParameter('translatables', $batch)
                    ->getQuery()
                    ->getResult();
            }
        }
    }
}
