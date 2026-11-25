<?php use App\Core\View; ?>
    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-column support">
                <h3>HỖ TRỢ KHÁCH HÀNG</h3>
                <p class="hotline">Hotline: <a href="tel:18006324"><span>1800 6324</span></a></p>
                <p class="hotline-note">(Miễn phí, 08-22h kể cả T7, CN)</p>
                <ul>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=cau-hoi-thuong-gap'); ?>">Các câu hỏi thường gặp</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=huong-dan-dat-hang'); ?>">Hướng dẫn đặt hàng</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=phuong-thuc-thanh-toan'); ?>">Phương thức thanh toán</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=phuong-thuc-van-chuyen'); ?>">Phương thức vận chuyển</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=chinh-sach-doi-tra'); ?>">Chính sách đổi trả</a></li>
                </ul>
            </div>
            <div class="footer-column about">
                <h3>VỀ ÉCRIN JEWELRY</h3>
                <ul>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=gioi-thieu'); ?>">Giới thiệu Écrin Jewelry</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=chinh-sach-bao-mat'); ?>">Chính sách bảo mật</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=dieu-khoan-su-dung'); ?>">Điều khoản sử dụng</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=tuyen-dung'); ?>">Tuyển dụng</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=lien-he'); ?>">Liên hệ</a></li>
                    <li id="admin-footer-link" style="display: none;"><a href="<?php echo View::e($appBaseUrl . '/admin/san_pham.php'); ?>"
                            style="color: var(--gold); font-weight: bold;">Quản trị hệ thống</a></li>
                </ul>
            </div>
            <div class="footer-column partners">
                <h3>HỢP TÁC & LIÊN KẾT</h3>
                <ul>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=gioi-thieu'); ?>">Écrin Jewelry</a></li>
                    <li><a href="<?php echo View::e($appBaseUrl . '/client/thong-tin.php?topic=trang-suc-ecrin'); ?>">Trang sức Écrin</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-note">
            <p>© 2026 ÉCRIN JEWELRY. Công Ty TNHH Thương Mại Và Đầu Tư Xuất Nhập Khẩu Việt Nam. MST: 0107837344</p>
        </div>
    </footer>

    <?php include VIEW_PATH . '/layouts/hop_dang_nhap.php'; ?>

    <script>window.APP_BASE_URL = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;</script>
    <script src="<?php echo View::e(View::asset('assets/script.js')); ?>"></script>
</body>

</html>
