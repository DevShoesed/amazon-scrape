<?php

namespace App\Services;

use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductNotFoundResource;
use App\Http\Resources\ProductResource;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\ProductRepositoryInterface;
use Goutte\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use NumberFormatter;

class ProductService
{

    protected CategoryRepositoryInterface $categoryRepository;
    protected ProductRepositoryInterface $productRepository;
    protected Client $client;

    public function __construct(CategoryRepositoryInterface $categoryRepository, ProductRepositoryInterface $productRepository, Client $client)
    {
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->client = $client;
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
        $crawler = $this->client->request('GET', 'http://webcache.googleusercontent.com/search?q=cache:www.amazon.it/dp/' . $asin, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.72 Safari/537.36'
            ]
        ]);

        $nameContainer = $crawler->filter('#productTitle');
        if ($nameContainer->count() == 0) {
            Log::error("SCRAPE $asin - Impossible scrape Product, name not found");
            return false;
        }
        $name = $nameContainer->text();

        $categories = [];
        $categoriesContainer = $crawler->filter('#wayfinding-breadcrumbs_feature_div > ul > li:not([class]) ');

        if ($categoriesContainer->count() == 0) {
            Log::error("SCRAPE $asin - Impossible scrape Product, categories not found");
            return false;
        }

        $categoriesContainer->each(function ($cat) use (&$categories) {
            array_push($categories, $cat->text());
        });



        $single_price = $crawler->filter('#price');
        $price_buy_boc = $crawler->filter('#price_inside_buybox');
        $offer_price = $crawler->filter('#priceblock_ourprice');
        $sale_price = $crawler->filter('#priceblock_saleprice');
        $multi_price = $crawler->filter('span[data-action=show-all-offers-display]');

        if (
            $single_price->count() > 0
            and !str_contains($single_price->text(), "opzioni di acquisto")
        ) {
            $price_text = $single_price->text();
        } elseif (
            $price_buy_boc->count() > 0
            and !str_contains($price_buy_boc->text(), "opzioni di acquisto")
        ) {
            $price_text = $price_buy_boc->text();
        } elseif ($offer_price->count() > 0) {
            $price_text = $offer_price->text();
        } elseif ($sale_price->count() > 0) {
            $price_text = $sale_price->text();
        } elseif ($multi_price->count() > 0) {
            $price_text = $multi_price->filter("a > span.a-color-price")->text();
        } else {
            $price_text = "0";
        }

        $price_text = str_replace("Tutti i prezzi includono l'IVA.", "", $price_text);
        $price_text = str_replace("Prezzo: ", "", $price_text);
        $price = $price_text;

        $formatter = new NumberFormatter('it_IT', NumberFormatter::CURRENCY);
        $price = $formatter->parseCurrency($price, $curr);

        if ($price == 0) {
            Log::error("SCRAPE $asin - Impossible scrape Product, price not found <$price>");
            return false;
        }

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

        $this->productRepository->updatePrice($asin, $price);

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
