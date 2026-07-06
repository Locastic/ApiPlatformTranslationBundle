# UPGRADE FROM 1.x TO 2.0

1. Dependencies: 2.0 requires PHP `^8.2`, `api-platform/symfony ^3.4 || ^4.0`
   (the `api-platform/core` package is no longer accepted directly) and
   `doctrine/orm ^3.0`.

2. `Locastic\ApiPlatformTranslationBundle\Translation\Translator` now also
   implements `Symfony\Contracts\Translation\LocaleAwareInterface`, and its
   constructor requires the wrapped translator to implement
   `TranslatorInterface&LocaleAwareInterface` (the default `translator` service
   does). `trans()` now declares `string $id` and `setLocale()` declares
   `string $locale`; passing non-string values was never supported.

3. `Locastic\ApiPlatformTranslationBundle\EventListener\AssignLocaleListener`
   method signatures changed from `Doctrine\Common\EventArgs` to
   `Doctrine\Persistence\Event\LifecycleEventArgs` (the type Doctrine actually
   dispatches). Update any subclass overriding `postLoad()` or `prePersist()`.

4. When `framework.enabled_locales` is configured, requests for locales outside
   the list now fall back to the default locale instead of being accepted
   verbatim (both the `?locale=` query parameter and the `Accept-Language`
   header). Without `enabled_locales`, behavior is unchanged.
