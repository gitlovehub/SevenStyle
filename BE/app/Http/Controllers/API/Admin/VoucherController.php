<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Events\VoucherUpdated;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $vouchers = Voucher::paginate(15);
            return response()->json($vouchers, 200);
        } catch (\Throwable $th) {
            return response()->json(["message" => "Lỗi", 500]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'code' => 'required|unique:vouchers',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'discount_percent' => 'nullable|integer|required_without:amount',
                'amount' => 'nullable|integer|required_without:discount_percent',
                'max_discount_amount' => 'required|integer',
                'min_product_price' => 'required|integer',
                'usage_limit' => 'required|integer',
                'expiry_date' => 'required|date',
                'start_date' => 'required|date',
            ]);

            $voucher = Voucher::create($data);

            // Phát sự kiện
            // broadcast(new VoucherUpdated($voucher))->toOthers();

            return response()->json($voucher, 201);
        } catch (ValidationException $e) {
            return response()->json(["message" => "Nhập đầy đủ và đúng thông tin", "errors" => $e->errors()], 422);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json(['message' => 'Có lỗi xảy ra khi thêm voucher', 'error' => $th->getMessage()],  500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $code)
    {
        try {
            $voucher = Voucher::where('code', $code)->firstOrFail();
            return response()->json($voucher, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Voucher không tồn tại'], 404);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json(['message' => 'Có lỗi xảy ra'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'code' => 'required',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'discount_percent' => 'nullable|integer|required_without:amount',
                'amount' => 'nullable|integer|required_without:discount_percent',
                'max_discount_amount' => 'required|integer',
                'min_product_price' => 'required|integer',
                'usage_limit' => 'required|integer',
                'expiry_date' => 'required|date',
                'start_date' => 'required|date',
            ]);

            $voucher = Voucher::findOrFail($id);
            $voucher->update($data);

            // Phát sự kiện nếu cần
            // broadcast(new VoucherUpdated($voucher))->toOthers();

            return response()->json($voucher, 200);
        } catch (ValidationException $e) {
            return response()->json(["message" => "Nhập đầy đủ và đúng thông tin", "errors" => $e->errors()], 422);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json(['error' => 'Lỗi cập nhật: ' . $th->getMessage()], 500);
        }
    }





    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $code)
    {
        try {
            $voucher = Voucher::where('code', $code)->firstOrFail();
            $voucher->delete();

            // Phát sự kiện
            // broadcast(new VoucherUpdated($voucher))->toOthers();

            return response()->json(['message' => 'Voucher đã được xóa'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Voucher không tồn tại'], 404);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json(['message' => 'Có lỗi xảy ra'], 500);
        }
    }
}
