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

5. Nested translation writes are now reconciled in place. When a write payload
   carries the `translations` map, each submitted locale updates its existing
   translation row (keeping the id) instead of the collection being recreated:
   `PATCH` leaves locales absent from the payload untouched (partial edit) and
   `PUT` removes them (full replace). The map key is the authoritative locale; a
   `locale` in the body no longer relabels the addressed row. Expose `PUT` on
   translatable resources with `extraProperties: ['standard_put' => false]` (or
   set `api_platform.defaults.extra_properties.standard_put: false` globally) so
   API Platform edits the managed entity; with `standard_put` on, its persist
   processor copies the fresh translations collection over the managed one and
   the rows cannot be matched, so the bundle rejects such a `PUT` with a
   `LogicException`. `PATCH` needs no extra configuration.
