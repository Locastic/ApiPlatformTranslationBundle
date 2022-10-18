<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * Class AbstractTranslation
 *
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
abstract class AbstractTranslation implements TranslationInterface
{
    protected ?TranslatableInterface $translatable = null;
    protected ?string $locale = null;
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
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }
}
