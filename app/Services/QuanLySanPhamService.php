<?php

namespace App\Services;

use App\Core\Database;
use App\Core\DatabaseException;
use App\Models\DanhMuc;
use App\Models\SanPham;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Lớp QuanLySanPhamService - nghiệp vụ quản trị sản phẩm:
 *  - Thêm / sửa sản phẩm: SKU, giá gốc, giá khuyến mãi + hạn kết thúc, trạng thái active/inactive/hidden
 *  - Ảnh: tải lên (kiểm tra MIME thật, tối đa 5 MB), URL http/https, tệp trong assets, gỡ ảnh về ảnh mặc định
 *  - Ghi lịch sử giá (product_price_history) khi giá thay đổi
 *  - Xóa mềm vào thùng rác / khôi phục (không còn xóa vĩnh viễn)
 */
class QuanLySanPhamService
{
    public const STATUSES = ['active', 'inactive', 'hidden'];

    /** MIME ảnh được chấp nhận => phần mở rộng khi lưu. */
    public const IMAGE_EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    public const FALLBACK_IMAGE = 'assets/banner/logo.png';

    /** Mô tả mẫu theo danh mục (nút gợi ý trong form). */
    public const DESCRIPTION_TEMPLATES = [
        2 => 'Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, đôi bông tai được chế tác thủ công từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh trọn vẹn nét đài các, yêu kiều và là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng rạng ngời trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.',
        3 => 'Mặt dây chuyền được chạm khắc tỉ mỉ với đường nét thanh mảnh, uốn lượn mềm mại ôm trọn viên đá chủ lấp lánh như viên ngọc quý giữa lòng đại dương. Sợi dây bạc Ý 925 mạ rhodium cao cấp nhẹ nhàng tôn vinh xương quai xanh quyến rũ, biểu trưng cho tình yêu vĩnh cửu và phong thái kiêu sa, đài các khó phai.',
        4 => 'Thiết kế nhẫn solitaire kinh điển kết hợp kỹ thuật nạm đá tinh xảo bậc nhất, nâng niu viên đá chủ Zirconia giác cắt Brilliant Cut phản chiếu ánh hào quang lộng lẫy tựa kim cương tự nhiên. Sự lựa chọn hoàn mỹ biểu trưng cho tình yêu đích thực và khẳng định phong cách vương giả, độc bản của quý cô hiện đại.',
        5 => 'Chiếc vòng tay ôm khít cổ tay yêu kiều, chế tác công phu từ chất liệu Bạc Ý 925 đúc nguyên khối dày dặn và các hạt charm đính đá vi mô sáng trong vắt. Thiết kế thanh lịch, chống oxy hóa bền lâu, mang lại nguồn năng lượng an lành, duyên dáng và phong thái thượng lưu trong từng cử chỉ tay.',
        6 => 'Tuyệt phẩm hộp quà tặng ÉCRIN phiên bản giới hạn sang trọng gồm các món trang sức chế tác tinh tuyển đồng điệu, đi kèm thiệp chúc mừng cao cấp. Món quà hoàn hảo trao trọn tình cảm chân thành, biểu tượng cho hạnh phúc viên mãn và những cột mốc kỷ niệm đáng nhớ nhất.',
    ];

    private SanPham $products;
    private DanhMuc $categories;
    private TaiAnhLenService $uploader;
    private AnhBiaService $banners;
    private Database $db;
    private string $uploadDir;

    public function __construct(
        ?SanPham $products = null,
        ?DanhMuc $categories = null,
        ?TaiAnhLenService $uploader = null,
        ?AnhBiaService $banners = null
    ) {
        $this->products = $products ?? new SanPham();
        $this->categories = $categories ?? new DanhMuc();
        $this->uploader = $uploader ?? new TaiAnhLenService();
        $this->banners = $banners ?? new AnhBiaService($this->uploader);
        $this->db = $this->products->getDb();
        $this->uploadDir = APP_ROOT . '/assets/uploads/';
        $this->uploader->ensureDirectory($this->uploadDir);
    }

    /** Dữ liệu danh sách cho trang quản trị theo chế độ xem 'active' | 'trash'. */
    public function listing(string $view): array
    {
        return [
            'countActive' => $this->products->countByDeletedFlag(0),
            'countTrash' => $this->products->countByDeletedFlag(1),
            'categories' => $this->categories->findActiveIdNameForAdmin(),
            'products' => $this->products->findDetailsForAdmin($view === 'trash'),
        ];
    }

