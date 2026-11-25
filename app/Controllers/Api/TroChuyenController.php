<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\TroChuyenException;
use App\Services\MauTraLoiTuDongService;
use App\Services\TepDinhKemService;
use App\Services\TroChuyenService;

/**
 * Lớp TroChuyenController (API) - các endpoint trong api/chat/:
 *  - adminChat():          api/chat/tro_chuyen_quan_tri.php (hội thoại, tin nhắn, gửi, đánh dấu đã đọc, mẫu tự động)
 *  - customerMessages():   api/chat/khach_lay_tin_nhan.php
 *  - customerSend():       api/chat/khach_gui_tin_nhan.php
 *  - uploadAttachment():   api/chat/tai_len_tep_dinh_kem.php
 *  - downloadAttachment(): api/chat/tai_tep_dinh_kem.php
 */
class TroChuyenController extends \App\Core\Controller
{
    /** Cờ JSON của API phía quản trị và tải tệp. */
    private const ADMIN_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** API phía khách hàng giữ định dạng json_encode mặc định như trước. */
    private const CUSTOMER_JSON_FLAGS = 0;

    private const CUSTOMER_LOGIN_REQUIRED = 'Vui lòng đăng nhập tài khoản khách hàng để sử dụng chat.';

    private ?TroChuyenService $chat;
    private ?TepDinhKemService $attachments;
    private ?MauTraLoiTuDongService $autoResponses;

    public function __construct(
        ?TroChuyenService $chat = null,
        ?TepDinhKemService $attachments = null,
        ?MauTraLoiTuDongService $autoResponses = null
    ) {
        // Khởi tạo trễ để kiểm tra đăng nhập trước khi kết nối CSDL.
        $this->chat = $chat;
        $this->attachments = $attachments;
        $this->autoResponses = $autoResponses;
    }

    // ───────────── api/chat/tro_chuyen_quan_tri.php ─────────────

