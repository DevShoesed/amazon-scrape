<?php

namespace App\Repositories;

use App\Models\Price;
use App\Models\Product;

/**
 * Interface for Products Repository
 */
interface ProductRepositoryInterface
{
    /**
     * Fetch all Products
     */
    public function getAllProducts();

    /**
     * Create new Product or Update if exist
     * 
     * @param array $data
     */
    public function storeProduct(array $data);

    /**
     * Update Product price if not equal to last price
     * 
     * @param String $asin
     * @param float $price
     * 
     * @return Price $productPrice
     */
    public function updatePrice(String $asin, float $productPrice): ?Price;


    /**
     * Fetch a single Product
     * 
     * @param String $asin
     * 
     * @return Product
     */
    public function getProduct(string $asin): Product;
}
