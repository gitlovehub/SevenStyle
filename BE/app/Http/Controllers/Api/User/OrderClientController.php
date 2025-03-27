<?php

namespace App\Http\Controllers\Api\User;

use App\Events\OrderEvent;
use App\Http\Controllers\Controller;

use App\Http\Requests\Admin\Order\StoreOrderRequest;
use App\Http\Requests\User\OrderClientRequest;
use App\Jobs\SendMailSuccessOrderJob;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\OrderStatusLog;
use App\Models\PaymentStatus;
use App\Models\ProductVariation;
use App\Models\RefundRequest;
use App\Models\Shipment;
use App\Models\ShippingLog;
use App\Models\ShippingStatus;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\GhnApiService;
use App\Services\OrderActionService;
use App\Services\OrderStatusFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PaymentVnpay;
use App\Services\TransactionFlowService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderClientController extends Controller
{
    protected $paymentVnpay;
    protected $ghn;

    public function __construct(PaymentVnpay $paymentVnpay, GhnApiService $ghn)
    {
        $this->paymentVnpay = $paymentVnpay;
        $this->ghn = $ghn;
    }
    //
    private function generateUniqueOrderCode()
    {
        do {
            // Lấy ngày hiện tại
            $date = now()->format('Ymd');

            // Tạo mã số ngẫu nhiên (6 ký tự)
            $randomCode = strtoupper(Str::random(6));

            // Tạo mã đơn hàng
            $codeOrder = "ORD-{$date}-{$randomCode}";

            // Kiểm tra xem mã có tồn tại trong database không
            $exists = Order::where('code', $codeOrder)->exists();
        } while ($exists); // Nếu trùng, tạo lại

        return $codeOrder;
    }
    //
    public function store(OrderClientRequest $request)
    {
        $totalWeight = 0;
        try {
            DB::beginTransaction();
            $validatedData = $request->validated();

            // Kiểm tra nếu không có sản phẩm trong đơn hàng
            if (empty($validatedData['products'])) {
                return response()->json([
                    'message' => 'Không có sản phẩm nào trong đơn hàng!'
                ], 400);
            }

            //
            $user = auth('sanctum')->user();
            $userId = $user ? $user->id : null;
            // Kiểm tra phương thức thanh toán
            if ($validatedData['payment_method'] == 'vnpay' && $validatedData['final_amount'] == 0) {
                return response()->json([
                    'message' => 'Thanh toán VNPay không hợp lệ (số tiền phải lớn hơn 0)!'
                ], 400);
            }

            // Tạo mã đơn hàng
            $orderCode = $this->generateUniqueOrderCode();

            // Tạo đơn hàng
            $dataOrder = [
                'user_id' => $userId,
                'code' => $orderCode,
                'total_amount' => $validatedData['total_amount'],
                'discount_amount' => $validatedData['discount_amount'] ?? 0,
                'final_amount' => $validatedData['final_amount'],
                'payment_method' => $validatedData['payment_method'],
                'shipping' => $validatedData['shipping'],
                'o_name' => strip_tags($validatedData['o_name']),
                'o_address' => strip_tags($validatedData['o_address']),
                'o_phone' => $validatedData['o_phone'],
                'o_mail' => $validatedData['o_mail'] ?? null,
                'note' => strip_tags($validatedData['note'] ?? ''),
                'order_status_id' => OrderStatus::idByCode('pending'),
                'payment_status_id' => PaymentStatus::idByCode('unpaid'),
                'shipping_status_id' => ShippingStatus::idByCode('not_created'),
            ];
            //
            $order = Order::create($dataOrder);
            //

            if (!$order) {
                DB::rollBack();
                return response()->json(['message' => 'Tạo đơn hàng thất bại!'], 500);
            }

            // Broadcast và Event
            event(new OrderEvent($order));
            broadcast(new OrderEvent($order));
            Log::info('Broadcast completed');
            // Lưu bảng trạng thái đơn hàng orderstatus
            OrderStatusFlowService::createInitialStatus($order);
            // Lưu bảng thanh toán
            $transaction = TransactionFlowService::create(
                [
                    'order' => $order,
                    'method' => $order->payment_method,
                    'type' => 'payment',
                    'amount' => $order->final_amount,
                    'status' => 'pending'
                ]
            );
            if (!$transaction) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Không thể tạo giao dịch do không đúng luồng xử lý!'
                ], 400);
            }
            // Lưu trạng thái đơn hàng shipping
            Shipment::create(
                [
                    'order_id' => $order->id,
                    'shipping_status_id' => ShippingStatus::idByCode('not_created'),
                    'shipping_fee' => $order->shipping,
                    'carrier' => 'ghn',
                    'from_estimate_date' => $validatedData['from_estimate_date'] ?? null,
                    'to_estimate_date' => $validatedData['to_estimate_date'] ?? null,
                ]
            );
            //
            $orderItems = [];

            foreach ($validatedData['products'] as $product) {
                $variant = ProductVariation::find($product['id']);
                $totalWeight += $product['weight'] * $product['quantity'];

                if (!$variant) {
                    DB::rollBack();
                    return response()->json(['message' => 'Sản phẩm không tồn tại!'], 400);
                }
                $variation = $variant->getFormattedVariation();
                // Kiểm tra tồn kho trước khi trừ
                if ($variant->stock_quantity < $product['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Sản phẩm "' . $product['name'] . '" không đủ hàng tồn kho!'
                    ], 400);
                }

                // Lưu sản phẩm vào order_itemss
                $orderItems[] = [
                    'order_id' => $order->id,
                    'product_id' => $product['product_id'],
                    'variation_id' => $product['id'] ?? null,
                    'weight' => $product['weight'],
                    'image' => $product['image_url'] ?? null,
                    'variation' => json_encode($variation),
                    'product_name' => strip_tags($product['name']),
                    'price' => $product['sale_price'] ?? $product['regular_price'], // Lấy giá khuyến mãi nếu có
                    'quantity' => $product['quantity'],
                ];

                // Giảm số lượng tồn kho
                $variant->decrement('stock_quantity', (int) $product['quantity']);
            }
            if ($totalWeight >= 20000) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Tổng cân nặng của đơn hạng đã vượt quá mức cho phép, vui lòng chia đơn hàng này thành 2 đơn nhỏ'
                ], 400);
            }
            // Thêm nhiều sản phẩm vào bảng `order_items`
            OrderItem::insert($orderItems);


            // Sau khi hoàn tất việc tạo đơn hàng và trước khi commit transaction
            if (isset($validatedData['voucher_code'])) {
                $voucher = Voucher::where('code', $validatedData['voucher_code'])->first();

                if ($voucher) {
                    // Chỉ tăng số lượt sử dụng sau khi đơn hàng được tạo thành công
                    if ($voucher->usage_limit && $voucher->times_used < $voucher->usage_limit) {
                        // Tăng số lần sử dụng ngay trước khi commit
                        $voucher->increment('times_used');
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Voucher đã đạt giới hạn số lần sử dụng!'
                        ], 400);
                    }
                }
            }

            // Gửi email xác nhận đơn hàng (background job)
            SendMailSuccessOrderJob::dispatch($order);
            DB::commit();
            //Xóa giỏ hhangf
            if ($userId) {
                try {
                    // Lấy danh sách cart_id của user
                    $cart = Cart::where('user_id', $userId)->first();

                    if ($cart) {
                        // Xóa các cart_items có trong đơn hàng
                        Log::info('Deleting cart items with variation_ids:', array_column($orderItems, 'variation_id'));

                        CartItem::where('cart_id', $cart->id)
                            ->whereIn('variation_id', array_column($orderItems, 'variation_id'))
                            ->delete();
                    }
                } catch (\Throwable $th) {
                    Log::error("Lỗi khi xóa cart_items cho user_id {$userId}: " . $th->getMessage());
                }
            }

            // Nếu phương thức thanh toán là VNPay, trả về URL thanh toán
            if ($order->payment_method == "vnpay") {
                $paymentUrl = $this->paymentVnpay->createPaymentUrl($order);
                return response()->json([
                    'message' => 'Thành công',
                    'url' => $paymentUrl,
                    'code' => 200
                ], 201);
            }

            return response()->json([
                'message' => 'Bạn đã thêm đơn hàng thành công!',
                'order_code' => $order->code,
                'user' => $user,
                'code' => 201
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi trong quá trình tạo đơn hàng!',
                'errors' => $th->getMessage(),
                'data' => $dataOrder
            ], 500);
        }
    }
    //Call bakc
    public function callbackPayment(Request $request)
    {
        $vnp_HashSecret = env('VNP_HASH_SECRET');
        $vnp_SecureHash = $request['vnp_SecureHash'];
        //
        $order = Order::where('code', $request['vnp_TxnRef'])->first();
        if (!$order) {
            Transaction::create([
                'order_id'               => null,
                'method'                 => 'vnpay',
                'type'                   => 'payment',
                'amount'                 => ($request['vnp_Amount'] ?? 0) / 100,
                'transaction_code'       => $request['vnp_TxnRef'],
                'vnp_transaction_no'     => $request['vnp_TransactionNo'] ?? null,
                'vnp_bank_code'          => $request['vnp_BankCode'] ?? null,
                'vnp_bank_tran_no'       => $request['vnp_BankTranNo'] ?? null,
                'vnp_card_type'          => $request['vnp_CardType'] ?? null,
                'vnp_pay_date'           => isset($request['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', $request['vnp_PayDate']) : null,
                'vnp_response_code'      => $request['vnp_ResponseCode'] ?? null,
                'vnp_transaction_status' => $request['vnp_TransactionStatus'] ?? null,
                'vnp_create_date'        => isset($request['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', $request['vnp_PayDate']) : null,
                'status'                 => 'failed',
                'note'                   => 'Không tìm thấy đơn hàng',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng!',
            ]);
        }
        // xác thực vnpay
        $data = array();
        foreach ($_GET as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $data[$key] = $value;
            }
        }

        unset($data['vnp_SecureHash']);
        ksort($data);
        $hashData = http_build_query($data, '', '&');
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        if ($secureHash !== $vnp_SecureHash) {
            Transaction::create([
                'order_id'               => $order?->id, // Có thể null nếu đơn ko tìm thấy
                'method'                 => 'vnpay',
                'type'                   => 'payment',
                'amount'                 => ($request['vnp_Amount'] ?? 0) / 100,
                'transaction_code'       => $request['vnp_TxnRef'],
                'vnp_transaction_no'     => $request['vnp_TransactionNo'] ?? null,
                'vnp_bank_code'          => $request['vnp_BankCode'] ?? null,
                'vnp_bank_tran_no'       => $request['vnp_BankTranNo'] ?? null,
                'vnp_card_type'          => $request['vnp_CardType'] ?? null,
                'vnp_pay_date'           => isset($request['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', $request['vnp_PayDate']) : null,
                'vnp_response_code'      => $request['vnp_ResponseCode'] ?? null,
                'vnp_transaction_status' => $request['vnp_TransactionStatus'] ?? null,
                'vnp_create_date'        => isset($request['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', $request['vnp_PayDate']) : null,
                'status'                 => 'failed',
                'note'                   => 'Xác thực chữ ký thất bại',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Sai chữ kí',
            ]);
        }
        // tránh tạo trùng đơn hàng
        $exists = Transaction::where('transaction_code', $request['vnp_TxnRef'])
            ->where('vnp_transaction_no', $request['vnp_TransactionNo'] ?? null)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => true,
                'message' => 'Giao dịch đã xử lý',
            ]);
        }

        $status = $request['vnp_ResponseCode'] === '00' ? 'success' : 'failed'; // xem nó thanh toán thành công hay thất bại

        Transaction::create([
            'order_id'               => $order->id,
            'method'                 => 'vnpay',
            'type'                   => 'payment',
            'amount'                 => ($request['vnp_Amount'] ?? 0) / 100,
            'transaction_code'       => $request['vnp_TxnRef'],
            'vnp_transaction_no'     => $request['vnp_TransactionNo'] ?? null,
            'vnp_bank_code'          => $request['vnp_BankCode'] ?? null,
            'vnp_bank_tran_no'       => $request['vnp_BankTranNo'] ?? null,
            'vnp_card_type'          => $request['vnp_CardType'] ?? null,
            'vnp_pay_date'           => isset($request['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', $request['vnp_PayDate']) : null,
            'vnp_response_code'      => $request['vnp_ResponseCode'] ?? null,
            'vnp_transaction_status' => $request['vnp_TransactionStatus'] ?? null,
            'vnp_create_date'        => isset($request['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', $request['vnp_PayDate']) : null,
            'status'                 => $status,
            'note'                   => $this->paymentVnpay->mapVnpResponseCode($request['vnp_ResponseCode'] ?? null),
        ]);

        if ($status === 'success') {
            if ($order->payment_status_id !== PaymentStatus::idByCode('paid')) {
                $order->update([
                    'payment_status_id' => PaymentStatus::idByCode('paid'),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Thanh toán thành công',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Thanh toán thất bại',
        ]);
    }






    //Lấy ra danh sách sản phẩm
    public function getOrdersForUser(Request $request)
    {
        try {
            $status = $request->get('status'); // query param
            $userId = auth('sanctum')->user()->id;

            $query = Order::with(['items', 'status', 'paymentStatus'])
                ->where('user_id', $userId);

            switch ($status) {
                case 'waiting_payment':
                    $query->where('payment_method', 'vnpay')
                        ->whereHas('paymentStatus', fn($q) => $q->where('code', 'unpaid'));
                    break;

                case 'pending':
                    $query->whereHas('status', fn($q) => $q->where('code', 'pending'));
                    break;

                case 'confirmed':
                    $query->whereHas('status', fn($q) => $q->where('code', 'confirmed'));
                    break;

                case 'shipping':
                    $query->whereHas('status', fn($q) => $q->where('code', 'shipping'));
                    break;

                case 'completed':
                    $query->whereHas('status', fn($q) => $q->where('code', 'completed'));
                    break;

                case 'closed':
                    $query->whereHas('status', fn($q) => $q->where('code', 'closed'));
                    break;

                case 'cancelled':
                    $query->whereHas('status', fn($q) => $q->where('code', 'cancelled'));
                    break;

                case 'refund':
                    $query->whereHas('status', function ($q) {
                        $q->whereIn('code', ['return_requested', 'return_approved', 'refunded']);
                    })->orWhereHas('refundRequest', function ($q) {
                        $q->whereIn('status', ['pending', 'approved', 'rejected', 'refunded']);
                    });
                    break;

                default:
                    // Không lọc
                    break;
            }

            $orders = $query->latest()->paginate(1);
            // Nó đỏ nhưng ko lỗi, nó chưa xác định được $orders có phải 1 collection hay không, 
            // ai thấy đỏ thì đừng hỏi bạn Hoàng nhé
            $data = $orders->map(function ($order) {
                return [
                    'code' => $order->code,
                    'status' => $order->status->name ?? '',
                    'status_code' => $order->status->code ?? '',
                    'payment_status' => $order->paymentStatus->name ?? '',
                    'payment_code' => $order->paymentStatus->code ?? '',
                    'created_at' => $order->created_at->format('d-m-Y H:i'),
                    'final_amount' => $order->final_amount,
                    'products' => $order->items->map(function ($item) {
                        return [
                            'name' => $item->product_name,
                            'image' => $item->image,
                            'price' => $item->price,
                            'quantity' => $item->quantity,
                            'variation' => json_decode($item->variation, true),
                        ];
                    }),
                ];
            });

            return response()->json([
                'message' => 'Success',
                'data' => [
                    'orders' => $data,
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'last_page' => $orders->lastPage(),
                        'per_page' => $orders->perPage(),
                        'total' => $orders->total(),
                    ],
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách đơn hàng!',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    //Tạo map các trạng thái hiển thị 
    public function getOrderStatuses()
    {
        $statuses = [
            ['code' => null, 'label' => 'Tất cả '],
            ['code' => 'waiting_payment', 'label' => 'Chờ thanh toán'],
            ['code' => 'pending', 'label' => 'Chờ xác nhận'],
            ['code' => 'confirmed', 'label' => 'Đã xác nhận'],
            ['code' => 'shipping', 'label' => 'Đang giao'],
            ['code' => 'completed', 'label' => 'Đã giao'],
            ['code' => 'closed', 'label' => 'Hoàn thành'],
            ['code' => 'cancelled', 'label' => 'Đã hủy'],
            ['code' => 'refund', 'label' => 'Trả hàng/Hoàn tiền'],
        ];
        return response()->json([
            'message' => 'Success',
            'data' => $statuses,
        ]);
    }


    //
    public function getOrderDetail($code)
    {
        try {
            $order = Order::with([
                'items',
                'status',
                'paymentStatus',
                'shippingStatus',
                'shipment.shippingLogsTimeline',
                'refundRequests',
                'statusLogs.fromStatus',
                'statusLogs.toStatus',
            ])->where('code', $code)->first();

            // Convert dữ liệu
            $data = [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'status' => $order->status->name,
                'payment_status' => $order->paymentStatus->name ?? null,
                'shipping_status' => $order->shippingStatus->name ?? null,
                'total_amount' => $order->total_amount,
                'final_amount' => $order->final_amount,
                'discount_amount' => $order->discount_amount,
                'shipping_fee' => $order->shipping,
                'payment_method' => $order->payment_method,
                'o_name' => $order->o_name,
                'o_phone' => $order->o_phone,
                'o_email' => $order->o_mail,
                'o_address' => $order->o_address,
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'image' => $item->image,
                        'variation' => $item->variation
                    ];
                }),
                'shipping_logs' => $order->shipment?->shippingLogs->map(function ($log) {
                    return [
                        'status' => $log->ghn_status,
                        'location' => $log->location,
                        'note' => $log->note,
                        'created_at' => $log->timestamp
                    ];
                }),
                'refund_requests' => $order->refundRequests->map(function ($refund) {
                    return [
                        'status' => $refund->status,
                        'reason' => $refund->reason,
                        'amount' => $refund->amount,
                        'approved_at' => optional($refund->approved_at),
                    ];
                }),
                'status_timelines' => $order->statusLogs->map(function ($log) {
                    return [
                        'from' => $log->fromStatus->name ?? null,
                        'to' => $log->toStatus->name ?? null,
                        'changed_at' => $log->changed_at,
                    ];
                }),
                'actions' => OrderActionService::availableActions($order, 'admin')
            ];

            return response()->json([
                'message' => 'Lấy chi tiết đơn hàng thành công!',
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Không thể lấy đơn hàng!',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    //Hủy đơn hàng

    public function cancel(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|exists:orders,code',
            'cancel_reason' => 'required|string|max:1000'
        ]);

        $order = Order::with('shipment')->where('code', $validated['code'])->firstOrFail();

        if (!in_array($order->status->code, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Không thể hủy đơn hàng ở trạng thái hiện tại!'], 400);
        }

        DB::beginTransaction();

        try {
            // Cập nhật trạng thái đơn hàng & shipping
            $fromStatusId = $order->order_status_id;
            $cancelStatusId = OrderStatus::idByCode('cancelled');
            $cancelStatusShipId = ShippingStatus::idByCode('cancelled');

            $order->update([
                'shipping_status_id' => $cancelStatusShipId,
                'order_status_id' => $cancelStatusId,
                'cancel_reason' => $validated['cancel_reason'],
                'cancel_by' => 'user',
                'cancelled_at' => now()
            ]);

            // Ghi log trạng thái
            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $cancelStatusId,
                'changed_by' => 'user',
                'changed_at' => now(),
            ]);

            // Nếu thanh toán online (VNPAY) & đã thanh toán
            if ($order->payment_method === 'vnpay' && $order->payment_status->code === 'paid') {
                // Tạo bản ghi refund_requests
                RefundRequest::create([
                    'order_id' => $order->id,
                    'type' => 'not_received',
                    'reason' => $validated['cancel_reason'],
                    'amount' => $order->final_amount,
                    'status' => 'approved',
                    'approved_by' => 'system',
                    'approved_at' => now()
                ]);

                // Lấy transaction gốc (thanh toán thành công)
                $paymentTransaction = $order->transactions()
                    ->where('method', 'vnpay')
                    ->where('type', 'payment')
                    ->where('status', 'success')
                    ->latest()
                    ->first();

                // Tạo transaction hoàn tiền mới (trạng thái pending)
                $refundTransaction = Transaction::create([
                    'order_id' => $order->id,
                    'method' => 'vnpay',
                    'type' => 'refund',
                    'amount' => $order->final_amount,
                    'status' => 'pending',
                    'note' => 'Yêu cầu hoàn tiền khi khách huỷ đơn chưa nhận hàng',
                    'created_at' => now()
                ]);

                // Chuẩn bị dữ liệu gọi API hoàn tiền
                $refundData = [
                    'txn_ref'    => $paymentTransaction->transaction_code,
                    'amount'     => $paymentTransaction->amount,
                    'txn_date'   => optional($paymentTransaction->vnp_pay_date)->format('YmdHis'),
                    'txn_no'     => $paymentTransaction->vnp_transaction_no,
                    'type'       => '02',
                    'create_by'  => 'system',
                    'ip'         => $request->ip(),
                    'order_info' => 'Khách huỷ đơn hàng chưa nhận'
                ];

                // Gọi API hoàn tiền
                $result = $this->paymentVnpay->refundTransaction($refundData);

                // Xử lý kết quả hoàn tiền
                if (isset($result['vnp_ResponseCode']) && $result['vnp_ResponseCode'] === '00') {
                    $order->update([
                        'payment_status_id' => PaymentStatus::idByCode('refunded'),
                    ]);
                }
                Transaction::create([
                    'order_id'               => $order->id,
                    'method'                 => 'vnpay',
                    'type'                   => 'refund',
                    'amount'                 => $order->final_amount,
                    'status'                 => $result['success'] ? 'success' : 'failed',
                    'transaction_code'       => $order->code,
                    'vnp_transaction_no'     => $result['response_data']['vnp_TransactionNo'] ?? null,
                    'vnp_bank_code'          => $result['response_data']['vnp_BankCode'] ?? null,
                    'vnp_response_code'      => $result['response_data']['vnp_ResponseCode'] ?? null,
                    'vnp_transaction_status' => $result['response_data']['vnp_TransactionStatus'] ?? null,
                    'vnp_refund_request_id'  => $result['response_data']['vnp_ResponseId'] ?? null,
                    'vnp_pay_date'           => isset($result['response_data']['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', $result['response_data']['vnp_PayDate']) : now(),
                    'vnp_create_date'        => now(),
                    'note'                   => $result['error'] ?? 'Hoàn tiền thành công từ VNPAY',
                ]);
            }

            // Nếu đã có vận đơn GHN
            if (!in_array($order->shipping_status->code, ['not_created', 'cancelled'])) {
                $dataCancelOrderGhn[] = $order->shipment->shipping_code;
            }

            DB::commit();
            return response()->json(['message' => 'Đơn hàng đã được hủy.']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Cancel Order Error: ' . $th->getMessage());
            return response()->json(['message' => 'Lỗi khi hủy đơn hàng!'], 500);
        }
    }



    /**
     * Yêu cầu hoàn hàng / hoàn tiền
     */
    public function requestRefund(Request $request, $code)
    {
        $request->validate([
            'reason' => 'required|string',
            'type' => 'required|in:not_received,return_after_received',
        ]);

        $order = Order::where('code', $code)->firstOrFail();

        if (!in_array($order->status->code, ['shipping', 'completed'])) {
            return response()->json(['message' => 'Không thể yêu cầu hoàn tiền ở trạng thái hiện tại.'], 400);
        }

        DB::beginTransaction();

        try {
            RefundRequest::create([
                'order_id' => $order->id,
                'type' => $request->type,
                'reason' => $request->reason,
                'amount' => $order->final_amount,
                'status' => 'pending',
            ]);

            $fromStatusId = $order->order_status_id;
            $toStatusId = OrderStatus::idByCode('return_requested');
            $order->update(['order_status_id' => $toStatusId]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatusId,
                'changed_by' => 'user',
                'changed_at' => now(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Đã gửi yêu cầu hoàn hàng thành công.']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Refund Request Error: ' . $th->getMessage());
            return response()->json(['message' => 'Lỗi khi gửi yêu cầu hoàn hàng!'], 500);
        }
    }

    // Thanh toán lại
    public function retryPaymentVnpay(Request $request)
    {
        $orderCode = $request->input('code');

        $order = Order::where('code', $orderCode)->first();
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        if ($order->payment_method !== 'vnpay') {
            return response()->json(['message' => 'Đơn hàng không sử dụng phương thức thanh toán VNPAY'], 400);
        }

        if ($order->payment_status_id === PaymentStatus::idByCode('paid')) {
            return response()->json(['message' => 'Đơn hàng đã được thanh toán thành công'], 400);
        }
        $paymentUrl = $this->paymentVnpay->createPaymentUrl($order);
        return response()->json([
            'message' => 'Thành công',
            'url' => $paymentUrl,
            'code' => 200
        ], 201);
    }
}
