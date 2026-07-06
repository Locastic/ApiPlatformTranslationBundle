<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Translation;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Translator implements TranslatorInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private string $defaultLocale,
        private array $enabledLocales = [],
    ) {
    }

    public function trans($id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if (null === $locale) {
            $locale = $this->loadCurrentLocale();
        }

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function loadCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $this->defaultLocale;
        }

        $localeCode = $request->query->get('locale');

        if ($localeCode) {
            if (!$this->enabledLocales || \in_array($localeCode, $this->enabledLocales, true)) {
                return $localeCode;
            }

            return $this->defaultLocale;
        }

        // getPreferredLanguage() falls back to the first element of the list when
        // nothing matches, so the default locale is pinned first to keep it the fallback.
        $preferredLanguage = $request->getPreferredLanguage(
            $this->enabledLocales
                ? array_values(array_unique([$this->defaultLocale, ...$this->enabledLocales]))
                : null
        );

        return empty($preferredLanguage) ? $this->defaultLocale : $preferredLanguage;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setLocale($locale = 'en'): void
    {
        $this->translator->setLocale($locale);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
