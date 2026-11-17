<h1 align="center">
Locastic Api Translation Bundle<br>
    <a href="https://packagist.org/packages/locastic/api-platform-translation-bundle" title="License" target="_blank">
        <img src="https://img.shields.io/packagist/l/locastic/api-platform-translation-bundle.svg" />
    </a>
    <a href="https://packagist.org/packages/locastic/api-platform-translation-bundle" title="Version" target="_blank">
        <img src="https://img.shields.io/packagist/v/Locastic/api-platform-translation-bundle.svg" />
    </a>
    <a href="https://github.com/Locastic/ApiPlatformTranslationBundle/actions/workflows/phpunit.yml" title="Build status" target="_blank">
        <img src="https://github.com/Locastic/ApiPlatformTranslationBundle/actions/workflows/phpunit.yml/badge.svg" />
    </a>
    <a href="https://packagist.org/packages/locastic/api-platform-translation-bundle" title="Total Downloads" target="_blank">
        <img src="https://poser.pugx.org/locastic/api-platform-translation-bundle/downloads" />
    </a>
</h1>

Translation bundle for [API Platform](https://api-platform.com/) based on [Sylius translation](https://docs.sylius.com/en/1.2/book/architecture/translations.html): translations are stored per locale in a dedicated translation entity and exposed through your API as embedded objects, with the active locale resolved from each request.

Supported versions:
-------------------

| Version              | PHP    | API Platform               | Doctrine ORM |
|----------------------|--------|----------------------------|--------------|
| 2.x (`master`)       | `^8.2` | `^3.4 \|\| ^4.0 \|\| ^5.0` | `^3.0`       |
| 1.4                  | `^8.1` | `^2.1 \|\| ^3.0`           | `^3.0`       |

Installation:
-------------
```bash
composer require locastic/api-platform-translation-bundle
```

Configuration:
--------------
The bundle works without any configuration. All options and their defaults:

```yaml
# config/packages/api_platform_translation.yaml
api_platform_translation:
    # Locales accepted from the ?locale= query parameter and Accept-Language
    # negotiation. Empty (the default) inherits framework.enabled_locales;
    # when both are empty, any requested locale is accepted.
    enabled_locales: []

    # Locale used when a translation for the current locale does not exist.
    # null (the default) inherits framework.default_locale.
    fallback_locale: null

    # true (the legacy behavior): reading a translation that does not exist
    # creates an empty one and attaches it to the entity, and with the
    # documented cascade persist mapping a plain read can then insert empty
    # rows into the database. false: reads never write, a missing translation
    # just shows empty fields. Setters work either way, they write through
    # getOrCreateTranslation() (see below). The default flips to false in
    # 3.0; leaving the option unset is deprecated since 2.1.
    auto_create_translations: true

    # Whether the translations of translatable resources are loaded up front,
    # so listing N resources does not issue one query per entity per locale:
    # an item query fetch-joins them, a collection loads the translations of
    # its page with one extra query. Set false to restore lazy loading.
    eager_load_translations: true

    # Ordered sources the request locale is resolved from; the first source
    # producing a locale wins. Remove a source to disable it.
    locale_resolution:
        - query_param
        - accept_language
```

For example, to resolve the locale from the `Accept-Language` header only and
ignore the `?locale=` query parameter:

```yaml
api_platform_translation:
    locale_resolution: [accept_language]
```

Implementation:
--------------
**Translatable entity:**

- Extend your resource with `Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable`
- Add a `createTranslation()` method which returns a new object of the translation entity
- Add a `translations` property: a `OneToMany` to the translation entity, indexed by locale, with the `translations` serialization group
- Add virtual fields for all translatable fields; getters delegate to `getTranslation()` (current locale, with fallback), setters to `getOrCreateTranslation()` (exact locale, created and attached when missing)

Example:
```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(normalizationContext: ['groups' => ['translations']]),
        new Patch(normalizationContext: ['groups' => ['translations']]),
        // PUT replaces the resource; standard_put must be off so it edits the
        // managed entity instead of building a new one (see the notes below).
        new Put(
            normalizationContext: ['groups' => ['translations']],
            extraProperties: ['standard_put' => false],
        ),
    ],
    normalizationContext: ['groups' => ['article_read']],
    denormalizationContext: ['groups' => ['article_write']],
    filters: ['translation.groups'],
)]
class Article extends AbstractTranslatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(
        targetEntity: ArticleTranslation::class,
        mappedBy: 'translatable',
        fetch: 'EXTRA_LAZY',
        indexBy: 'locale',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[Groups(['article_write', 'translations'])]
    protected Collection $translations;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(['article_read'])]
    public function getTitle(): ?string
    {
        return $this->getTranslation()->getTitle();
    }

    public function setTitle(string $title): void
    {
        $this->getOrCreateTranslation()->setTitle($title);
    }

    protected function createTranslation(): TranslationInterface
    {
        return new ArticleTranslation();
    }
}
```

**Translation entity:**
- Add an entity with all translatable fields. The convention is the name of the translatable entity + `Translation`
- Extend `Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslation`
- Add the `translations` serialization group to all fields, plus your usual read/write groups

Example:
```php
use Doctrine\ORM\Mapping as ORM;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslation;
use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class ArticleTranslation extends AbstractTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['translations'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'translations')]
    protected ?TranslatableInterface $translatable = null;

    #[ORM\Column]
    #[Groups(['article_read', 'article_write', 'translations'])]
    private ?string $title = null;

    #[ORM\Column]
    #[Groups(['article_write', 'translations'])]
    protected ?string $locale = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
```

**API resource notes:**
- The `translation.groups` filter (registered by this bundle) lets clients request all translation objects in a response via `?groups[]=translations`. Without the `translations` group, responses contain only the requested (or fallback) locale.
- Add the `translations` group to the `normalizationContext` of `POST` and `PUT`/`PATCH` operations, as in the example above, so write operations return all translation objects.

**Editing translations (`PUT` vs `PATCH`):** the bundle populates the submitted translations onto the managed entity, keeping existing translation rows (and their ids) stable, following the HTTP semantics of each method:
- `PATCH` (`application/merge-patch+json`) is a partial edit: it updates the submitted locales and leaves the others untouched. This is the recommended way to edit translations and needs no extra configuration.
- `PUT` is a full replace: locales absent from the payload are removed.

For `PUT` you **must** disable API Platform's `standard_put`, either per operation (`extraProperties: ['standard_put' => false]`, as above) or once for the whole API:

```yaml
# config/packages/api_platform.yaml
api_platform:
    defaults:
        extra_properties:
            standard_put: false
```

With `standard_put` on, API Platform deserializes into a brand-new object and copies its properties (including the `translations` collection) over the managed entity, so translations cannot be matched to their existing rows. The bundle detects this misconfiguration and fails with an explicit error instead of letting the write die in the persistence layer.

Usage:
------

### Request a single locale

Pass the locale as a query parameter:

```
GET /articles/1?locale=de
```

Or use the `Accept-Language` HTTP header:

```
GET /articles/1
Accept-Language: de
```

Translatable fields are returned in the requested locale; when no translation
exists for it, the fallback locale is used.

**Restricting locales:** if [`framework.enabled_locales`](https://symfony.com/doc/current/reference/configuration/framework.html#enabled-locales) or the bundle's own `enabled_locales` option (which takes precedence) is configured, only those locales are accepted: a `?locale=` value outside the list and non-matching `Accept-Language` headers fall back to the default locale. When neither is configured (Symfony's default), any requested locale is accepted.

### Return all translations in a response

Add the `translations` serialization group through the `translation.groups`
filter (registered by this bundle, enabled on the resource via
`filters: ['translation.groups']`):

```
GET /articles/1?groups[]=translations
```

The group is added on top of the operation's normalization groups, so the
response contains the single-locale virtual fields plus the full collection:

```json
{
    "@id": "/articles/1",
    "title": "test",
    "translations": {
        "en": {
            "id": 2,
            "title": "test",
            "content": "test",
            "locale": "en"
        },
        "de": {
            "id": 3,
            "title": "test de",
            "content": "test de",
            "locale": "de"
        }
    }
}
```

### Create a resource with translations (POST)

Submit `translations` as an object keyed by locale; each entry must repeat its
`locale` field:

```json
{
    "datetime": "2017-10-10",
    "translations": {
        "en": {
            "title": "test",
            "content": "test",
            "locale": "en"
        },
        "de": {
            "title": "test de",
            "content": "test de",
            "locale": "de"
        }
    }
}
```

### Update translations (PATCH, recommended)

A merge patch updates only the submitted locales and leaves the others
untouched; existing translation rows are updated in place, no `id` needed:

```
PATCH /articles/1
Content-Type: application/merge-patch+json
```

```json
{
    "translations": {
        "de": {
            "title": "test edit de",
            "locale": "de"
        }
    }
}
```

Here the `de` title is updated while the `en` translation is left as is.

### Replace all translations (PUT)

`PUT` is a full replace: locales absent from the payload are removed. It
requires `standard_put` to be disabled (see the editing notes under
[Implementation](#implementation) above). Send the `id` of each
existing translation so it is updated instead of replaced:

```json
{
    "datetime": "2017-10-10T00:00:00+02:00",
    "translations": {
        "de": {
          "id": 3,
          "title": "test edit de",
          "content": "test edit de",
          "locale": "de"
        },
        "en": {
          "id": 2,
          "title": "test edit",
          "content": "test edit",
          "locale": "en"
        }
    }
}
```

### Filter and order by translated fields

Translated values live on the translation entity, so built-in API Platform
filters cannot target them on the resource (a core `SearchFilter` on
`translations.title` works, but matches against **every** locale). The bundle
ships two experimental Doctrine ORM filters that are constrained to the
locale resolved from the request. Declare the translation entity's fields by
their bare name (`title`, not `translations.title`):

```php
use ApiPlatform\Metadata\ApiFilter;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationOrderFilter;
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Filter\TranslationSearchFilter;

#[ApiResource]
#[ApiFilter(TranslationSearchFilter::class, properties: ['title' => 'ipartial'])]
#[ApiFilter(TranslationOrderFilter::class, properties: ['title'])]
class Article extends AbstractTranslatable
{
    // ...
}
```

```
GET /articles?locale=de&title=katze           searches the de title only
GET /articles?locale=de&order[title]=asc      orders by the de title
```

- `TranslationSearchFilter` supports the same strategies as the core
  `SearchFilter`: `exact` (the default), `partial`, `start`, `end` and
  `word_start`, each optionally prefixed with `i` for case-insensitive
  matching. Multiple values for an `exact` property
  (`?title[]=foo&title[]=bar`) become an `IN` clause. The `LIKE` wildcards
  `%` and `_` in a searched value are matched literally.
- `TranslationOrderFilter` accepts `?order[title]=asc|desc`. A direction
  configured in `properties` (e.g. `['title' => 'DESC']`) is the default
  applied when the query parameter gives none; the parameter name is
  customizable with `arguments: ['orderParameterName' => ...]`. Resources
  without a translation in the current locale stay in the collection, ordered
  by the database's null ordering.
- Omitting `properties` enables every field of the translation entity (except
  its id and `locale`).
- Both filters match the resolved locale only: fallback-locale values are not
  considered, so a resource whose `de` translation is missing does not match a
  `de` search by its fallback value.

Both filters rely on a resource having one translation per locale, as the
`indexBy: 'locale'` mapping does. On large collections, declare that as a
unique constraint and index what you filter by on the translation entity:

```php
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['translatable_id', 'locale'])]
#[ORM\Index(columns: ['locale', 'title'])]
class ArticleTranslation extends AbstractTranslation
{
    // ...
}
```

- The unique constraint on the translatable and `locale` serves the lookup of
  the current-locale translation, which both filters and the count query of
  an ordered collection use.
- An index on `locale` plus the field serves the `exact` and `start`
  strategies.
- `partial`, `end` and `word_start` start with a wildcard and the `i` variants
  wrap the column in `LOWER()`, so a plain index cannot serve them; use what
  your database offers for text search (on PostgreSQL, a `pg_trgm` index on
  `LOWER(title)`).
- Ordering by a translated field joins the current-locale translation of
  every resource in the filtered collection and sorts by it before the page is
  cut; the cost grows with the collection, not with the page. Measure it on
  realistic data before you expose it on a very large, unfiltered collection.

A collection issues the same number of queries whatever the page size: the
count, the page, and one query for the translations of that page. The bundle
does this by decorating API Platform's Doctrine ORM collection provider. A
custom provider that builds its result some other way can load the
translations of its entities with the same single query:

```php
use Locastic\ApiPlatformTranslationBundle\Doctrine\Orm\Loader\TranslationsLoaderInterface;

public function __construct(private TranslationsLoaderInterface $translationsLoader)
{
}

// ...
$this->translationsLoader->load($articles);
```

For service-based declaration (YAML/XML instead of the attribute), define a
child service of the abstract parent
`locastic_api_platform_translation.doctrine.orm.filter.translation_search` or
`locastic_api_platform_translation.doctrine.orm.filter.translation_order`,
pass `$properties`, and tag it `api_platform.filter`.

## Contribution

If you have an idea on how to improve this bundle, feel free to contribute. If you have problems or you found some bugs, please open an issue.

## Support

Want us to help you with this bundle or any API Platform/Symfony project? Write us an email on info@locastic.com
