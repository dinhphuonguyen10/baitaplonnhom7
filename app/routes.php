<?php
/**
 * app/routes.php - Bảng định tuyến của ứng dụng (MVC).
 *
 * Khóa: đường dẫn file công khai (tính từ thư mục gốc dự án).
 * Giá trị: [Lớp Controller, tên action, (tham số cho action)].
 *
 * App\Core\Router đọc bảng này để gọi đúng Controller@action cho mỗi request.
 */

use App\Controllers\Admin;
use App\Controllers\Api;
use App\Controllers\Client;

return [
    // ───────────── Cửa hàng (Client) ─────────────
    '/index.php' => [Client\TrangChuController::class, 'index'],
    '/client/bong_tai.php' => [Client\DanhMucSanPhamController::class, 'fixed', ['Bông tai', 'bong-tai']],
    '/client/day_chuyen.php' => [Client\DanhMucSanPhamController::class, 'fixed', ['Dây chuyền', 'day-chuyen']],
    '/client/nhan.php' => [Client\DanhMucSanPhamController::class, 'fixed', ['Nhẫn', 'nhan']],
    '/client/vong_tay.php' => [Client\DanhMucSanPhamController::class, 'fixed', ['Vòng tay', 'vong-tay']],
    '/client/danh_muc.php' => [Client\DanhMucSanPhamController::class, 'bySlug'],
    '/client/san_pham.php' => [Client\ChiTietSanPhamController::class, 'index'],
    '/client/danhgia.php' => [Client\DanhGiaSanPhamController::class, 'submit'],
    '/client/gio_hang.php' => [Client\GioHangController::class, 'index'],
    '/client/dat_hang.php' => [Client\DatHangController::class, 'index'],
    '/client/thanh_toan.php' => [Client\ThanhToanTrangController::class, 'index'],
    '/client/tracuudh.php' => [Client\TraCuuDonHangController::class, 'index'],
    '/client/diachi.php' => [Client\SoDiaChiController::class, 'index'],
    '/client/yeu_thich.php' => [Client\DanhSachYeuThichController::class, 'index'],
    '/client/tro_chuyen.php' => [Client\TroChuyenController::class, 'index'],
    '/client/htch.php' => [Client\HeThongCuaHangController::class, 'index'],
    '/client/thong-tin.php' => [Client\ThongTinController::class, 'index'],
    '/client/quen_mat_khau.php' => [Client\QuenMatKhauController::class, 'index'],
    '/client/hoso.php' => [Client\HoSoController::class, 'legacyAlias'],
    '/client/ho_so.php' => [Client\HoSoController::class, 'index'],
    '/client/quan_ly_danh_muc.php' => [Admin\ChuyenHuongCuController::class, 'clientCategories'],

    // ───────────── Quản trị (Admin) ─────────────
    '/admin/dang_nhap.php' => [Admin\DangNhapController::class, 'index'],
    '/admin/dang_xuat.php' => [Admin\DangNhapController::class, 'logout'],
    '/admin/index.php' => [Admin\TongQuanController::class, 'index'],
    '/admin/tong_quan.php' => [Admin\TongQuanController::class, 'legacyDashboard'],
    '/admin/manager/index.php' => [Admin\TongQuanController::class, 'managerHome'],
    '/admin/staff/index.php' => [Admin\TongQuanController::class, 'staffHome'],
    '/admin/thanh_toan.php' => [Admin\ChuyenHuongCuController::class, 'payments'],
    '/admin/san_pham.php' => [Admin\SanPhamController::class, 'index'],
    '/admin/danh_muc.php' => [Admin\DanhMucController::class, 'index'],
    '/admin/danh_gia.php' => [Admin\DanhGiaController::class, 'index'],
    '/admin/don_hang.php' => [Admin\DonHangController::class, 'index'],
    '/admin/tai_khoan.php' => [Admin\TaiKhoanController::class, 'index'],
    '/admin/cua_hang.php' => [Admin\CuaHangController::class, 'index'],
    '/admin/tro_chuyen.php' => [Admin\TroChuyenController::class, 'index'],
    '/admin/mau_tra_loi_tu_dong.php' => [Admin\MauTraLoiTuDongController::class, 'index'],
    '/admin/sua_mau_tra_loi_tu_dong.php' => [Admin\MauTraLoiTuDongController::class, 'edit'],
    '/admin/bao_cao.php' => [Admin\BaoCaoController::class, 'index'],

    // ───────────── API (JSON) ─────────────
    '/api/auth/kiem_tra_phien.php' => [Api\XacThucController::class, 'checkSession'],
    '/api/auth/dang_nhap.php' => [Api\XacThucController::class, 'login'],
    '/api/auth/dang_xuat.php' => [Api\XacThucController::class, 'logout'],
    '/api/auth/dang_ky.php' => [Api\XacThucController::class, 'register'],
    '/api/auth/dat_lai_mat_khau.php' => [Api\XacThucController::class, 'resetPassword'],
    '/api/catalog/lay_san_pham.php' => [Api\DanhSachSanPhamController::class, 'products'],
    '/api/catalog/yeu_thich.php' => [Api\YeuThichController::class, 'handle'],
    '/api/chat/tro_chuyen_quan_tri.php' => [Api\TroChuyenController::class, 'adminChat'],
    '/api/chat/khach_lay_tin_nhan.php' => [Api\TroChuyenController::class, 'customerMessages'],
    '/api/chat/khach_gui_tin_nhan.php' => [Api\TroChuyenController::class, 'customerSend'],
    '/api/chat/tai_tep_dinh_kem.php' => [Api\TroChuyenController::class, 'downloadAttachment'],
    '/api/chat/tai_len_tep_dinh_kem.php' => [Api\TroChuyenController::class, 'uploadAttachment'],
    '/api/orders/huy_don_hang.php' => [Api\DonHangController::class, 'cancel'],
    '/api/orders/tra_cuu_don_hang.php' => [Api\DonHangController::class, 'lookup'],
    '/api/orders/don_hang_cua_toi.php' => [Api\DonHangController::class, 'myOrders'],
    '/api/orders/chi_tiet_don_hang.php' => [Api\DonHangController::class, 'detail'],
    '/api/orders/dat_hang.php' => [Api\DonHangController::class, 'place'],
    '/api/payment/doi_phuong_thuc_vietqr.php' => [Api\ThanhToanController::class, 'changeMethod'],
    '/api/payment/lam_moi_vietqr.php' => [Api\ThanhToanController::class, 'refresh'],
    '/api/payment/bao_ket_qua_vietqr.php' => [Api\ThanhToanController::class, 'report'],
];
