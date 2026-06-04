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
    private ?string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY') ?? '';
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
            $message = $request->message;
            $customerName = $request->input('customer_name', 'Customer');

            // 🔥 STEP 1: Detect intent
            $intent = $this->detectIntent($message);

            // 🔥 STEP 2: Route request
            if ($intent === 'order') {
                $reply = $this->handleOrder($request);
            } elseif ($intent === 'product') {
                $reply = $this->handleProduct($message);
            } else {
                // AI fallback (Gemini)
                $systemPrompt = $this->buildSystemPrompt($customerName, $message);

                $contents = [];

                foreach ($request->input('history', []) as $item) {
                    $contents[] = [
                        'role' => $item['role'],
                        'parts' => [['text' => $item['text']]]
                    ];
                }

                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $message]]
                ];

                $reply = $this->callGemini([
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]]
                    ],
                    'contents' => $contents
                ]);
            }

            // Update history
            $updatedHistory = $request->input('history', []);
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build system prompt with real store data
     */
    private function buildSystemPrompt(string $customerName, string $message): string
    {
        $storeData = $this->getRelevantStoreData($message);
        $currentTime = now()->format('l, d M Y H:i');

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
            - Always end with offering further help, for example: Is there anything else I can help you with?
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
            I am sorry, I could not find an order with that ID. Could you please double-check the order number? You can find it in your confirmation email. I am happy to help once I have the correct order ID.
 
            Out of stock:
            I am sorry, that item is currently out of stock. Would you like me to suggest a similar product, or I can notify you when it becomes available again?
 
            Complaint handling:
            I am really sorry to hear about your experience, {$customerName}. That is not the standard we aim for at Vendo. Let me look into this for you right away and make sure it gets resolved as quickly as possible.
 
            Escalation:
            This issue needs attention from our team directly. Please email us at support@vendo.com or call +855 xx xxx xxx during Mon-Fri 9AM-6PM and we will take care of you personally.
        ";
    }

    /**
     * Fetch relevant store data based on message
     */
    private function getRelevantStoreData(string $message): string
    {
        $data = '';
        $messageLower = strtolower($message);

        // Check if message contains an order ID (e.g. #1234 or order 1234)
        preg_match('/\b(?:order[#\s]*)?(\d{3,10})\b/i', $message, $matches);

        if (!empty($matches[1])) {
            $orderId = $matches[1];
            $order = Order::with('items.product')->find($orderId);

            if ($order) {
                $items = $order->items->map(
                    fn($item) =>
                    "  - {$item->product->name} x{$item->quantity} (\${$item->price})"
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
        if (
            str_contains($messageLower, 'product') ||
            str_contains($messageLower, 'item') ||
            str_contains($messageLower, 'buy') ||
            str_contains($messageLower, 'price') ||
            str_contains($messageLower, 'available') ||
            str_contains($messageLower, 'stock')
        ) {
            $products = Product::select('id', 'name', 'price', 'stock', 'description')
                ->where('status', 'active')
                ->latest()
                ->take(10)
                ->get();

            if ($products->isNotEmpty()) {
                $data .= "\nAVAILABLE PRODUCTS:\n";
                foreach ($products as $product) {
                    $stock = $product->stock > 0 ? "In Stock ({$product->stock})" : 'Out of Stock';
                    $data .= "- {$product->name} | \${$product->price} | {$stock}\n";
                }
            }
        }

        return $data ?: 'No specific store data needed for this query.';
    }
    private function handleOrder(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return "Please login so I can check your order history.";
        }

        $order = Order::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$order) {
            return "You don't have any orders yet.";
        }

        return "Your latest order #{$order->order_number} is currently {$order->status}. Total: {$order->total_amount}.";
    }
    private function handleProduct(Request $request)
    {
        $message = strtolower($request->message);

        $products = Product::query()
            ->when(str_contains($message, 'phone'), function ($q) {
                $q->where('category', 'phone');
            })
            ->when(str_contains($message, 'laptop'), function ($q) {
                $q->where('category', 'laptop');
            })
            ->take(3)
            ->get();

        if ($products->isEmpty()) {
            return "I couldn't find products matching your request.";
        }

        $response = "Here are some products you may like:\n";

        foreach ($products as $p) {
            $response .= "- {$p->name} (${$p->price})\n";
        }

        return $response;
    }
    private function detectIntent(string $message): string
    {
        $message = strtolower($message);

        return match (true) {
            str_contains($message, 'last order'),
            str_contains($message, 'my order'),
            str_contains($message, 'order status'),
            str_contains($message, 'where is my order') => 'order',

            str_contains($message, 'buy'),
            str_contains($message, 'phone'),
            str_contains($message, 'laptop'),
            str_contains($message, 'product'),
            str_contains($message, 'price') => 'product',

            str_contains($message, 'refund'),
            str_contains($message, 'return'),
            str_contains($message, 'cancel') => 'support',

            default => 'ai',
        };
    }

    /**
     * Shared Gemini API caller
     */
    private function callGemini(array $body): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is missing. Check your .env file.');
        }

        $response = Http::withoutVerifying()
            ->timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->apiUrl}?key={$this->apiKey}", $body);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? 'Unknown Gemini API error';
            $status = $response->status();
            Log::error("Gemini API failed [{$status}]: {$error}");
            throw new \Exception("Gemini API error ({$status}): {$error}");
        }

        $reply = $response->json('candidates.0.content.parts.0.text') ?? 'No response.';

        // Remove markdown and clean up line breaks
        return trim(preg_replace('/\n+/', ' ', $reply));
    }
}