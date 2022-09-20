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
 * @template T of TranslationInterface
 */
trait TranslatableTrait
{
    /**
     * Protected to allow access in classes using this Trait or extending provided AbstractTranslatable
     * @var Collection<string, TranslationInterface>
     * @psalm-var Collection<string, T>
     */
    protected Collection $translations;

    /**
     * @var array|TranslationInterface[]
     * @psalm-var array<string, T>
     */
    protected array $translationsCache = [];
    protected ?string $currentLocale = null;
    protected ?string $fallbackLocale = null;

    /**
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     *
     * @return TranslationInterface
     * @psalm-return T
     *
     * @throws \RuntimeException
     */
    public function getTranslation(?string $locale = null): TranslationInterface
    {
        $locale = $locale ?: $this->currentLocale;
        if (null === $locale) {
            throw new \RuntimeException('No locale has been set and current locale is undefined.');
        }

        $translation = $this->matchTranslation($locale);
        if (null !== $translation) {
            return $translation;
        }

        if ($locale !== $this->fallbackLocale && null !== $this->fallbackLocale) {
            $fallbackTranslation = $this->matchTranslation($this->fallbackLocale);
            if (null !== $fallbackTranslation) {
                return $fallbackTranslation;
            }
        }

        $translation = $this->createTranslation();
        $translation->setLocale($locale);

        $this->addTranslation($translation);

        $this->translationsCache[$locale] = $translation;

        return $translation;
    }

    /**
     * @param string $locale
     * @return TranslationInterface|null
     * @psalm-return T|null
     */
    private function matchTranslation(string $locale): ?TranslationInterface
    {
        if (isset($this->translationsCache[$locale])) {
            return $this->translationsCache[$locale];
        }

        $expr = new Comparison('locale', '=', $locale);
        $translation = $this->translations->matching(new Criteria($expr))->first();

        if ($translation instanceof TranslationInterface) {
            $this->translationsCache[$locale] = $translation;

            return $translation;
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
        $translations = $this->getTranslations();

        foreach ($translations as $translation) {
            if ($translation->getLocale() === $locale) {
                $this->removeTranslation($translation);

                $translation->setTranslatable(null);
            }
        }
    }

    /**
     * @return Collection<string, TranslationInterface>
     * @psalm-return Collection<string, T>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * @param TranslationInterface $translation
     * @psalm-param T $translation
     * @return bool
     */
    public function hasTranslation(TranslationInterface $translation): bool
    {
        return isset($this->translationsCache[$translation->getLocale()])
               || $this->translations->containsKey($translation->getLocale());
    }

    /**
     * @param TranslationInterface $translation
     * @psalm-param T $translation
     * @return void
     */
    public function addTranslation(TranslationInterface $translation): void
    {
        if (!$this->hasTranslation($translation)) {
            $this->translationsCache[$translation->getLocale()] = $translation;

            $this->translations->set($translation->getLocale(), $translation);
            $translation->setTranslatable($this);
        }
    }

    /**
     * @param TranslationInterface $translation
     * @psalm-param T $translation
     * @return void
     */
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

    /**
     * Create resource translation model.
     *
     * @return TranslationInterface $translation
     * @psalm-return T $translation
     */
    abstract protected function createTranslation(): TranslationInterface;
}
