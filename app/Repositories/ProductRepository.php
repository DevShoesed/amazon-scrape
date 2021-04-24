<?php

namespace App\Repositories;

use App\Models\Price;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductRepository implements ProductRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getAllProducts(int $categoryId = null, string $name = null)
    {
        //return Product::with('latestPrice')->orderBy('price')->get();
        $latestPrice = DB::table('prices')
            ->select('product_asin', DB::raw('MAX(updated_at) as last_price_updated_at'))
            ->groupBy('product_asin');

        $queryProducts = Product::joinSub($latestPrice, 'latest_price', function ($join) {
            $join->on('products.asin', '=', 'latest_price.product_asin');
        })->join('prices', function ($join) {
            $join->on('latest_price.product_asin', '=', 'prices.product_asin');
            $join->on('latest_price.last_price_updated_at', '=', 'prices.updated_at');
        });

        if ($categoryId) {
            $queryProducts = $queryProducts->where('category_id', $categoryId);
        }

        if ($name) {
            $queryProducts = $queryProducts->where('products.name', 'LIKE', '%' . $name . '%');
        }
        logger($queryProducts->toSql());
        return $queryProducts->orderBy('prices.price')->get();
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
            : Price::where('product_asin', $product->asin)
            ->orderBy('created_at', 'desc')
            ->first();
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
