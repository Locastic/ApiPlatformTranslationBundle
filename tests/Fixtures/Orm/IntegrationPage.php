<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;

/**
 * Doctrine-mapped translatable with the mapping the README recommends: the
 * `translations` association is extra lazy and indexed by locale.
 *
 * @extends AbstractTranslatable<IntegrationPageTranslation>
 */
#[ORM\Entity]
class IntegrationPage extends AbstractTranslatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<string, IntegrationPageTranslation>
     */
    #[ORM\OneToMany(
        targetEntity: IntegrationPageTranslation::class,
        mappedBy: 'translatable',
        fetch: 'EXTRA_LAZY',
        indexBy: 'locale',
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
        return new IntegrationPageTranslation();
    }
}
