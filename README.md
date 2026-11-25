# ÉCRIN Jewelry


Website bán trang sức xây dựng bằng PHP, MySQL, HTML, CSS và JavaScript, chạy local qua XAMPP.

Phạm vi hiện tại gồm các chức năng phía khách hàng và một số nghiệp vụ Admin. Quản lý sản phẩm có thêm trạng thái kinh doanh, xóa mềm, thùng rác, khôi phục và bảo vệ dữ liệu liên quan; một số khu vực Admin khác vẫn là giao diện mẫu.


# Cấu trúc dự án

```text
Baitaplon/
├── index.php                              # Trang chủ cửa hàng
├── admin/                                 # Trang quản trị nội bộ
│   ├── index.php                          # Điểm vào/tổng quan quản trị
│   ├── dang_nhap.php, dang_xuat.php       # Đăng nhập / đăng xuất nhân viên, quản lý
│   ├── tong_quan.php                      # Chuyển hướng cũ về trang tổng quan
│   ├── san_pham.php, danh_muc.php         # Quản lý sản phẩm, danh mục
│   ├── don_hang.php, thanh_toan.php       # Quản lý đơn hàng (thanh_toan.php chuyển hướng về don_hang.php)
│   ├── tai_khoan.php, cua_hang.php        # Quản lý tài khoản nhân viên, cửa hàng
│   ├── tro_chuyen.php, anh_bia.php        # Quản lý chat, banner trang chủ
│   ├── bao_cao.php, danh_gia.php          # Báo cáo thống kê, đánh giá sản phẩm
│   ├── mau_tra_loi_tu_dong.php            # Mẫu trả lời tự động (sua_mau_tra_loi_tu_dong.php để sửa)
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
│   ├── payment/                           # Tạo mới mã VietQR cho đơn hàng
│   └── chat/                              # API chat phía khách hàng
│
├── client/                                # Trang và chức năng phía khách hàng
│   ├── bong_tai.php, day_chuyen.php, ...  # Các trang danh mục cố định (nhan.php, vong_tay.php)
│   ├── danh_muc.php, san_pham.php         # Danh mục theo slug, chi tiết sản phẩm
│   ├── gio_hang.php                       # Trang giỏ hàng toàn màn hình
│   ├── dat_hang.php                       # Trang nhập thông tin, đặt COD hoặc lấy mã VietQR
│   ├── thanh_toan.php                     # Trang thanh toán VietQR
│   ├── ho_so.php                          # Hồ sơ khách hàng (họ tên, số điện thoại, đổi mật khẩu, đăng xuất)
│   ├── yeu_thich.php, tro_chuyen.php      # Sản phẩm yêu thích, chat với cửa hàng
│   ├── diachi.php, tracuudh.php           # Địa chỉ, tra cứu đơn hàng
├── assets/                                # CSS, JavaScript và hình ảnh website
│   ├── style.css                          # CSS cửa hàng
│   ├── script.js                          # Tương tác giao diện, giỏ hàng và tài khoản
│   ├── admin.css                          # CSS quản trị
│   ├── banner/                            # Banner và logo hiện tại
│   ├── qrcode-generator.js                # Thư viện tạo mã QR cục bộ
│   ├── bôngtai/                           # Ảnh sản phẩm bông tai
│   ├── dây chuyền/                        # Ảnh sản phẩm dây chuyền
│   ├── nhẫn/                              # Ảnh sản phẩm nhẫn
│   ├── quà/                               # Ảnh sản phẩm danh mục Set quà tặng
│   └── vòng tay/                          # Ảnh sản phẩm vòng tay
│
├── app/                                   # Mã nguồn hướng đối tượng (namespace App\)
│   ├── bootstrap.php                      # Autoloader PSR-4 + khởi động session
│   ├── routes.php                         # Bảng định tuyến: URL -> Controller@action
│   ├── Core/                              # Router, Controller, View, Database, Session, Auth, Url, Request, Response
│   ├── Models/                            # (M) BaseModel + mỗi bảng một Model (SanPham, DonHang, KhachHang, ...)
│   ├── Views/                             # (V) Giao diện HTML
│   │   ├── layouts/                       #     dau_trang, chan_trang, hop_dang_nhap, thanh_ben_quan_tri
│   │   ├── client/                        #     Trang cửa hàng (trang_chu, chi_tiet_san_pham, gio_hang, dat_hang, ho_so, ...)
│   │   └── admin/                         #     Trang quản trị (tong_quan, san_pham, don_hang, bao_cao, ...)
│   ├── Controllers/{Client,Admin,Api}/    # (C) Controller cho từng trang và API
│   ├── Services/                          # Nghiệp vụ: đặt hàng, thanh toán, chat, báo cáo, ...
│   ├── Database/                          # QuanLyCauTruc (tự đồng bộ CSDL), seeder cho scripts
│   ├── Helpers/, Exceptions/, Content/    # Lớp định dạng hiển thị, ngoại lệ, nội dung tĩnh
│
├── config/
│   ├── database.php                       # Thông số kết nối MySQL
│   ├── payment.example.php                # Mẫu cấu hình tài khoản VietQR
│   └── payment.local.php                  # Cấu hình riêng, không đưa vào Git
│
├── database/
│   ├── baitaplon.sql                      # Tạo các bảng và dữ liệu ban đầu
```

