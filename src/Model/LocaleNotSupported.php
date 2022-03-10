<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

final class LocaleNotSupported extends \RuntimeException
{
    public static function unsupported(): self
    {
        return new self('No locale was received and the current locale is not supported for translations.');
    }
}
