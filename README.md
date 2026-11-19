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

    # Whether queries for translatable resources fetch-join the translations,
    # so listing N resources issues one query instead of one per entity per
    # locale. Set false to restore lazy loading.
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
  the current-locale translation, which both filters use.
- An index on `locale` plus the field serves the `exact` and `start`
  strategies.
- `partial`, `end` and `word_start` start with a wildcard and the `i` variants
  wrap the column in `LOWER()`, so a plain index cannot serve them; use what
  your database offers for text search (on PostgreSQL, a `pg_trgm` index on
  `LOWER(title)`).
- Ordering by a translated field joins the current-locale translation of
  every resource in the filtered collection and sorts by it before the page is
  cut; the cost grows with the collection, not with the page. With
  `eager_load_translations` enabled the query also joins the translations of
  all locales, so the cost grows with the number of locales too. Measure it
  on realistic data before you expose it on a very large, unfiltered
  collection.

For service-based declaration (YAML/XML instead of the attribute), define a
child service of the abstract parent
`locastic_api_platform_translation.doctrine.orm.filter.translation_search` or
`locastic_api_platform_translation.doctrine.orm.filter.translation_order`,
pass `$properties`, and tag it `api_platform.filter`.

Using DTO resources:
--------------------

Everything above exposes the Doctrine entity as the API resource and shapes it
with serialization groups. API Platform's documentation recommends the
[DTO as a resource](https://api-platform.com/docs/core/dto/) pattern: a plain
class carries `#[ApiResource]` and the state layer maps between it and the
entity. The bundle works in that setup too:

- **Reads need nothing extra.** The bundle sets the current and fallback
  locale on every translatable entity when Doctrine loads it, so the
  translated getters return the right locale when you map the entity to the
  DTO. `?locale=`, `Accept-Language` and the fallback locale behave as
  described under [Usage](#usage).
- **Writes need your own matching by locale.** The bundle matches submitted
  translations to their existing rows only when the resource is the entity
  itself. With a DTO your code does it, as in the two examples below, so that
  existing rows are updated in place and keep their ids.
- `eager_load_translations` and the two translation filters work unchanged.
  Declare the filters with `#[ApiFilter]` on the DTO class.
- The `translation.groups` filter and the `translations` serialization group
  do not apply. Expose the translations as a property of the DTO instead.

The entity stays as shown under [Implementation](#implementation), without the
`#[ApiResource]` attribute and the serialization groups. Both examples use the
same DTO for one translation:

```php
// src/ApiResource/ArticleTranslationDto.php
namespace App\ApiResource;

final class ArticleTranslationDto
{
    public ?string $title = null;
    public ?string $locale = null;
}
```

The examples type the `translations` property of the resource as
`array<string, ArticleTranslationDto>`. The serializer reads that type from
the docblock, and that needs two packages. Without them the entries reach
your code as plain arrays:

```bash
composer require phpstan/phpdoc-parser phpdocumentor/type-resolver
```

### With a state provider and processor

This variant works on every supported API Platform version. The provider and
the processor delegate fetching, pagination, filters and persistence to API
Platform's own Doctrine services, which read `stateOptions` and work on the
entity:

```php
// src/ApiResource/ArticleResource.php
namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Article;
use App\State\ArticleResourceProcessor;
use App\State\ArticleResourceProvider;

#[ApiResource(
    shortName: 'Article',
    // The entity behind this resource, for API Platform's Doctrine services.
    stateOptions: new Options(entityClass: Article::class),
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
        // PUT needs standard_put off, see the note below.
        new Put(extraProperties: ['standard_put' => false]),
        new Delete(),
    ],
    provider: ArticleResourceProvider::class,
    processor: ArticleResourceProcessor::class,
)]
final class ArticleResource
{
    public ?int $id = null;

    /**
     * Translated value in the requested (or fallback) locale. The processor
     * does not write it: writes go through `translations`.
     */
    public ?string $title = null;

    /**
     * All translations, keyed by locale.
     *
     * @var array<string, ArticleTranslationDto>
     */
    public array $translations = [];

    public static function fromEntity(Article $article): self
    {
        $resource = new self();
        $resource->id = $article->getId();
        $resource->title = $article->getTitle();

        foreach ($article->getTranslations() as $translation) {
            $dto = new ArticleTranslationDto();
            $dto->title = $translation->getTitle();
            $dto->locale = $translation->getLocale();
            $resource->translations[$dto->locale] = $dto;
        }

        return $resource;
    }
}
```

```php
// src/State/ArticleResourceProvider.php
namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ArticleResource;
use App\Entity\Article;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ArticleResourceProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $articles = $this->collectionProvider->provide($operation, $uriVariables, $context);

            $resources = [];
            foreach ($articles as $article) {
                $resources[] = ArticleResource::fromEntity($article);
            }

            if ($articles instanceof PaginatorInterface) {
                return new TraversablePaginator(
                    new \ArrayIterator($resources),
                    $articles->getCurrentPage(),
                    $articles->getItemsPerPage(),
                    $articles->getTotalItems(),
                );
            }

            return $resources;
        }

        $article = $this->itemProvider->provide($operation, $uriVariables, $context);

        return $article instanceof Article ? ArticleResource::fromEntity($article) : null;
    }
}
```

The processor applies the submitted translations to the managed entity.
`getOrCreateTranslation($locale)` returns the existing translation of exactly
that locale, or creates and attaches a new one:

```php
// src/State/ArticleResourceProcessor.php
namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ArticleResource;
use App\Entity\Article;
use App\Entity\ArticleTranslation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ArticleResourceProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ArticleResource
    {
        if ($operation instanceof DeleteOperationInterface) {
            $article = $this->itemProvider->provide($operation, $uriVariables, $context);
            $this->removeProcessor->process($article, $operation, $uriVariables, $context);

            return null;
        }

        \assert($data instanceof ArticleResource);

        // A new entity for POST, the managed one for PATCH and PUT.
        $article = [] === $uriVariables
            ? new Article()
            : $this->itemProvider->provide($operation, $uriVariables, $context);

        foreach ($data->translations as $locale => $translationDto) {
            // Exact locale, no fallback: the existing row, or a new one.
            $translation = $article->getOrCreateTranslation($locale);
            \assert($translation instanceof ArticleTranslation);

            if (null !== $translationDto->title) {
                $translation->setTitle($translationDto->title);
            }
        }

        // PUT is a full replace: remove the locales absent from the payload.
        if ('PUT' === $operation->getMethod()) {
            foreach ($article->getTranslationLocales() as $existingLocale) {
                if (!isset($data->translations[$existingLocale])) {
                    $article->removeTranslationWithLocale($existingLocale);
                }
            }
        }

        $article = $this->persistProcessor->process($article, $operation, $uriVariables, $context);

        return ArticleResource::fromEntity($article);
    }
}
```

`POST` and `PATCH` take the payloads shown under [Usage](#usage): a
`translations` object keyed by locale. `PATCH` updates the submitted locales
and leaves the others untouched.

`PUT` needs `standard_put` disabled in this setup too. With `standard_put`
on, API Platform's persist processor expects an entity where this setup has
the DTO, and the request fails with "Given object is not an instance of the
class this property was declared in".

### With the Symfony Object Mapper

On API Platform 4.2 or later, the
[Symfony Object Mapper](https://symfony.com/doc/current/object_mapper.html)
(`symfony/object-mapper` 7.4 or later) can map between the DTO and the entity.
This variant has no provider and no processor:

```bash
composer require symfony/object-mapper
```

On an update, API Platform maps the submitted DTO onto the managed entity and
passes that entity to the transformer as `$target`, so one transformer service
can match the translations by locale:

```php
// src/ApiResource/ArticleResource.php
namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Article;
use App\State\ArticleTranslationsTransformer;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[ApiResource(
    shortName: 'Article',
    stateOptions: new Options(entityClass: Article::class),
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
        new Delete(),
    ],
)]
#[Map(source: Article::class)]
final class ArticleResource
{
    public ?int $id = null;

    /**
     * Translated value in the requested (or fallback) locale. Mapped from the
     * entity only: mapped back, it would go through the entity's virtual
     * setter and write into a single locale. Writes go through `translations`.
     */
    #[Map(if: [self::class, 'onlyFromEntity'])]
    public ?string $title = null;

    /**
     * All translations, keyed by locale.
     *
     * @var array<string, ArticleTranslationDto>
     */
    #[Map(source: 'translations', transform: ArticleTranslationsTransformer::class)]
    public array $translations = [];

    public static function onlyFromEntity(mixed $value, object $source): bool
    {
        return $source instanceof Article;
    }
}
```

```php
// src/State/ArticleTranslationsTransformer.php
namespace App\State;

use App\ApiResource\ArticleResource;
use App\ApiResource\ArticleTranslationDto;
use App\Entity\Article;
use App\Entity\ArticleTranslation;
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * Maps the `translations` property in both directions:
 *  - entity to resource: translation entities to DTOs keyed by locale;
 *  - resource to entity: matches the submitted locales to the existing
 *    translations of the entity (update in place, create only new locales).
 */
final class ArticleTranslationsTransformer implements TransformCallableInterface
{
    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if ($source instanceof Article) {
            return $this->toDtos($source);
        }

        \assert($source instanceof ArticleResource);

        return $this->toTranslations($value, $target);
    }

    /**
     * @return array<string, ArticleTranslationDto>
     */
    private function toDtos(Article $article): array
    {
        $dtos = [];

        foreach ($article->getTranslations() as $translation) {
            $dto = new ArticleTranslationDto();
            $dto->title = $translation->getTitle();
            $dto->locale = $translation->getLocale();
            $dtos[$dto->locale] = $dto;
        }

        return $dtos;
    }

    /**
     * Returns the complete set (existing plus submitted). The returned list
     * is applied to the entity through addTranslation()/removeTranslation(),
     * so a list with only the submitted locales would remove the others.
     *
     * @param array<string, ArticleTranslationDto> $value
     *
     * @return list<ArticleTranslation>
     */
    private function toTranslations(array $value, ?object $target): array
    {
        $existing = [];

        // On POST the target is a new entity that was created without its
        // constructor, so the translations collection is not initialized yet.
        if ($target instanceof Article && $this->isInitialized($target)) {
            foreach ($target->getTranslations() as $translation) {
                $existing[(string) $translation->getLocale()] = $translation;
            }
        }

        foreach ($value as $locale => $dto) {
            $translation = $existing[$locale] ?? null;

            if (null === $translation) {
                $translation = new ArticleTranslation();
                $translation->setLocale($locale);
                $existing[$locale] = $translation;
            }

            if (null !== $dto->title) {
                $translation->setTitle($dto->title);
            }
        }

        return array_values($existing);
    }

    private function isInitialized(Article $article): bool
    {
        return (new \ReflectionProperty(AbstractTranslatable::class, 'translations'))
            ->isInitialized($article);
    }
}
```

`GET`, `POST`, `PATCH` and `DELETE` work with no further code. This variant
has no `PUT`: with `standard_put` on, the request fails as described above,
and with it off this transformer merges like `PATCH` and keeps the locales
that are absent from the payload. Use `PATCH`, or the provider and processor
variant when you need a full replace.

## Contribution

If you have an idea on how to improve this bundle, feel free to contribute. If you have problems or you found some bugs, please open an issue.

## Support

Want us to help you with this bundle or any API Platform/Symfony project? Write us an email on info@locastic.com
