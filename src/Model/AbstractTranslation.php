<?php

namespace Locastic\ApiPlatformTranslationBundle\Model;

/**
 * Class AbstractTranslation
 *
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
class AbstractTranslation implements TranslationInterface
{
    /**
     * @var string
     */
    protected $locale;

    /**
     * @var TranslatableInterface
     */
    protected $translatable;

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function getTranslatable(): TranslatableInterface
    {
        return $this->translatable;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslatable(TranslatableInterface $translatable = null): void
    {
        if ($translatable === $this->translatable) {
            return;
        }

        $previousTranslatable = $this->translatable;
        $this->translatable = $translatable;

        if (null !== $previousTranslatable) {
            $previousTranslatable->removeTranslation($this);
        }

        if (null !== $translatable) {
            $translatable->addTranslation($this);
        }
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function setLocale($locale): void
    {
        $this->locale = $locale;
    }
}
