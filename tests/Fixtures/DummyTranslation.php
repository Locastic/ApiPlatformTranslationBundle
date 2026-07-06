<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures;

use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslation;

class DummyTranslation extends AbstractTranslation
{
    private ?string $translation = null;

    public function setTranslation(string $translation): DummyTranslation
    {
        $this->translation = $translation;

        return $this;
    }

    public function getTranslation(): ?string
    {
        return $this->translation;
    }
}
