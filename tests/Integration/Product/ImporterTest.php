<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusAkeneoPlugin\Integration\Product;

use Fidry\AliceDataFixtures\Loader\PurgerLoader;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductVariantRepository;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webgriffe\SyliusAkeneoPlugin\ImporterInterface;

final class ImporterTest extends KernelTestCase
{
    /** @var ImporterInterface */
    private $importer;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var ProductVariantRepository */
    private $productVariantRepository;

    /** @var PurgerLoader */
    private $fixtureLoader;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->importer = self::$container->get('webgriffe_sylius_akeneo.product.importer');
        $this->productRepository = self::$container->get('sylius.repository.product');
        $this->productVariantRepository = self::$container->get('sylius.repository.product_variant');
        $this->fixtureLoader = self::$container->get('fidry_alice_data_fixtures.loader.doctrine');
        $this->fixtureLoader->load([], [], [], PurgeMode::createDeleteMode());
    }

    /**
     * @test
     */
    public function it_imports_product_variant_of_a_product_model()
    {
        $this->fixtureLoader->load(
            [
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/en_US.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Product/model-braided-hat.yaml',
            ],
            [],
            [],
            PurgeMode::createDeleteMode()
        );
        $this->importer->import('braided-hat-m');

        $allVariants = $this->productVariantRepository->findAll();
        $this->assertCount(1, $allVariants);
        $this->assertInstanceOf(ProductVariantInterface::class, $allVariants[0]);
    }

    /**
     * @test
     */
    public function it_creates_proper_product_option_value_with_translations_if_missing()
    {
        $this->fixtureLoader->load(
            [
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/en_US.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/it_IT.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Product/model-braided-hat.yaml',
            ],
            [],
            [],
            PurgeMode::createDeleteMode()
        );

        $this->importer->import('braided-hat-m');

        /** @var ProductVariantInterface $variant */
        $variant = $this->productVariantRepository->findAll()[0];
        $this->assertCount(1, $variant->getOptionValues());
        $this->assertEquals('size_m', $variant->getOptionValues()[0]->getCode());
        $this->assertCount(2, $variant->getOptionValues()[0]->getTranslations());
        $this->assertEquals('it_IT', $variant->getOptionValues()[0]->getTranslation('it_IT')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('it_IT')->getValue());
        $this->assertEquals('en_US', $variant->getOptionValues()[0]->getTranslation('en_US')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('en_US')->getValue());
    }

    /**
     * @test
     */
    public function it_updates_alredy_existent_product_option_value()
    {
        $this->fixtureLoader->load(
            [
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/en_US.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/it_IT.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Product/model-braided-hat.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/ProductOptionValue/size_m.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/ProductVariant/braided-hat-m.yaml',
            ],
            [],
            [],
            PurgeMode::createDeleteMode()
        );
        self::$kernel->getContainer()->get('doctrine')->reset(); // Hack to get rid of weird collection keys loading

        $this->importer->import('braided-hat-m');

        /** @var ProductVariantInterface $variant */
        $variant = $this->productVariantRepository->findAll()[0];
        $this->assertCount(2, $variant->getOptionValues()[0]->getTranslations());
        $this->assertEquals('it_IT', $variant->getOptionValues()[0]->getTranslation('it_IT')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('it_IT')->getValue());
        $this->assertEquals('en_US', $variant->getOptionValues()[0]->getTranslation('en_US')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('en_US')->getValue());
    }

    /**
     * @test
     */
    public function it_creates_missing_translations_while_updating_alredy_existent_product_option_value()
    {
        $this->fixtureLoader->load(
            [
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/en_US.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/it_IT.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Product/model-braided-hat.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/ProductOptionValue/size_m/without-it_IT.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/ProductVariant/braided-hat-m.yaml',
            ],
            [],
            [],
            PurgeMode::createDeleteMode()
        );
        self::$kernel->getContainer()->get('doctrine')->reset(); // Hack to get rid of weird collection keys loading

        $this->importer->import('braided-hat-m');

        /** @var ProductVariantInterface $variant */
        $variant = $this->productVariantRepository->findAll()[0];
        $this->assertCount(2, $variant->getOptionValues()[0]->getTranslations());
        $this->assertEquals('it_IT', $variant->getOptionValues()[0]->getTranslation('it_IT')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('it_IT')->getValue());
        $this->assertEquals('en_US', $variant->getOptionValues()[0]->getTranslation('en_US')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('en_US')->getValue());
    }

    /**
     * @test
     */
    public function it_creates_missing_option_value_while_updating_alredy_existent_product_variant()
    {
        $this->fixtureLoader->load(
            [
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/en_US.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/it_IT.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/Product/model-braided-hat.yaml',
                __DIR__ . '/../DataFixtures/ORM/resources/ProductVariant/braided-hat-m/without-option-values.yaml',
            ],
            [],
            [],
            PurgeMode::createDeleteMode()
        );
        self::$kernel->getContainer()->get('doctrine')->reset(); // Hack to get rid of weird collection keys loading

        $this->importer->import('braided-hat-m');

        /** @var ProductVariantInterface $variant */
        $variant = $this->productVariantRepository->findAll()[0];
        $this->assertCount(1, $variant->getOptionValues());
        $this->assertCount(2, $variant->getOptionValues()[0]->getTranslations());
        $this->assertEquals('it_IT', $variant->getOptionValues()[0]->getTranslation('it_IT')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('it_IT')->getValue());
        $this->assertEquals('en_US', $variant->getOptionValues()[0]->getTranslation('en_US')->getLocale());
        $this->assertEquals('M', $variant->getOptionValues()[0]->getTranslation('en_US')->getValue());
    }

    /**
     * @test
     */
    public function it_creates_product_and_product_variant_when_akeneo_product_has_no_parent()
    {
        $this->fixtureLoader->load(
            [
                __DIR__ . '/../DataFixtures/ORM/resources/Locale/en_US.yaml',
            ],
            [],
            [],
            PurgeMode::createDeleteMode()
        );
        $this->importer->import('10627329');

        $variants = $this->productVariantRepository->findAll();
        $this->assertCount(1, $variants);
        $this->assertInstanceOf(ProductVariantInterface::class, $variants[0]);

        $products = $this->productRepository->findAll();
        $this->assertCount(1, $products);
        $this->assertInstanceOf(ProductInterface::class, $products[0]);
    }
}