Các file `.php` trong `index.php`, `client/`, `admin/`, `api/` chỉ là **điểm vào** (giữ nguyên URL), mỗi file 2 dòng gọi `Router::dispatch()`.

## Quy ước đặt file


- Điểm vào trang khách hàng đặt trong `client/`, trang quản trị trong `admin/`, API trong `api/<nhóm>/`; mỗi điểm vào phải được khai báo trong `app/routes.php`.
- HTML đặt trong `app/Views/`; layout dùng chung đặt trong `app/Views/layouts/`.
- Logic xử lý đặt trong Controller/Service, câu SQL đặt trong Model; View không truy vấn dữ liệu.
- CSS, JavaScript và hình ảnh đặt trong `assets/`.
- Cấu trúc database và dữ liệu khởi tạo đặt trong `database/`.
- Đặt tên file bằng tiếng Việt không dấu theo chức năng: file trang/điểm vào/View dùng chữ thường và dấu gạch dưới (ví dụ `gio_hang.php`, `dat_hang.php`); file class trong `app/` dùng kiểu PascalCase (ví dụ `DonHangService.php`, `SanPhamController.php`). Giữ nguyên tên kỹ thuật mặc định như `index.php`, `bootstrap.php`, `Router`, `Database`, `Session`, `Auth` và hậu tố `Controller`, `Service`.
- Chỉ tạo file/thư mục dùng chung khi có từ hai nơi trở lên cần dùng; tránh tạo bản sao cấu hình hoặc helper ở từng module.


## Đối chiếu chức năng theo sơ đồ ERD


Database trong `database/baitaplon.sql` hiện có các bảng cho tài khoản/role, danh mục, sản phẩm, địa chỉ, đơn hàng/chi tiết đơn, đánh giá, giỏ hàng, chat, banner, cấu hình website và cửa hàng. `order_items` là bảng chi tiết để lưu các sản phẩm thuộc từng đơn hàng; quyền Admin/nhân viên/quản lý được biểu diễn bằng role trong `users`, không cần một bảng Admin riêng.

Trang quản lý đơn hàng phía Admin đọc trực tiếp đơn hàng và chi tiết sản phẩm từ các bảng `orders`/`order_items`; trang tổng quan cũng hiển thị số đơn và số đơn đang chờ xác nhận.

Giỏ hàng được lưu theo tài khoản/khách trong trình duyệt. Trang đặt hàng tải danh sách tỉnh/thành và xã/phường từ `provinces.open-api.vn`; chỉ yêu cầu dữ liệu địa giới, không gửi thông tin người đặt hàng đến dịch vụ này. API đặt hàng tự bổ sung cột email cho bảng `orders` nếu cơ sở dữ liệu cũ chưa có cột đó.

Quản lý vòng đời sản phẩm dùng MySQL/MariaDB qua MySQLi. `products.status` phân biệt `active`, `inactive` và `hidden`; hết hàng được xác định riêng bằng `stock = 0`. Xóa trong Admin luôn là xóa mềm qua `is_deleted`, `deleted_at` và `deleted_by`. Sản phẩm đã xóa mềm được xem/khôi phục trong Thùng rác; Admin không có thao tác xóa cứng. Khóa ngoại của sản phẩm và chi tiết đơn hàng dùng `RESTRICT` để tránh xóa dây chuyền làm mất lịch sử.

