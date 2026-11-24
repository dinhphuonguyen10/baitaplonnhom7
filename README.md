# ÉCRIN Jewelry


Website bán trang sức xây dựng bằng PHP, MySQL, HTML, CSS và JavaScript, chạy local qua XAMPP.

Phạm vi hiện tại gồm các chức năng phía khách hàng và một số nghiệp vụ Admin. Quản lý sản phẩm có thêm trạng thái kinh doanh, xóa mềm, thùng rác, khôi phục và bảo vệ dữ liệu liên quan; một số khu vực Admin khác vẫn là giao diện mẫu.


# Cấu trúc dự án

```text
Baitaplon/
├── index.php                              # Trang chủ cửa hàng
├── admin/                                 # Trang quản trị nội bộ
│   ├── index.php                          # Điểm vào/tổng quan quản trị
│   ├── login.php                          # Đăng nhập nhân viên/quản lý
│   ├── logout.php                         # Đăng xuất
│   ├── dashboard.php                      # Dashboard
│   ├── products.php                       # Giao diện mẫu sản phẩm và đơn hàng
│   ├── categories.php                     # Giao diện mẫu danh mục
│   ├── users.php                          # Giao diện mẫu tài khoản nhân viên
│   ├── chat.php                           # Giao diện mẫu chat
│   ├── reports.php                        # Giao diện mẫu báo cáo
│   ├── payments.php                       # Giao diện mẫu thanh toán
│   ├── stores.php                         # Giao diện mẫu cửa hàng
│   ├── style.css                          #  CSS quản trị
│   ├── manager/
│   │   └── index.php                      #  Trang riêng cho quản lý
│   ├── staff/
│   │   └── index.php                      # Trang riêng cho nhân viên
│   └── assets/
│       └── ecrin-logo.png                 # Logo quản trị
│
├── api/                                   # Endpoint xử lý dữ liệu phía máy chủ
│   ├── auth/                              # Đăng ký, đăng nhập, đăng xuất, phiên
│   ├── catalog/                           # Dữ liệu danh mục/sản phẩm
│   ├── orders/                            # Đặt, tra cứu và hủy đơn hàng phía khách
│   └── chat/                              # API chat phía khách hàng
│
├── client/                                # Trang và chức năng phía khách hàng
│   ├── bong_tai.php, day_chuyen.php, ...  # Các trang danh mục
│   ├── cart.php                           # Trang giỏ hàng toàn màn hình
│   ├── checkout.php                       # Trang nhập thông tin và đặt hàng COD
│   ├── diachi.php, tracuudh.php          # Địa chỉ, tra cứu đơn hàng
├── assets/                                # CSS, JavaScript và hình ảnh website
│   ├── style.css                          # CSS cửa hàng
│   ├── script.js                          # Tương tác giao diện, giỏ hàng và tài khoản
│   ├── admin.css                          # CSS quản trị
│   ├── banner/                            # Banner và logo hiện tại
│   ├── bôngtai/                           # Ảnh sản phẩm bông tai
│   ├── dây chuyền/                        # Ảnh sản phẩm dây chuyền
│   ├── nhẫn/                              # Ảnh sản phẩm nhẫn
│   ├── quà/                               # Ảnh sản phẩm danh mục Set quà tặng
│   └── vòng tay/                          # Ảnh sản phẩm vòng tay
│
├── config/
│   └── db.php                             # Tạo kết nối MySQL dùng chung cho PHP
│
├── database/
│   ├── baitaplon.sql                      # Tạo các bảng và dữ liệu ban đầu
│
├── include/                               # Thành phần PHP dùng chung
│   ├── header.php                         # Header/menu cửa hàng
│   ├── footer.php                         # Footer cửa hàng
│   ├── auth_modals.php                    # Modal đăng nhập/đăng ký dùng chung
│   ├── admin_guard.php                    # Chặn truy cập trang quản trị khi chưa đăng nhập
│   ├── admin_roles.php                    # Hàm kiểm tra vai trò nhân viên/quản lý
│   └── admin_navigation.php               #  Menu điều hướng quản trị
```

## Quy ước đặt file


- Trang khách hàng đặt trong `client/`; trang quản trị đặt trong `admin/`.
- API đặt trong thư mục con phù hợp của `api/` (ví dụ `api/orders/`).
- Thành phần giao diện dùng chung đặt trong `include/`.
- CSS, JavaScript và hình ảnh đặt trong `assets/`.
- Cấu trúc database và dữ liệu khởi tạo đặt trong `database/`.
- Đặt tên file PHP theo chức năng, dùng chữ thường và dấu gạch dưới, ví dụ `tracuudh.php`, `place_order.php`.
- Chỉ tạo file/thư mục dùng chung khi có từ hai nơi trở lên cần dùng; tránh tạo bản sao cấu hình hoặc helper ở từng module.


## Đối chiếu chức năng theo sơ đồ ERD


Database trong `database/baitaplon.sql` hiện có các bảng cho tài khoản/role, danh mục, sản phẩm, địa chỉ, đơn hàng/chi tiết đơn, đánh giá, giỏ hàng, chat, banner, cấu hình website và cửa hàng. `order_items` là bảng chi tiết để lưu các sản phẩm thuộc từng đơn hàng; quyền Admin/nhân viên/quản lý được biểu diễn bằng role trong `users`, không cần một bảng Admin riêng.

Trang quản lý đơn hàng phía Admin đọc trực tiếp đơn hàng và chi tiết sản phẩm từ các bảng `orders`/`order_items`; trang tổng quan cũng hiển thị số đơn và số đơn đang chờ xác nhận.

