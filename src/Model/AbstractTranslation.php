<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * Class AbstractTranslation.
 */
abstract class AbstractTranslation implements TranslationInterface
{
    /**
     * @var TranslatableInterface<TranslationInterface>|null
     */
    protected ?TranslatableInterface $translatable = null;
    protected ?string $locale = null;

    /**
     * @codeCoverageIgnore
     *
     * @return TranslatableInterface<TranslationInterface>|null
     */
    public function getTranslatable(): ?TranslatableInterface
    {
        return $this->translatable;
    }

    /**
     * @param TranslatableInterface<TranslationInterface>|null $translatable
     */
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
