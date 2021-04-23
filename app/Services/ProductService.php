<?php

namespace App\Services;

use App\Http\Resources\CategoryResource;
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
            logger("SCRAPE - Impossible scrape Product " . $asin);
            return false;
        }

        $offer_price = $crawler->filter('#priceblock_ourprice');
        $sale_price = $crawler->filter('#priceblock_saleprice');
        $categoriesContainer = $crawler->filter('#wayfinding-breadcrumbs_feature_div > ul > li:not([class]) ');
        $imagesContainer = $crawler->filter("#altImages > ul > li");

        $name = $nameContainer->text();
        $price = $offer_price->count() > 0 ? $offer_price->text() :  $sale_price->text();

        $formatter = new NumberFormatter('it_IT', NumberFormatter::CURRENCY);
        $price = $formatter->parseCurrency($price, $curr);

        $categories = [];
        $images = [];

        $categoriesContainer->each(function ($cat) use (&$categories) {
            array_push($categories, $cat->text());
        });

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

        $this->productRepository->updatePrice($product, $price);

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
}
