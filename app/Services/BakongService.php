<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
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
    public function generateKhqr(Order $order, Payment $payment)
    {
        try {
            $merchantInfo = new MerchantInfo(
                bakongAccountID: 'jonhsmith@nbcq', // ⚠️ replace later
                merchantName: 'Jonh Smith',
                merchantCity: 'Phnom Penh',
                merchantID: '123456',
                acquiringBank: 'Dev Bank',
                mobileNumber: '85512345678',
                amount: $payment->amount
            );

            $response = BakongKHQR::generateMerchant($merchantInfo);

            // ❗ Check success
            if ($response->status['code'] !== 0) {
                throw new Exception('KHQR generation failed');
            }

            $qrString = $response->data['qr'];
            $md5 = $response->data['md5'];

            // ✅ Save transaction id (optional)
            $payment->update([
                'transaction_id' => $md5
            ]);

            return [
                'khqr_string' => $qrString,
                'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='
                    . urlencode($qrString),
                'payment_reference' => $payment->payment_reference,
                'amount' => $payment->amount
            ];

        } catch (Exception $e) {
            throw new Exception("KHQR Error: " . $e->getMessage());
        }
    }

    /**
     * Verify Transaction via NBC API
     */
    public function checkStatus(Payment $payment)
    {
        // Note: Added a slash check for the API URL
        $baseUrl = rtrim($this->apiUrl, '/') . '/';

        $response = Http::withToken($this->token)
            ->post($baseUrl . 'v1/check_transaction', [
                'merchantId' => config('services.bakong.merchant_id'),
                'billNumber' => $payment->payment_reference,
            ]);

        return $response->json();
    }
}