<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;

/**
 * @see TranslatableInterface
 *
 * @author Gonzalo Vilaseca <gvilaseca@reiss.co.uk>
 */
trait TranslatableTrait
{
    /**
     * Protected to allow access in classes using this Trait or extending provided AbstractTranslatable
     * @var Collection<TranslationInterface>|TranslationInterface[]
     */
    protected array|Collection|ArrayCollection $translations;
    /**
     * @var array|TranslationInterface[]
     */
    private array $translationsCache = [];
    private ?string $currentLocale;
    /**
     * Cache current translation. Useful in Doctrine 2.4+
     */
    private TranslationInterface $currentTranslation;
    private ?string $fallbackLocale;

    /**
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * @throws \RuntimeException
     */
    public function getTranslation(string $locale = null): ?TranslationInterface
    {
        // Determine requested locale
        $locale = $locale ?: $this->currentLocale;
        if ($locale === null) {
            throw LocaleNotSupported::unsupported();
        }

        // Attempt to grab Translation from Translatable object
        $translation = $this->matchTranslation($locale);
        if ($translation instanceof TranslationInterface) {
            return $translation;
        }

        // If no translation above, start getting fallback. This check return early if fallback matches non-available
        // translation locales from above
        if ($this->fallbackLocale === $locale || $this->fallbackLocale === $this->currentLocale) {
            return null;
        }

        return $this->matchTranslation($this->fallbackLocale);
    }

    /**
     * Attempts to match available TranslationInterface instances to locale, adds to Translatable objects' cache on
     * success
     */
    private function matchTranslation(string $locale): ?TranslationInterface
    {
        // Return early if Translation in object cache
        if (isset($this->translationsCache[$locale])) {
            return $this->translationsCache[$locale];
        }

        $expr = new Comparison('locale', '=', $locale);
        $translation = $this->translations->matching(new Criteria($expr))->first();

        if ($translation instanceof TranslationInterface) {
            $this->translationsCache[$locale] = $translation; //@codeCoverageIgnore

            return $translation; //@codeCoverageIgnore
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getTranslationLocales(): array
    {
        $locales = [];

        foreach ($this->getTranslations() as $translation) {
            $locales[] = $translation->getLocale();
        }

        return $locales;
    }

    /**
     * @param string $locale
     */
    public function removeTranslationWithLocale(string $locale): void
    {
        foreach ($this->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                $this->removeTranslation($translation);

                $translation->setTranslatable(null);
            }
        }
    }

    /**
     * {@inheritdoc}
     * @return Collection<TranslationInterface>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function hasTranslation(TranslationInterface $translation): bool
    {
        return isset($this->translationsCache[$translation->getLocale()])
               || $this->translations->containsKey($translation->getLocale());
    }

    public function addTranslation(TranslationInterface $translation): void
    {
        if (!$this->hasTranslation($translation)) {
            $this->translationsCache[$translation->getLocale()] = $translation;

            $this->translations->set($translation->getLocale(), $translation);
            $translation->setTranslatable($this);
        }
    }

    public function removeTranslation(TranslationInterface $translation): void
    {
        if ($this->translations->removeElement($translation)) {
            unset($this->translationsCache[$translation->getLocale()]);

            $translation->setTranslatable(null);
        }
    }

    public function setCurrentLocale(?string $currentLocale): void
    {
        $this->currentLocale = $currentLocale;
    }

    public function setFallbackLocale(?string $fallbackLocale): void
    {
        $this->fallbackLocale = $fallbackLocale;
    }
}