Thêm/sửa sản phẩm yêu cầu SKU duy nhất, giá gốc không âm và giá khuyến mãi (nếu có) thấp hơn giá gốc. `promotion_ends_at` xác định thời hạn khuyến mãi; các trang Client và API đặt hàng tự dùng giá gốc sau khi khuyến mãi hết hạn. Mỗi lần thay đổi giá được ghi vào `product_price_history` trong cùng transaction với cập nhật sản phẩm. Giá trong `order_items` được lưu thành snapshot khi tạo đơn, không bị thay đổi khi Admin cập nhật giá.

Mỗi sản phẩm dùng một ảnh đại diện tại `products.image`, phù hợp với cấu trúc và giao diện hiện tại; chưa triển khai gallery ảnh phụ. Admin có thể thêm, thay hoặc gỡ ảnh (gỡ sẽ chuyển sang ảnh mặc định). Ảnh tải lên phải là JPEG, PNG, WebP hoặc GIF hợp lệ, tối đa 5 MB; ảnh có sẵn trong `assets` cũng được kiểm tra đường dẫn, MIME và dung lượng. Tệp ảnh cũ không bị xóa khi thay/gỡ ảnh hoặc xóa sản phẩm vì có thể còn được sản phẩm khác hoặc `order_items.image` dùng làm snapshot. Giao diện dùng `assets/banner/logo.png` nếu ảnh rỗng, sai đường dẫn hoặc không tải được.

Quản lý danh mục dùng mã tự sinh, tên đã chuẩn hóa khoảng trắng, mô tả, ảnh đại diện tùy chọn, trạng thái hoạt động và dấu thời gian tạo/cập nhật. Danh mục được xóa mềm và có thể khôi phục; nếu còn sản phẩm, Admin cần chuyển chúng trong trang Quản lý sản phẩm trước, hệ thống không tự chuyển hàng loạt. Danh mục ngừng hoạt động hoặc đã xóa mềm không xuất hiện trong luồng mua sắm Client và không thể được chọn khi tạo/sửa sản phẩm.


## Kiến trúc MVC hướng đối tượng


Luồng xử lý một request theo mô hình MVC truyền thống:

```text
Trình duyệt ──> client/gio_hang.php (điểm vào) ──> Router (app/routes.php)
                                                   │
                                                   ▼
                              Controller (Client\GioHangController::index)
                               │  gọi Model / Service lấy dữ liệu
                               ▼
                              $this->render('client/gio_hang', $data)
                               │
                               ▼
                              View (app/Views/client/gio_hang.php + layouts) ──> HTML trả về
```

- **Model** (`app/Models/`) – mỗi bảng một lớp kế thừa `BaseModel` (CRUD chung: `find`, `all`, `where`, `create`, `update`, `delete`). Mọi câu SQL nằm trong Model hoặc Service.
- **View** (`app/Views/`) – chỉ chứa HTML và lệnh hiển thị (`echo`, `if`, `foreach`, `View::e()`); không truy vấn CSDL, không gọi Controller. Layout dùng chung nằm ở `app/Views/layouts/`.
- **Controller** (`app/Controllers/`) – nhận request, gọi Model/Service, rồi `render()` View hoặc trả JSON. Phân cấp kế thừa:
  - `App\Core\Controller` (trừu tượng: `render`, `json`, `redirect`)
    - `Client\BaseClientController` – tự thêm dữ liệu layout cửa hàng (menu, tài khoản, logo) khi render
    - `Admin\BaseAdminController` – bắt buộc đăng nhập quản trị / quyền quản lý, CSRF, dữ liệu sidebar
    - `Api\*Controller` – mỗi endpoint một action, trả JSON
- **Router** (`app/Core/Router.php` + `app/routes.php`) – ánh xạ đường dẫn tới `Controller@action`.
- **Service** (`app/Services/`) – nghiệp vụ dùng lại giữa nhiều Controller (VD: `DonHangService`, `ThanhToanService`, `VietQrService`, `TroChuyenService`). Báo cáo dùng kế thừa: `BaoCaoCoSo` → `BaoCaoDoanhThu`, `BaoCaoKhachHang`.

Mỗi điểm vào chỉ gồm 2 dòng, ví dụ `client/gio_hang.php`:

```php
require_once __DIR__ . '/../app/bootstrap.php';
App\Core\Router::dispatch();
```

Khai báo route và action tương ứng:

```php
// app/routes.php
'/client/gio_hang.php' => [Client\GioHangController::class, 'index'],

// app/Controllers/Client/GioHangController.php
public function index(): void
{
    $this->render('client/gio_hang', $this->indexData());
}
```

