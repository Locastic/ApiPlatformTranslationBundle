<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Serializer;

use Locastic\ApiPlatformTranslationBundle\Model\TranslatableInterface;
use Locastic\ApiPlatformTranslationBundle\Model\TranslationInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizes the locale-keyed `translations` map of a translatable resource.
 *
 * Without this, API Platform denormalizes the map as plain objects with no identity,
 * so it recreates every row (ignoring `id`) and orphan-removes locales absent from the
 * payload. This denormalizer intercepts a translatable, handles the `translations` map
 * itself, and:
 *   - populates the EXISTING translation object for a locale (stable id, in-place update),
 *   - creates a translation only for genuinely new locales,
 *   - on PATCH, leaves locales absent from the payload untouched (partial edit),
 *   - on PUT, removes locales absent from the payload (full replace).
 *
 * PUT follows HTTP replace semantics, so translatable resources must expose it with
 * `extraProperties: ['standard_put' => false]` (or only offer PATCH). Otherwise API
 * Platform's standard_put builds a fresh object with no existing translations to
 * reconcile against, and its persist processor then copies the fresh collection over
 * the managed one, so the relation cannot be persisted. A misconfigured PUT fails
 * loudly here (see assertNotStandardPut()) instead of dying later in the persist layer.
 */
final class TranslatableItemDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'LOCASTIC_TRANSLATABLE_DENORMALIZER_CALLED';

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \is_array($data)
            && isset($data['translations'])
            && \is_array($data['translations'])
            && is_a($type, TranslatableInterface::class, true)
            && empty($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Only ever supports object denormalization, and the decision depends on the
        // payload (must contain `translations`), so it is never cacheable.
        return ['object' => false];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $this->assertNotStandardPut($type, $context);

        $translationsData = $data['translations'];
        unset($data['translations']);

        // Let API Platform build/populate the translatable itself (minus translations).
        $context[self::ALREADY_CALLED] = true;
        $translatable = $this->denormalizer->denormalize($data, $type, $format, $context);

        if (!$translatable instanceof TranslatableInterface) {
            return $translatable;
        }

        $submittedLocales = [];

        foreach ($translationsData as $key => $translationData) {
            if (!\is_array($translationData)) {
                continue;
            }

            // The map key is the authoritative locale; only fall back to the body
            // for list-shaped payloads (numeric keys).
            $locale = \is_string($key) ? $key : ($translationData['locale'] ?? null);
            if (null === $locale) {
                continue;
            }

            // A `locale` in the body must not override the resolved locale.
            unset($translationData['locale']);

            $submittedLocales[$locale] = true;

            $target = $this->matchTranslation($translatable, $locale);
            $isNew = null === $target;
            if ($isNew) {
                $target = $this->createTranslationFor($translatable);
                if (null === $target) {
                    continue;
                }
            }
            $target->setLocale($locale);

            $nestedContext = $context;
            $nestedContext[AbstractNormalizer::OBJECT_TO_POPULATE] = $target;
            unset($nestedContext[self::ALREADY_CALLED]);

            $translation = $this->denormalizer->denormalize(
                $translationData,
                $target::class,
                $format,
                $nestedContext,
            );

            if ($isNew && $translation instanceof TranslationInterface) {
                $translatable->addTranslation($translation);
            }
        }

        // PUT replaces the whole resource: drop locales absent from the payload.
        // PATCH is a partial edit and leaves them in place.
        if ($this->isFullReplace($context)) {
            foreach ($translatable->getTranslations()->toArray() as $translation) {
                if (!isset($submittedLocales[(string) $translation->getLocale()])) {
                    $translatable->removeTranslation($translation);
                }
            }
        }

        return $translatable;
    }

    /**
     * Matches an existing translation by locale.
     *
     * Iterates the collection instead of using `get($locale)` so it does not depend on
     * the association being mapped with `indexBy: 'locale'`; a plain OneToMany hydrates
     * as a 0-indexed list. This mirrors how the model trait resolves translations.
     *
     * @param TranslatableInterface<TranslationInterface> $translatable
     */
    private function matchTranslation(TranslatableInterface $translatable, string $locale): ?TranslationInterface
    {
        foreach ($translatable->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Instantiates a new translation via the translatable's own factory
     * (TranslatableTrait::createTranslation()), without widening its visibility.
     *
     * @param TranslatableInterface<TranslationInterface> $translatable
     */
    private function createTranslationFor(TranslatableInterface $translatable): ?TranslationInterface
    {
        if (!method_exists($translatable, 'createTranslation')) {
            return null;
        }

        $translation = (new \ReflectionMethod($translatable, 'createTranslation'))->invoke($translatable);

        return $translation instanceof TranslationInterface ? $translation : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isFullReplace(array $context): bool
    {
        $operation = $this->findOperation($context);

        return null !== $operation && 'PUT' === strtoupper((string) $operation->getMethod());
    }

    /**
     * Fails loudly on a misconfigured PUT instead of letting the write die later in
     * the persist layer with an obscure Doctrine error.
     *
     * With standard_put enabled, API Platform deserializes into a fresh object (no
     * OBJECT_TO_POPULATE) and its Doctrine PersistProcessor then reflection-copies
     * every property, including the translations collection, from that fresh object
     * onto the managed entity. Translations therefore cannot be reconciled by locale
     * at any layer; the operation must disable standard_put.
     *
     * @param array<string, mixed> $context
     */
    private function assertNotStandardPut(string $type, array $context): void
    {
        if (isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]) || !$this->isFullReplace($context)) {
            return;
        }

        $operation = $this->findOperation($context);
        if (null === $operation || !method_exists($operation, 'getExtraProperties')) {
            return;
        }

        $extraProperties = $operation->getExtraProperties();
        if (\is_array($extraProperties) && false !== ($extraProperties['standard_put'] ?? true)) {
            throw new \LogicException(sprintf('PUT operations on translatable resources require standard_put to be disabled, otherwise translations cannot be matched to their existing rows. Set extraProperties: [\'standard_put\' => false] on the PUT operation of "%s" (or globally via api_platform.defaults.extra_properties.standard_put: false), or expose PATCH instead.', $type));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function findOperation(array $context): ?object
    {
        foreach (['operation', 'root_operation'] as $key) {
            $operation = $context[$key] ?? null;
            if (\is_object($operation) && method_exists($operation, 'getMethod')) {
                return $operation;
            }
        }

        return null;
    }
}
