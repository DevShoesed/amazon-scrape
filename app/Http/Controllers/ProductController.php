<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{

    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }


    /**
     * Scrape Product Page on Amazon and Store info
     * 
     * @param String $asin
     * 
     * @return JsonResponse
     */
    public function scrapeProduct(string $asin): JsonResponse
    {
        try {
            $this->productService->handleScrapeProduct($asin);

            return response()->json(
                $this->productService->getProduct($asin),
                200
            );
        } catch (\Exception $e) {
            logger($e->getMessage());

            return response()->json([
                'message' => 'Impossible Scrape Product ' . $asin
            ], 404);
        }
    }


    /**
     * Fetch All Products
     * 
     * @return JsonResponse
     */
    public function index()
    {
        return new ProductCollection(Product::all());
    }

    /**
     * Fetch single Product
     * 
     * @param String $asin
     * @return JsonResource
     */
    public function show(string $asin)
    {
        return $this->productService->getProduct($asin);
    }
}
