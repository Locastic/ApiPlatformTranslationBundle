<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

use Doctrine\Common\Collections\Collection;

/**
 * @package Locastic\ApiPlatformTranslationBundle\Model
 */
interface TranslatableInterface
{
    /**
     * @return Collection<TranslationInterface>
     */
    public function getTranslations(): Collection;

    public function getTranslation(string $locale = null): ?TranslationInterface;
    public function hasTranslation(TranslationInterface $translation): bool;

    public function addTranslation(TranslationInterface $translation): void;
    public function removeTranslation(TranslationInterface $translation): void;

    public function setCurrentLocale(?string $locale): void;
    public function setFallbackLocale(?string $locale): void;
}
