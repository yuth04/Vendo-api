<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    private const MAX_HISTORY = 20;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
    }

    /**
     * Main customer support chat
     * POST /api/v1/admin/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
            'history.*.role' => 'required|in:user,model',
            'history.*.name' => 'nullable|string',
            'history.*.text' => 'required|string',
            'customer_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $message = $request->input('message');
            $customerName = $request->input('customer_name', 'Customer');

            // Cap history to avoid hitting context limits
            $history = array_slice($request->input('history', []), -self::MAX_HISTORY);

            // Detect intent and route
            $intent = $this->detectIntent($message);

            $reply = match ($intent) {
                'order' => $this->handleOrder($request),
                'product' => $this->handleProduct($message),
                'support' => $this->handleSupport($message, $customerName, $history),
                default => $this->handleAI($message, $customerName, $history),
            };

            // Update history
            $updatedHistory = $history;
            $updatedHistory[] = ['role' => 'user', 'name' => $customerName, 'text' => $message];
            $updatedHistory[] = ['role' => 'model', 'name' => 'Vendo Assistant', 'text' => $reply];

            return response()->json([
                'success' => true,
                'reply' => $reply,
                'history' => $updatedHistory,
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    /**
     * Detect the intent from the customer message
     */
    private function detectIntent(string $message): string
    {
        $msg = strtolower($message);

        return match (true) {
            // Order intent
            str_contains($msg, 'last order'),
            str_contains($msg, 'my order'),
            str_contains($msg, 'order status'),
            str_contains($msg, 'where is my order') => 'order',

            // Product intent
            str_contains($msg, 'buy'),
            str_contains($msg, 'color'),
            str_contains($msg, 'sold'),
            str_contains($msg, 'product'),
            str_contains($msg, 'price'),
            str_contains($msg, 'under'),
            str_contains($msg, 'below'),
            str_contains($msg, 'cheap'),
            str_contains($msg, 'affordable'),
            str_contains($msg, 'recommend'),
            str_contains($msg, 'suggest') => 'product', // ← was missing

            // Support intent
            str_contains($msg, 'refund'),
            str_contains($msg, 'return'),
            str_contains($msg, 'cancel') => 'support',

            default => 'ai',
        };
    }

    /**
     * Handle order intent — requires authenticated user
     */
    private function handleOrder(Request $request): string
    {
        $user = $request->user();

        if (!$user) {
            return 'Please log in so I can check your order history.';
        }

        $order = Order::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$order) {
            return "You don't have any orders yet. Is there anything else I can help you with?";
        }

        return "Your latest order #{$order->id}.Order number:{$order->order_number} is currently {$order->status}. Total: \${$order->total_price}. Is there anything else I can help you with?";
    }

    /**
     * Handle product intent — search products by keyword
     */
    private function handleProduct(string $message): string
    {
        $msg = strtolower($message);

        // Extract price filter e.g. "under $50", "below $100", "less than $200"
        $maxPrice = null;
        if (preg_match('/(?:under|below|less than|cheaper than)\s*\$?\s*(\d+)/i', $message, $m)) {
            $maxPrice = (float) $m[1];
        }

        $products = Product::select('id', 'name', 'price', 'stock', 'category', 'status')
            ->where('status', 'active')
            ->when($maxPrice, fn($q) => $q->where('price', '<=', $maxPrice))
            ->when(str_contains($msg, 'phone'), fn($q) => $q->where('category', 'phone'))
            ->when(str_contains($msg, 'laptop'), fn($q) => $q->where('category', 'laptop'))
            ->orderBy('price', 'asc')
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            $priceNote = $maxPrice ? " under \${$maxPrice}" : '';
            return "I couldn't find any products{$priceNote} at the moment. Is there anything else I can help you with?";
        }

        $lines = $products->map(function ($p) {
            $name = $p->name ?? 'Unknown Product';
            $price = $p->price ?? '0.00';
            $stock = $p->stock > 0 ? 'In Stock' : 'Out of Stock';
            return "- {$name} (\${$price}) — {$stock}";
        })->join("\n");

        $priceNote = $maxPrice ? " under \${$maxPrice}" : '';

        return "Here are some products{$priceNote}:\n{$lines}\n\nIs there anything else I can help you with?";
    }

    /**
     * Handle support intent (refund, return, cancel) via AI
     */
    private function handleSupport(string $message, string $customerName, array $history): string
    {
        return $this->handleAI($message, $customerName, $history);
    }

    /**
     * Handle general AI fallback via Gemini
     */
    private function handleAI(string $message, string $customerName, array $history): string
    {
        $systemPrompt = $this->buildSystemPrompt($customerName, $message);

        $contents = [];

        foreach ($history as $item) {
            $contents[] = [
                'role' => $item['role'],
                'parts' => [['text' => $item['text']]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        return $this->callGemini([
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
        ]);
    }

    /**
     * Build system prompt with real store data
     */
    private function buildSystemPrompt(string $customerName, string $message): string
    {
        $storeData = $this->getRelevantStoreData($message);
        $currentTime = now()->timezone('Asia/Phnom_Penh')->format('l, d M Y H:i');

        return "
            You are Vendo Assistant, a friendly, professional, and knowledgeable customer support chatbot for Vendo Online Store.
            You are chatting with: {$customerName}.
            Current date and time: {$currentTime} (Phnom Penh, Cambodia time)

            =====================
            STORE INFORMATION
            =====================
            - Store Name: Vendo Online Store
            - Website: https://vendo-ecommerce-two.vercel.app
            - Location: Phnom Penh, Cambodia
            - Support Email: support@vendo.com
            - Support Phone: +855 xx xxx xxx
            - Support Hours: Monday to Friday, 9:00 AM - 6:00 PM (Cambodia Time)
            - Social Media: Facebook: fb.com/vendo, Instagram: @vendostore

            =====================
            YOUR PERSONALITY
            =====================
            - Friendly, warm, and welcoming
            - Professional but not robotic
            - Patient and empathetic especially with frustrated customers
            - Concise, never give unnecessarily long answers
            - Proactive, always offer next steps or additional help
            - Use the customer name ({$customerName}) occasionally to make it personal

            =====================
            YOUR RESPONSIBILITIES
            =====================
            - Greet customers warmly when they first message
            - Help customers track and check their order status
            - Answer questions about products, availability, and pricing
            - Explain store policies clearly (shipping, returns, payments)
            - Handle complaints calmly and empathetically
            - Suggest popular or related products when relevant
            - Escalate to human agent when issue is too complex
            - Collect feedback at end of conversation

            =====================
            STORE POLICIES
            =====================

            SHIPPING:
            - Standard Delivery: 3-5 business days (Free for orders over \$50)
            - Express Delivery: 1-2 business days (\$5 fee)
            - Same Day Delivery: Available in Phnom Penh only (\$8 fee, order before 12PM)
            - International Shipping: Not available yet

            RETURNS AND REFUNDS:
            - 30-day return policy for unused and unopened items
            - Items must be in original packaging
            - Refund processed within 3-5 business days after item received
            - Non-returnable: Digital products, Sale items, Intimate wear
            - To start a return, email support@vendo.com with order ID

            PAYMENTS:
            - ABA Pay
            - Wing Money
            - Cash on Delivery (COD)
            - Visa / Mastercard
            - PayPal
            - Installment plans available for orders over \$100

            CANCELLATIONS:
            - Orders can be cancelled within 1 hour of placing
            - After 1 hour, cancellation is not guaranteed if order is already packed
            - To cancel, contact support immediately with order ID

            =====================
            ORDER STATUS MEANINGS
            =====================
            - Pending: Order placed, waiting for confirmation
            - Confirmed: Order confirmed, preparing for shipment
            - Processing: Order is being packed
            - Shipped: Order is on the way
            - Out for Delivery: Order will arrive today
            - Delivered: Order has been delivered
            - Cancelled: Order has been cancelled
            - Refunded: Refund has been processed

            =====================
            LIVE STORE DATA
            =====================
            {$storeData}

            =====================
            CONVERSATION RULES
            =====================
            - Always be polite and professional even if customer is rude
            - Use plain text only, never use Markdown like **, *, #, _, or bullet symbols
            - Reply in the same language the customer uses (Khmer or English)
            - If customer writes in Khmer, reply fully in Khmer
            - Keep replies short and clear, maximum 5 sentences unless more detail is needed
            - Never guess or make up order details, product prices, or store information
            - If order is not found, politely ask customer to double check the order ID
            - If question is outside your knowledge, offer to connect to a human agent
            - Always end with offering further help
            - Never share other customers personal information
            - If customer seems angry or frustrated, apologize first before giving information
            - Do not discuss politics, religion, or topics unrelated to the store
            - If asked who made you or what AI you are, say: I am Vendo Assistant, here to help you shop better

            =====================
            EXAMPLE RESPONSES
            =====================

            Greeting:
            Hi {$customerName}! Welcome to Vendo Online Store. I am Vendo Assistant and I am happy to help you today. What can I do for you?

            Order not found:
            I am sorry, I could not find an order with that ID. Could you please double-check the order number? You can find it in your confirmation email.

            Out of stock:
            I am sorry, that item is currently out of stock. Would you like me to suggest a similar product, or I can notify you when it becomes available again?

            Complaint handling:
            I am really sorry to hear about your experience, {$customerName}. That is not the standard we aim for at Vendo. Let me look into this for you right away.

            Escalation:
            This issue needs attention from our team directly. Please email us at support@vendo.com or call +855 xx xxx xxx during Mon-Fri 9AM-6PM.
        ";
    }

    /**
     * Fetch relevant store data based on message content
     */
    private function getRelevantStoreData(string $message): string
    {
        $data = '';
        $messageLower = strtolower($message);

        // Require 'order' keyword to avoid false positives like "$1234"
        preg_match('/\border[#\s]*(\d{3,10})\b/i', $message, $matches);

        if (!empty($matches[1])) {
            $orderId = $matches[1];
            $order = Order::with('items.product')->find($orderId);

            if ($order) {
                $items = $order->items->map(
                    fn($item) => "  - {$item->product->name} x{$item->quantity} (\${$item->price})"
                )->join("\n");

                $data .= "
ORDER FOUND (ID: #{$order->id}):
- Status: {$order->status}
- Total: \${$order->total}
- Date: {$order->created_at->format('M d, Y')}
- Items:
{$items}
- Shipping Address: {$order->shipping_address}
                ";
            } else {
                $data .= "\nORDER #{$orderId}: Not found in system.\n";
            }
        }

        // Include products if customer asks about products
        $productKeywords = ['product', 'item', 'buy', 'price', 'available', 'stock'];
        $asksAboutProduct = collect($productKeywords)
            ->contains(fn($kw) => str_contains($messageLower, $kw));

        if ($asksAboutProduct) {
            $products = Product::select('id', 'name', 'price', 'stock', 'status')
                ->where('status', 'active')
                ->latest()
                ->take(10)
                ->get();

            if ($products->isNotEmpty()) {
                $data .= "\nAVAILABLE PRODUCTS:\n";
                foreach ($products as $product) {
                    $name = $product->name ?? 'Unknown Product';
                    $price = $product->price ?? '0.00';
                    $stock = $product->stock > 0
                        ? "In Stock ({$product->stock})"
                        : 'Out of Stock';
                    $data .= "- {$name} | \${$price} | {$stock}\n";
                }
            }
        }

        return $data ?: 'No specific store data needed for this query.';
    }

    /**
     * Call Gemini API and return plain text reply
     */
    private function callGemini(array $body): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is missing. Check your .env file.');
        }

        $http = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json']);

        // Disable SSL verification only in local environment
        if (app()->isLocal()) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post("{$this->apiUrl}?key={$this->apiKey}", $body);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? 'Unknown Gemini API error';
            $status = $response->status();
            Log::error("Gemini API failed [{$status}]: {$error}");
            throw new \Exception("Gemini API error ({$status}): {$error}");
        }

        $reply = $response->json('candidates.0.content.parts.0.text') ?? 'No response.';

        // Strip markdown symbols and collapse extra whitespace
        $reply = preg_replace('/[*_#`>~]+/', '', $reply);
        $reply = preg_replace('/\n+/', ' ', $reply);

        return trim($reply);
    }
}