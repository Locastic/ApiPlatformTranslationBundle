<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;

/**
 * Doctrine-mapped translatable used by the serializer integration tests.
 *
 * The `translations` association is intentionally mapped WITHOUT `indexBy: 'locale'`
 * so the collection hydrates as a plain 0-indexed list. This mirrors the minimal
 * mapping a user may write and guards the denormalizer against silently depending on
 * a locale-keyed collection.
 *
 * @extends AbstractTranslatable<IntegrationArticleTranslation>
 */
#[ORM\Entity]
class IntegrationArticle extends AbstractTranslatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<string, IntegrationArticleTranslation>
     */
    #[ORM\OneToMany(
        targetEntity: IntegrationArticleTranslation::class,
        mappedBy: 'translatable',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    protected Collection $translations;

    public function getId(): ?int
    {
        return $this->id;
    }

    protected function createTranslation(): TranslationInterface
    {
        return new IntegrationArticleTranslation();
    }
}
