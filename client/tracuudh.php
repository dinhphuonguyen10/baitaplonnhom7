<?php
$pageTitle = "Tra cứu đơn hàng - ÉCRIN JEWELRY";
$bodyClass = 'tracking-page';
include __DIR__ . '/../include/header.php';
?>
    <style>
        .tracking-page .header .search {
            display: none;
        }

        .tracking-container {
            max-width: 800px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .tracking-header h1 {
            color: #1a153a;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .tracking-header p {
            color: #666;
            margin-bottom: 40px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            max-width: 500px;
            margin: 0 auto 40px;
        }

        .search-box input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s;
        }

        .search-box input:focus {
            border-color: var(--gold);
        }

        .track-btn {
            background: var(--navy);
            color: white;
            border: none;
            padding: 0 30px;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .track-btn:hover {
            background: var(--navy-mid);
            transform: translateY(-2px);
        }

        /* Order Info (Hidden by default) */
        .order-result {
            display: none;
            text-align: left;
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px dashed #ddd;
        }

        .order-status-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            position: relative;
        }

        .order-status-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #eee;
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 16.6667%;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #eee;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #999;
        }

        .step.active .step-icon {
            border-color: var(--gold);
            background: var(--navy);
            color: white;
        }

        .step.active label {
            color: var(--gold);
            font-weight: bold;
        }

        .step label {
            font-size: 0.85rem;
            color: #999;
        }

        /* Lịch sử đặt hàng nâng cao */
        .order-history-card {
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: left;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .order-history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.04);
        }
        .order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed #eee;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .order-card-header h4 {
            color: var(--gold);
            font-size: 1.1rem;
            margin: 0;
        }
        .order-date {
            font-size: 0.85rem;
            color: #888;
        }
        .order-details-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 15px;
        }
        .order-products-list {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #f0f0f0;
        }
        .order-product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            padding: 8px 0;
            border-bottom: 1px solid #f9f9f9;
        }
        .order-product-item:last-child {
            border-bottom: none;
        }
        .order-product-name {
            font-weight: 500;
            color: #333;
        }
        .order-product-meta {
            color: #777;
            font-size: 0.85rem;
            margin-left: 8px;
        }
        .order-product-price {
            font-weight: bold;
            color: #1a153a;
        }
        .order-total-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1rem;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .order-total-price {
            color: var(--gold);
            font-weight: 800;
            font-size: 1.2rem;
        }
        /* Redesign tra cứu đơn hàng: gọn hơn, rõ thứ bậc và responsive */        .tracking-page {            background:                radial-gradient(circle at 12% 0%, rgba(196, 164, 90, .14), transparent 32%),                var(--cream);        }        .tracking-page main {            min-height: calc(100vh - 260px);            padding: 34px 20px 56px;        }        .tracking-container {            position: relative;            max-width: 980px;            margin: 0 auto;            padding: 54px clamp(24px, 6vw, 76px) 46px;            border: 1px solid rgba(11, 32, 51, .08);            border-radius: 28px;            box-shadow: 0 20px 55px rgba(11, 32, 51, .10);            overflow: hidden;        }        .tracking-container::before {            content: '';            position: absolute;            top: 0;            left: 0;            right: 0;            height: 5px;            background: linear-gradient(90deg, var(--navy), var(--gold), var(--navy));        }        .tracking-header h1 {            margin: 0;            color: var(--navy);            font-size: clamp(2rem, 4vw, 3rem);            letter-spacing: -.04em;        }        .tracking-header p {            max-width: 620px;            margin: 12px auto 30px;            color: #667085;            line-height: 1.6;            font-size: 1rem;        }        .search-box {            width: min(100%, 680px);            max-width: none;            gap: 12px;            margin: 0 auto;            align-items: stretch;        }        .search-box input {            min-width: 0;            height: 56px;            padding: 0 18px;            border: 1px solid #d8dee6;            border-radius: 14px;            font-weight: 500;            box-shadow: inset 0 1px 2px rgba(11, 32, 51, .04);        }        .search-box input:focus {            border-color: var(--gold);            box-shadow: 0 0 0 4px rgba(196, 164, 90, .16);        }        .track-btn {            min-width: 150px;            min-height: 56px;            padding: 0 24px;            border-radius: 14px;            letter-spacing: .06em;            font-size: .85rem;            box-shadow: 0 8px 18px rgba(11, 32, 51, .18);        }        #result-container {            margin-top: 36px !important;            padding-top: 30px !important;            border-top: 1px solid #edf0f3 !important;        }        .tracking-pagination {            display: flex;            justify-content: center;            align-items: center;            gap: 8px;            margin: 24px 0 4px;        }        .tracking-pagination button {            min-width: 36px;            height: 36px;            padding: 0 10px;            border: 1px solid #d8dee6;            border-radius: 9px;            background: #fff;            color: var(--navy);            font-weight: 700;            cursor: pointer;        }        .tracking-pagination button:hover:not(:disabled),        .tracking-pagination button.is-active {            border-color: var(--navy);            background: var(--navy);            color: #fff;        }        .tracking-pagination button:disabled {            cursor: not-allowed;            opacity: .45;        }        .tracking-page-count {            margin-left: 6px;            color: #667085;            font-size: .85rem;            font-weight: 600;        }        .order-history-card {            background: #fff;            border: 1px solid #e7ebf0;            border-radius: 18px;            padding: 24px;            box-shadow: 0 8px 24px rgba(11, 32, 51, .07);        }        .order-card-header h4 {            color: var(--navy);            font-weight: 800;        }        .order-status-steps {            margin-top: 30px;            padding: 0 6px;            gap: 5px;        }        .step label {            display: block;            font-size: .75rem;            line-height: 1.35;            font-weight: 600;        }        @media (max-width: 700px) {            .tracking-page main {                padding: 20px 12px 36px;            }            .tracking-container {                padding: 42px 18px 28px;                border-radius: 20px;            }            .tracking-header h1 {                font-size: 2rem;            }            .tracking-header p {                margin-bottom: 24px;                font-size: .92rem;            }            .search-box {                flex-direction: column;            }            .track-btn {                width: 100%;                min-height: 52px;            }            .order-history-card {                padding: 18px 14px;            }            .order-status-steps {                padding: 0;                gap: 0;            }            .step label {                font-size: .62rem;            }            .step-icon {                width: 34px;                height: 34px;                font-size: .8rem;            }            .order-status-steps::before {                top: 17px;                left: 7%;                right: 7%;            }        }    </style>

    <main>
        <div class="tracking-container">
            <div class="tracking-header">
                <h1>Tra cứu đơn hàng</h1>
                <p>Nhập mã đơn hàng hoặc số điện thoại để theo dõi hành trình gói hàng của bạn.</p>
            </div>

            <div class="search-box">
                <input type="text" id="order-input" placeholder="Ví dụ: BLUE123456">
                <button class="track-btn" id="btn-track">TRA CỨU</button>
            </div>

            <div id="result-container" style="display: none; margin-top: 40px; padding-top: 40px; border-top: 1px dashed #ddd;">
                <!-- Danh sách đơn hàng/lịch sử đặt hàng động sẽ được render ở đây -->
            </div>

            <p id="error-msg" style="color: red; display: none; margin-top: 20px;">Rất tiếc! Chúng tôi không tìm thấy thông tin đơn hàng này.</p>
        </div>
    </main>

    <script>
        document.getElementById('btn-track').onclick = async () => {
            const input = document.getElementById('order-input').value.trim();
            const resultContainer = document.getElementById('result-container');
            const errorMsg = document.getElementById('error-msg');

            if (!input) {
                alert("Vui lòng nhập mã đơn hàng hoặc số điện thoại!");
                return;
            }

            try {
                // Chuẩn hóa chuỗi truy vấn (xóa tiền tố BLUE nếu có)
                const query = input.replace(/BLUE/gi, '');
                const response = await fetch(`../api/orders/lookup_order.php?query=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.status === 'success' && data.orders && data.orders.length > 0) {
                    errorMsg.style.display = 'none';
                    resultContainer.style.display = 'block';
                    resultContainer.innerHTML = '';

                    // Hiển thị tiêu đề lịch sử đặt hàng
                    const headerHtml = `
                        <h2 style="color: #1a153a; margin-bottom: 25px; text-align: left; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
                            📋 Lịch sử đặt hàng (${data.orders.length} đơn hàng)
                        </h2>
                    `;
                    resultContainer.innerHTML = headerHtml;

                    // Lấy thông tin session để biết user hiện tại (nếu có)
                    let currentUserId = null;
                    try {
                        const sessionResp = await fetch('../api/auth/check_session.php');
                        const sessionData = await sessionResp.json();
                        if (sessionData.status === 'success') currentUserId = sessionData.user_id;
                    } catch (e) {
                        console.warn('Không thể kiểm tra session', e);
                    }

                    data.orders.forEach((order, index) => {
                        // Xác định trạng thái của từng bước
                        const status = order.status;
                        const statusSteps = ['Chờ xác nhận', 'Đã xác nhận', 'Chuẩn bị đơn hàng', 'Đang vận chuyển', 'Đang giao', 'Đã giao hàng'];
                        const legacyStatusMap = { 'Đang xử lý': 'Đã xác nhận', 'Đang chuẩn bị': 'Chuẩn bị đơn hàng', 'Đã giao': 'Đã giao hàng' };                        const normalizedStatus = legacyStatusMap[status] || status;                        const currentStep = statusSteps.indexOf(normalizedStatus);                        const stepClasses = statusSteps.map((step, index) => index <= currentStep ? 'active' : '');



                        const isCanceled = status === 'Đã hủy';
                        const statusLabelColor = isCanceled ? 'red' : '#c4a45a';

                        // Render danh sách sản phẩm
                        let productsHtml = '';
                        if (order.items && order.items.length > 0) {
                            order.items.forEach(item => {
                                const priceFormatted = Number(item.price).toLocaleString('vi-VN');
                                const totalItemPrice = (Number(item.price) * Number(item.quantity)).toLocaleString('vi-VN');
                                productsHtml += `
                                    <div class="order-product-item">
                                        <div>
                                            <span class="order-product-name">${item.product_name}</span>
                                            <span class="order-product-meta">x${item.quantity} ${item.color ? `(Màu: ${item.color})` : ''}</span>
                                        </div>
                                        <span class="order-product-price">${totalItemPrice}đ</span>
                                    </div>
                                `;
                            });
                        } else {
                            productsHtml = `<p style="font-size: 0.9rem; color: #888; margin: 0;">Không có thông tin chi tiết sản phẩm.</p>`;
                        }

                        // Định dạng tổng tiền và ngày đặt
                        const totalOrderPrice = Number(order.total_price).toLocaleString('vi-VN');
                        const orderDate = new Date(order.created_at).toLocaleString('vi-VN');

                        const orderCard = document.createElement('div');
                        orderCard.className = 'order-history-card';                        orderCard.dataset.orderIndex = String(index);
                        orderCard.innerHTML = `
                            <div class="order-card-header">
                                <h4>Đơn hàng: #BLUE${order.id} ${isCanceled ? '<span style="color: red; font-weight: bold;">(ĐÃ HỦY)</span>' : ''}</h4>
                                <span class="order-date">${orderDate}</span>
                            </div>
                            
                            <div class="order-details-info">
                                <div>👤 <strong>Khách hàng:</strong> ${order.full_name}</div>
                                <div>📞 <strong>Số điện thoại:</strong> ${order.phone}</div>
                                <div style="grid-column: 1/-1;">📍 <strong>Địa chỉ giao hàng:</strong> ${order.address}</div>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <strong style="font-size: 0.95rem; color: #333; display: block; margin-bottom: 8px;">🛍️ Danh sách sản phẩm:</strong>
                                <div class="order-products-list">
                                    ${productsHtml}
                                </div>
                            </div>

                            <div class="order-status-steps" style="${isCanceled ? 'display: none;' : ''}">
                                <div class="step ${stepClasses[0]}">
                                    <div class="step-icon">1</div>
                                    <label>Chờ xác nhận</label>
                                </div>
                                <div class="step ${stepClasses[1]}">
                                    <div class="step-icon">2</div>
                                    <label>Đã xác nhận</label>
                                </div>
                                <div class="step ${stepClasses[2]}">
                                    <div class="step-icon">3</div>
                                    <label>Chuẩn bị đơn hàng</label>
                                </div>
                                <div class="step ${stepClasses[3]}">
                                    <div class="step-icon">4</div>
                                    <label>Đang vận chuyển</label>
                                </div>                                <div class="step ${stepClasses[4]}">                                    <div class="step-icon">5</div>                                    <label>Đang giao</label>                                </div>                                <div class="step ${stepClasses[5]}">                                    <div class="step-icon">6</div>                                    <label>Đã giao hàng</label>                                </div>
                            </div>

                            <div class="order-total-bar" style="margin-top: 20px; display:flex; gap:10px; justify-content:space-between; align-items:center;">
                                <div>
                                    <span>Trạng thái: <strong style="color: ${statusLabelColor};">${status}</strong></span>
                                </div>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <span>Tổng cộng: <strong class="order-total-price">${totalOrderPrice}đ</strong></span>
                                    ${(!isCanceled && (currentUserId && Number(currentUserId) === Number(order.user_id)) && order.status === 'Chờ xác nhận') ? `<button class="track-cancel-btn" data-order-id="${order.id}" style="background:#fff;border:1px solid #c4a45a;color:#0b2033;padding:6px 10px;border-radius:8px;cursor:pointer;font-weight:700;">Hủy đơn</button>` : ''}
                                </div>
                            </div>
                        `;
                        resultContainer.appendChild(orderCard);
                    });                    const orderCards = Array.from(resultContainer.querySelectorAll('.order-history-card'));                    const ordersPerPage = 3;                    const totalOrderPages = Math.ceil(orderCards.length / ordersPerPage);                    const pagination = document.createElement('nav');                    pagination.className = 'tracking-pagination';                    pagination.setAttribute('aria-label', 'Phân trang lịch sử đơn hàng');                    const renderOrderPage = (page) => {                        const safePage = Math.max(1, Math.min(page, totalOrderPages));                        orderCards.forEach((card, index) => {                            card.hidden = index < (safePage - 1) * ordersPerPage || index >= safePage * ordersPerPage;                        });                        pagination.innerHTML = '';                        const previous = document.createElement('button');                        previous.type = 'button';                        previous.textContent = '‹';                        previous.disabled = safePage === 1;                        previous.setAttribute('aria-label', 'Trang trước');                        previous.addEventListener('click', () => renderOrderPage(safePage - 1));                        pagination.appendChild(previous);                        for (let pageNumber = 1; pageNumber <= totalOrderPages; pageNumber += 1) {                            const pageButton = document.createElement('button');                            pageButton.type = 'button';                            pageButton.textContent = String(pageNumber);                            pageButton.className = pageNumber === safePage ? 'is-active' : '';                            pageButton.setAttribute('aria-label', `Trang ${pageNumber}`);                            pageButton.setAttribute('aria-current', pageNumber === safePage ? 'page' : 'false');                            pageButton.addEventListener('click', () => renderOrderPage(pageNumber));                            pagination.appendChild(pageButton);                        }                        const next = document.createElement('button');                        next.type = 'button';                        next.textContent = '›';                        next.disabled = safePage === totalOrderPages;                        next.setAttribute('aria-label', 'Trang sau');                        next.addEventListener('click', () => renderOrderPage(safePage + 1));                        pagination.appendChild(next);                        const pageCount = document.createElement('span');                        pageCount.className = 'tracking-page-count';                        pageCount.textContent = `${safePage}/${totalOrderPages}`;                        pagination.appendChild(pageCount);                    };                    resultContainer.appendChild(pagination);                    renderOrderPage(1);

                    // Delegated event listener for cancel buttons
                    resultContainer.addEventListener('click', async (ev) => {
                        const btn = ev.target.closest('.track-cancel-btn');
                        if (!btn) return;
                        const oid = btn.getAttribute('data-order-id');
                        if (!confirm('Bạn có chắc muốn hủy đơn hàng #' + oid + '?')) return;
                        try {
                            const resp = await fetch('huydon.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ order_id: oid })
                            });
                            const resData = await resp.json();
                            if (resData.status === 'success') {
                                alert('Đã hủy đơn hàng thành công');
                                // Tải lại danh sách bằng cách tái click nút TRA CỨU
                                document.getElementById('btn-track').click();
                            } else {
                                alert('Lỗi: ' + (resData.message || 'Không thể hủy đơn'));
                            }
                        } catch (e) {
                            console.error(e);
                            alert('Lỗi kết nối khi hủy đơn');
                        }
                    });
                } else {
                    resultContainer.style.display = 'none';
                    errorMsg.style.display = 'block';
                    errorMsg.innerText = data.message || "Không tìm thấy thông tin đơn hàng này!";
                }
            } catch (error) {
                console.error(error);
                alert("Lỗi kết nối máy chủ!");
            }
        };
    </script>

<?php
include __DIR__ . '/../include/footer.php';
?>