    /**
     * Xử lý một thao tác form quản trị sản phẩm.
     *
     * @param array      $input     dữ liệu POST
     * @param array|null $imageFile $_FILES['image_file'] (nếu có)
     * @return array{0: string, 1: string}|null [thông báo, 'success'|'error']; null khi không có gì để báo
     */
    public function handleAction(string $action, array $input, ?array $imageFile, string $adminUsername): ?array
    {
        $changedBy = $adminUsername !== '' ? $adminUsername : 'Admin';
        switch ($action) {
            case 'add':
                return $this->create($input, $imageFile, $changedBy);
            case 'edit':
                return $this->update($input, $imageFile, $changedBy);
            case 'delete':
                return $this->softDelete((int) ($input['id'] ?? 0), $changedBy);
            case 'restore':
                return $this->restore((int) ($input['id'] ?? 0));
            default:
                return ['Thao tác không hợp lệ. Sản phẩm chỉ có thể được xóa mềm và khôi phục.', 'error'];
        }
    }

    private function create(array $input, ?array $imageFile, string $changedBy): array
    {
        $validated = $this->validateProductInput($input);
        if (isset($validated['error'])) {
            return [$validated['error'], 'error'];
        }
        $product = $validated['data'];

        return $this->runWrite('Product creation failed: ', 'Không thể thêm sản phẩm', function () use ($product, $input, $imageFile, $changedBy): string {
            if (!$this->categories->lockActive($product['category_id'])) {
                throw new DomainException('Danh mục không tồn tại, đã bị xóa hoặc đang ngừng hoạt động.');
            }
            if ($this->products->skuUsedByOther($product['sku'], 0)) {
                throw new DomainException('Mã SKU đã được sử dụng cho sản phẩm khác.');
            }

            $imageInput = $input['image_url'] ?? '';
            if (!is_string($imageInput)) {
                throw new InvalidArgumentException('Đường dẫn ảnh không hợp lệ.');
            }
            $product['image'] = $this->resolveProductImage($imageFile, $imageInput, $this->defaultImagePath());

            $productId = $this->products->createWithPricing($product);
            $this->products->insertPriceHistory(
                $productId,
                null,
                $this->requireEffectivePrice($productId),
                null,
                $product['regular_price'],
                null,
                $product['promotion_price'],
                null,
                $product['promotion_ends_at'],
                $changedBy
            );

            return "Đã thêm sản phẩm '{$product['name']}' thành công.";
        });
    }

    private function update(array $input, ?array $imageFile, string $changedBy): array
    {
        $productId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $validated = $this->validateProductInput($input);
        if ($productId === false || $productId < 1) {
            return ['Mã sản phẩm không hợp lệ.', 'error'];
        }
        if (isset($validated['error'])) {
            return [$validated['error'], 'error'];
        }
        $product = $validated['data'];
        $productId = (int) $productId;

        return $this->runWrite('Product update failed: ', 'Không thể cập nhật sản phẩm', function () use ($productId, $product, $input, $imageFile, $changedBy): string {
            $oldProduct = $this->products->lockForAdminEdit($productId);
            if (!$oldProduct) {
                throw new DomainException('Sản phẩm không tồn tại hoặc đã nằm trong thùng rác.');
            }
            if (!$this->categories->lockActive($product['category_id'])) {
                throw new DomainException('Danh mục không tồn tại, đã bị xóa hoặc đang ngừng hoạt động.');
            }
            if ($this->products->skuUsedByOther($product['sku'], $productId)) {
                throw new DomainException('Mã SKU đã được sử dụng cho sản phẩm khác.');
            }

            $imageInput = $input['image_url_new'] ?? '';
            if (!is_string($imageInput)) {
                throw new InvalidArgumentException('Đường dẫn ảnh không hợp lệ.');
            }
            $uploadError = is_array($imageFile) ? (int) ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $removeImage = ($input['remove_image'] ?? '') === '1';
            $hasReplacementImage = $uploadError !== UPLOAD_ERR_NO_FILE || trim($imageInput) !== '';
            if ($removeImage && $hasReplacementImage) {
                throw new InvalidArgumentException('Hãy chọn gỡ ảnh hoặc thay ảnh mới, không chọn đồng thời.');
            }
            if ($removeImage) {
                $product['image'] = $this->defaultImagePath();
            } elseif ($uploadError === UPLOAD_ERR_NO_FILE && trim($imageInput) === '') {
                $product['image'] = (string) $oldProduct['image'];
            } else {
                $product['image'] = $this->resolveProductImage($imageFile, $imageInput, (string) $oldProduct['image']);
            }

            // price_sale lưu giá bán mới (giá khuyến mãi nếu có, ngược lại là giá gốc).
            $this->products->updateWithPricing($productId, $product, $product['sale_price']);

            $oldSaleCents = self::parseMoneyCents((string) $oldProduct['price_sale']);
            $oldRegularCents = self::parseMoneyCents((string) $oldProduct['price_original']);
            $newSaleCents = self::parseMoneyCents($product['sale_price']);
            $newRegularCents = self::parseMoneyCents($product['regular_price']);
            $oldPromotionEndsAt = $oldProduct['promotion_ends_at'] !== null ? (string) $oldProduct['promotion_ends_at'] : null;
            if ($oldSaleCents !== $newSaleCents
                || $oldRegularCents !== $newRegularCents
                || $oldPromotionEndsAt !== $product['promotion_ends_at']) {
                $oldPromotionPrice = $oldSaleCents < $oldRegularCents ? (string) $oldProduct['price_sale'] : null;
                $this->products->insertPriceHistory(
                    $productId,
                    (string) $oldProduct['effective_price'],
                    $product['sale_price'],
                    (string) $oldProduct['price_original'],
                    $product['regular_price'],
                    $oldPromotionPrice,
                    $product['promotion_price'],
                    $oldPromotionEndsAt,
                    $product['promotion_ends_at'],
                    $changedBy
                );
            }

            return "Đã cập nhật sản phẩm '{$product['name']}' thành công.";
        });
    }

