    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-column support">
                <h3>HỖ TRỢ KHÁCH HÀNG</h3>
                <p class="hotline">Hotline: <a href="tel:18006324"><span>1800 6324</span></a></p>
                <p class="hotline-note">(Miễn phí, 08-22h kể cả T7, CN)</p>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=cau-hoi-thuong-gap', ENT_QUOTES, 'UTF-8'); ?>">Các câu hỏi thường gặp</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=huong-dan-dat-hang', ENT_QUOTES, 'UTF-8'); ?>">Hướng dẫn đặt hàng</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=phuong-thuc-thanh-toan', ENT_QUOTES, 'UTF-8'); ?>">Phương thức thanh toán</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=phuong-thuc-van-chuyen', ENT_QUOTES, 'UTF-8'); ?>">Phương thức vận chuyển</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=chinh-sach-doi-tra', ENT_QUOTES, 'UTF-8'); ?>">Chính sách đổi trả</a></li>
                </ul>
            </div>
            <div class="footer-column about">
                <h3>VỀ ÉCRIN JEWELRY</h3>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=gioi-thieu', ENT_QUOTES, 'UTF-8'); ?>">Giới thiệu Écrin Jewelry</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=chinh-sach-bao-mat', ENT_QUOTES, 'UTF-8'); ?>">Chính sách bảo mật</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=dieu-khoan-su-dung', ENT_QUOTES, 'UTF-8'); ?>">Điều khoản sử dụng</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=tuyen-dung', ENT_QUOTES, 'UTF-8'); ?>">Tuyển dụng</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=lien-he', ENT_QUOTES, 'UTF-8'); ?>">Liên hệ</a></li>
                    <li id="admin-footer-link" style="display: none;"><a href="<?php echo htmlspecialchars($appBaseUrl . '/admin/products.php', ENT_QUOTES, 'UTF-8'); ?>"
                            style="color: var(--gold); font-weight: bold;">Quản trị hệ thống</a></li>
                </ul>
            </div>
            <div class="footer-column partners">
                <h3>HỢP TÁC & LIÊN KẾT</h3>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=gioi-thieu', ENT_QUOTES, 'UTF-8'); ?>">Écrin Jewelry</a></li>
                    <li><a href="<?php echo htmlspecialchars($appBaseUrl . '/client/thong-tin.php?topic=trang-suc-ecrin', ENT_QUOTES, 'UTF-8'); ?>">Trang sức Écrin</a></li>
                </ul>
            </div>
            <div class="footer-column subscribe">
                <h3>CẬP NHẬT THÔNG TIN KHUYẾN MÃI</h3>
                <div class="subscribe-box">
                    <input type="email" placeholder="Email của bạn">
                    <button>Đăng ký</button>
                </div>
            </div>
        </div>
        <div class="footer-note">
            <p>© 2026 ÉCRIN JEWELRY. Công Ty TNHH Thương Mại Và Đầu Tư Xuất Nhập Khẩu Việt Nam. MST: 0107837344</p>
        </div>
    </footer>

    <?php include __DIR__ . '/auth_modals.php'; ?>

    <script>window.APP_BASE_URL = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;</script>
    <script src="<?php echo htmlspecialchars($appBaseUrl . '/assets/script.js?v=' . filemtime(__DIR__ . '/../assets/script.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>
