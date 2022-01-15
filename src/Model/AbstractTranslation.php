<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * Class AbstractTranslation
 *
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
class AbstractTranslation implements TranslationInterface
{
    private ?TranslatableInterface $translatable = null;

    public function __construct(
        private ?string $locale,
        ?TranslatableInterface $translatable
    ) {
        $this->setTranslatable($translatable);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTranslatable(): ?TranslatableInterface
    {
        return $this->translatable;
    }

    public function setTranslatable(?TranslatableInterface $translatable): void
    {
        if ($translatable === $this->translatable) {
            return;
        }

        $previousTranslatable = $this->translatable;
        $this->translatable = $translatable;

        $previousTranslatable?->removeTranslation($this);

        $translatable?->addTranslation($this);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
