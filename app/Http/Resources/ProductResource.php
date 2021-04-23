<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'asin' => $this['product']->asin,
            'name' => $this['product']->name,
            'price' => $this['product']->last_price,
            'prices' => $this['product']->prices ? new PriceCollection($this['product']->prices) : [],
            'category' => new CategoryResource($this['product']->category),
            'categories' => $this['categories']
        ];
    }
}
