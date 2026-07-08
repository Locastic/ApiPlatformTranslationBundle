# UPGRADE FROM 2.0 TO 2.1

2.1 contains no BC breaks. Typical installs need no changes.

## Deprecations

1. `Locastic\ApiPlatformTranslationBundle\DependencyInjection\ApiPlatformTranslationExtension`
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
(`enabled_locales`, `fallback_locale`, `locale_resolution`). All defaults
preserve 2.0 behavior; see the README "Configuration" section.