    /** Xóa mềm (chuyển vào thùng rác); null khi id không hợp lệ. */
    private function softDelete(int $id, string $deletedBy): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $affected = $this->products->softDeleteActive($id, $deletedBy);
        } catch (DatabaseException $e) {
            return ['Lỗi khi xóa mềm sản phẩm: ' . $e->getMessage(), 'error'];
        }

        return $affected === 1
            ? ['Đã chuyển sản phẩm vào Thùng rác (xóa mềm)! Dữ liệu vẫn được bảo toàn trong database cho lịch sử đơn hàng và báo cáo.', 'success']
            : ['Sản phẩm không tồn tại hoặc đã nằm trong thùng rác.', 'error'];
    }

    /** Khôi phục sản phẩm từ thùng rác; null khi id không hợp lệ. */
    private function restore(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $affected = $this->products->restoreDeleted($id);
        } catch (DatabaseException $e) {
            return ['Lỗi khi khôi phục sản phẩm: ' . $e->getMessage(), 'error'];
        }

        return $affected === 1
            ? ['Đã khôi phục sản phẩm thành công! Sản phẩm đã quay lại danh sách hoạt động.', 'success']
            : ['Sản phẩm không tồn tại trong thùng rác.', 'error'];
    }

    /**
     * Chạy thao tác ghi trong transaction và quy đổi ngoại lệ thành thông báo:
     * lỗi nghiệp vụ => thông báo gốc; trùng khóa (1062) => trùng SKU; lỗi DB khác / lỗi hệ thống => thông báo chung.
     */
    private function runWrite(string $logPrefix, string $failurePrefix, callable $work): array
    {
        $transactionStarted = false;
        try {
            $this->db->beginTransaction();
            $transactionStarted = true;
            $message = $work();
            $this->db->commit();

            return [$message, 'success'];
        } catch (DomainException | InvalidArgumentException $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }

            return [$e->getMessage(), 'error'];
        } catch (DatabaseException $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            if ((int) $e->getCode() === 1062) {
                return ['Mã SKU đã được sử dụng cho sản phẩm khác.', 'error'];
            }
            error_log($logPrefix . $e->getMessage());

            return [$failurePrefix . ' do lỗi database.', 'error'];
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log($logPrefix . $e->getMessage());

            return [$failurePrefix . ' do lỗi hệ thống.', 'error'];
        }
    }

    private function requireEffectivePrice(int $productId): string
    {
        $price = $this->products->effectivePrice($productId);
        if ($price === null) {
            throw new RuntimeException('Không tìm thấy giá bán hiệu lực của sản phẩm.');
        }

        return $price;
    }

    /** Chuỗi tiền tệ (tối đa 10 chữ số nguyên, 2 chữ số thập phân) => số xu; null nếu không hợp lệ. */
    public static function parseMoneyCents($value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $raw = trim((string) $value);
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function moneyCentsToDecimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Kiểm tra và chuẩn hoá dữ liệu form sản phẩm.
     *
     * @return array{error: string}|array{data: array}
     */
    public function validateProductInput(array $post): array
    {
        $name = $post['name'] ?? null;
        $sku = $post['sku'] ?? null;
        $description = $post['description'] ?? null;
        $status = $post['status'] ?? null;
        if (!is_string($name) || trim($name) === ''
            || (function_exists('mb_strlen') ? mb_strlen(trim($name), 'UTF-8') : strlen(trim($name))) > 255) {
            return ['error' => 'Tên sản phẩm bắt buộc và không được vượt quá 255 ký tự.'];
        }
        if (!is_string($sku)) {
            return ['error' => 'Mã SKU không hợp lệ.'];
        }
        $sku = strtoupper(trim($sku));
        if ($sku === '' || strlen($sku) > 100 || !preg_match('/^[A-Z0-9][A-Z0-9._-]*$/D', $sku)) {
            return ['error' => 'SKU bắt buộc, tối đa 100 ký tự và chỉ gồm chữ, số, dấu chấm, gạch ngang hoặc gạch dưới.'];
        }

        $categoryId = filter_var($post['category_id'] ?? null, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId < 1) {
            return ['error' => 'Vui lòng chọn danh mục hợp lệ.'];
        }
        $regularPriceCents = self::parseMoneyCents($post['regular_price'] ?? null);
        $promotionInput = $post['promotion_price'] ?? '';
        if (!is_string($promotionInput)) {
            return ['error' => 'Giá khuyến mãi không hợp lệ.'];
        }
        $promotionPriceCents = trim($promotionInput) === '' ? null : self::parseMoneyCents($promotionInput);
        if ($regularPriceCents === null || ($promotionPriceCents === null && trim($promotionInput) !== '')) {
            return ['error' => 'Giá phải không âm, có tối đa 10 chữ số phần nguyên và 2 chữ số thập phân.'];
        }
        if ($promotionPriceCents !== null && $promotionPriceCents >= $regularPriceCents) {
            return ['error' => 'Giá khuyến mãi phải nhỏ hơn giá gốc.'];
        }

        $promotionEndInput = $post['promotion_ends_at'] ?? '';
        if (!is_string($promotionEndInput)) {
            return ['error' => 'Thời điểm kết thúc khuyến mãi không hợp lệ.'];
        }
        $promotionEndsAt = null;
        if (trim($promotionEndInput) !== '') {
            $promotionEnd = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($promotionEndInput));
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$promotionEnd
                || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $promotionEnd->format('Y-m-d\TH:i') !== trim($promotionEndInput)) {
                return ['error' => 'Thời điểm kết thúc khuyến mãi không hợp lệ.'];
            }
            $promotionEndsAt = $promotionEnd->format('Y-m-d H:i:s');
        }
        if ($promotionPriceCents === null && $promotionEndsAt !== null) {
            return ['error' => 'Chỉ nhập thời điểm kết thúc khi có giá khuyến mãi.'];
        }

        $stock = filter_var($post['stock'] ?? null, FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0 || $stock > 2147483647) {
            return ['error' => 'Số lượng tồn kho phải là số nguyên không âm hợp lệ.'];
        }
        if (!is_string($description) || trim($description) === '' || strlen($description) > 65535) {
            return ['error' => 'Mô tả sản phẩm bắt buộc và không được vượt quá giới hạn lưu trữ.'];
        }
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            return ['error' => 'Trạng thái kinh doanh không hợp lệ.'];
        }

        $salePriceCents = $promotionPriceCents ?? $regularPriceCents;

        return [
            'data' => [
                'name' => trim($name),
                'sku' => $sku,
                'category_id' => $categoryId,
                'regular_price' => self::moneyCentsToDecimal($regularPriceCents),
                'sale_price' => self::moneyCentsToDecimal($salePriceCents),
                'promotion_price' => $promotionPriceCents === null ? null : self::moneyCentsToDecimal($promotionPriceCents),
                'promotion_ends_at' => $promotionEndsAt,
                'stock' => $stock,
                'description' => trim($description),
                'status' => $status,
                'is_featured' => isset($post['is_featured']) ? 1 : 0,
                'is_suggested' => isset($post['is_suggested']) ? 1 : 0,
                'is_new' => isset($post['is_new']) ? 1 : 0,
            ],
        ];
    }

    /**
     * Ảnh mặc định của sản phẩm: logo cửa hàng (storage/banner/logo.json) nếu là URL hợp lệ
     * hoặc ảnh hợp lệ trong assets; ngược lại assets/banner/logo.png.
     */
    public function defaultImagePath(): string
    {
        $path = $this->banners->currentLogoSrc();
        if (preg_match('~^https?://~i', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false ? $path : self::FALLBACK_IMAGE;
        }

        $relativePath = ltrim(str_replace('\\', '/', $path), '/');
        $resolvedPath = $this->resolveAssetFile($relativePath);
        if ($resolvedPath === null) {
            return self::FALLBACK_IMAGE;
        }
        $size = filesize($resolvedPath);
        $mime = $this->uploader->detectImageMime($resolvedPath);

        return ($size !== false && $size > 0 && $size <= self::MAX_IMAGE_BYTES && isset(self::IMAGE_EXTENSIONS_BY_MIME[$mime]))
            ? $path
            : self::FALLBACK_IMAGE;
    }

    /**
     * Xác định ảnh sản phẩm: không có file tải lên => dùng đường dẫn nhập (hoặc $fallbackPath),
     * chỉ nhận URL http/https hoặc tệp ảnh hợp lệ trong assets; có file => kiểm tra rồi lưu vào assets/uploads.
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    private function resolveProductImage(?array $file, string $inputPath, string $fallbackPath): string
    {
        $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            $path = trim($inputPath) !== '' ? trim($inputPath) : $fallbackPath;
            if (strlen($path) > 500) {
                throw new InvalidArgumentException('Đường dẫn ảnh không được vượt quá 500 ký tự.');
            }
            if (preg_match('~^https?://~i', $path)) {
                if (filter_var($path, FILTER_VALIDATE_URL) === false
                    || !in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                    throw new InvalidArgumentException('URL ảnh không hợp lệ.');
                }

                return $path;
            }

            $relativePath = ltrim(str_replace('\\', '/', $path), '/');
            $resolvedPath = $this->resolveAssetFile($relativePath);
            if ($resolvedPath === null) {
                throw new InvalidArgumentException('Ảnh phải là URL http/https hợp lệ hoặc tệp có sẵn trong thư mục assets.');
            }
            $fileSize = filesize($resolvedPath);
            $mime = $this->uploader->detectImageMime($resolvedPath);
            if ($fileSize === false || $fileSize <= 0 || $fileSize > self::MAX_IMAGE_BYTES
                || !isset(self::IMAGE_EXTENSIONS_BY_MIME[$mime])) {
                throw new InvalidArgumentException('Ảnh trong thư mục assets phải là JPEG, PNG, WebP hoặc GIF hợp lệ và tối đa 5 MB.');
            }

            return $relativePath;
        }

        if ($uploadError !== UPLOAD_ERR_OK || !is_array($file) || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new InvalidArgumentException('Không thể nhận tệp ảnh tải lên.');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > self::MAX_IMAGE_BYTES) {
            throw new InvalidArgumentException('Ảnh tải lên phải có dung lượng tối đa 5 MB.');
        }
        $mime = $this->uploader->detectImageMime((string) $file['tmp_name']);
        if (!isset(self::IMAGE_EXTENSIONS_BY_MIME[$mime])) {
            throw new InvalidArgumentException('Tệp tải lên không phải ảnh JPEG, PNG, WebP hoặc GIF hợp lệ.');
        }

        $fileName = $this->uploader->reserveUniqueFile($this->uploadDir, 'prod_', self::IMAGE_EXTENSIONS_BY_MIME[$mime]);
        if ($fileName === null) {
            throw new RuntimeException('Không thể cấp tên tệp riêng cho ảnh sản phẩm.');
        }
        $absolutePath = $this->uploadDir . $fileName;
        if (!$this->uploader->move($file, $absolutePath)) {
            if (!unlink($absolutePath)) {
                error_log('Unable to remove reserved product image path: ' . $absolutePath);
            }
            throw new RuntimeException('Không thể lưu ảnh sản phẩm đã tải lên.');
        }

        return 'assets/uploads/' . $fileName;
    }

    /** Đường dẫn tuyệt đối của tệp nằm thật sự bên trong thư mục assets; null nếu không hợp lệ. */
    private function resolveAssetFile(string $relativePath): ?string
    {
        $assetsRoot = realpath(APP_ROOT . '/assets');
        $resolvedPath = realpath(APP_ROOT . '/' . $relativePath);
        if (!str_starts_with($relativePath, 'assets/')
            || $assetsRoot === false
            || $resolvedPath === false
            || !is_file($resolvedPath)
            || !str_starts_with($resolvedPath, $assetsRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $resolvedPath;
    }
}
