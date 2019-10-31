<h1 align="center">
Locastic Api Translation Bundle<br>
    <a href="https://packagist.org/packages/locastic/api-platform-translation-bundle" title="License" target="_blank">
        <img src="https://img.shields.io/packagist/l/locastic/api-platform-translation-bundle.svg" />
    </a>
    <a href="https://packagist.org/packages/locastic/api-platform-translation-bundle" title="Version" target="_blank">
        <img src="https://img.shields.io/packagist/v/Locastic/api-platform-translation-bundle.svg" />
    </a>
    <a href="https://travis-ci.org/Locastic/ApiPlatformTranslationBundle" title="Build status" target="_blank">
        <img src="https://img.shields.io/travis/Locastic/ApiPlatformTranslationBundle/master.svg" />
    </a>
    <a href="https://scrutinizer-ci.com/g/Locastic/ApiPlatformTranslationBundle/" title="Scrutinizer" target="_blank">
        <img src="https://img.shields.io/scrutinizer/g/Locastic/ApiPlatformTranslationBundle.svg" />
    </a>
    <a href="https://packagist.org/packages/locastic/api-platform-translation-bundle" title="Total Downloads" target="_blank">
        <img src="https://poser.pugx.org/locastic/api-platform-translation-bundle/downloads" />
    </a>
</h1>

Translation bundle for [ApiPlatform](https://api-platform.com/) based on [Sylius translation](https://docs.sylius.com/en/1.2/book/architecture/translations.html)

Installation:
-------------
```
$ composer require locastic/api-platform-translation-bundle
```

Implementation:
--------------
**Translatable entity:**

- Extend your model/resource with `Locastic\ApiTranslationBundle\Model\AbstractTranslatable`
- Add createTranslation method which returns new object of translation Entity. Example:
``` php
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;

class Post extends AbstractTranslatable
{
    // ...
    
    protected function createTranslation()
    {
        return new PostTranslation();
    }
}
```

- Add `translations` serialization group to translations relation:
``` php
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;

class Post extends AbstractTranslatable
{
    // ...
    
    /**
     * @Groups({"post_write", "translations"})
     */
    protected $translations;
}
```

- Add virtual fields for all translatable fields, and add read serialization group. 
Getters and setters must call getters and setters from translation class. Example:
``` php
use Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslatable;
use Symfony\Component\Serializer\Annotation\Groups;

class Post extends AbstractTranslatable
{
    // ...
    
    /**
    * @var string
    *
    * @Groups({"post_read"})
    */
    private $title;
    
    public function setTitle($title)
    {
        $this->getTranslation()->setTitle($title);

        return $this;
    }

    public function getTitle()
    {
        return $this->getTranslation()->getTitle();
    }
}
```


**Translation entity:**
- Add entity with all translatable fields. Name needs to be name of translatable entity + Translation
- Extend `Locastic\ApiPlatformTranslationBundle\Model\AbstractTranslation`
- Add serialization group `translations` to all fields and other read/write groups.
Example Translation entity:
``` php
use Symfony\Component\Serializer\Annotation\Groups;
use Locastic\ApiTranslationBundle\Model\AbstractTranslation;

class PostTranslation extends AbstractTranslation
{
    // ...
    
    /**
     * @var string
     *
     * @Groups({"post_read", "post_write", "translations"})
     */
    private $title;
    
    /**
     * @var string
     * @Groups({"post_write", "translations"})
     */
    protected $locale;

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }
}
```


**Api resource**
- Add `translation.groups` filter if you would like to have option to return all translation objects in response.
If you don't use `translations` group, response will return only requested locale translation or fallback locale translation.
- Add translations to normalization_context for PUT and POST methods to make sure 
they return all translation objects.
- Example:
``` yaml
AppBundle\Entity\Post:
    itemOperations:
        get:
            method: GET
        put:
            method: PUT
            normalization_context:
                groups: ['translations']
    collectionOperations:
        get:
            method: GET
        post:
            method: POST
            normalization_context:
                groups: ['translations']
    attributes:
        filters: ['translation.groups']
        normalization_context:
            groups: ['post_read']
        denormalization_context:
            groups: ['post_write']
```

Usage:
------

**Language param for displaying single translation:** 

`?locale=de`

**Or use Accept-Language http header**

`Accept-Language: de`

**Serialization group for displaying all translations:** 

`?groups[]=translations`

**POST translations example**
``` json
{
    "datetime":"2017-10-10",
    "translations": { 
        "en":{
            "title":"test",
            "content":"test",
            "locale":"en"
        },
        "de":{
            "title":"test de",
            "content":"test de",
            "locale":"de"
        }
    }
}
```

**EDIT translations example**
``` json
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

## Contribution

If you have idea on how to improve this bundle, feel free to contribute. If you have problems or you found some bugs, please open an issue.

## Support

Want us to help you with this bundle or any Api Platfrom/Symfony project? Write us an email on info@locastic.com
