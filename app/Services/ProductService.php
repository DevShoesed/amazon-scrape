<?php

namespace App\Services;

use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductNotFoundResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\CategoryRepositoryInterface;
use App\Repositories\ProductRepositoryInterface;
use Exception;
use Goutte\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use NumberFormatter;
use Symfony\Component\DomCrawler\Crawler;

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
     * @param string $asin
     * 
     * @return bool
     */
    public function handleScrapeProduct(string $asin): bool
    {

        $html = $this->client->request('GET', 'http://webcache.googleusercontent.com/search?q=cache:www.amazon.it/dp/' . $asin, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.72 Safari/537.36'
            ]
        ]);

        try {
            $name = $this->findNameProduct($html);
            if (!$name) {
                new Exception("Name Product Not Found");
            }

            $categories = $this->findCategories($html);
            if (count($categories) == 0) {
                new Exception("Product Categories Not Found");
            }

            $price = $this->findPrice($html);
            if (!$price) {
                new Exception("Product Price Not Found");
            }

            // TODO: Add store images
            $images = $this->findImages($html);

            $category_id = $this->categoryRepository->generateCategories($categories);

            $product = $this->productRepository->storeProduct([
                'asin' => $asin,
                'name' => $name,
                'category_id' => $category_id
            ]);

            $this->productRepository->updatePrice($product, $price);
        } catch (Exception $e) {
            Log::error("SCRAPE $asin - Impossible scrape Product: " . $e->getMessage());

            return false;
        }

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



    /**
     * Search the Product Name in the DOM
     * 
     * @param Crawler $html
     * 
     * @return string|null
     */
    public function findNameProduct(Crawler $html): ?string
    {
        $nameContainer = $html->filter('#productTitle');

        return $nameContainer->count() > 0 ? $nameContainer->text() : null;
    }


    /**
     * Search all Categories of Product in the DOM
     * 
     * @param Crawler $html
     * 
     * @return array
     */
    public function findCategories(Crawler $html): array
    {
        $categories = [];

        $categoriesContainer = $html->filter('#wayfinding-breadcrumbs_feature_div > ul > li:not([class]) ');

        if ($categoriesContainer->count() > 0) {
            $categoriesContainer->each(function ($cat) use (&$categories) {
                array_push($categories, $cat->text());
            });
        }

        return $categories;
    }

    /**
     * Search Product Price in the DOM
     * 
     * @param Crawler $html
     * 
     * @return float|null
     */
    public function findPrice(Crawler $html): ?float
    {
        $price = null;
        $price_text = null;

        $single_price = $html->filter('#price');
        $price_buy_box = $html->filter('#price_inside_buybox');
        $offer_price = $html->filter('#priceblock_ourprice');
        $sale_price = $html->filter('#priceblock_saleprice');
        $multi_price = $html->filter('span[data-action=show-all-offers-display]');

        if (
            $price_buy_box->count() > 0
            and !str_contains($price_buy_box->text(), "opzioni di acquisto")
        ) {
            $price_text = $price_buy_box->text();
        } elseif (
            $single_price->count() > 0
            and !str_contains($single_price->text(), "opzioni di acquisto")
        ) {
            $price_text = $single_price->text();
        } elseif ($offer_price->count() > 0) {
            $price_text = $offer_price->text();
        } elseif ($sale_price->count() > 0) {
            $price_text = $sale_price->text();
        } elseif ($multi_price->count() > 0) {
            $price_text = $multi_price->filter("a > span.a-color-price")->text();
        }

        $price_text = str_replace("Tutti i prezzi includono l'IVA.", "", $price_text);
        $price_text = str_replace("Prezzo: ", "", $price_text);

        if ($price_text) {
            $formatter = new NumberFormatter('it_IT', NumberFormatter::CURRENCY);
            $price = $formatter->parseCurrency($price_text, $curr);
        }

        return $price;
    }

    /**
     *  Search Product Image in the DOM
     * 
     * @param Crawler $html
     * 
     * @return array
     */
    public function findImages(Crawler $html): array
    {
        $images = [];
        $imagesContainer = $html->filter("#altImages > ul > li");
        $imagesContainer->each(function ($imgSpan) use (&$images) {
            $img = $imgSpan->filter(" span > img ");
            if ($img->count() > 0) {
                $images[] = $img->eq(0)->attr('src');
            }
        });

        return $images;
    }


    /**
     * Delete aProduct
     *
     * @param  string $asin
     * @return bool
     */
    public function deleteProduct(string $asin): bool
    {
        try {
            return Product::find($asin)->delete();
        } catch (Exception $e) {
            Log::error('Delete product Error: ' . $e->getMessage());
        }

        return false;
    }
}
