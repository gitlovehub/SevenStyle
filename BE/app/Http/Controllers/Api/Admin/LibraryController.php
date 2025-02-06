<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Library;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        try {
            //code...
            $publicIds = Library::latest()->paginate(12);
            foreach($publicIds as $key=>$value){
                $url = cloudinary()->getUrl($value['public_id']);
                $publicIds[$key]['url'] = $url;
            }
            return response()->json($publicIds,200);
        } catch (\Throwable $th) {
            //throw $th;
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
        $request->validate([
            'images' => 'required|array',
        ]); 
    
        $validImages = [];
        $invalidImages = [];
            foreach ($request->file('images') as $image) {
                if ($image->isValid()) { 
                    if ($image->extension() && in_array($image->extension(), ['jpeg', 'png', 'jpg'])) { 
                        if ($image->getSize() <= 5120 * 1024) {  // 5MB in bytes
                            try {
                                $publicId = cloudinary()->upload($image->getRealPath())->getPublicId();
    
                                $library = Library::create([
                                    'public_id' => $publicId,
                                ]);
    
                                $validImages[] = [
                                    'success' => $image->getClientOriginalName() . " đã upload thành công",
                                    
                                ];
                            } catch (\Exception $e) {
                                $invalidImages[] = [
                                    'file' => $image->getClientOriginalName(),
                                    'error' => $e->getMessage()
                                ];
                            }
                        } else {
                            $invalidImages[] = [
                                'error' => $image->getClientOriginalName().'đã upload thất bại do file lớn hơn 5Mb.'
                            ];
                        }
                    } else {
                        $invalidImages[] = [
                            'error' => $image->getClientOriginalName() . ' upload thất bại do không thuộc type: jpg,jpeg,png.',
                            
                        ];
                    }
                } else {
                    $invalidImages[] = [
                        
                        'error' => $image->getClientOriginalName().'đã upload thất bại do file không hợp lệ'
                    ];
                }
            }
    
        return response()->json([
            'success' => $validImages,
            'errors' => $invalidImages
        ]);
    } catch (\Throwable $th) {
        //throw $th;
        return response()->json([
            'message' => 'Lỗi hệ thống',
            'error' => $th->getMessage()

        ], 500);
    }

}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        try {
            //code...
            $image = Library::findOrFail($id);
            Cloudinary::destroy($image['public_id']);
            $image->delete();
            return response()->json(['message'=>'Xóa ảnh thành công'],200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => 'Lỗi hệ thống',
                'error' => $th->getMessage()

            ], 500);
        }

    }
}
