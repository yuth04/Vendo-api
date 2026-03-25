<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Exception;
use KhqrGateway\Khqr; // common
use KHQR\BakongKHQR;
use KHQR\Models\MerchantInfo;

class BakongService
{
    protected $apiUrl;
    protected $token;

    public function __construct()
    {
        $this->apiUrl = config('services.bakong.api_url');
        $this->token = config('services.bakong.token');
    }

    /**
     * Generate Real KHQR using the Package
     */
    public function generateKhqr(Order $order)
    {

        $merchantInfo = new MerchantInfo(
            bakongAccountID: 'jonhsmith@nbcq',
            merchantName: 'Jonh Smith',
            merchantCity: 'Siem Reap',
            merchantID: '123456',
            acquiringBank: 'Dev Bank',
            mobileNumber: '85512345678',
        );

        var_dump(BakongKHQR::generateMerchant($merchantInfo));

        return [
            'qr_string' => $merchantInfo,
            'qr_image_url' => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($merchantInfo),
            'order_number' => $order->order_number
        ];
    }

    /**
     * Verify Transaction via NBC API
     */
    public function checkStatus(Order $order)
    {
        // Note: Added a slash check for the API URL
        $baseUrl = rtrim($this->apiUrl, '/') . '/';

        $response = Http::withToken($this->token)
            ->post($baseUrl . 'v1/check_transaction', [
                'merchantId' => config('services.bakong.merchant_id'),
                'billNumber' => $order->order_number,
            ]);

        return $response->json();
    }
}