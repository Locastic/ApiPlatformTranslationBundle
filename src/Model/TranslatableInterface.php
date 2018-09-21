<?php

namespace Locastic\ApiPlatformTranslationBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * Interface TranslatableInterface
 *
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
interface TranslatableInterface
{
    /**
     * @return ArrayCollection|TranslationInterface[]
     */
    public function getTranslations();

    /**
     * @param string $locale
     *
     * @return TranslationInterface
     */
    public function getTranslation($locale = null): ?TranslationInterface;

    /**
     * @param TranslationInterface $translation
     *
     * @return bool
     */
    public function hasTranslation(TranslationInterface $translation): bool ;

    /**
     * @param TranslationInterface $translation
     */
    public function addTranslation(TranslationInterface $translation): void;

    /**
     * @param TranslationInterface $translation
     */
    public function removeTranslation(TranslationInterface $translation): void;

    /**
     * @param string $locale
     */
    public function setCurrentLocale($locale): void;

    /**
     * @param string $locale
     */
    public function setFallbackLocale($locale): void;
}
