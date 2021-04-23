<?php

namespace App\Repositories;

use App\Models\Price;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;

class ProductRepository implements ProductRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getAllProducts()
    {
        return Product::all();
    }

    /**
     * @inheritdoc
     */
    public function storeProduct(array $data)
    {
        $validator = Validator::make($data, [
            'asin' => 'required|string',
            'name' => 'required|string',
            'category_id' => 'required|numeric'
        ]);

        $product = null;
        if ($validator->fails()) {
            logger($validator->getMessageBag()->first());
            return null;
        }
        $product = Product::updateOrCreate($data);

        return $product;
    }

    /**
     * @inheritdoc
     */
    public function updatePrice(Product $product, float $productPrice): ?Price
    {
        return $productPrice !== $product->last_price
            ? Price::create([
                'product_asin' => $product->asin,
                'price' => $productPrice
            ])
            : $product->last_price;
    }

    /**
     * @inheritdoc
     */
    public function getProduct(string $asin): Product
    {
        $product = Product::with('category')->findOrFail($asin);

        return $product;
    }
}
