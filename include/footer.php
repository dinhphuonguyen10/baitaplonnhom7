    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-column support">
                <h3>HỖ TRỢ KHÁCH HÀNG</h3>
                <p class="hotline">Hotline: <span>1800 6324</span></p>
                <p class="hotline-note">(Miễn phí, 08-22h kể cả T7, CN)</p>
                <ul>
                    <li>Các câu hỏi thường gặp</li>
                    <li>Hướng dẫn đặt hàng</li>
                    <li>Phương thức thanh toán</li>
                    <li>Phương thức vận chuyển</li>
                    <li>Chính sách đổi trả</li>
                </ul>
            </div>
            <div class="footer-column about">
                <h3>VỀ ÉCRIN JEWELRY</h3>
                <ul>
                    <li>Giới thiệu Écrin Jewelry</li>
                    <li>Chính sách bảo mật</li>
                    <li>Điều khoản sử dụng</li>
                    <li>Tuyển dụng</li>
                    <li>Liên hệ</li>
                    <li id="admin-footer-link" style="display: none;"><a href="<?php echo htmlspecialchars($appBaseUrl . '/admin/products.php', ENT_QUOTES, 'UTF-8'); ?>"
                            style="color: var(--gold); font-weight: bold;">Quản trị hệ thống</a></li>
                </ul>
            </div>
            <div class="footer-column partners">
                <h3>HỢP TÁC & LIÊN KẾT</h3>
                <ul>
                    <li>Écrin Jewelry</li>
                    <li>Trang sức Écrin</li>
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
