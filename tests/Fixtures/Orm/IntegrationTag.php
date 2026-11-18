<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;

/**
 * Translatable whose `translations` association is unidirectional: the
 * translation entity has no mapped field that points back to it. The filters
 * cannot join the translation entity on such a mapping and must fall back to
 * the association join.
 *
 * @extends AbstractTranslatable<IntegrationTagTranslation>
 */
#[ORM\Entity]
class IntegrationTag extends AbstractTranslatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<string, IntegrationTagTranslation>
     */
    #[ORM\ManyToMany(targetEntity: IntegrationTagTranslation::class, cascade: ['persist'], indexBy: 'locale')]
    protected Collection $translations;

    public function getId(): ?int
    {
        return $this->id;
    }

    protected function createTranslation(): TranslationInterface
    {
        return new IntegrationTagTranslation();
    }
}
