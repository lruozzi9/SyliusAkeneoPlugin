<?php

declare(strict_types=1);

namespace Webgriffe\SyliusAkeneoPlugin\ValueHandler;

use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webgriffe\SyliusAkeneoPlugin\ApiClientInterface;
use Webgriffe\SyliusAkeneoPlugin\ValueHandlerInterface;
use Webmozart\Assert\Assert;

final class ImageValueHandler implements ValueHandlerInterface
{
    /** @var FactoryInterface */
    private $productImageFactory;

    /** @var ApiClientInterface */
    private $apiClient;

    /** @var string */
    private $akeneoAttributeCode;

    /** @var string */
    private $syliusImageType;

    public function __construct(
        FactoryInterface $productImageFactory,
        ApiClientInterface $apiClient,
        string $akeneoAttributeCode,
        string $syliusImageType
    ) {
        $this->productImageFactory = $productImageFactory;
        $this->apiClient = $apiClient;
        $this->akeneoAttributeCode = $akeneoAttributeCode;
        $this->syliusImageType = $syliusImageType;
    }

    /**
     * @param mixed $subject
     */
    public function supports($subject, string $attribute, array $value): bool
    {
        return ($subject instanceof ProductInterface || $subject instanceof ProductVariantInterface) && $this->akeneoAttributeCode === $attribute;
    }

    /**
     * @param mixed $subject
     */
    public function handle($subject, string $attribute, array $value): void
    {
        if (!$subject instanceof ProductInterface && !$subject instanceof ProductVariantInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'This image value handler only supports instances of %s and %s, %s given.',
                    ProductInterface::class,
                    ProductVariantInterface::class,
                    is_object($subject) ? get_class($subject) : gettype($subject)
                )
            );
        }
        $downloadUrl = $value[0]['_links']['download']['href'] ?? null;
        if (!is_string($downloadUrl)) {
            throw new \InvalidArgumentException('Invalid Akeneo image data. Cannot find download URL.');
        }
        $imageFile = $this->apiClient->downloadFile($downloadUrl);

        $productImage = $this->productImageFactory->createNew();
        Assert::isInstanceOf($productImage, ProductImageInterface::class);
        /** @var ProductImageInterface $productImage */
        $productImage->setType($this->syliusImageType);
        $productImage->setFile($imageFile);

        if ($subject instanceof ProductVariantInterface) {
            $productImage->addProductVariant($subject);
            $subject = $subject->getProduct();
        }
        /** @var ProductInterface $subject */
        Assert::isInstanceOf($subject, ProductInterface::class);

        foreach ($subject->getImagesByType($this->syliusImageType) as $existentImage) {
            $subject->removeImage($existentImage);
        }
        $subject->addImage($productImage);
    }
}
