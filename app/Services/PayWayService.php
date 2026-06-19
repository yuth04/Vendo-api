<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayWayService
{
    private string $merchantId;
    private string $publicKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->merchantId = config('services.payway.merchant_id');
        $this->publicKey = config('services.payway.public_key');
        $this->baseUrl = config('services.payway.base_url');
    }

    public function createTransaction(
        string $transactionId,
        float $amount,
        string $currency = 'USD',
        string $returnUrl = '',
        string $cancelUrl = '',
        string $firstname = '',
        string $lastname = '',
        string $email = '',
        string $phone = '',
        string $paymentOption = '',
    ): array {
        $reqTime = now()->utc()->format('YmdHis');
        $amountStr = number_format($amount, 2, '.', '');
        $type = 'purchase';
        $returnUrl = $returnUrl ?: config('app.url') . '/payment/return';
        $cancelUrl = $cancelUrl ?: config('app.url') . '/payment/cancel';
        $continueSuccessUrl = config('app.url') . '/payment/success';

        // Hash field order per official docs:
        // req_time + merchant_id + tran_id + amount + items + shipping +
        // firstname + lastname + email + phone + type + payment_option +
        // return_url + cancel_url + continue_success_url + return_deeplink +
        // currency + custom_fields + return_params + payout + lifetime +
        // additional_params + google_pay_token + skip_success_page
        $b4hash = $reqTime
            . $this->merchantId
            . $transactionId
            . $amountStr
            . ''   // items
            . ''   // shipping
            . $firstname
            . $lastname
            . $email
            . $phone
            . $type
            . $paymentOption
            . $returnUrl
            . $cancelUrl
            . $continueSuccessUrl
            . ''   // return_deeplink
            . $currency
            . ''   // custom_fields
            . ''   // return_params
            . ''   // payout
            . ''   // lifetime
            . ''   // additional_params
            . ''   // google_pay_token
            . '';  // skip_success_page

        $hash = base64_encode(hash_hmac('sha512', $b4hash, $this->publicKey, true));

        // Must be multipart/form-data, NOT application/json
        $response = Http::asMultipart()->post($this->baseUrl, [
            'req_time' => $reqTime,
            'merchant_id' => $this->merchantId,
            'tran_id' => $transactionId,
            'amount' => $amountStr,
            'currency' => $currency,
            'type' => $type,
            'payment_option' => $paymentOption,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'continue_success_url' => $continueSuccessUrl,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'phone' => $phone,
            'hash' => $hash,
        ]);

        if ($response->failed()) {
            Log::error('PayWay createTransaction failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \RuntimeException('PayWay API error: ' . $response->status());
        }

        return $response->json() ?? [];
    }

    public function checkTransaction(string $transactionId): array
    {
        $checkUrl = str_replace('/purchase', '/check-transaction', $this->baseUrl);
        $reqTime = now()->utc()->format('YmdHis');

        $b4hash = $reqTime . $this->merchantId . $transactionId;
        $hash = base64_encode(hash_hmac('sha512', $b4hash, $this->publicKey, true));

        $response = Http::asMultipart()->post($checkUrl, [
            'merchant_id' => $this->merchantId,
            'tran_id' => $transactionId,
            'req_time' => $reqTime,
            'hash' => $hash,
        ]);

        if ($response->failed()) {
            Log::error('PayWay checkTransaction failed', [
                'tran_id' => $transactionId,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException('PayWay check error: ' . $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Verify webhook callback signature from PayWay.
     * PayWay sends the signature in the X-Payway-Hmac-Sha512 header.
     * The body fields are sorted by key (ascending) and values concatenated.
     */
    public function verifyWebhook(array $data, string $receivedSignature): bool
    {
        ksort($data);
        $b4hash = '';
        foreach ($data as $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $b4hash .= $value;
        }
        $expected = base64_encode(hash_hmac('sha512', $b4hash, $this->publicKey, true));

        Log::info('PayWay webhook verify debug', [
            'b4hash' => $b4hash,
            'expected' => $expected,
            'received' => $receivedSignature,
            'match' => hash_equals($expected, $receivedSignature),
        ]);

        return hash_equals($expected, $receivedSignature);
    }
}