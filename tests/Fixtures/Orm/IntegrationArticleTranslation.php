<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures\Orm;

use Doctrine\ORM\Mapping as ORM;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslation;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;

#[ORM\Entity]
class IntegrationArticleTranslation extends AbstractTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: IntegrationArticle::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?TranslatableInterface $translatable = null;

    // Nullable to match the inherited `?string` property type (invariance forbids
    // narrowing it here); locale is always set in practice.
    #[ORM\Column(nullable: true)]
    protected ?string $locale = null;

    #[ORM\Column(nullable: true)]
    private ?string $title = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }
}