Giỏ hàng được lưu theo tài khoản/khách trong trình duyệt. Trang đặt hàng tải danh sách tỉnh/thành và xã/phường từ `provinces.open-api.vn`; chỉ yêu cầu dữ liệu địa giới, không gửi thông tin người đặt hàng đến dịch vụ này. API đặt hàng tự bổ sung cột email cho bảng `orders` nếu cơ sở dữ liệu cũ chưa có cột đó.

Quản lý vòng đời sản phẩm dùng MySQL/MariaDB qua MySQLi. `products.status` phân biệt `active`, `inactive` và `hidden`; hết hàng được xác định riêng bằng `stock = 0`. Xóa trong Admin là xóa mềm qua `is_deleted`, `deleted_at` và `deleted_by`. Sản phẩm đã xóa mềm được xem/khôi phục trong Thùng rác; xóa cứng chỉ được phép khi không còn đơn hàng hoặc dữ liệu liên quan. Khóa ngoại của sản phẩm và chi tiết đơn hàng dùng `RESTRICT` để tránh xóa dây chuyền làm mất lịch sử.

Thêm/sửa sản phẩm yêu cầu SKU duy nhất, giá gốc không âm và giá khuyến mãi (nếu có) thấp hơn giá gốc. `promotion_ends_at` xác định thời hạn khuyến mãi; các trang Client và API đặt hàng tự dùng giá gốc sau khi khuyến mãi hết hạn. Mỗi lần thay đổi giá được ghi vào `product_price_history` trong cùng transaction với cập nhật sản phẩm. Giá trong `order_items` được lưu thành snapshot khi tạo đơn, không bị thay đổi khi Admin cập nhật giá.

Mỗi sản phẩm dùng một ảnh đại diện tại `products.image`, phù hợp với cấu trúc và giao diện hiện tại; chưa triển khai gallery ảnh phụ. Admin có thể thêm, thay hoặc gỡ ảnh (gỡ sẽ chuyển sang ảnh mặc định). Ảnh tải lên phải là JPEG, PNG, WebP hoặc GIF hợp lệ, tối đa 5 MB; ảnh có sẵn trong `assets` cũng được kiểm tra đường dẫn, MIME và dung lượng. Tệp ảnh cũ không bị xóa khi thay/gỡ ảnh hoặc xóa sản phẩm vì có thể còn được sản phẩm khác hoặc `order_items.image` dùng làm snapshot. Giao diện dùng `assets/banner/logo.png` nếu ảnh rỗng, sai đường dẫn hoặc không tải được.

Quản lý danh mục dùng mã tự sinh, tên đã chuẩn hóa khoảng trắng, mô tả, ảnh đại diện tùy chọn, trạng thái hoạt động và dấu thời gian tạo/cập nhật. Danh mục được xóa mềm và có thể khôi phục; nếu đang có sản phẩm, Admin phải chọn danh mục hoạt động để chuyển toàn bộ sản phẩm trước khi xóa. Danh mục ngừng hoạt động hoặc đã xóa mềm không xuất hiện trong luồng mua sắm Client và không thể được chọn khi tạo/sửa sản phẩm.


## Cấu hình PHP và database


- `config/db.php` là nơi duy nhất tạo kết nối MySQL cho ứng dụng.
- Kết nối hiện được cung cấp qua biến `$conn` (MySQLi) và đặt charset `utf8mb4`.
- Từ file nằm trong thư mục con, nạp cấu hình bằng đường dẫn dựa trên `__DIR__`, không dựa vào thư mục chạy hiện thời. Ví dụ trong `api/orders/place_order.php`:


  ```php
  require_once __DIR__ . '/../../config/db.php';
  ```


- Dùng `require_once` để tránh nạp lặp cấu hình. Không tự tạo kết nối MySQL thứ hai trong trang hoặc API.
- Cấu hình local XAMPP có thể khác môi trường triển khai. Không đưa mật khẩu, khóa hoặc thông tin máy chủ production vào repository; nếu đổi cách quản lý cấu hình thì cả nhóm thống nhất trước và cập nhật hướng dẫn chạy web.
- API tự đặt header và định dạng phản hồi theo chức năng (ví dụ JSON); không thêm logic giao diện vào `config/db.php`.

## File dùng chung


- `include/header.php`, `include/footer.php`: khung giao diện khách hàng.
- `include/admin_guard.php`, `include/admin_roles.php`: kiểm tra đăng nhập và quyền truy cập quản trị.
- `include/admin_navigation.php`: điều hướng khu vực quản trị.
- Trước khi sửa file dùng chung, kiểm tra các trang đang nạp file đó để tránh làm lỗi nhiều chức năng cùng lúc.


## Khởi chạy local


1. Đặt dự án trong thư mục web của XAMPP, ví dụ `C:\xampp\htdocs\Baitaplon`.
2. Bật Apache và MySQL trong XAMPP.
3. Tạo database theo `database/baitaplon.sql` trong phpMyAdmin. File này cũng khởi tạo sản phẩm mẫu theo danh mục, sử dụng ảnh có sẵn trong `assets/`.
4. Kiểm tra cấu hình kết nối local trong `config/db.php`. Ứng dụng tự đồng bộ trường giới hạn lượt sửa và các bảng ảnh/phản hồi đánh giá nếu database chưa có.
5. Mở `http://localhost/Baitaplon/`.



## Tài khoản 
Tài khoản nhân viên ( staff)
Tên đăng nhập : nhanvien_demo
Mật khẩu : EcrinStaff@2026
Vai trò : Nhân viên
Tài khoản quản lí ( manager)
Tên đăng nhập:
Mật khẩu :admin_demo	Admin!9481c10b0054
Tài khoản khách hàng : Khach!4b3d93491143
