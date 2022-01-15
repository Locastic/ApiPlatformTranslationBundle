<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Tests\Fixtures;

use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslation;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;

/**
 * @package Locastic\ApiPlatformTranslationBundle\Tests\Fixtures
 */
class DummyTranslation extends AbstractTranslation
{
    public function __construct(
        ?string $locale = null,
        ?TranslatableInterface $translatable = null,
        private ?string $translation = null
    ) {
        parent::__construct($locale, $translatable);
    }

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
