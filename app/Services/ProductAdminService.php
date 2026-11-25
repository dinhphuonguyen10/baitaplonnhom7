<?php

namespace App\Services;

use App\Core\DatabaseException;
use App\Models\Category;
use App\Models\Product;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lớp ProductAdminService - nghiệp vụ quản trị sản phẩm:
 * thêm / sửa (kèm tải ảnh), xóa mềm, khôi phục, xóa vĩnh viễn.
 * Các phương thức trả về thông báo thành công; lỗi ném InvalidArgumentException / RuntimeException
 * với thông báo tiếng Việt hiển thị cho người dùng.
 */
class ProductAdminService
{
    public const STATUSES = ['active', 'inactive', 'hidden'];
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Mô tả mẫu theo danh mục, dùng khi người quản trị để trống mô tả. */
    public const DESCRIPTION_TEMPLATES = [
        2 => 'Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, đôi bông tai được chế tác thủ công từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh trọn vẹn nét đài các, yêu kiều và là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng rạng ngời trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.',
        3 => 'Mặt dây chuyền được chạm khắc tỉ mỉ với đường nét thanh mảnh, uốn lượn mềm mại ôm trọn viên đá chủ lấp lánh như viên ngọc quý giữa lòng đại dương. Sợi dây bạc Ý 925 mạ rhodium cao cấp nhẹ nhàng tôn vinh xương quai xanh quyến rũ, biểu trưng cho tình yêu vĩnh cửu và phong thái kiêu sa, đài các khó phai.',
        4 => 'Thiết kế nhẫn solitaire kinh điển kết hợp kỹ thuật nạm đá tinh xảo bậc nhất, nâng niu viên đá chủ Zirconia giác cắt Brilliant Cut phản chiếu ánh hào quang lộng lẫy tựa kim cương tự nhiên. Sự lựa chọn hoàn mỹ biểu trưng cho tình yêu đích thực và khẳng định phong cách vương giả, độc bản của quý cô hiện đại.',
        5 => 'Chiếc vòng tay ôm khít cổ tay yêu kiều, chế tác công phu từ chất liệu Bạc Ý 925 đúc nguyên khối dày dặn và các hạt charm đính đá vi mô sáng trong vắt. Thiết kế thanh lịch, chống oxy hóa bền lâu, mang lại nguồn năng lượng an lành, duyên dáng và phong thái thượng lưu trong từng cử chỉ tay.',
        6 => 'Tuyệt phẩm hộp quà tặng ÉCRIN phiên bản giới hạn sang trọng gồm các món trang sức chế tác tinh tuyển đồng điệu, đi kèm thiệp chúc mừng cao cấp. Món quà hoàn hảo trao trọn tình cảm chân thành, biểu tượng cho hạnh phúc viên mãn và những cột mốc kỷ niệm đáng nhớ nhất.',
    ];

    private Product $products;
    private Category $categories;
    private ImageUploadService $uploader;
    private BannerService $banners;
    private string $uploadDir;

    public function __construct(
        ?Product $products = null,
        ?Category $categories = null,
        ?ImageUploadService $uploader = null,
        ?BannerService $banners = null
    ) {
        $this->products = $products ?? new Product();
        $this->categories = $categories ?? new Category();
        $this->uploader = $uploader ?? new ImageUploadService();
        $this->banners = $banners ?? new BannerService($this->uploader);
        $this->uploadDir = APP_ROOT . '/assets/uploads/';
        $this->uploader->ensureDirectory($this->uploadDir);
    }

    /** Dữ liệu danh sách cho trang quản trị theo chế độ xem 'active' | 'trash'. */
    public function listing(string $view): array
    {
        return [
            'countActive' => $this->products->countByDeletedFlag(0),
            'countTrash' => $this->products->countByDeletedFlag(1),
            'categories' => $this->categories->findAllIdNameForAdmin(),
            'products' => $this->products->findDetailsForAdmin($view === 'trash'),
        ];
    }

    /** Thêm sản phẩm mới từ dữ liệu form + file ảnh (nếu có). */
    public function create(array $input, ?array $imageFile): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $description = $this->descriptionOrTemplate((string) ($input['description'] ?? ''), $categoryId);
        // Ảnh mặc định — đồng bộ với logo config nếu có
        $imagePath = $this->banners->currentLogoSrc();

        if ($name === '' || $categoryId <= 0) {
            throw new InvalidArgumentException('Vui lòng nhập tên sản phẩm và chọn danh mục!');
        }

        if (!empty($imageFile['name']) && $imageFile['error'] === UPLOAD_ERR_OK) {
            $uploaded = $this->storeImage($imageFile);
            if ($uploaded !== null) {
                $imagePath = $uploaded;
            }
        } elseif (!empty($input['image_url'])) {
            $imagePath = trim((string) $input['image_url']);
        }

        try {
            $this->products->createFromAdmin([
                'name' => $name,
                'category_id' => $categoryId,
                'price_sale' => (float) ($input['price'] ?? 0),
                'stock' => max(0, (int) ($input['stock'] ?? 0)),
                'image' => $imagePath,
                'description' => $description,
                'is_featured' => isset($input['is_featured']) ? 1 : 0,
                'is_suggested' => isset($input['is_suggested']) ? 1 : 0,
                'is_new' => isset($input['is_new']) ? 1 : 0,
                'status' => $this->normalizeStatus($input['status'] ?? ''),
            ]);
        } catch (DatabaseException $e) {
            throw new RuntimeException('Lỗi hệ thống khi thêm sản phẩm: ' . $e->getMessage(), 0, $e);
        }

