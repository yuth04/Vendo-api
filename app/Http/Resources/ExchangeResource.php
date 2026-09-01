<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_order_id' => $this->return_order_id,
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'price_difference' => (float) $this->price_difference,
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'processing_at' => $this->processing_at ? $this->processing_at->format('d-m-Y g:i:s A') : null,
            'shipped_at' => $this->shipped_at ? $this->shipped_at->format('d-m-Y g:i:s A') : null,
            'received_at' => $this->received_at ? $this->received_at->format('d-m-Y g:i:s A') : null,
            'failed_at' => $this->failed_at ? $this->failed_at->format('d-m-Y g:i:s A') : null,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y g:i:s A') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y g:i:s A') : null,
        ];
    }
}