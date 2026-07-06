# Changelog

All notable changes to this project are documented in this file. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

### Added
- PHPStan level 6 and php-cs-fixer, enforced in CI (#78)
- CI matrix covering PHP 8.2 to 8.5 plus a lowest-dependencies leg (#76)

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
