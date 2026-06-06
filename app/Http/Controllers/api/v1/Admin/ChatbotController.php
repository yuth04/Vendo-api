<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    private const MAX_HISTORY = 20;
    private const GEMINI_MODEL = 'gemini-2.5-flash';
    private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->apiUrl = self::GEMINI_BASE_URL . '/' . self::GEMINI_MODEL . ':generateContent';
    }

    // =========================================================================
    // PUBLIC ENDPOINT
    // =========================================================================

    /**
     * POST /api/v1/admin/chat
     * Main customer-support entry point.
     */
    public function chat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:' . self::MAX_HISTORY,
            'history.*.role' => 'required|in:user,model',
            'history.*.text' => 'required|string|max:4000',
            'customer_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $message = trim($request->string('message'));
            $customerName = trim($request->string('customer_name', 'Customer'));
            $history = array_slice($request->input('history', []), -self::MAX_HISTORY);

            $intent = $this->detectIntent($message);

            $reply = match ($intent) {
                'order' => $this->handleOrder($request, $message),
                'product' => $this->handleProduct($message),
                default => $this->handleAI($message, $customerName, $history, $intent),
            };

            $updatedHistory = $history;
            $updatedHistory[] = ['role' => 'user', 'text' => $message];
            $updatedHistory[] = ['role' => 'model', 'text' => $reply];

            return response()->json([
                'success' => true,
                'reply' => $reply,
                'intent' => $intent,
                'history' => $updatedHistory,
            ]);

        } catch (\Throwable $e) {
            Log::error('ChatbotController error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'reply' => 'Something went wrong on our end. Please try again shortly.',
            ], 500);
        }
    }

    // =========================================================================
    // INTENT DETECTION
    // =========================================================================

    /**
     * Classify the customer's message into a routing intent.
     * Rules are ordered from most-specific to most-general to avoid misfires.
     */
    private function detectIntent(string $message): string
    {
        $msg = mb_strtolower($message);

        // Support / policy queries first — so "refund for my order" → support, not order
        if ($this->matches($msg, ['refund', 'return', 'exchange', 'cancel', 'complaint', 'damaged', 'wrong item', 'missing item'])) {
            return 'support';
        }

        // Order status queries
        if ($this->matches($msg, ['my order', 'order status', 'where is my', 'track my', 'order #', 'last order', 'did my order'])) {
            return 'order';
        }

        // Product / catalogue queries
        if ($this->matches($msg, ['buy', 'purchase', 'product', 'item', 'price', 'cost', 'how much', 'available', 'in stock', 'out of stock', 'category', 'brand', 'color', 'size', 'laptop', 'phone', 'shoes'])) {
            return 'product';
        }

        return 'ai';
    }

    /**
     * Return true if $haystack contains any of the $needles.
     */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    // INTENT HANDLERS
    // =========================================================================

    /**
     * Return the authenticated user's latest order — or look up a specific
     * order ID mentioned in the message, scoped to the current user.
     */
    private function handleOrder(Request $request, string $message): string
    {
        $user = $request->user();

        if (!$user) {
            return 'Please log in so I can pull up your order details for you.';
        }

        // Did the customer mention a specific order number?
        preg_match('/\border[#:\s]*(\d{3,10})\b/i', $message, $matches);

        $order = isset($matches[1])
            ? Order::where('id', $matches[1])
                ->where('user_id', $user->id) // ownership check — critical
                ->first()
            : Order::where('user_id', $user->id)->latest()->first();

        if (!$order) {
            $hint = isset($matches[1])
                ? "I couldn't find order #{$matches[1]} linked to your account. Could you double-check the number?"
                : "You don't have any orders yet. Can I help you find something to buy?";
            return $hint;
        }

        $statusLabel = $this->humanizeOrderStatus($order->status);

        return "Here's your order #{$order->order_number}: it is currently {$statusLabel}. "
            . "Total paid: \${$order->total_price}. "
            . "Placed on: {$order->created_at->format('M d, Y')}. "
            . "Is there anything else I can help you with?";
    }

    /**
     * Search the product catalogue using filters extracted from the message.
     */
    private function handleProduct(string $message): string
    {
        $msg = mb_strtolower($message);
        $maxPrice = $this->extractMaxPrice($message);
        $keyword = $this->extractProductKeyword($msg);

        $products = Product::select('id', 'name', 'price', 'stock', 'category', 'status')
            ->where('status', 'active')
            ->when($maxPrice, fn($q) => $q->where('price', '<=', $maxPrice))
            ->when($keyword, fn($q) => $q->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('category', 'like', "%{$keyword}%");
            }))
            ->orderBy('price', 'asc')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            $note = $maxPrice ? " under \${$maxPrice}" : '';
            return "I couldn't find any products{$note} right now. Would you like me to suggest our most popular items instead?";
        }

        $lines = $products->map(function (Product $p) {
            $stock = $p->stock > 0 ? "In Stock ({$p->stock} left)" : 'Out of Stock';
            return "- {$p->name} | \${$p->price} | {$stock}";
        })->implode("\n");

        $note = $maxPrice ? " under \${$maxPrice}" : '';

        return "Here are some products{$note} I found for you:\n{$lines}\n\nWould you like more details on any of these?";
    }

    /**
     * Route to the AI with a support-specific context hint injected.
     */
    private function handleSupport(string $message, string $customerName, array $history): string
    {
        // Prepend a policy context hint so the AI focuses on the right policies
        $contextualMessage = "[Customer is asking about returns, refunds, or cancellations] {$message}";
        return $this->handleAI($contextualMessage, $customerName, $history, 'support');
    }

    /**
     * General AI fallback via Gemini — used for support, greeting, and unknown intents.
     */
    private function handleAI(string $message, string $customerName, array $history, string $intent = 'ai'): string
    {
        // For support intent, inject a policy hint before the message
        if ($intent === 'support') {
            $message = "[Customer is asking about returns, refunds, or cancellations] {$message}";
        }

        $systemPrompt = $this->buildSystemPrompt($customerName, $message);

        $contents = collect($history)
            ->map(fn($item) => [
                'role' => $item['role'],
                'parts' => [['text' => $item['text']]],
            ])
            ->push([
                'role' => 'user',
                'parts' => [['text' => $message]],
            ])
            ->all();

        return $this->callGemini([
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 512,
            ],
        ]);
    }

    // =========================================================================
    // SYSTEM PROMPT
    // =========================================================================

    /**
     * Build a rich, grounded system prompt for the AI using live store data.
     */
    private function buildSystemPrompt(string $customerName, string $message): string
    {
        $s = StoreSetting::allKeyed();
        $storeData = $this->getRelevantStoreData($message);
        $currentTime = now()->timezone('Asia/Phnom_Penh')->format('l, d M Y H:i');

        $storeName = $s['store_name'] ?? 'Vendo';
        $storeWebsite = $s['store_website'] ?? '';
        $storeAddress = $s['store_address'] ?? '';
        $storeEmail = $s['store_email'] ?? 'support@vendo.com';
        $storePhone = $s['store_phone'] ?? '';
        $storeHours = $s['store_hours'] ?? 'Mon–Fri, 9AM–6PM';
        $facebook = $s['social_facebook'] ?? '';
        $instagram = $s['social_instagram'] ?? '';

        return <<<PROMPT
You are Vendo Assistant, the friendly and knowledgeable AI customer support agent for {$storeName} — a trusted online e-commerce store.
You are currently chatting with: {$customerName}.
Current date and time: {$currentTime} (Phnom Penh, Cambodia)

=====================
STORE INFORMATION
=====================
- Store Name:    {$storeName}
- Website:       {$storeWebsite}
- Address:       {$storeAddress}
- Support Email: {$storeEmail}
- Support Phone: {$storePhone}
- Support Hours: {$storeHours}
- Facebook:      {$facebook}
- Instagram:     {$instagram}

=====================
YOUR PERSONALITY
=====================
- Warm, friendly, and genuinely helpful — like a knowledgeable shop assistant
- Professional but never robotic or stiff
- Patient and empathetic, especially with frustrated customers
- Concise — never give long walls of text unless the question requires detail
- Proactive — always offer a clear next step or follow-up
- Occasionally use the customer's name ({$customerName}) to make it personal

=====================
YOUR RESPONSIBILITIES
=====================
1. Greet customers warmly on first message
2. Help check order status and track deliveries
3. Answer product questions (price, availability, specifications)
4. Explain store policies clearly (shipping, returns, payments, cancellations)
5. Handle complaints calmly — apologise first, then solve
6. Suggest related or popular products when relevant
7. Escalate to a human agent when issues are too complex
8. Collect a quick satisfaction signal at end of resolved conversations

=====================
STORE POLICIES
=====================

SHIPPING:
- Standard: 3–5 business days (Free on orders over \$50)
- Express: 1–2 business days (\$5 fee)
- Same Day: Phnom Penh only (\$8 fee, order before 12PM)
- International: Not available yet

RETURNS & REFUNDS:
- 30-day return window for unused, unopened items in original packaging
- Refund processed within 3–5 business days after item received
- Non-returnable: digital products, sale items, intimate wear
- To start a return: email {$storeEmail} with your order ID

PAYMENTS ACCEPTED:
- ABA Pay, Wing Money, Cash on Delivery (COD)
- Visa / Mastercard, PayPal
- Installment plans available for orders over \$100

CANCELLATIONS:
- Cancel within 1 hour of placing the order (contact support immediately)
- After 1 hour, cancellation is not guaranteed if order is already packed

=====================
ORDER STATUS GUIDE
=====================
- Pending:          Order placed, awaiting confirmation
- Confirmed:        Confirmed, preparing for shipment
- Processing:       Being packed
- Shipped:          On its way
- Out for Delivery: Arriving today
- Delivered:        Successfully delivered
- Cancelled:        Order cancelled
- Refunded:         Refund has been processed

=====================
LIVE STORE DATA
=====================
{$storeData}

=====================
CONVERSATION RULES
=====================
- Always be polite, even if the customer is rude or frustrated
- Use plain text ONLY — no Markdown (no **, *, #, _, bullets, or dashes as list markers)
- Reply in the same language the customer uses — if they write in Khmer, reply fully in Khmer
- Keep replies short and focused (max 4–5 sentences unless detail is specifically needed)
- Never fabricate order details, prices, or policies — only use what is provided above
- If an order is not found, ask the customer to double-check the order number
- If a question is outside your scope, offer to connect them to a human agent
- Always end by offering further help
- Never share other customers' personal information under any circumstance
- If asked who made you or what AI you are, say: "I am Vendo Assistant, powered by {$storeName} to help you shop better."
- Do not discuss politics, religion, or any topics unrelated to the store

=====================
EXAMPLE RESPONSES
=====================

First greeting:
Hi {$customerName}! Welcome to {$storeName}. I am Vendo Assistant and I am happy to help you today. What can I do for you?

Order not found:
I am sorry, I could not find an order with that ID. Could you please double-check the order number? You can find it in your confirmation email.

Out of stock:
I am sorry, that item is currently out of stock. Would you like me to suggest something similar, or I can let you know when it becomes available again?

Handling a complaint:
I am really sorry to hear about your experience, {$customerName}. That is not the standard we hold ourselves to at {$storeName}. Let me look into this for you right away.

Escalation:
This needs attention from our team directly. Please email {$storeEmail} or call {$storePhone} during {$storeHours} and they will sort this out for you quickly.
PROMPT;
    }

    // =========================================================================
    // LIVE STORE DATA
    // =========================================================================

    /**
     * Pull only the data that is actually relevant to the current message.
     * Scopes order lookups to the authenticated user where possible.
     */
    private function getRelevantStoreData(string $message): string
    {
        $data = '';

        // ── Order lookup ──────────────────────────────────────────────────────
        // Only attempt a lookup when an explicit order number appears in the message
        preg_match('/\border[#:\s]*(\d{3,10})\b/i', $message, $matches);

        if (!empty($matches[1])) {
            $orderId = (int) $matches[1];
            $order = Order::with('items.product')->find($orderId);

            if ($order) {
                $items = $order->items->map(
                    fn($item) => "  - {$item->product->name} x{$item->quantity} (\${$item->price})"
                )->implode("\n");

                $data .= "\nORDER FOUND (#{$order->id}):\n"
                    . "- Order Number: {$order->order_number}\n"
                    . "- Status:       {$order->status}\n"
                    . "- Total:        \${$order->total_price}\n"
                    . "- Date:         {$order->created_at->format('M d, Y')}\n"
                    . "- Shipping To:  {$order->shipping_address}\n"
                    . "- Items:\n{$items}\n";
            } else {
                $data .= "\nORDER #{$orderId}: Not found in system.\n";
            }
        }

        // ── Product catalogue ─────────────────────────────────────────────────
        $productKeywords = ['product', 'item', 'buy', 'purchase', 'price', 'cost', 'available', 'stock', 'how much'];

        if ($this->matches(mb_strtolower($message), $productKeywords)) {
            $products = Product::select('id', 'name', 'price', 'stock', 'category', 'status')
                ->where('status', 'active')
                ->latest()
                ->limit(10)
                ->get();

            if ($products->isNotEmpty()) {
                $data .= "\nAVAILABLE PRODUCTS:\n";
                foreach ($products as $p) {
                    $stock = $p->stock > 0 ? "In Stock ({$p->stock})" : 'Out of Stock';
                    $data .= "- {$p->name} | \${$p->price} | {$p->category} | {$stock}\n";
                }
            }
        }

        return $data ?: 'No specific store data needed for this query.';
    }

    // =========================================================================
    // GEMINI API
    // =========================================================================

    /**
     * Call the Gemini API and return a clean plain-text reply.
     *
     * @throws \RuntimeException on API failure
     */
    private function callGemini(array $body): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is missing. Please check your .env file.');
        }

        $http = Http::timeout(30)->withHeaders(['Content-Type' => 'application/json']);

        // Only skip SSL verification in local development
        if (app()->isLocal()) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post("{$this->apiUrl}?key={$this->apiKey}", $body);

        if ($response->failed()) {
            $errorMsg = $response->json('error.message') ?? 'Unknown Gemini API error';
            $status = $response->status();

            Log::error("Gemini API failed [{$status}]", [
                'error' => $errorMsg,
                'request' => array_keys($body),
            ]);

            throw new \RuntimeException("Gemini API error ({$status}): {$errorMsg}");
        }

        $reply = $response->json('candidates.0.content.parts.0.text') ?? '';

        if (empty($reply)) {
            return 'I am sorry, I could not generate a response. Please try again.';
        }

        return $this->sanitizeReply($reply);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Strip Markdown formatting and normalise whitespace from the AI reply.
     * Targets only structural markdown tokens, not mid-word characters.
     */
    private function sanitizeReply(string $text): string
    {
        // Remove markdown headers
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        // Remove bold/italic markers (**text**, *text*, __text__, _text_)
        $text = preg_replace('/(\*{1,2}|_{1,2})(.*?)\1/', '$2', $text);
        // Remove inline code
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        // Remove blockquote markers
        $text = preg_replace('/^>\s+/m', '', $text);
        // Collapse multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Extract a maximum price from natural-language phrases like "under $50".
     */
    private function extractMaxPrice(string $message): ?float
    {
        if (preg_match('/(?:under|below|less than|cheaper than|max|maximum)\s*\$?\s*(\d+(?:\.\d{1,2})?)/i', $message, $m)) {
            return (float) $m[1];
        }
        return null;
    }

    /**
     * Extract a meaningful product search keyword from the message.
     * Returns null if no specific product category or name is detected.
     */
    private function extractProductKeyword(string $msg): ?string
    {
        $knownCategories = ['phone', 'laptop', 'tablet', 'shoes', 'shirt', 'bag', 'watch', 'headphone', 'camera', 'tv'];

        foreach ($knownCategories as $category) {
            if (str_contains($msg, $category)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Convert raw order status slugs into human-readable labels.
     */
    private function humanizeOrderStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending (awaiting confirmation)',
            'confirmed' => 'Confirmed and preparing for shipment',
            'processing' => 'Being packed',
            'shipped' => 'Shipped and on its way to you',
            'out_for_delivery' => 'Out for delivery — arriving today',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            default => ucfirst($status),
        };
    }
}