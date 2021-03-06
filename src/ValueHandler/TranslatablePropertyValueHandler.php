<?php

declare(strict_types=1);

namespace Webgriffe\SyliusAkeneoPlugin\ValueHandler;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Webgriffe\SyliusAkeneoPlugin\ValueHandlerInterface;
use Webmozart\Assert\Assert;

final class TranslatablePropertyValueHandler implements ValueHandlerInterface
{
    /** @var PropertyAccessorInterface */
    private $propertyAccessor;

    /** @var FactoryInterface */
    private $productTranslationFactory;

    /** @var FactoryInterface */
    private $productVariantTranslationFactory;

    /** @var TranslationLocaleProviderInterface */
    private $localeProvider;

    /** @var string */
    private $akeneoAttributeCode;

    /** @var string */
    private $translationPropertyPath;

    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        FactoryInterface $productTranslationFactory,
        FactoryInterface $productVariantTranslationFactory,
        TranslationLocaleProviderInterface $localeProvider,
        string $akeneoAttributeCode,
        string $translationPropertyPath
    ) {
        $this->propertyAccessor = $propertyAccessor;
        $this->productTranslationFactory = $productTranslationFactory;
        $this->productVariantTranslationFactory = $productVariantTranslationFactory;
        $this->localeProvider = $localeProvider;
        $this->akeneoAttributeCode = $akeneoAttributeCode;
        $this->translationPropertyPath = $translationPropertyPath;
    }

    /**
     * @param mixed $subject
     */
    public function supports($subject, string $attribute, array $value): bool
    {
        return $subject instanceof ProductVariantInterface && $attribute === $this->akeneoAttributeCode;
    }

    /**
     * @param mixed $subject
     */
    public function handle($subject, string $attribute, array $value): void
    {
        if (!$subject instanceof ProductVariantInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'This translatable property value handler only support instances of %s, %s given.',
                    ProductVariantInterface::class,
                    is_object($subject) ? get_class($subject) : gettype($subject)
                )
            );
        }
        foreach ($value as $item) {
            $localeCode = $item['locale'];
            if (!$localeCode) {
                $this->setValueOnAllTranslations($subject, $item);

                continue;
            }

            if (!in_array($localeCode, $this->localeProvider->getDefinedLocalesCodes())) {
                continue;
            }

            $variantTranslation = $this->getOrCreateNewProductVariantTranslation($subject, $localeCode);
            $this->setValueOnProductVariantAndProductTranslation($variantTranslation, $item['data']);
        }
    }

    private function setValueOnAllTranslations(ProductVariantInterface $subject, array $value): void
    {
        foreach ($this->localeProvider->getDefinedLocalesCodes() as $localeCode) {
            $variantTranslation = $this->getOrCreateNewProductVariantTranslation($subject, $localeCode);
            $this->setValueOnProductVariantAndProductTranslation($variantTranslation, $value['data']);
        }
    }

    /**
     * @param mixed $value
     */
    private function setValueOnProductVariantAndProductTranslation(
        ProductVariantTranslationInterface $variantTranslation,
        $value
    ): void {
        $hasBeenSet = false;

        $variant = $variantTranslation->getTranslatable();
        Assert::isInstanceOf($variant, ProductVariantInterface::class);
        if ($this->propertyAccessor->isWritable($variantTranslation, $this->translationPropertyPath)) {
            $this->propertyAccessor->setValue(
                $variantTranslation,
                $this->translationPropertyPath,
                $value
            );
            $hasBeenSet = true;
        }

        $product = $variant->getProduct();
        Assert::isInstanceOf($product, ProductInterface::class);
        $productTranslation = $this->getOrCreateNewProductTranslation($product, $variantTranslation->getLocale());
        if ($this->propertyAccessor->isWritable($productTranslation, $this->translationPropertyPath)) {
            $this->propertyAccessor->setValue(
                $productTranslation,
                $this->translationPropertyPath,
                $value
            );
            $hasBeenSet = true;
        }
        if (!$hasBeenSet) {
            throw new \RuntimeException(
                sprintf(
                    'Property path "%s" is not writable on both %s and %s but it should be for at least once.',
                    $this->translationPropertyPath,
                    ProductVariantTranslationInterface::class,
                    ProductTranslationInterface::class
                )
            );
        }
    }

    private function getOrCreateNewProductTranslation(
        ProductInterface $subject,
        string $localeCode
    ): ProductTranslationInterface {
        $translation = $subject->getTranslation($localeCode);
        if ($translation->getLocale() !== $localeCode) {
            $translation = $this->productTranslationFactory->createNew();
            Assert::isInstanceOf($translation, ProductTranslationInterface::class);
            /** @var ProductTranslationInterface $translation */
            $translation->setLocale($localeCode);
            $subject->addTranslation($translation);
        }

        return $translation;
    }

    private function getOrCreateNewProductVariantTranslation(
        ProductVariantInterface $subject,
        string $localeCode
    ): ProductVariantTranslationInterface {
        $translation = $subject->getTranslation($localeCode);
        if ($translation->getLocale() !== $localeCode) {
            $translation = $this->productVariantTranslationFactory->createNew();
            Assert::isInstanceOf($translation, ProductVariantTranslationInterface::class);
            /** @var ProductVariantTranslationInterface $translation */
            $translation->setLocale($localeCode);
            $subject->addTranslation($translation);
        }

        return $translation;
    }
}
