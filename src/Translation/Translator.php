<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Translation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Translator implements TranslatorInterface, LocaleAwareInterface
{
    public const RESOLUTION_QUERY_PARAM = 'query_param';
    public const RESOLUTION_ACCEPT_LANGUAGE = 'accept_language';

    /**
     * @param list<string> $enabledLocales
     * @param list<string> $localeResolution ordered self::RESOLUTION_* values
     */
    public function __construct(
        private readonly TranslatorInterface&LocaleAwareInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly string $defaultLocale,
        private readonly array $enabledLocales = [],
        private readonly array $localeResolution = [self::RESOLUTION_QUERY_PARAM, self::RESOLUTION_ACCEPT_LANGUAGE],
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
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

        foreach ($this->localeResolution as $source) {
            $locale = match ($source) {
                self::RESOLUTION_QUERY_PARAM => $this->resolveFromQueryParam($request),
                self::RESOLUTION_ACCEPT_LANGUAGE => $this->resolveFromAcceptLanguage($request),
                default => throw new \InvalidArgumentException(sprintf('Unknown locale resolution source "%s".', $source)),
            };

            if (null !== $locale) {
                return $locale;
            }
        }

        return $this->defaultLocale;
    }

    private function resolveFromQueryParam(Request $request): ?string
    {
        $localeCode = $request->query->get('locale');

        if (!$localeCode) {
            return null;
        }

        if (!$this->enabledLocales || \in_array($localeCode, $this->enabledLocales, true)) {
            return (string) $localeCode;
        }

        // A locale outside the enabled list pins the default instead of falling
        // through: an explicit but invalid request must not be re-interpreted
        // from a lower-priority source.
        return $this->defaultLocale;
    }

    private function resolveFromAcceptLanguage(Request $request): ?string
    {
        // An absent header falls through to the next source; getPreferredLanguage()
        // would otherwise resolve it to the head of the locale list.
        if (!$request->headers->has('Accept-Language')) {
            return null;
        }

        // getPreferredLanguage() falls back to the first element of the list when
        // nothing matches, so the default locale is pinned first to keep it the fallback.
        $preferredLanguage = $request->getPreferredLanguage(
            $this->enabledLocales
                ? array_values(array_unique([$this->defaultLocale, ...$this->enabledLocales]))
                : null
        );

        return empty($preferredLanguage) ? null : $preferredLanguage;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setLocale(string $locale = 'en'): void
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
