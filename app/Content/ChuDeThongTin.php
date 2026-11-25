<?php

namespace App\Content;

/**
 * Lớp ChuDeThongTin - nội dung tĩnh các trang thông tin (client/thong-tin.php?topic=...):
 * câu hỏi thường gặp, hướng dẫn đặt hàng, chính sách, giới thiệu, liên hệ...
 */
final class ChuDeThongTin
{
    /** Toàn bộ chủ đề, khóa = giá trị tham số ?topic= */
    public static function all(): array
    {
        return [
            'cau-hoi-thuong-gap' => [
                'title' => 'Các câu hỏi thường gặp',
                'eyebrow' => 'HỖ TRỢ KHÁCH HÀNG',
                'intro' => 'Những thông tin hữu ích giúp bạn an tâm chọn mua và chăm sóc món trang sức yêu thích tại Écrin Jewelry.',
                'faqs' => [
                    ['Tôi có cần tạo tài khoản để đặt hàng không?', 'Bạn cần đăng nhập tài khoản khách hàng để hoàn tất đặt hàng trên website. Nếu chưa có tài khoản, hãy chọn Đăng ký ở đầu trang.'],
                    ['Làm sao để đặt hàng?', 'Chọn sản phẩm, thêm vào giỏ hàng và kiểm tra lại sản phẩm cùng số lượng. Tiếp tục đến trang đặt hàng, đăng nhập tài khoản và điền đầy đủ thông tin người nhận để gửi đơn.'],
                    ['Website hiện có những cách thanh toán nào?', 'Bản demo hỗ trợ chọn thanh toán khi nhận hàng (COD) hoặc phương thức QR minh họa. Mã QR hiện tại chỉ để trình bày giao diện, không dùng để chuyển tiền hay xác nhận thanh toán.'],
                    ['Phí và thời gian giao hàng được tính thế nào?', 'Phí giao hàng và thời gian dự kiến phụ thuộc vào địa chỉ nhận. Cửa hàng sẽ trao đổi thông tin cụ thể khi liên hệ xác nhận đơn; website hiện không hiển thị mức phí hoặc thời hạn cố định.'],
                    ['Làm sao theo dõi đơn hàng?', 'Mở mục Tra cứu đơn hàng trên thanh điều hướng và nhập mã đơn hàng hoặc số điện thoại đã dùng khi đặt hàng.'],
                    ['Tôi muốn cập nhật hoặc hủy đơn thì làm thế nào?', 'Hãy liên hệ cửa hàng sớm nhất có thể qua hotline hoặc Chat với cửa hàng, kèm mã đơn hàng. Nhân viên sẽ kiểm tra tình trạng xử lý và hướng dẫn phương án phù hợp.'],
                    ['Tôi cần hỗ trợ đổi hoặc trả sản phẩm thì sao?', 'Liên hệ cửa hàng, cung cấp mã đơn và mô tả vấn đề. Yêu cầu sẽ được kiểm tra theo tình trạng sản phẩm và thông tin đơn hàng; vui lòng chờ hướng dẫn trước khi gửi sản phẩm.'],
                    ['Tôi cần tư vấn thêm thì liên hệ ở đâu?', 'Gọi hotline 1800 6324 (08:00–22:00, kể cả thứ Bảy và Chủ nhật), nhắn tin qua trang Chat với cửa hàng hoặc ghé cửa hàng Écrin.'],
                ],
                'actions' => [
                    ['label' => 'Tra cứu đơn hàng', 'href' => '/client/tracuudh.php', 'primary' => true],
                    ['label' => 'Chat với cửa hàng', 'href' => '/client/tro_chuyen.php'],
                ],
            ],
            'huong-dan-dat-hang' => [
                'title' => 'Hướng dẫn đặt hàng',
                'eyebrow' => 'MUA SẮM DỄ DÀNG',
                'intro' => 'Bạn có thể chọn trang sức, lưu sản phẩm vào giỏ và gửi yêu cầu đặt hàng trực tuyến. Hãy dành ít phút kiểm tra sản phẩm cũng như thông tin nhận hàng trước khi xác nhận.',
                'sections' => [
                    ['title' => '01 · Khám phá và chọn sản phẩm', 'paragraphs' => ['Duyệt các danh mục trên thanh điều hướng hoặc sử dụng ô tìm kiếm. Mở trang sản phẩm để xem hình ảnh, tên và thông tin được cung cấp trước khi thêm vào giỏ hàng.']],
                    ['title' => '02 · Kiểm tra giỏ hàng', 'paragraphs' => ['Đối chiếu tên sản phẩm, số lượng và tổng tiền hàng. Nếu muốn thay đổi lựa chọn, bạn có thể cập nhật giỏ hàng trước khi chuyển sang bước đặt hàng.']],
                    ['title' => '03 · Đăng nhập và nhập thông tin người nhận', 'paragraphs' => ['Đăng nhập tài khoản khách hàng để tiếp tục. Điền họ tên, số điện thoại, email, tỉnh/thành, xã/phường và địa chỉ cụ thể. Kiểm tra số nhà, tên đường và thông tin liên hệ để hạn chế nhầm lẫn khi giao hàng.', 'Bạn có thể chọn một địa chỉ đã lưu hoặc lưu địa chỉ mới làm mặc định cho lần mua sau.']],
                    ['title' => '04 · Kiểm tra phương thức và gửi đơn', 'paragraphs' => ['Chọn thanh toán khi nhận hàng (COD) hoặc phương thức QR minh họa. Nếu chọn QR, số tiền và hình QR demo sẽ hiển thị; hình này không dùng để chuyển tiền hay xác nhận thanh toán. Kiểm tra lại thông tin đơn rồi chọn Hoàn tất đặt hàng.']],
                    ['title' => '05 · Lưu mã và theo dõi đơn', 'paragraphs' => ['Sau khi đặt hàng thành công, lưu mã đơn hàng được hiển thị. Mở mục Tra cứu đơn hàng và nhập mã đơn hoặc số điện thoại đã dùng khi đặt để xem tình trạng xử lý.']],
                    ['title' => 'Nếu cần thay đổi thông tin', 'paragraphs' => ['Liên hệ cửa hàng càng sớm càng tốt, cung cấp mã đơn hàng và nội dung cần điều chỉnh. Khả năng cập nhật phụ thuộc vào tình trạng xử lý đơn; nhân viên sẽ kiểm tra và hướng dẫn bạn.']],
                ],
                'actions' => [
                    ['label' => 'Mở giỏ hàng', 'href' => '/client/gio_hang.php', 'primary' => true],
                    ['label' => 'Tra cứu đơn hàng', 'href' => '/client/tracuudh.php'],
                ],
            ],
            'phuong-thuc-thanh-toan' => [
                'title' => 'Phương thức thanh toán',
                'eyebrow' => 'THANH TOÁN AN TÂM',
                'intro' => 'Để bạn nắm rõ khoản cần thanh toán, phương thức hiện có và những lưu ý trước khi nhận hàng, vui lòng tham khảo thông tin dưới đây.',
                'sections' => [
                    ['title' => 'Thanh toán khi nhận hàng (COD)', 'paragraphs' => ['Chọn COD nếu muốn thanh toán trực tiếp cho nhân viên giao hàng khi nhận đơn. Số tiền căn cứ vào thông tin đơn hàng đã được xác nhận; phí giao hàng (nếu có) sẽ được cửa hàng thông báo khi liên hệ.']],
                    ['title' => 'Phương thức QR minh họa', 'paragraphs' => ['Khi chọn phương thức QR ở bước đặt hàng, trang hiển thị số tiền hàng và hình QR demo. Đây chỉ là nội dung minh họa giao diện, không nhận chuyển khoản và không xác nhận thanh toán thật.']],
                    ['title' => 'Trước khi hoàn tất đặt hàng', 'items' => ['Kiểm tra sản phẩm, số lượng và tổng tiền hàng trong giỏ.', 'Đảm bảo số điện thoại và địa chỉ nhận hàng có thể liên hệ được.', 'Chờ cửa hàng xác nhận thông tin đơn hàng và khoản phí giao hàng (nếu có).']],
                    ['title' => 'Khi cần hỗ trợ', 'paragraphs' => ['Nếu thông tin thanh toán chưa rõ hoặc có thay đổi về đơn hàng, hãy gọi hotline 1800 6324 trong khung giờ 08:00–22:00 hằng ngày hoặc nhắn tin qua trang Chat với cửa hàng. Vui lòng cung cấp mã đơn hàng để được hỗ trợ nhanh hơn.']],
                ],
                'actions' => [
                    ['label' => 'Bắt đầu mua sắm', 'href' => '/index.php', 'primary' => true],
                    ['label' => 'Liên hệ hỗ trợ', 'href' => '/client/tro_chuyen.php'],
                ],
            ],
            'phuong-thuc-van-chuyen' => [
                'title' => 'Phương thức vận chuyển',
                'eyebrow' => 'GIAO HÀNG TẬN NƠI',
                'intro' => 'Écrin tiếp nhận thông tin giao hàng trực tuyến và giao hàng đến địa chỉ bạn cung cấp. Vui lòng điền thông tin rõ ràng để cửa hàng thuận tiện xác nhận và giao nhận.',
                'sections' => [
                    ['title' => 'Địa chỉ và người nhận', 'paragraphs' => ['Hình thức hiện có trên website là giao hàng tiêu chuẩn. Khi đặt hàng, chọn tỉnh/thành, xã/phường và nhập địa chỉ cụ thể gồm số nhà, tên đường hoặc thông tin nhận diện cần thiết.', 'Vui lòng cung cấp tên và số điện thoại của người có thể nhận hàng. Trước khi gửi đơn, kiểm tra lại địa chỉ để tránh thiếu hoặc nhầm thông tin.']],
                    ['title' => 'Phí giao hàng và thời gian dự kiến', 'paragraphs' => ['Phí giao hàng (nếu có) và thời gian dự kiến phụ thuộc vào địa chỉ nhận cũng như quá trình vận chuyển. Cửa hàng sẽ trao đổi thông tin cụ thể khi liên hệ xác nhận đơn; website không áp dụng một mức phí hoặc thời hạn cố định cho mọi địa chỉ.']],
                    ['title' => 'Theo dõi và cập nhật đơn hàng', 'paragraphs' => ['Dùng mã đơn hàng hoặc số điện thoại đặt hàng tại mục Tra cứu đơn hàng để xem tình trạng xử lý. Nếu cần thay đổi địa chỉ hoặc hỗ trợ giao nhận, liên hệ cửa hàng sớm và cung cấp mã đơn. Việc cập nhật phụ thuộc vào tiến độ đơn hàng.']],
                ],
                'actions' => [
                    ['label' => 'Tra cứu đơn hàng', 'href' => '/client/tracuudh.php', 'primary' => true],
                    ['label' => 'Hệ thống cửa hàng', 'href' => '/client/htch.php'],
                ],
            ],
            'chinh-sach-doi-tra' => [
                'title' => 'Chính sách đổi trả',
                'eyebrow' => 'ĐỒNG HÀNH CÙNG BẠN',
                'intro' => 'Nếu sản phẩm hoặc đơn hàng chưa đúng như mong đợi, hãy liên hệ Écrin để cửa hàng tiếp nhận thông tin, kiểm tra tình trạng thực tế và hướng dẫn phương án xử lý phù hợp.',
                'sections' => [
                    ['title' => 'Bước 1 · Gửi yêu cầu cho cửa hàng', 'paragraphs' => ['Liên hệ hotline 1800 6324 hoặc Chat với cửa hàng. Chuẩn bị mã đơn hàng, tên sản phẩm, thời điểm nhận hàng và mô tả rõ tình trạng cần hỗ trợ.', 'Nếu thuận tiện, gửi kèm hình ảnh sản phẩm và bao bì để cửa hàng có thêm thông tin ban đầu.']],
                    ['title' => 'Bước 2 · Bảo quản sản phẩm trong khi chờ hướng dẫn', 'items' => ['Giữ sản phẩm, bao bì và phụ kiện đi kèm (nếu có) ở tình trạng hiện tại.', 'Không tự sửa chữa hoặc gửi sản phẩm về địa chỉ chưa được cửa hàng xác nhận.', 'Liên hệ sớm khi phát hiện vấn đề để cửa hàng có thể kiểm tra thông tin đơn hàng.']],
                    ['title' => 'Bước 3 · Kiểm tra và thống nhất hướng xử lý', 'paragraphs' => ['Cửa hàng sẽ đối chiếu thông tin đơn hàng và tình trạng sản phẩm, sau đó phản hồi phương án cũng như hướng dẫn các bước tiếp theo. Điều kiện và cách xử lý được xác nhận theo từng trường hợp; vui lòng liên hệ trước khi gửi sản phẩm.', 'Website chưa công bố thời hạn tiếp nhận hoặc điều kiện đổi/trả cố định. Nếu bạn cần xác nhận quyền lợi áp dụng cho đơn hàng cụ thể, hãy liên hệ cửa hàng để được tư vấn trước khi thực hiện.']],
                ],
                'actions' => [
                    ['label' => 'Chat về đơn hàng', 'href' => '/client/tro_chuyen.php', 'primary' => true],
                    ['label' => 'Tra cứu đơn hàng', 'href' => '/client/tracuudh.php'],
                ],
            ],
            'gioi-thieu' => [
                'title' => 'Giới thiệu Écrin Jewelry',
                'eyebrow' => 'VẺ ĐẸP CỦA NHỮNG ĐIỀU ĐÁNG TRÂN TRỌNG',
                'intro' => 'Écrin Jewelry đồng hành cùng bạn trên hành trình tìm kiếm món trang sức phù hợp với phong cách, câu chuyện và những dịp đáng nhớ trong cuộc sống.',
                'sections' => [
                    ['title' => 'Trang sức cho nhiều phong cách', 'paragraphs' => ['Bộ sưu tập trực tuyến có bông tai, dây chuyền, nhẫn, vòng tay và set quà tặng. Bạn có thể khám phá từng danh mục, xem thông tin sản phẩm và lựa chọn thiết kế phù hợp với nhu cầu của mình.']],
                    ['title' => 'Lấy trải nghiệm khách hàng làm trọng tâm', 'paragraphs' => ['Từ lúc tìm hiểu sản phẩm đến khi cần hỗ trợ về đơn hàng, Écrin hướng đến trải nghiệm rõ ràng, thuận tiện và gần gũi. Thông tin liên hệ và các hướng dẫn mua sắm được tập hợp ngay trên website.']],
                    ['title' => 'Kết nối trực tuyến và tại cửa hàng', 'paragraphs' => ['Bạn có thể đặt hàng trên website, trao đổi với cửa hàng qua kênh chat hoặc xem địa chỉ các điểm bán tại trang Hệ thống cửa hàng. Hotline chăm sóc khách hàng hoạt động từ 08:00 đến 22:00 hằng ngày.']],
                ],
                'actions' => [
                    ['label' => 'Khám phá bộ sưu tập', 'href' => '/index.php', 'primary' => true],
                    ['label' => 'Tìm cửa hàng', 'href' => '/client/htch.php'],
                ],
            ],
            'chinh-sach-bao-mat' => [
                'title' => 'Chính sách bảo mật',
                'eyebrow' => 'THÔNG TIN CỦA BẠN ĐƯỢC TÔN TRỌNG',
                'intro' => 'Chính sách này giải thích cách Écrin Jewelry sử dụng thông tin bạn cung cấp khi tạo tài khoản, đặt hàng hoặc liên hệ với cửa hàng.',
                'sections' => [
                    ['title' => 'Thông tin có thể được thu thập', 'items' => ['Thông tin tài khoản và liên hệ như họ tên, số điện thoại, email và thông tin đăng nhập.', 'Thông tin nhận hàng bạn nhập khi đặt đơn, chẳng hạn tên người nhận, số điện thoại và địa chỉ.', 'Thông tin đơn hàng và nội dung bạn chủ động gửi khi trao đổi với cửa hàng để được hỗ trợ.']],
                    ['title' => 'Mục đích sử dụng thông tin', 'paragraphs' => ['Thông tin được sử dụng để tạo và quản lý tài khoản, tiếp nhận và xác nhận đơn hàng, giao sản phẩm đến đúng địa chỉ, hỗ trợ tra cứu đơn và phản hồi yêu cầu của khách hàng.', 'Thông tin chỉ nên được cung cấp ở mức cần thiết cho mục đích giao dịch và hỗ trợ. Vui lòng không gửi mật khẩu hoặc thông tin nhạy cảm qua nội dung chat.']],
                    ['title' => 'Chia sẻ thông tin khi cần thiết', 'paragraphs' => ['Để thực hiện giao dịch, một số thông tin liên hệ và giao nhận có thể được chuyển cho nhân sự hoặc đối tác cung cấp dịch vụ liên quan, chẳng hạn đơn vị vận chuyển. Thông tin không được sử dụng cho mục đích không liên quan đến yêu cầu của bạn, trừ trường hợp pháp luật yêu cầu.']],
                    ['title' => 'Lưu trữ, bảo vệ và lựa chọn của bạn', 'paragraphs' => ['Thông tin được lưu giữ trong thời gian cần thiết để hỗ trợ tài khoản, đơn hàng, chăm sóc khách hàng và đáp ứng nghĩa vụ liên quan. Écrin áp dụng các biện pháp phù hợp nhằm hạn chế việc truy cập hoặc sử dụng thông tin không đúng mục đích.', 'Bạn có thể yêu cầu hỗ trợ cập nhật thông tin tài khoản hoặc đặt câu hỏi về dữ liệu cá nhân qua hotline 1800 6324 hoặc trang Chat với cửa hàng. Một số thông tin liên quan đến đơn hàng có thể cần được lưu giữ theo quy định áp dụng.']],
                    ['title' => 'Cập nhật chính sách', 'paragraphs' => ['Nội dung chính sách có thể được điều chỉnh khi cách thức vận hành website thay đổi. Phiên bản hiện đăng trên trang này là thông tin tham khảo tại thời điểm bạn truy cập. Nếu có thắc mắc, vui lòng liên hệ Écrin trước khi cung cấp thêm thông tin cá nhân.']],
                ],
                'actions' => [
                    ['label' => 'Chat với cửa hàng', 'href' => '/client/tro_chuyen.php', 'primary' => true],
                ],
            ],
            'dieu-khoan-su-dung' => [
                'title' => 'Điều khoản sử dụng',
                'eyebrow' => 'SỬ DỤNG WEBSITE CÓ TRÁCH NHIỆM',
                'intro' => 'Các điều khoản dưới đây giúp khách hàng sử dụng website Écrin Jewelry an toàn, minh bạch và đúng mục đích.',
                'sections' => [
                    ['title' => 'Tài khoản và thông tin cung cấp', 'paragraphs' => ['Khi đăng ký hoặc đặt hàng, vui lòng cung cấp thông tin đầy đủ, chính xác và cập nhật khi có thay đổi. Bạn có trách nhiệm bảo vệ thông tin đăng nhập và thông báo cho Écrin nếu nghi ngờ tài khoản bị sử dụng trái phép.']],
                    ['title' => 'Thông tin sản phẩm và đặt hàng', 'paragraphs' => ['Thông tin trên website nhằm giúp khách hàng tham khảo sản phẩm và gửi yêu cầu đặt hàng. Đơn hàng cần được cửa hàng tiếp nhận, kiểm tra và xác nhận các thông tin cần thiết. Phí giao hàng (nếu có) được trao đổi khi xác nhận; thời gian thực tế phụ thuộc địa chỉ và quá trình vận chuyển.', 'Hình ảnh hiển thị có thể có chênh lệch nhỏ về màu sắc do màn hình và điều kiện hiển thị. Vui lòng tham khảo mô tả sản phẩm và liên hệ cửa hàng nếu cần thêm thông tin trước khi đặt.']],
                    ['title' => 'Sử dụng website đúng mục đích', 'items' => ['Không sử dụng website để cung cấp thông tin giả mạo, gây ảnh hưởng đến hệ thống hoặc xâm phạm quyền và lợi ích hợp pháp của người khác.', 'Không tự ý sao chép, sửa đổi hoặc sử dụng nội dung, hình ảnh và dấu hiệu nhận diện cho mục đích thương mại khi chưa được chủ sở hữu cho phép.', 'Không cố gắng truy cập trái phép vào tài khoản, dữ liệu hoặc khu vực quản trị của website.']],
                    ['title' => 'Quyền sở hữu nội dung', 'paragraphs' => ['Nội dung, hình ảnh và dấu hiệu nhận diện trên website thuộc Écrin Jewelry hoặc bên cấp quyền. Việc truy cập website không đồng nghĩa với việc chuyển giao quyền sở hữu hoặc quyền sử dụng các nội dung này.']],
                    ['title' => 'Cập nhật và hỗ trợ', 'paragraphs' => ['Écrin có thể cập nhật nội dung website hoặc điều khoản để phù hợp với hoạt động và quy định hiện hành. Nếu một nội dung chưa rõ hoặc cần hỗ trợ về giao dịch, vui lòng liên hệ cửa hàng trước khi tiếp tục sử dụng dịch vụ.']],
                ],
                'actions' => [
                    ['label' => 'Liên hệ hỗ trợ', 'href' => '/client/tro_chuyen.php', 'primary' => true],
                ],
            ],
            'tuyen-dung' => [
                'title' => 'Cơ hội nghề nghiệp',
                'eyebrow' => 'CÙNG ÉCRIN TẠO NÊN ĐIỀU ĐẸP ĐẼ',
                'intro' => 'Écrin Jewelry chào đón những ứng viên yêu thích trang sức, trân trọng trải nghiệm khách hàng và muốn phát triển trong môi trường bán lẻ.',
                'sections' => [
                    ['title' => 'Cùng xây dựng trải nghiệm mua sắm tốt hơn', 'paragraphs' => ['Các vị trí tại cửa hàng và hoạt động hỗ trợ có thể phù hợp với người giao tiếp thân thiện, làm việc có trách nhiệm, ham học hỏi và quan tâm đến sản phẩm trang sức. Écrin đề cao sự chuyên nghiệp, tinh thần hợp tác và thái độ phục vụ tận tâm.']],
                    ['title' => 'Vị trí đang tuyển', 'paragraphs' => ['Danh sách vị trí tuyển dụng sẽ được cập nhật theo nhu cầu thực tế. Website hiện chưa hiển thị vị trí đang mở, vì vậy vui lòng liên hệ trước để xác nhận cơ hội tuyển dụng hiện có.']],
                    ['title' => 'Cách liên hệ ứng tuyển', 'paragraphs' => ['Gọi hotline 1800 6324 trong khung giờ 08:00–22:00 hoặc nhắn tin qua trang Chat với cửa hàng. Vui lòng ghi rõ bạn quan tâm đến cơ hội nghề nghiệp để được hướng dẫn kênh tiếp nhận hồ sơ phù hợp. Không gửi thông tin nhạy cảm hoặc giấy tờ cá nhân qua kênh chat khi chưa được yêu cầu.']],
                ],
                'actions' => [
                    ['label' => 'Liên hệ Écrin', 'href' => '/client/tro_chuyen.php', 'primary' => true],
                ],
            ],
            'lien-he' => [
                'title' => 'Liên hệ Écrin Jewelry',
                'eyebrow' => 'CHÚNG TÔI LUÔN SẴN SÀNG LẮNG NGHE',
                'intro' => 'Đội ngũ Écrin sẵn sàng tiếp nhận câu hỏi về sản phẩm, đơn hàng và trải nghiệm mua sắm. Hãy chọn kênh phù hợp với nhu cầu của bạn.',
                'sections' => [
                    ['title' => 'Hotline chăm sóc khách hàng', 'paragraphs' => ['Gọi 1800 6324 để được hỗ trợ miễn phí từ 08:00 đến 22:00 hằng ngày, kể cả thứ Bảy và Chủ nhật.']],
                    ['title' => 'Chat với cửa hàng', 'paragraphs' => ['Gửi tin nhắn qua trang Chat với cửa hàng để hỏi về sản phẩm hoặc trao đổi với nhân viên. Với yêu cầu liên quan đến đơn hàng, hãy chuẩn bị mã đơn hoặc số điện thoại đặt hàng để việc tra cứu thuận tiện hơn.']],
                    ['title' => 'Hệ thống cửa hàng', 'paragraphs' => ['Xem địa chỉ, số điện thoại và giờ hoạt động của các điểm bán tại trang Hệ thống cửa hàng. Bạn nên liên hệ cửa hàng trước khi ghé nếu cần xác nhận thông tin cụ thể.']],
                    ['title' => 'Hỗ trợ đơn hàng', 'paragraphs' => ['Bạn cũng có thể mở trang Tra cứu đơn hàng để xem tình trạng xử lý. Nếu cần thay đổi thông tin hoặc báo vấn đề, vui lòng liên hệ Écrin sớm và cung cấp mã đơn.']],
                ],
                'actions' => [
                    ['label' => 'Chat với cửa hàng', 'href' => '/client/tro_chuyen.php', 'primary' => true],
                    ['label' => 'Xem hệ thống cửa hàng', 'href' => '/client/htch.php'],
                    ['label' => 'Gọi 1800 6324', 'href' => 'tel:18006324'],
                ],
            ],
            'trang-suc-ecrin' => [
                'title' => 'Trang sức Écrin',
                'eyebrow' => 'TÌM ĐIỂM NHẤN CỦA RIÊNG BẠN',
                'intro' => 'Từ món trang sức điểm xuyết hằng ngày đến món quà cho một dịp đáng nhớ, khám phá các danh mục Écrin và tìm lựa chọn phù hợp với bạn.',
                'sections' => [
                    ['title' => 'Khám phá theo danh mục', 'items' => ['Bông tai: những thiết kế tạo điểm nhấn tinh tế cho phong cách mỗi ngày.', 'Dây chuyền: lựa chọn nhẹ nhàng để phối cùng trang phục yêu thích.', 'Nhẫn: món trang sức mang dấu ấn riêng hoặc món quà giàu ý nghĩa.', 'Vòng tay: hoàn thiện diện mạo với chi tiết thanh lịch.', 'Set quà tặng: gợi ý để gửi trao tình cảm trong những dịp đặc biệt.']],
                    ['title' => 'Chọn món phù hợp', 'paragraphs' => ['Xem thông tin và hình ảnh trên trang sản phẩm để cân nhắc lựa chọn. Nếu cần tư vấn thêm về sản phẩm hoặc muốn ghé xem trực tiếp, hãy liên hệ đội ngũ Écrin qua hotline, chat hoặc trang Hệ thống cửa hàng.']],
                ],
                'actions' => [
                    ['label' => 'Bông tai', 'href' => '/client/bong_tai.php', 'primary' => true],
                    ['label' => 'Dây chuyền', 'href' => '/client/day_chuyen.php'],
                    ['label' => 'Nhẫn', 'href' => '/client/nhan.php'],
                    ['label' => 'Vòng tay', 'href' => '/client/vong_tay.php'],
                    ['label' => 'Set quà tặng', 'href' => '/client/danh_muc.php?slug=set-qua-tang'],
                    ['label' => 'Tư vấn cùng Écrin', 'href' => '/client/tro_chuyen.php'],
                ],
            ],
        ];
    }

    /** Lấy một chủ đề theo khóa, null nếu không tồn tại. */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
