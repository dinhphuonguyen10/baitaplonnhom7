<?php

namespace App\Core;

/**
 * Lớp trừu tượng Controller - lớp cha của mọi Controller (C trong mô hình MVC).
 *
 * Luồng xử lý MVC:
 *   Request -> Router -> Controller (gọi Model/Service lấy dữ liệu) -> render() View -> Response
 */
abstract class Controller
{
    /**
     * Hiển thị một View trong app/Views với dữ liệu truyền vào.
     * VD: $this->render('client/cart', ['pageTitle' => 'Giỏ hàng']);
     */
    protected function render(string $view, array $data = []): void
    {
        View::render($view, $this->shareLayoutData($data));
    }

    /**
     * Cho lớp con bổ sung dữ liệu dùng chung của layout (header, sidebar...).
     * Mặc định không thêm gì.
     */
    protected function shareLayoutData(array $data): array
    {
        return $data;
    }

    /** Trả JSON và kết thúc request. */
    protected function json($data, int $status = 200, int $flags = Response::JSON_FLAGS): void
    {
        Response::json($data, $status, $flags);
    }

    /** Chuyển hướng và kết thúc request. */
    protected function redirect(string $url, int $status = 302): void
    {
        Response::redirect($url, $status);
    }
}
