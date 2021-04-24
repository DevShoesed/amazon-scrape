<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductNotFoundResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\ProductRepositoryInterface;
use Goutte\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use NumberFormatter;

class ProductService
{

    protected CategoryRepositoryInterface $categoryRepository;
    protected ProductRepositoryInterface $productRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository, ProductRepositoryInterface $productRepository)
    {
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
    }


    /**
     * Scrape Amazon Product Page and store info
     * 
     * @param String $asin
     * 
     * @return Boolean $result
     * 
     */
    public function handleScrapeProduct(string $asin): bool
    {
        $client = new Client();
        $crawler = $client->request('GET', 'http://webcache.googleusercontent.com/search?q=cache:www.amazon.it/dp/' . $asin);

        $nameContainer = $crawler->filter('#productTitle');
        if ($nameContainer->count() == 0) {
            logger("SCRAPE $asin - Impossible scrape Product, name not found");
            return false;
        }
        $name = $nameContainer->text();

        $categories = [];
        $categoriesContainer = $crawler->filter('#wayfinding-breadcrumbs_feature_div > ul > li:not([class]) ');

        if ($categoriesContainer->count() == 0) {
            logger("SCRAPE $asin - Impossible scrape Product, categories not found");
            return false;
        }

        $categoriesContainer->each(function ($cat) use (&$categories) {
            array_push($categories, $cat->text());
        });


        $multi_price = $crawler->filter('span[data-action=show-all-offers-display]');
        $offer_price = $crawler->filter('#priceblock_ourprice');
        $sale_price = $crawler->filter('#priceblock_saleprice');

        if ($multi_price->count() > 0) {
            $price = $multi_price->filter(".a-color-price")->text();
        } else {
            $price = $offer_price->count() > 0 ? $offer_price->text() :  $sale_price->text();
        }

        $formatter = new NumberFormatter('it_IT', NumberFormatter::CURRENCY);
        $price = $formatter->parseCurrency($price, $curr);

        $images = [];
        $imagesContainer = $crawler->filter("#altImages > ul > li");
        $imagesContainer->each(function ($imgSpan) use (&$images) {
            $img = $imgSpan->filter(" span > img ");
            if ($img->count() > 0) {
                $images[] = $img->eq(0)->attr('src');
            }
        });

        $category_id = $this->categoryRepository->generateCategories($categories);

        $product = $this->productRepository->storeProduct([
            'asin' => $asin,
            'name' => $name,
            'category_id' => $category_id
        ]);

        if (!$product) {
            return false;
        }

        $this->productRepository->updatePrice($product->fresh(), $price);

        return true;
    }


    /**
     * Fetch a single Product with Price and All category's hierarchy 
     * 
     * @param String $asin
     * 
     * @return JsonResource
     */
    public function getProduct(string $asin): JsonResource
    {
        try {
            $product = $this->productRepository->getProduct($asin);

            $categories = $this->categoryRepository->getAllParent($product->category);

            return new ProductResource([
                'product' => $product,
                'categories' => array_reverse($categories)
            ]);
        } catch (\Exception $e) {
            logger($e->getMessage());

            return new ProductNotFoundResource([
                'asin' => $asin
            ]);
        }
    }

    /**
     * Fetch All Products
     */
    public function getAllProducts(int $categoryId = null, string $name = null): JsonResource
    {
        $products = $this->productRepository->getAllProducts($categoryId, $name);
        $collection = $products->map(function ($product) {
            return [
                'product' => $product,
                'categories' => array_reverse($this->categoryRepository->getAllParent($product->category))
            ];
        });

        return new ProductCollection($collection);
    }
}
