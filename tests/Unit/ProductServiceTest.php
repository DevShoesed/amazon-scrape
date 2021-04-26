<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
//use PHPUnit\Framework\TestCase;
use Tests\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductService $service;

    protected function getHtmlProductPage(string $productName, array $productCategories, string $productPrice)
    {
        $crawler = new Crawler();
        $titleNode = "<span id='productTitle'>$productName</span>";

        $categoryNode = '<div id="wayfinding-breadcrumbs_feature_div"><ul>';
        foreach ($productCategories as $cat) {
            $categoryNode .= "<li>$cat</li>";
            $categoryNode .= "<li class='divider'></li>";
        }
        $categoryNode .= "</ul></div>";
        $priceNode = "<span id='price'>Prezzo: $productPrice&nbsp;€</span>";

        $node = "<body>$titleNode $priceNode $categoryNode</body>";
        $crawler->add($node);

        return $crawler;
    }


    /**
     * Test find Product Name
     * 
     * @return void
     */
    public function test_find_product_name()
    {
        $name = "Test Name Product.";
        $categories = [
            "Informatica",
            "Portatili"
        ];
        $price = "320,99";
        $this->assertEquals(
            $name,
            $this->service->findNameProduct($this->getHtmlProductPage($name, $categories, $price))
        );
    }

    /**
     * Test find Categories
     * 
     * @return void
     */
    public function test_find_product_categories()
    {
        $name = "Test Categories Product.";
        $expectedCategories = [
            "Informatica",
            "Computer",
            "Portatili",
            "Accessori"
        ];
        $price = "180,59";

        $foundCategories = $this->service->findCategories($this->getHtmlProductPage($name, $expectedCategories, $price));

        $this->assertEquals($expectedCategories, $foundCategories);
    }

    /**
     * Test find Product Price
     */
    public function test_find_product_price()
    {
        $name = "Test Price Product.";
        $categories = [
            "Informatica",
            "Accessori"
        ];
        $expectedPrice = 18.99;

        $price = $this->service->findPrice($this->getHtmlProductPage($name, $categories, number_format($expectedPrice, 2, ",", ".")));

        $this->assertNotNull($price);
        $this->assertEquals(
            $expectedPrice,
            $price
        );
    }

    /**
     * Test handle scrape action.
     *
     * @return void
     */
    public function test_handle_scrape_product()
    {
        $asinProduct = "BAX4321";
        $nameProduct = "Product Scaped from Service Test.";
        $categoriesProduct = [
            "Casa e cucina",
            "Tè e caffè",
            "Macchine da caffè",
            "Macchine per espresso e cappuccino",
            "Macchine da caffè manuali"
        ];
        $priceProduct = 183.29;

        $mockClient = Mockery::mock(\Goutte\Client::class)->makePartial();
        $mockClient->shouldReceive('request')
            ->andReturn($this->getHtmlProductPage($nameProduct, $categoriesProduct, number_format($priceProduct, 2, ",", ".")));

        $this->service = new ProductService(new CategoryRepository(), new ProductRepository(), $mockClient);

        $response = $this->service->handleScrapeProduct($asinProduct);

        $this->assertTrue($response);

        $product = Product::with('category')->find($asinProduct);

        $this->assertEquals(
            $nameProduct,
            $product->name
        );

        $this->assertEquals(
            $priceProduct,
            $product->last_price
        );

        $this->assertEquals(
            last($categoriesProduct),
            $product->category->name
        );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductService(new CategoryRepository(), new ProductRepository(), new \Goutte\Client());

        Log::shouldReceive('error');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
