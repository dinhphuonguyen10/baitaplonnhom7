<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AddressService;
use DomainException;

/**
 * Lớp AddressBookController - trang sổ địa chỉ nhận hàng (client/diachi.php).
 * Xử lý form thêm / đặt mặc định / xóa (PRG) và trả dữ liệu cho giao diện.
 */
class AddressBookController extends BaseClientController
{
    private const STATUS_MESSAGES = [
        'added' => 'Đã thêm địa chỉ.',
        'default' => 'Đã cập nhật địa chỉ mặc định.',
        'deleted' => 'Đã xóa địa chỉ.',
    ];

    private AddressService $service;

    public function __construct(?AddressService $service = null)
    {
        $this->service = $service ?? new AddressService();
    }

    protected function handle(): array
    {
        $userId = Auth::userId();
        if ($userId === null) {
            Response::redirect('../index.php');
        }

        $message = '';
        $messageType = 'success';

        if (Request::isPost()) {
            try {
                $this->handlePost($userId);
            } catch (DomainException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }

        $status = Request::query('status');
        if ($status !== null) {
            $message = self::STATUS_MESSAGES[$status] ?? '';
        }

        return [
            'message' => $message,
            'message_type' => $messageType,
            'addresses' => $this->service->listForUser($userId),
        ];
    }

    /** Xử lý một thao tác POST; thành công thì chuyển hướng (PRG). */
    private function handlePost(int $userId): void
    {
        $action = Request::post('action', '');

        if ($action === 'add') {
            $this->service->add(
                $userId,
                trim(Request::post('recipient_name', '')),
                trim(Request::post('phone', '')),
                trim(Request::post('address', '')),
                Request::post('is_default') !== null
            );
            Response::redirect('diachi.php?status=added');
        }

        if ($action === 'default' || $action === 'delete') {
            $addressId = filter_var(Request::post('address_id'), FILTER_VALIDATE_INT);
            if ($addressId === false || $addressId < 1) {
                throw new DomainException('Địa chỉ không hợp lệ.');
            }

            if ($action === 'default') {
                $this->service->setDefault($userId, $addressId);
                Response::redirect('diachi.php?status=default');
            }

            $this->service->delete($userId, $addressId);
            Response::redirect('diachi.php?status=deleted');
        }
    }

    /** Action: hiển thị View client/address_book. */
    public function index(): void
    {
        $this->render('client/address_book', $this->handle());
    }
}
