<?php

namespace App\Services;

class BakongService
{
    public function generateKHQR($order)
    {
        $bakongId = "nheung_phearakyuth@aba";
        $merchantName = "Vendo Store";
        $city = "Phnom Penh";

        // ✅ Convert USD to KHR (Bakong 116 requires Riel)
        // Adjust the rate (4100) based on your business preference
        $amount = (int) round($order->total_price * 4100);

        $data = [
            '00' => '01',
            '01' => '12', 
            '29' => [
                '00' => 'bakong.kh',
                '01' => $bakongId,
            ],
            '52' => '5912',
            '53' => '116', // KHR
            '54' => (string)$amount,
            '58' => 'KH',
            '59' => $merchantName,
            '60' => $city,
            '62' => [
                '01' => $order->order_number,
                '05' => $order->order_number, 
            ]
        ];

        $payload = $this->serialize($data);
        $payload .= "6304";
        $payload .= $this->crc16($payload);

        return $payload;
    }

    private function serialize($data)
    {
        $result = '';
        foreach ($data as $tag => $value) {
            $val = is_array($value) ? $this->serialize($value) : (string)$value;
            $result .= str_pad($tag, 2, '0', STR_PAD_LEFT) . str_pad(strlen($val), 2, '0', STR_PAD_LEFT) . $val;
        }
        return $result;
    }

    private function crc16($data)
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
            $x ^= $x >> 4;
            $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

}