# UPGRADE FROM 2.0 TO 2.1

2.1 contains no BC breaks. Typical installs need no changes.

## Deprecations

1. Not setting the `api_platform_translation.auto_create_translations` option
   explicitly is deprecated. It defaults to `true` (the 2.0 behavior:
   `getTranslation()` creates and attaches a translation when the requested
   locale is missing, which `cascade: persist` then inserts on the next flush,
   so a read can write empty rows). In 3.0 the default flips to `false`, where
   `getTranslation()` returns a detached, never-persisted translation instead.
   Set the option explicitly to silence the deprecation and, if you opt into
   `false`, change virtual setters to write through the new
   `getOrCreateTranslation()` method:

   ```php
   public function setTitle(string $title): void
   {
       $this->getOrCreateTranslation()->setTitle($title);
   }
   ```

   `getOrCreateTranslation()` always creates and attaches the missing
   translation for the exact locale (it never falls back to another locale, so
   it cannot overwrite the fallback translation's content), regardless of the
   `auto_create_translations` setting. Getters keep using `getTranslation()`.
   Writes through the API (the `translations` payload) are unaffected by the
   option: the denormalizer creates translations explicitly.

2. `Locastic\ApiPlatformTranslationBundle\DependencyInjection\ApiPlatformTranslationExtension`
   is deprecated and will be removed in 3.0. The bundle now extends
   `AbstractBundle` and registers its extension itself. The class keeps working
   if you instantiate it directly, but stop referencing it: registering the
   bundle in `config/bundles.php` is all that is needed.

## Internal layout changes

These only affect you if you referenced bundle files by path:

1. Services are now defined in `config/services.php` at the bundle root;
   `src/Resources/config/services.yml` no longer exists. Service IDs and
   behavior are unchanged.
2. `symfony/yaml` is no longer a dependency of the bundle. If your application
   used it without requiring it, add it to your own `composer.json`.

## New configuration

The bundle now has a configuration tree under `api_platform_translation`
(`enabled_locales`, `fallback_locale`, `auto_create_translations`,
`eager_load_translations`, `locale_resolution`). All defaults except
`eager_load_translations` preserve 2.0 behavior; see the README
"Configuration" section.

## Eager loading of translations

Queries for translatable resources now fetch-join the translations
(experimental), so listing N resources issues one query instead of one
translation query per entity per locale. Responses are unchanged; only the
generated SQL differs. Set `eager_load_translations: false` to restore the
2.0 lazy behavior.
