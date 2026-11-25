<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Services\ProductCatalogService;
use App\Services\ReviewService;

/**
 * Controller trang chi tiết sản phẩm (client/product.php?id=...).
 * Trả về 404 nếu sản phẩm không tồn tại, đã xóa mềm hoặc bị ẩn.
 */
class ProductPageController extends BaseClientController
{
    private const DEFAULT_DESCRIPTION = 'Một thiết kế được ÉCRIN tuyển chọn để bạn dễ dàng tìm thấy món trang sức phù hợp với phong cách của mình.';

    /** Mô tả mặc định theo danh mục khi sản phẩm chưa có mô tả riêng. */
    private const DESCRIPTION_BY_CATEGORY = [
        'bong-tai' => 'Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, tuyệt phẩm bông tai ÉCRIN được chế tác thủ công tinh xảo từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh nét đài các, yêu kiều, là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.',
        'day-chuyen' => 'Mặt dây chuyền được chạm khắc tỉ mỉ với đường nét thanh mảnh, uốn lượn mềm mại ôm trọn viên đá chủ lấp lánh như viên ngọc quý giữa lòng đại dương. Sợi dây bạc Ý 925 mạ rhodium cao cấp nhẹ nhàng tôn vinh xương quai xanh quyến rũ, biểu trưng cho tình yêu vĩnh cửu và phong thái kiêu sa, đài các khó phai.',
        'nhan' => 'Thiết kế nhẫn solitaire kinh điển kết hợp kỹ thuật nạm đá tinh xảo bậc nhất, nâng niu viên đá chủ Zirconia giác cắt Brilliant Cut phản chiếu ánh hào quang lộng lẫy tựa kim cương tự nhiên. Sự lựa chọn hoàn mỹ biểu trưng cho tình yêu đích thực và khẳng định phong cách vương giả, độc bản của quý cô hiện đại.',
        'vong-tay' => 'Chiếc vòng tay ôm khít cổ tay yêu kiều, chế tác công phu từ chất liệu Bạc Ý 925 đúc nguyên khối dày dặn và các hạt charm đính đá vi mô sáng trong vắt. Thiết kế thanh lịch, chống oxy hóa bền lâu, mang lại nguồn năng lượng an lành, duyên dáng và phong thái thượng lưu trong từng cử chỉ tay.',
        'set-qua-tang' => 'Tuyệt phẩm hộp quà tặng ÉCRIN phiên bản giới hạn sang trọng gồm các món trang sức chế tác tinh tuyển đồng điệu, đi kèm thiệp chúc mừng cao cấp. Món quà hoàn hảo trao trọn tình cảm chân thành, biểu tượng cho hạnh phúc viên mãn và những cột mốc kỷ niệm đáng nhớ nhất.',
        'qua' => 'Tuyệt phẩm hộp quà tặng ÉCRIN phiên bản giới hạn sang trọng gồm các món trang sức chế tác tinh tuyển đồng điệu, đi kèm thiệp chúc mừng cao cấp. Món quà hoàn hảo trao trọn tình cảm chân thành, biểu tượng cho hạnh phúc viên mãn và những cột mốc kỷ niệm đáng nhớ nhất.',
    ];

    private ProductCatalogService $catalog;

    public function __construct(?ProductCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new ProductCatalogService();
    }

    /** Dữ liệu cho view chi tiết sản phẩm (gọi trước khi nạp header). */
    protected function show(): array
    {
        $productId = filter_var(Request::query('id'), FILTER_VALIDATE_INT);
        $product = ($productId !== false && $productId > 0) ? $this->catalog->findVisibleProduct((int) $productId) : null;

        if ($product === null) {
            http_response_code(404);
        }

        $data = [
            'product' => $product,
            'pageTitle' => $product
                ? $product['name'] . ' - ÉCRIN JEWELRY'
                : 'Không tìm thấy sản phẩm - ÉCRIN JEWELRY',
            'escape' => static fn($value): string => View::e($value),
        ];

        return $product
            ? array_merge($data, $this->presentProduct($product), ['reviewSection' => $this->reviewSection((int) $product['id'])])
            : $data + ['reviewSection' => null];
    }

    /** Action: hiển thị trang chi tiết sản phẩm. */
    public function index(): void
    {
        $this->render('client/product', $this->show());
    }

    /** Dữ liệu cho khối đánh giá (View client/product_reviews) trên trang chi tiết sản phẩm. */
    private function reviewSection(int $productId): array
    {
        $data = (new ReviewService())->productSection(
            $productId,
            Auth::customerId(),
            filter_input(INPUT_GET, 'rating', FILTER_VALIDATE_INT),
            filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)
        );

        $reviewFlash = Session::get(ProductReviewController::FLASH_KEY);
        Session::remove(ProductReviewController::FLASH_KEY);

        $appBaseUrl = Url::base();
        $reviewBaseUrl = $appBaseUrl . '/client/product.php?id=' . $productId;

        return $data + [
            'reviewCsrfToken' => Session::csrfToken(ProductReviewController::CSRF_KEY),
            'reviewFlash' => $reviewFlash,
            'appBaseUrl' => $appBaseUrl,
            'reviewBaseUrl' => $reviewBaseUrl,
            'reviewPageUrl' => $reviewBaseUrl . ($data['reviewFilter'] > 0 ? '&rating=' . $data['reviewFilter'] : '') . '&page=',
        ];
    }

    /** Tính các giá trị hiển thị: ảnh, tồn kho, trạng thái mua, % giảm giá, mô tả. */
    private function presentProduct(array $product): array
    {
        $imagePath = (string) $product['image'];
        $localImagePath = APP_ROOT . '/' . ltrim($imagePath, '/');
        $imgVersion = file_exists($localImagePath) ? filemtime($localImagePath) : time();
        $imageUrl = preg_match('~^https?://~i', $imagePath)
            ? $imagePath
            : Url::base() . '/' . ltrim($imagePath, '/') . '?v=' . $imgVersion;

        $stock = (int) $product['stock'];
        $isDiscontinued = ($product['status'] ?? '') !== 'active';

        $original = (float) $product['price_original'];
        $sale = (float) $product['price_sale'];
        $discountPercentage = 0;
        if ($original > $sale && $original > 0) {
            $discountPercentage = round((($original - $sale) / $original) * 100);
        }

        $rawDescription = trim((string) ($product['description'] ?? ''));

        return [
            'imageUrl' => $imageUrl,
            'stock' => $stock,
            'isDiscontinued' => $isDiscontinued,
            'canBuy' => $stock > 0 && !$isDiscontinued,
            'discountPercentage' => $discountPercentage,
            'description' => $rawDescription !== ''
                ? $rawDescription
                : (self::DESCRIPTION_BY_CATEGORY[$product['category']] ?? self::DEFAULT_DESCRIPTION),
        ];
    }
}