Thêm trang mới: tạo file điểm vào 2 dòng, khai báo route, viết action trong Controller và tạo View trong `app/Views/`.

Truy vấn CSDL qua Model hoặc trực tiếp `Database`:

```php
$db = App\Core\Database::getInstance();
$product = $db->fetchOne('SELECT * FROM products WHERE id = ?', [$id]);
```


## Cấu hình PHP và database


- Thông số kết nối nằm trong `config/database.php`; `App\Core\Database` là nơi duy nhất tạo kết nối MySQL (charset `utf8mb4`). Không tự tạo kết nối MySQL thứ hai trong trang hoặc API.
- Khi kết nối lần đầu, `App\Database\QuanLyCauTruc` tự đồng bộ cấu trúc (bảng, cột, VIEW, FUNCTION, TRIGGER) cho database tạo từ bản SQL cũ.
- Cấu hình local XAMPP có thể khác môi trường triển khai. Không đưa mật khẩu, khóa hoặc thông tin máy chủ production vào repository.

## Cấu hình thanh toán VietQR

- Sao chép `config/payment.example.php` thành `config/payment.local.php` và điền tên ngân hàng, BIN, số tài khoản và tên chủ tài khoản. File `payment.local.php` được Git bỏ qua; không đưa thông tin tài khoản thật vào commit.
- Mã VietQR được tạo cục bộ trong trình duyệt theo số tiền và nội dung riêng của đơn hàng; ứng dụng không gửi thông tin chuyển khoản tới dịch vụ tạo QR bên ngoài. Thư viện tạo QR MIT được ghim phiên bản trong `package.json` và đóng gói tại `assets/qrcode-generator.js`.
- Mã trên màn hình tự đổi sau 60 giây. Ngân hàng không làm mã QR chuyển khoản thông thường hết hiệu lực, nên mã cũ vẫn có thể được quét; cửa hàng cần đối soát giao dịch trước khi xác nhận trạng thái trong trang quản lý đơn hàng.
- Nút “Thanh toán thành công” chỉ đưa đơn sang trạng thái chờ xác nhận; cửa hàng phải kiểm tra tiền thực nhận trước khi xác nhận. Nút “Thanh toán thất bại” giữ đơn trong 30 phút để khách thanh toán lại hoặc đổi sang COD. Hết hạn, đơn được hủy và tồn kho được hoàn lại.
- Để tự hủy đơn đúng hạn cả khi website không có người truy cập, tạo tác vụ trong Windows Task Scheduler chạy mỗi phút:

  Ví dụ với dự án hiện tại (thay bằng thư mục XAMPP/dự án trên máy bạn nếu khác):

  ```text
  Program: D:\xampp\php\php.exe
  Arguments: D:\xampp\htdocs\baitaplonnhom7\scripts\don_dep_don_vietqr_het_han.php
  ```

  Khi website đang hoạt động, trang thanh toán, danh sách đơn của khách và trang quản trị cũng tự xử lý các đơn đã hết hạn. Cột thời hạn được bổ sung tự động cho database hiện có; database mới cũng khai báo cột này trong `database/baitaplon.sql`.

## File dùng chung


- `app/Views/layouts/dau_trang.php`, `chan_trang.php`, `hop_dang_nhap.php`: khung giao diện khách hàng (dữ liệu do `BaseClientController` cung cấp).
- `App\Core\Auth` (`requireAdmin()`, `requireManager()`) và `App\Controllers\Admin\BaseAdminController`: kiểm tra đăng nhập và quyền truy cập quản trị.
- `app/Views/layouts/thanh_ben_quan_tri.php`: điều hướng khu vực quản trị (menu khai báo trong `BaseAdminController::SIDE_MENU`).
- Trước khi sửa file dùng chung, kiểm tra các trang đang nạp file đó để tránh làm lỗi nhiều chức năng cùng lúc.


## Khởi chạy local


1. Đặt dự án trong thư mục web của XAMPP, ví dụ `C:\xampp\htdocs\Baitaplon`.
2. Bật Apache và MySQL trong XAMPP.
3. Tạo database theo `database/baitaplon.sql` trong phpMyAdmin. File này cũng khởi tạo sản phẩm mẫu theo danh mục, sử dụng ảnh có sẵn trong `assets/`.
4. Kiểm tra cấu hình kết nối local trong `config/database.php`. Ứng dụng tự đồng bộ trường giới hạn lượt sửa và các bảng ảnh/phản hồi đánh giá nếu database chưa có.
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
