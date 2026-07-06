# Contributing

Thanks for considering a contribution. Bug reports, fixes, docs improvements,
and features are all welcome.

## Setup

```bash
git clone git@github.com:<your-fork>/ApiPlatformTranslationBundle.git
cd ApiPlatformTranslationBundle
composer install
```

PHP 8.2 or newer is required. Tools are installed to `bin/`.

## Quality gates

All four must pass before a pull request can be merged (CI enforces them):

```bash
bin/phpunit                  # test suite (pure unit tests, no services needed)
bin/phpstan analyse          # static analysis, level 6, zero errors
bin/php-cs-fixer fix         # code style (@Symfony + declare_strict_types)
composer validate --strict
```

New logic ships with tests. Tests use `testCamelCase` naming and data providers.

## Pull requests

- Target `master`. Bug fixes may be backported to the latest `1.x` release
  branch by the maintainers.
- One logical change per PR; don't mix refactoring with features.
- Fill in the PR template honestly, in particular the BC-breaks question:
  public method signatures, service IDs, the `translations` serializer group,
  the `translation.groups` filter id, and `?locale=` behavior are all public API.
  A BC break requires an entry in the current `UPGRADE-X.Y.md` in the same PR.
- Add a line to the Unreleased section of `CHANGELOG.md`.

## Bugs and questions

Open an issue using the templates. For security vulnerabilities do NOT open a
public issue; see [SECURITY.md](SECURITY.md).
