<?php

declare(strict_types=1);

namespace Webgriffe\SyliusAkeneoPlugin\ValueHandler;

use Sylius\Component\Attribute\AttributeType\CheckboxAttributeType;
use Sylius\Component\Attribute\AttributeType\IntegerAttributeType;
use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Attribute\AttributeType\TextareaAttributeType;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Webgriffe\SyliusAkeneoPlugin\Converter\ValueConverter;
use Webgriffe\SyliusAkeneoPlugin\Converter\ValueConverterInterface;
use Webgriffe\SyliusAkeneoPlugin\ValueHandlerInterface;
use Webmozart\Assert\Assert;

final class AttributeValueHandler implements ValueHandlerInterface
{
    /** @var RepositoryInterface */
    private $attributeRepository;

    /** @var FactoryInterface */
    private $factory;

    /** @var TranslationLocaleProviderInterface */
    private $localeProvider;

    /** @var ValueConverterInterface */
    private $valueConverter;

    public function __construct(
        RepositoryInterface $attributeRepository,
        FactoryInterface $factory,
        TranslationLocaleProviderInterface $localeProvider,
        ValueConverterInterface $valueConverter = null
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->factory = $factory;
        $this->localeProvider = $localeProvider;
        if ($valueConverter === null) {
            trigger_deprecation(
                'webgriffe/sylius-akeneo-plugin',
                '1.5',
                'Not passing a value converter to "%s" is deprecated and will be removed in %s.',
                __CLASS__,
                '2.0'
            );
            $valueConverter = new ValueConverter();
        }
        $this->valueConverter = $valueConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($subject, string $attributeCode, array $value): bool
    {
        if (!$subject instanceof ProductVariantInterface) {
            return false;
        }

        if ($this->isProductOption($subject, $attributeCode)) {
            return false;
        }

        $attribute = $this->attributeRepository->findOneBy(['code' => $attributeCode]);

        return $attribute && $this->hasSupportedType($attribute);
    }

    /**
     * {@inheritdoc}
     */
    public function handle($subject, string $attributeCode, array $value): void
    {
        if (!$subject instanceof ProductVariantInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'This attribute value handler only supports instances of %s, %s given.',
                    ProductVariantInterface::class,
                    is_object($subject) ? get_class($subject) : gettype($subject)
                )
            );
        }

        $attribute = $this->attributeRepository->findOneBy(['code' => $attributeCode]);

        if ($attribute === null) {
            throw new \InvalidArgumentException(
                sprintf(
                    'This attribute value handler only supports existing attributes. ' .
                    'Attribute with the given %s code does not exist.',
                    $attributeCode
                )
            );
        }

        $product = $subject->getProduct();
        Assert::isInstanceOf($product, ProductInterface::class);
        foreach ($value as $valueData) {
            if ($valueData['locale']) {
                $this->addAttributeValue($attribute, $valueData['data'], $valueData['locale'], $product);
            } else {
                foreach ($this->localeProvider->getDefinedLocalesCodes() as $localeCode) {
                    $this->addAttributeValue($attribute, $valueData['data'], $localeCode, $product);
                }
            }
        }
    }

    /**
     * @param bool|string $value
     */
    private function addAttributeValue(
        AttributeInterface $attribute,
        $value,
        string $localeCode,
        ProductInterface $product
    ): void {
        $attributeCode = $attribute->getCode();
        Assert::notNull($attributeCode);
        $attributeValue = $product->getAttributeByCodeAndLocale($attributeCode, $localeCode);

        if (!$attributeValue) {
            /** @var ProductAttributeValueInterface $attributeValue */
            $attributeValue = $this->factory->createNew();
        }

        $attributeValue->setAttribute($attribute);
        $attributeValue->setValue($this->valueConverter->convert($attribute, $value, $localeCode));
//        $attributeValue->setValue($this->getAttributeValue($attribute, $value));
        $attributeValue->setLocaleCode($localeCode);

        $product->addAttribute($attributeValue);
    }

    private function hasSupportedType(AttributeInterface $attribute): bool
    {
        return $attribute->getType() === TextareaAttributeType::TYPE ||
            $attribute->getType() === TextAttributeType::TYPE ||
            $attribute->getType() === CheckboxAttributeType::TYPE ||
            $attribute->getType() === SelectAttributeType::TYPE ||
            $attribute->getType() === IntegerAttributeType::TYPE;
    }

    private function isProductOption(ProductVariantInterface $subject, string $attributeCode): bool
    {
        $product = $subject->getProduct();
        Assert::isInstanceOf($product, ProductInterface::class);
        $options = $product->getOptions();

        $productOptions = $options->filter(function (ProductOptionInterface $option) use ($attributeCode): bool {
            return $option->getCode() === $attributeCode;
        });

        return !$productOptions->isEmpty();
    }
}