    public function adminChat(): void
    {
        Auth::requireAdmin();
        Response::jsonHeaders();

        if (!$this->chat()->ensureReady()) {
            $this->adminJson(['status' => 'error', 'message' => TroChuyenService::NOT_READY_MESSAGE], 500);
        }

        try {
            $method = Request::method();
            if ($method === 'GET') {
                $this->handleAdminGet();
            }
            if ($method === 'POST') {
                $this->handleAdminPost();
            }
        } catch (TroChuyenException $e) {
            $this->adminJson(['status' => 'error', 'message' => $e->getMessage()], $e->getHttpStatus());
        }

        $this->adminJson(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ.'], 405);
    }

    private function handleAdminGet(): void
    {
        $action = Request::query('action', '');
        if ($action === 'conversations') {
            $this->adminJson(['status' => 'success', 'conversations' => $this->chat()->conversations()]);
        }
        if ($action === 'messages') {
            $messages = $this->chat()->adminMessages(Request::query('user_id'));
            $this->adminJson(['status' => 'success', 'messages' => $messages]);
        }

        $this->adminJson(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.'], 400);
    }

    private function handleAdminPost(): void
    {
        $payload = json_decode(Request::rawBody(), true);
        if (!is_array($payload)) {
            $this->adminJson(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.'], 400);
        }
        $action = $payload['action'] ?? '';
        $adminUsername = (string) Session::get('admin_username', Session::get('username', 'Nhân viên ÉCRIN'));

        if ($action === 'save_auto_responses') {
            $responses = $payload['responses'] ?? [];
            if (!is_array($responses)) {
                $this->adminJson(['status' => 'error', 'message' => 'Dữ liệu trả lời không hợp lệ.'], 400);
            }
            $this->autoResponses()->saveRaw($responses);
            $this->adminJson(['status' => 'success']);
        }

        if ($action === 'auto_reply') {
            $message = $this->chat()->adminAutoReply($payload['type'] ?? '', $payload['user_id'] ?? null, $adminUsername);
            $this->adminJson(['status' => 'success', 'message' => $message]);
        }

        if ($action === 'mark_read') {
            $this->chat()->markRead($payload['user_id'] ?? null, $payload['last_message_id'] ?? null);
            $this->adminJson(['status' => 'success']);
        }

        if ($action !== 'send') {
            $this->adminJson(['status' => 'error', 'message' => 'Yêu cầu gửi tin nhắn không hợp lệ.'], 400);
        }

        $message = $this->chat()->adminSend($payload['user_id'] ?? null, $payload['message'] ?? '', $adminUsername);
        $this->adminJson(['status' => 'success', 'message' => $message]);
    }

    // ───────────── api/chat/khach_lay_tin_nhan.php ─────────────

    public function customerMessages(): void
    {
        Response::jsonHeaders();
        $userId = $this->requireCustomer();

        if (!$this->chat()->ensureReady()) {
            $this->customerJson(['status' => 'error', 'message' => TroChuyenService::NOT_READY_MESSAGE], 500);
        }

        $this->customerJson(['status' => 'success', 'messages' => $this->chat()->customerMessages($userId)]);
    }

    // ───────────── api/chat/khach_gui_tin_nhan.php ─────────────

    public function customerSend(): void
    {
        Response::jsonHeaders();
        $userId = $this->requireCustomer();

        $data = json_decode(Request::rawBody(), true);
        $data = is_array($data) ? $data : [];

        try {
            $message = $this->chat()->validateCustomerMessage($data);
            $productId = $this->chat()->validateSharedProduct($data);
        } catch (TroChuyenException $e) {
            $this->customerJson(['status' => 'error', 'message' => $e->getMessage()], $e->getHttpStatus());
        }

        $username = (string) Session::get('username', 'Khách');

        if (!$this->chat()->ensureReady()) {
            $this->customerJson(['status' => 'error', 'message' => TroChuyenService::NOT_READY_MESSAGE], 500);
        }

        $messageData = $this->chat()->customerSend($userId, $username, $message, $productId);
        if ($messageData === false) {
            $this->customerJson(['status' => 'error', 'message' => 'Không thể gửi tin nhắn. Vui lòng thử lại sau.'], 500);
        }

        // Trả kết quả cho khách trước, sau đó gửi tin chăm sóc đầu ngày (nếu là tin đầu tiên).
        $body = (string) json_encode(['status' => 'success', 'message' => $messageData], self::CUSTOMER_JSON_FLAGS);
        ignore_user_abort(true);
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        echo $body;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        $this->chat()->sendDailyGreetingIfFirstMessage($userId);
        exit;
    }

    // ───────────── api/chat/tai_len_tep_dinh_kem.php ─────────────

    public function uploadAttachment(): void
    {
        $hasCustomerSession = Auth::isCustomer();
        $isCustomerRequest = Request::post('viewer', '') === 'customer'
            || (Request::post('user_id') === null && $hasCustomerSession);

        $sender = '';
        $userId = 0;
        if ($isCustomerRequest && $hasCustomerSession) {
            $sender = 'customer';
            $userId = (int) Auth::customerId();
        } elseif (Session::has('admin_user_id')) {
            Auth::requireAdmin();
            $sender = 'admin';
        } elseif ($hasCustomerSession) {
            $sender = 'customer';
            $userId = (int) Auth::customerId();
        } else {
            Response::json(['status' => 'error', 'message' => 'Vui lòng đăng nhập để gửi tệp.'], 401, JSON_UNESCAPED_UNICODE);
        }

        Response::jsonHeaders();

        try {
            if (Request::method() !== 'POST') {
                throw new TroChuyenException('Phương thức không được hỗ trợ.', 405);
            }
            $this->attachments()->assertWithinPostLimit((int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
            if (!$this->chat()->ensureReady()) {
                throw new TroChuyenException(TroChuyenService::NOT_READY_MESSAGE, 500);
            }

            $file = $this->attachments()->validateUpload(Request::file('file'));

            if ($sender === 'admin') {
                $userId = TroChuyenService::positiveInt(Request::post('user_id'));
                if ($userId === null) {
                    throw new TroChuyenException(TroChuyenService::INVALID_CONVERSATION, 400);
                }
                // Xác minh khách hàng tồn tại trong bảng users (chỉ chứa tài khoản khách hàng).
                if ((string) $this->chat()->customerUsername($userId) === '') {
                    throw new TroChuyenException('Không tìm thấy khách hàng này.', 404);
                }
                $senderUsername = (string) Session::get('admin_username', 'Nhân viên ÉCRIN');
            } else {
                $senderUsername = (string) Session::get('username', 'Khách');
            }

            $message = $this->attachments()->storeAndCreateMessage($file, $userId, $senderUsername, $sender);
        } catch (TroChuyenException $e) {
            $this->adminJson(['status' => 'error', 'message' => $e->getMessage()], $e->getHttpStatus());
        }

        $this->adminJson(['status' => 'success', 'message' => $message]);
    }

    // ───────────── api/chat/tai_tep_dinh_kem.php ─────────────

    public function downloadAttachment(): void
    {
        $isAdmin = false;
        $hasCustomerSession = Auth::isCustomer();
        $isCustomer = false;
        if (Request::query('viewer', '') === 'customer') {
            $isCustomer = $hasCustomerSession;
        } elseif (Session::has('admin_user_id')) {
            Auth::requireAdmin();
            $isAdmin = true;
        } elseif ($hasCustomerSession) {
            $isCustomer = true;
        }
        if (!$isCustomer && !$isAdmin) {
            $this->plainError('Vui lòng đăng nhập để xem tệp.', 401);
        }

        if (!$this->chat()->ensureReady()) {
            $this->plainError('Không thể tải tệp lúc này.', 500);
        }

        try {
            $download = $this->attachments()->resolveDownload(
                Request::query('id'),
                $isCustomer ? (int) Auth::customerId() : null
            );
        } catch (TroChuyenException $e) {
            $this->plainError($e->getMessage(), $e->getHttpStatus());
        }

        $this->attachments()->stream($download);
    }

    // ───────────── Tiện ích ─────────────

    /** Bắt buộc phiên khách hàng, trả về ID khách. */
    private function requireCustomer(): int
    {
        $userId = Auth::customerId();
        if ($userId === null) {
            $this->customerJson(['status' => 'error', 'message' => self::CUSTOMER_LOGIN_REQUIRED], 401);
        }

        return $userId;
    }

    private function adminJson(array $data, int $status = 200): void
    {
        Response::json($data, $status, self::ADMIN_JSON_FLAGS);
    }

    private function customerJson(array $data, int $status = 200): void
    {
        Response::json($data, $status, self::CUSTOMER_JSON_FLAGS);
    }

    private function plainError(string $message, int $status): void
    {
        http_response_code($status);
        exit($message);
    }

    private function chat(): TroChuyenService
    {
        return $this->chat ??= new TroChuyenService();
    }

    private function attachments(): TepDinhKemService
    {
        return $this->attachments ??= new TepDinhKemService();
    }

    private function autoResponses(): MauTraLoiTuDongService
    {
        return $this->autoResponses ??= new MauTraLoiTuDongService();
    }
}
