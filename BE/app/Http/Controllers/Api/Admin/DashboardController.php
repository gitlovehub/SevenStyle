<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\OrderItem;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $statisticBy = $request->query('statisticBy', '7day'); // Mặc định là 7 ngày
        $salesData = $this->getSalesStatistics($statisticBy);
        $topSellingProducts = $this->getTopSellingProducts();
        $productByCategory = $this->getProductByCategory();
        $ratingStatistics = $this->getRatingStatistics();
        $topRatedProducts = $this->getTopRatedProducts();

        return response()->json([
            "status" => "success",
            "message" => "Lấy dữ liệu dashboard thành công!",
            "data" => [
                "totalCategories" => Category::count(),
                "totalProducts" => DB::table('products')->count(),
                "totalUsers" => DB::table('users')->count(),
                "totalVouchers" => DB::table('vouchers')->count(),
                "topSellingProducts" => $topSellingProducts,
                "salesStatistics" => $salesData,
                "ratingStatistics" => $ratingStatistics,
                "productByCategory" => $productByCategory,
                "topRatedProducts" => $topRatedProducts,
                "statisticBy" => $statisticBy
            ]
        ], 200);
    }

    // Thống kê số lượng sản phẩm theo danh mục
    private function getProductByCategory()
    {
        return Category::select(
            'categories.id',
            'categories.name',
            DB::raw('COALESCE(COUNT(product_category_relations.product_id), 0) as total_products')
        )
            ->leftJoin('product_category_relations', 'categories.id', '=', 'product_category_relations.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_products')
            ->get();
    }

    // Thống kê số lượng đánh giá theo từng mức rating
    private function getRatingStatistics()
    {
        return Comment::select('rating', DB::raw('COUNT(*) as total_reviews'))
            ->groupBy('rating')
            ->orderByDesc('rating')
            ->get();
    }

    // Lấy top 5 sản phẩm được đánh giá cao nhất
    private function getTopRatedProducts()
    {
        return Product::select(
            'products.id',
            'products.name',
            'products.rating as avg_rating', // Lấy rating từ bảng products
            DB::raw('COUNT(comments.id) as total_reviews') // Lấy số lượng đánh giá từ bảng comments
        )
            ->leftJoin('comments', function ($join) {
                $join->on('products.id', '=', 'comments.product_id')
                    ->where('comments.is_active', 1); // Chỉ lấy đánh giá đã duyệt
            })
            ->groupBy('products.id', 'products.name', 'products.rating')
            ->orderByDesc('products.rating') // Sắp xếp theo rating từ bảng products
            ->orderByDesc(DB::raw('COUNT(comments.id)')) // Nếu rating giống nhau, ưu tiên sản phẩm có nhiều đánh giá hơn
            ->take(5)
            ->get();
    }


    // Thống kê doanh số bán hàng theo thời gian
    private function getSalesStatistics($period)
    {
        $query = OrderItem::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(quantity) as totalSales')
        )
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        switch ($period) {
            case '7day':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '1month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
            case '6month':
                $query->where('created_at', '>=', now()->subMonths(6));
                break;
        }

        return $query->get();
    }

    // Lấy danh sách sản phẩm bán chạy nhất (top 5)
    private function getTopSellingProducts()
    {
        return OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->with('product:id,name,main_image')
            ->get();
    }
}
