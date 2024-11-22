<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Gnikyt\BasicShopifyAPI\Options;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $api;

    protected $customer;

    protected $shopId;

    public function __construct(User $shop)
    {

        $this->shopId = $shop->id;

        // Initialize Options with API version
        $options = new Options;
        $options->setType(true);
        $options->setVersion(env('SHOPIFY_API_VERSION', '2024-10')); // Specify the API version
        $options->setApiKey(env('SHOPIFY_API_KEY'));
        $options->setApiSecret(env('SHOPIFY_API_SECRET'));
        $options->setApiPassword($shop->password); // Set the access token here

        // Initialize BasicShopifyAPI with the Options instance
        $this->api = new BasicShopifyAPI($options);

        $this->api->setSession(new \Gnikyt\BasicShopifyAPI\Session($shop->name));
        Log::info("Shopify API initialization complete for store: {$shop->name}");

    }

    public function customerCreate($customerEmail = null)
    {
        $query = <<<'QUERY'
        mutation customerCreate($input: CustomerInput!) {
            customerCreate(input: $input) {
                userErrors {
                    field
                    message
                }
                customer {
                    id 
                }
            }
        }
        QUERY;

        $variables = [
            'input' => [
                'email' => $customerEmail,
                'emailMarketingConsent' => [
                    'marketingOptInLevel' => 'CONFIRMED_OPT_IN',
                    'marketingState' => 'SUBSCRIBED',
                ],
            ],
        ];
        $response = $this->api->graph($query, $variables);

        return $response;
    }

    public function getCustomer($customerEmail)
    {

        $query = <<<'QUERY'
        query getCustomerByEmail($email: String!) {
            customers(first: 1, query: $email) {
                edges {
                    node {
                        id
                        email
                        emailMarketingConsent {
                            marketingOptInLevel
                        }
                    }
                }
            }
        }
    QUERY;

        $variables = [
            'email' => "email:$customerEmail",
        ];
        $response = $this->api->graph($query, $variables);

        $customer = $response['body']->data->customers->edges;
        log::info('Customer Details: '.json_encode($customer));
        if (isset($customer) && count($customer) > 0) {
            $customerId = $customer[0]['node']['id'];

            return response()->json(['error' => 'Customer already exists.', 'customerId' => $customerId]);
        
        } else {
            // Customer found, use their details

            $newCustomer = $this->customerCreate($customerEmail);
            log::info('New Customer: '.json_encode($newCustomer));
            // Check for errors in the response
            if (isset($newCustomer['errors']) && ! empty($newCustomer['errors'])) {
                // Return the error if customer creation failed
                return response()->json(['error' => 'Customer creation failed', 'details' => $newCustomer['errors']]);
            } else {
                $customerId = $newCustomer['body']->container['data']['customerCreate']['customer']['id'];
                $discountCode = $this->createFirstOrderDiscount($customerId);
                if (isset($discountCode['errors']) && ! empty($discountCode['errors'])) {
                    // Return the error if customer creation failed
                    return response()->json(['error' => 'Customer creation failed', 'details' => $newCustomer['errors']]);
                }
            }
            Customer::create([
                'shop_id' => $this->shopId,
                'email' => $customerEmail,
            ]);

            return response()->json(['message' => 'new customer has been created', 'discountCode' => $discountCode]);
        }

        return $response;
    }

    public function createFirstOrderDiscount($customerId)
    {
        $query = <<<'QUERY'
            mutation discountCodeBasicCreate($basicCodeDiscount: DiscountCodeBasicInput!) {
                discountCodeBasicCreate(basicCodeDiscount: $basicCodeDiscount) {
                    codeDiscountNode {
                        codeDiscount {
                            ... on DiscountCodeBasic {
                                title
                                codes(first: 10) {
                                    nodes {
                                    code
                                    }
                                }
                                startsAt
                                customerGets {
                                    value {
                                    ... on DiscountPercentage {
                                        percentage
                                    }
                                    }
                                    items {
                                    ... on AllDiscountItems {
                                        allItems
                                    }
                                    }
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        code
                        message
                    }
                }
            }
        QUERY;
        $randomCode = $this->generateRandomCode(12); // Generate a random discount code
        log::info('CustomerID'.$customerId);
        $variables = [
            'basicCodeDiscount' => [
                'title' => '10% Off First Order',
                'code' => $randomCode, // Use the generated random code
                'startsAt' => now()->toISOString(),

                'customerSelection' => [
                    'customers' => [
                        'add' => $customerId,
                    ],
                ],
                'combinesWith' => [
                    'orderDiscounts' => true,
                ],
                'customerGets' => [
                    'value' => [
                        'percentage' => 0.1, // 10% discount
                    ],
                    'items' => [
                        'all' => true,
                    ],
                ],
                'usageLimit' => 1,
            ],
        ];

        // Log the generated discount code for debugging
        Log::info('Generated Discount Code: '.$randomCode);

        $response = $this->api->graph($query, $variables);
        Log::info('Discount Code Creation Response: ', $response);

        if (isset($response['body']->container['errors']) && ! empty($response['body']->container['errors'])) {
            return response()->json(['error' => 'Discount creation failed', 'details' => $response['errors']]);
        }

        $discountCode = $response['body']->data->discountCodeBasicCreate->codeDiscountNode->codeDiscount->codes->nodes[0]->code;

        return $discountCode;
    }

    public function generateRandomCode($length = 12)
    {
        return strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
    }
}
