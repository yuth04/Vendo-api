<?php 
namespace App\Services;
use App\Models\Cart;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function mergeCart($user, array $localCart)
    {
        $cart = Cart::where('user_id', $user->id)->first();

        DB::transaction(function () use ($cart, $localCart) {
            foreach ($localCart as $item) {

                if (!isset($item['product_variant_id'], $item['quantity'])) {
                    continue;
                }

                $variant = ProductVariant::find($item['product_variant_id']);
                if (!$variant) continue;

                $cartItem = $cart->items()->firstOrNew([
                    'product_variant_id' => $variant->id
                ]);

                $newQuantity = ($cartItem->exists ? $cartItem->quantity : 0) + $item['quantity'];

                $cartItem->quantity = min($newQuantity, $variant->stock);

                $cartItem->save();
            }
        });

        return $cart;
    }
}