        return "Đã thêm sản phẩm '{$name}' thành công!";
    }

    /** Cập nhật sản phẩm; chỉ thay ảnh khi có file mới hoặc URL ảnh mới. */
    public function update(array $input, ?array $imageFile): string
    {
        $id = (int) ($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $data = [
            'name' => $name,
            'category_id' => $categoryId,
            'price_sale' => (float) ($input['price'] ?? 0),
            'price_original' => isset($input['price_original']) ? (float) $input['price_original'] : 0.0,
            'stock' => max(0, (int) ($input['stock'] ?? 0)),
            'description' => $this->descriptionOrTemplate((string) ($input['description'] ?? ''), $categoryId),
            'is_featured' => isset($input['is_featured']) ? 1 : 0,
            'is_suggested' => isset($input['is_suggested']) ? 1 : 0,
            'is_new' => isset($input['is_new']) ? 1 : 0,
            'status' => $this->normalizeStatus($input['status'] ?? ''),
        ];

        if ($id <= 0 || $name === '' || $categoryId <= 0) {
            throw new InvalidArgumentException('Dữ liệu chỉnh sửa không hợp lệ!');
        }

        // Kiểm tra có tải ảnh mới lên không
        $newImagePath = null;
        if (!empty($imageFile['name']) && $imageFile['error'] === UPLOAD_ERR_OK) {
            $newImagePath = $this->storeImage($imageFile);
        } elseif (!empty($input['image_url_new'])) {
            $newImagePath = trim((string) $input['image_url_new']);
        }

        try {
            $this->products->updateFromAdmin($id, $data, $newImagePath);
        } catch (DatabaseException $e) {
            throw new RuntimeException('Lỗi cập nhật sản phẩm: ' . $e->getMessage(), 0, $e);
        }

        return 'Đã cập nhật thông tin sản phẩm thành công!';
    }

    /** Xóa mềm (chuyển vào thùng rác); null khi id không hợp lệ. */
    public function softDelete(int $id, string $adminUsername): ?string
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $this->products->softDeleteByAdmin($id, $adminUsername !== '' ? $adminUsername : 'Admin');
        } catch (DatabaseException $e) {
            throw new RuntimeException('Lỗi khi xóa mềm sản phẩm: ' . $e->getMessage(), 0, $e);
        }

        return 'Đã chuyển sản phẩm vào Thùng rác (xóa mềm)! Dữ liệu vẫn được bảo toàn trong database cho lịch sử đơn hàng và báo cáo.';
    }

    /** Khôi phục sản phẩm từ thùng rác; null khi id không hợp lệ. */
    public function restore(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $this->products->restoreFromTrash($id);
        } catch (DatabaseException $e) {
            throw new RuntimeException('Lỗi khi khôi phục sản phẩm: ' . $e->getMessage(), 0, $e);
        }

        return 'Đã khôi phục sản phẩm thành công! Sản phẩm đã quay lại danh sách hoạt động.';
    }

    /** Xóa vĩnh viễn - chỉ cho phép khi sản phẩm chưa phát sinh đơn hàng; null khi id không hợp lệ. */
    public function forceDelete(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $orderCount = $this->products->countOrderItemsForProduct($id);
        if ($orderCount > 0) {
            throw new InvalidArgumentException("KHÔNG THỂ XÓA VĨNH VIỄN! Sản phẩm này đã phát sinh {$orderCount} lượt giao dịch trong đơn hàng. Để bảo đảm tính toàn vẹn dữ liệu kế toán và báo cáo, sản phẩm chỉ có thể lưu trong Thùng rác hoặc chuyển sang trạng thái Ngừng kinh doanh.");
        }

        try {
            $this->products->forceDeleteFromTrash($id);
        } catch (\Throwable $e) {
            throw new RuntimeException('Lỗi khi xóa vĩnh viễn: ' . $e->getMessage(), 0, $e);
        }

        return 'Đã xóa vĩnh viễn sản phẩm khỏi cơ sở dữ liệu!';
    }

    private function normalizeStatus($status): string
    {
        return in_array($status, self::STATUSES, true) ? $status : 'active';
    }

    private function descriptionOrTemplate(string $description, int $categoryId): string
    {
        $description = trim($description);
        if ($description === '') {
            $description = self::DESCRIPTION_TEMPLATES[$categoryId] ?? self::DESCRIPTION_TEMPLATES[2];
        }

        return $description;
    }

    /** Lưu ảnh sản phẩm vào assets/uploads; trả về đường dẫn tương đối hoặc null nếu không hợp lệ. */
    private function storeImage(array $file): ?string
    {
        $ext = $this->uploader->extension($file);
        if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            return null;
        }

        $newFileName = 'prod_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
        if (!$this->uploader->move($file, $this->uploadDir . $newFileName)) {
            return null;
        }

        return 'assets/uploads/' . $newFileName;
    }
}
