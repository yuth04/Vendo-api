<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Services\BakongService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
class PaymentController extends Controller
{
    private function crc16($payload)
    {
        $polynomial = 0x1021;
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ $polynomial;
                } else {
                    $crc <<= 1;
                }
                $crc &= 0xFFFF;
            }
        }

        return dechex($crc);
    }
    public function generateBakongQr($orderId)
    {
        $order = Order::findOrFail($orderId);

        $account = "nheung_phearakyuth@aba";
        $merchantName = "Vendo Store";
        $city = "Phnom Penh";
        $amount = number_format($order->total_amount, 2, '.', '');

        // KHQR base (TLV format)
        $khqr = "";
        $khqr .= "000201"; // Payload Format Indicator
        $khqr .= "010212"; // Dynamic QR

        // Merchant Info
        $merchantInfo = "0016A000000677010111"; // Bakong ID
        $merchantInfo .= "01" . str_pad(strlen($account), 2, '0', STR_PAD_LEFT) . $account;

        $khqr .= "29" . str_pad(strlen($merchantInfo), 2, '0', STR_PAD_LEFT) . $merchantInfo;

        // Currency (USD = 840)
        $khqr .= "5303840";

        // Amount
        $khqr .= "54" . str_pad(strlen($amount), 2, '0', STR_PAD_LEFT) . $amount;

        // Country
        $khqr .= "5802KH";

        // Merchant Name
        $khqr .= "59" . str_pad(strlen($merchantName), 2, '0', STR_PAD_LEFT) . $merchantName;

        // City
        $khqr .= "60" . str_pad(strlen($city), 2, '0', STR_PAD_LEFT) . $city;

        // Additional data (Order number)
        $billNumber = $order->order_number;
        $additional = "01" . str_pad(strlen($billNumber), 2, '0', STR_PAD_LEFT) . $billNumber;
        $khqr .= "62" . str_pad(strlen($additional), 2, '0', STR_PAD_LEFT) . $additional;

        // CRC
        $khqr .= "6304";
        $crc = $this->crc16($khqr);
        $khqr .= strtoupper($crc);

        // Generate QR Image
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($khqr);

        return response()->json([
            "message" => "QR Generated",
            "data" => [
                "qr_code_url" => $qrUrl,
                "khqr_string" => $khqr,
                "order_number" => $order->order_number,
                "order_id" => $order->id
            ]
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string'
        ]);

        $order = Order::where('order_number', $request->order_number)->firstOrFail();

        // Call Bakong API
        $response = Http::post('https://api-bakong.nbc.gov.kh/v1/check_transaction', [
            "billNumber" => $order->order_number,
            "merchantId" => config('services.bakong.merchant_id')
        ]);

        $result = $response->json();

        /**
         * Example response:
         * {
         *   "responseCode": 0,
         *   "data": {
         *     "status": "SUCCESS"
         *   }
         * }
         */

        if (
            isset($result['data']['status']) &&
            $result['data']['status'] === 'SUCCESS'
        ) {
            if ($order->status === 'pending') {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'paid'
                ]);
            }

            return response()->json([
                'message' => 'Payment successful',
                'order' => $order
            ]);
        }

        return response()->json([
            'message' => 'Payment not completed yet'
        ], 400);
    }

}
