<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderResource extends JsonResource
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
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'return_type' => $this->return_type,
            'status' => $this->status,
            'refund_status' => $this->refund_status,
            'reason' => $this->reason,
            'note' => $this->note,
            'approved_at' => $this->approved_at ? $this->approved_at->format('d-m-Y g:i:s A') : null,
            'received_at' => $this->received_at ? $this->received_at->format('d-m-Y g:i:s A') : null,
            'completed_at' => $this->completed_at ? $this->completed_at->format('d-m-Y g:i:s A') : null,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y g:i:s A') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y g:i:s A') : null,
            'items' => $this->whenLoaded('items'),
            'user' => $this->whenLoaded('user'),
            'order' => $this->whenLoaded('order'),
            'refund' => $this->whenLoaded('refund'),
            'exchange' => new ExchangeResource($this->whenLoaded('exchange')),
        ];
    }
}