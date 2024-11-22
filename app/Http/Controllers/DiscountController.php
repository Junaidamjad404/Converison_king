<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiscountController extends Controller
{
    protected $shopifyService;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function checkCustomer(Request $request)
    {

        $request->validate([
            'shopDomain' => 'required|string',
            'email' => 'required|email',
        ]);

        // Get shopDomain and email
        $shopDomain = $request->shopDomain;
        $email = $request->email;
        // Retrieve the Shopify access token (this assumes it's stored in your database)
        $shop = User::where('name', $shopDomain)->first();
        if (! $shop || ! $shop->password) {
            return response()->json(['error' => 'Shop not found or unauthorized'], 403);
        }

        $customer = Customer::where('shop_id', $shop->id)
            ->where('email', $email)
            ->first();

        if ($customer) {
            $customer_with_order = $customer->where('no_of_orders', '!=', 0)->get();
            if (empty($customer_with_order)) {
                return response()->json([
                    'error' => 'You are already a customer with orders.',
                    'customer' => $customer,
                ]);
            }

            return response()->json([
                'error' => 'You are already a customer.',
                'customer' => $customer,
            ]);
        }

        // If no customer is found, consider returning a default response or continue with your logic.
        Log::info('No customer found with orders for email: '.$email);
        $shopifyService = new ShopifyService($shop);

        return $shopifyService->getCustomer($email);

    }
}
