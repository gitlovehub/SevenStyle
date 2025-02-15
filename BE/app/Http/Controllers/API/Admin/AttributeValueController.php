<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AttributeValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $id)
    {
        //
        try {
            //code...
            $attribute = Attribute::with('values')->findOrFail($id);
            return response()->json($attribute, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Không tìm thấy thuộc tính'], 404);
        } catch (\Throwable $th) {
            // throw $th;
            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $th->getMessage()

            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            //code...
            DB::beginTransaction();
            $data = $request->validate(
                [
                    "name" => "required|max:100",
                    "attribute_id"=>["required",Rule::exists('attributes', 'id')]
                ]
            );
            $attribute_value = AttributeValue::create($data);
            DB::commit();
            return response()->json($attribute_value, 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
            return response()->json([
                "message" => "Lỗi hệ thống",
                "error" => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        try {
            //code...
            $attribute = AttributeValue::select('id', 'name')->findOrFail($id);
            return response()->json($attribute, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Không tìm thấy giá trị thuộc tính'], 404);
        } catch (\Throwable $th) {
            // throw $th;
            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $th->getMessage()

            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            DB::beginTransaction();

            $data = $request->validate([
                "name" => "required|max:100",
            ]);

            $attribute_value = AttributeValue::findOrFail($id);
            $attribute_value->update($data);

            DB::commit();
            return response()->json($attribute_value, 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Lỗi nhập dữ liệu",
                    'error' => $e->getMessage()
                ],
                422
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
            return response()->json(["message" => "Lỗi",'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();
            $attribute_value = AttributeValue::findOrFail($id);
            //Xóa
            $attribute_value->delete();
            //Nếu thành công
            DB::commit();
            return response()->json(['message' => 'Giá trị thuộc tính đã được chuyển vào thùng rác'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Giá trị thuộc tính không tồn tại'], 404);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
