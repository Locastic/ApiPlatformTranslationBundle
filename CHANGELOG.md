# Changelog

All notable changes to this project are documented in this file. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Bundle configuration tree under the `api_platform_translation` alias:
  `enabled_locales` (inherits `framework.enabled_locales` when empty),
  `fallback_locale` (inherits `framework.default_locale` when null) and
  `locale_resolution` (ordered locale sources, `query_param` and
  `accept_language`; each can be removed or reordered) (#85)
- FQCN alias for the `Translator` service, so it can be autowired by type (#85)

### Changed
- Modern bundle layout: the bundle class extends `AbstractBundle` and services
  are defined in `config/services.php`; `src/Resources/config/services.yml` is
  gone (#85)
- A request without an `Accept-Language` header now falls through to the next
  configured locale source instead of being resolved by header negotiation;
  with the default resolution order the observable behavior is unchanged (#85)
- Dependencies: `symfony/config`, `symfony/http-kernel` and
  `symfony/deprecation-contracts` are direct requirements now that the bundle
  uses them directly; `symfony/yaml` is no longer required (#85)

### Deprecated
- `ApiPlatformTranslationExtension`, to be removed in 3.0; the bundle registers
  its extension itself through `AbstractBundle` (#85)

## [2.0.1] - 2026-07-07

### Fixed
- Translations referenced by IRI (translation exposed as its own `ApiResource`)
  are again resolved by API Platform's native denormalization; 2.0.0
  intercepted such payloads and silently dropped the references, removing every
  existing translation on `PUT` (#83)

## [2.0.0] - 2026-07-07

See [UPGRADE-2.0.md](UPGRADE-2.0.md) for upgrade instructions.

### Changed
- **BREAKING** Dependency floors: PHP `^8.2`, `api-platform/symfony ^3.4 || ^4.0`,
  `doctrine/orm ^3.0`, `doctrine/doctrine-bundle ^2.13 || ^3.0`; real version
  ranges for the symfony packages instead of wildcards (#76)
- **BREAKING** `AssignLocaleListener` signatures moved from `Doctrine\Common\EventArgs`
  to `Doctrine\Persistence\Event\LifecycleEventArgs` (#78)
- **BREAKING** `Translator` implements `LocaleAwareInterface`, requires a
  `TranslatorInterface&LocaleAwareInterface` wrapped translator, and declares
  parameter types on `trans()` and `setLocale()` (#78)
- When `framework.enabled_locales` is configured, requested locales outside the
  list (query parameter or `Accept-Language`) fall back to the default locale (#71)
- README rewritten with PHP attributes and current API Platform metadata (#77)
- **BREAKING** Translatable resources exposing `PUT` must set
  `extraProperties: ['standard_put' => false]` on the operation so nested
  translations are reconciled against the managed entity instead of a fresh
  object (#81)

### Added
- In-place, merge-by-locale denormalization of nested translation writes: each
  submitted locale updates its existing translation row (stable id), `PATCH`
  keeps locales absent from the payload and `PUT` removes them; a `PUT` without
  `standard_put` disabled is rejected with an actionable exception (#81)
- PHPStan level 6 and php-cs-fixer, enforced in CI (#78)
- CI matrix covering PHP 8.2 to 8.5 plus a lowest-dependencies leg (#76)
- Model edge-case tests and a kernel smoke test booting the bundle with
  Framework, Doctrine and API Platform (#80)
- Community files: CONTRIBUTING, SECURITY, issue and PR templates (#79)

## [1.4.1] - 2025-12-18

### Changed
- Support for API Platform 4 and Symfony 7.4 (#75). Note: this release also
  raised the PHP floor to 8.4 and dropped API Platform 2/3 support; 2.0 restores
  the wider ranges.

## [1.4] - 2024-05-10

### Added
- Doctrine ORM 3 support (#68)

Older releases are documented on the
[releases page](https://github.com/Locastic/ApiPlatformTranslationBundle/releases).

[Unreleased]: https://github.com/Locastic/ApiPlatformTranslationBundle/compare/v2.0.1...HEAD
[2.0.1]: https://github.com/Locastic/ApiPlatformTranslationBundle/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/Locastic/ApiPlatformTranslationBundle/compare/v1.4.1...v2.0.0
[1.4.1]: https://github.com/Locastic/ApiPlatformTranslationBundle/compare/v1.4...v1.4.1
[1.4]: https://github.com/Locastic/ApiPlatformTranslationBundle/compare/v1.3.7...v1.4
