<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$pageTitle = 'Chat với cửa hàng - ÉCRIN JEWELRY';
$isCustomerLoggedIn = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';
$chatUsername = $_SESSION['username'] ?? 'Quý khách';
include __DIR__ . '/../include/header.php';
$chatProduct = null;
$requestedProductId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);
if ($isCustomerLoggedIn && $requestedProductId !== false && $requestedProductId !== null && $requestedProductId > 0) {
    $productStatement = $conn->prepare("SELECT id, name FROM products WHERE id = ? AND is_deleted = 0 AND status = 'active' LIMIT 1");
    $productStatement->bind_param('i', $requestedProductId);
    $productStatement->execute();
    $chatProduct = $productStatement->get_result()->fetch_assoc() ?: null;
    $productStatement->close();
}
?>
<style>
    .support-chat-page { position:relative; max-width:1280px; margin:0 auto; padding:22px 20px 24px; }
    .support-chat-breadcrumb { position:absolute; top:16px; left:20px; z-index:2; margin:0; }
    .support-chat-heading { margin:0 0 20px; padding:16px 0; text-align:center; }
    .support-chat-eyebrow { margin:0; color:var(--navy); font:600 clamp(26px,3.2vw,34px) 'Roboto',Arial,sans-serif; }
    .support-chat-heading-brand { font-family:'Playfair Display',Georgia,serif; letter-spacing:.1em; }
    .support-chat-heading p { max-width:580px; margin:2px auto 0; color:var(--text-mid); font-size:14px; line-height:1.7; }
    .support-chat-layout { display:grid; max-width:1060px; margin:0 auto; grid-template-columns:minmax(245px,.78fr) minmax(0,1.55fr); height:clamp(480px,calc(100vh - 320px),760px); min-height:0; overflow:hidden; border:1px solid rgba(11,32,51,.1); border-radius:20px; background:#fff; box-shadow:0 18px 55px rgba(11,32,51,.09); }
    .support-chat-aside { position:relative; overflow:hidden; padding:34px 30px; background:linear-gradient(150deg,#0b2033,#172f45 72%,#274359); color:#fff; }
    .support-chat-aside:after { position:absolute; right:-74px; bottom:-130px; width:270px; height:270px; border:1px solid rgba(220,199,131,.2); border-radius:50%; content:""; box-shadow:0 0 0 24px rgba(220,199,131,.035),0 0 0 50px rgba(220,199,131,.025); }
    .support-chat-brand { display:flex; align-items:center; gap:13px; padding-bottom:30px; border-bottom:1px solid rgba(255,255,255,.13); }
    .support-chat-brand img { width:52px; height:52px; border:1px solid rgba(220,199,131,.65); border-radius:50%; object-fit:cover; }
    .support-chat-brand strong,.support-chat-brand small { display:block; }
    .support-chat-brand strong { font:600 20px 'Playfair Display',Georgia,serif; letter-spacing:.1em; }
    .support-chat-brand small { margin-top:4px; color:#DCC783; font-size:10px; letter-spacing:.25em; }
    .support-chat-aside h2 { margin:34px 0 10px; color:#fff; font:500 26px 'Roboto',Arial,sans-serif; letter-spacing:0; font-kerning:none; }
    .support-chat-aside > p { margin:0; color:#D1D8DF; font-size:14px; line-height:1.7; }
    .support-chat-points { display:grid; gap:18px; margin-top:31px; }
    .support-chat-point { display:flex; align-items:flex-start; gap:12px; color:#E9EDF1; font-size:13px; line-height:1.6; }
    .support-chat-point span:first-child { display:grid; width:30px; height:30px; flex:0 0 30px; place-items:center; border:1px solid rgba(220,199,131,.35); border-radius:9px; color:#DCC783; }
    .support-chat-hours { position:relative; z-index:1; margin-top:38px; padding-top:17px; border-top:1px solid rgba(255,255,255,.13); color:#BAC5CF; font-size:12px; line-height:1.7; }
    .support-chat-hours strong { display:block; margin-bottom:3px; color:#fff; font-size:13px; }
    .support-chat-panel { display:flex; min-width:0; min-height:0; flex-direction:column; background:#fff; }
    .support-chat-top { display:flex; min-height:78px; align-items:center; justify-content:space-between; gap:12px; padding:15px 23px; border-bottom:1px solid #ECEAE5; }
    .support-chat-agent { display:flex; align-items:center; gap:11px; }
    .support-chat-agent-avatar { display:grid; width:42px; height:42px; place-items:center; border-radius:50%; background:#F3EEE3; color:#8B7146; font-size:20px; }
    .support-chat-agent strong,.support-chat-agent small { display:block; }
    .support-chat-agent strong { color:var(--navy); font-size:15px; }
    .support-chat-agent small { margin-top:4px; color:#8995A4; font-size:13px; }
    .support-chat-online { color:#718198; font-size:12px; white-space:nowrap; }
    .support-chat-online i { display:inline-block; width:7px; height:7px; margin-right:5px; border-radius:50%; background:#6FA58C; }
    .support-chat-messages { display:flex; min-height:0; flex:1; flex-direction:column; gap:12px; overflow-y:auto; overscroll-behavior:contain; padding:23px; background:linear-gradient(#FBFAF8,#F7F6F3); }
    .support-chat-welcome { align-self:center; max-width:340px; margin:auto; padding:21px 22px; border:1px solid #EAE5DA; border-radius:15px; background:#fff; color:#66717D; text-align:center; box-shadow:0 5px 20px rgba(16,35,56,.04); }
    .support-chat-welcome-icon { display:grid; width:43px; height:43px; margin:0 auto 12px; place-items:center; border-radius:50%; background:#F5F0E5; color:#9B835D; font-size:21px; }
    .support-chat-welcome strong { display:block; margin-bottom:6px; color:var(--navy); font:500 20px 'Playfair Display',Georgia,serif; }
    .support-chat-welcome p { margin:0; font-size:13px; line-height:1.7; }
    .support-chat-bubble { align-self:flex-start; max-width:min(78%,460px); padding:11px 14px 8px; border-radius:15px 15px 15px 5px; background:#fff; color:#334155; box-shadow:0 3px 12px rgba(16,35,56,.07); overflow-wrap:anywhere; white-space:pre-wrap; font-size:14px; line-height:1.6; }
    .support-chat-bubble.is-customer { align-self:flex-end; border-radius:15px 15px 5px 15px; background:var(--navy); color:#fff; }
    .support-chat-bubble a { color:inherit; text-decoration:underline; text-underline-offset:2px; }
    .support-chat-product-card { display:flex; width:min(320px,100%); gap:11px; align-items:center; margin-top:10px; padding:9px; border:1px solid rgba(196,164,90,.32); border-radius:11px; background:#FAF8F3; color:#334155 !important; text-decoration:none !important; }
    .support-chat-product-card img { width:68px; height:68px; flex:0 0 68px; border-radius:8px; background:#fff; object-fit:cover; }
    .support-chat-product-info { min-width:0; }
    .support-chat-product-info strong { display:-webkit-box; overflow:hidden; color:var(--navy); font-size:12px; line-height:1.5; -webkit-box-orient:vertical; -webkit-line-clamp:2; }
    .support-chat-product-info span { display:block; margin-top:5px; color:#9B835D; font-size:12px; font-weight:700; }
    .support-chat-bubble time { display:block; margin-top:5px; color:#9CA3AF; font-size:10px; text-align:right; }
    .support-chat-bubble.is-customer time { color:#C9D1DB; }
    .support-chat-attachment-image { display:block; width:auto; max-width:min(100%,360px); max-height:290px; border-radius:9px; object-fit:contain; }
    .support-chat-file-link { display:flex; max-width:320px; align-items:center; gap:9px; color:inherit; font-size:13px; text-decoration:underline; text-underline-offset:2px; }
    .support-chat-file-icon { display:grid; width:34px; height:38px; flex:0 0 34px; place-items:center; border-radius:6px; background:rgba(196,164,90,.16); color:#9B835D; font-size:12px; font-weight:700; text-decoration:none; }
    .support-chat-compose { padding:8px 20px 10px; border-top:1px solid #ECEAE5; background:#fff; }
    .support-chat-compose-entry { display:flex; align-items:center; gap:6px; }
    .support-chat-compose-entry textarea { display:block; box-sizing:border-box; width:100%; min-width:0; height:56px; min-height:56px; max-height:110px; flex:1; resize:vertical; padding:18px 12px; border:1px solid #E4E3DF; border-radius:12px; outline:0; color:var(--text-dark); background:#fff; font:14px 'Roboto',sans-serif; line-height:18px; }
    .support-chat-compose textarea:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(196,164,90,.12); }
    .support-chat-compose-bottom { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-top:5px; }
    .support-chat-attach { display:grid; width:38px; height:38px; padding:0; place-items:center; border:0; border-radius:50%; background:transparent; color:#98A2AF; cursor:pointer; }
    .support-chat-attach svg { width:24px; height:24px; fill:none; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.8; }
    .support-chat-attach:hover { background:#F3F4F6; color:var(--navy); }
    .support-chat-attach:disabled { opacity:.55; cursor:wait; }
    .support-chat-note { min-height:17px; margin:0; color:#A34B42; font-size:12px; }
    .support-chat-note:empty { display:none; }
    .support-chat-submit { display:grid; width:38px; height:38px; flex:0 0 38px; padding:0; place-items:center; border:0; border-radius:50%; background:var(--navy); color:#fff; cursor:pointer; box-shadow:0 2px 6px rgba(16,35,56,.16); transition:background .18s ease,transform .18s ease; }
    .support-chat-submit svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.8; }
    .support-chat-submit:hover { background:var(--navy-mid); transform:translateY(-1px); }
    .support-chat-submit:disabled { opacity:.55; cursor:wait; transform:none; }
    .support-chat-login { display:grid; flex:1; align-content:center; justify-items:center; padding:35px; text-align:center; }
    .support-chat-login-icon { display:grid; width:62px; height:62px; margin-bottom:16px; place-items:center; border-radius:50%; background:#F5F0E5; color:#9B835D; font-size:25px; }
    .support-chat-login h3 { margin:0; color:var(--navy); font:500 24px 'Roboto',Arial,sans-serif; }
    .support-chat-login p { max-width:370px; color:var(--text-mid); font-size:14px; line-height:1.7; }
    .support-chat-login button { padding:11px 20px; border:0; border-radius:9px; background:var(--navy); color:#fff; font-size:14px; font-weight:600; cursor:pointer; }
    .support-chat-login button.login-trigger { color:#fff !important; }
    @media(min-width:761px) and (max-height:900px) { .support-chat-page { padding-top:12px; padding-bottom:24px; } .support-chat-heading { margin-bottom:8px; } .support-chat-layout { height:calc(100vh - 304px); min-height:400px; } .support-chat-aside { padding:20px 24px; } .support-chat-brand { padding-bottom:12px; } .support-chat-aside h2 { margin-top:16px; } .support-chat-points { gap:8px; margin-top:16px; } .support-chat-hours { margin-top:10px; padding-top:8px; } }
    @media(max-width:760px) { .support-chat-page { padding:20px 12px 36px; } .support-chat-breadcrumb { position:static; margin:0 4px 12px; } .support-chat-heading { padding:8px 4px 16px; text-align:left; } .support-chat-heading p { margin-left:0; } .support-chat-layout { height:auto; min-height:0; grid-template-columns:1fr; } .support-chat-panel { min-height:520px; } .support-chat-aside { min-width:0; padding:25px 20px; } .support-chat-aside h2 { max-width:100%; margin-top:23px; overflow-wrap:anywhere; } .support-chat-aside > p { overflow-wrap:anywhere; } .support-chat-points { grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:20px; } .support-chat-point { min-width:0; } .support-chat-point span:last-child { overflow-wrap:anywhere; } .support-chat-hours { margin-top:23px; } .support-chat-messages { min-height:0; padding:16px 12px; } .support-chat-top { padding:12px 14px; } .support-chat-compose { padding:8px 12px; } .support-chat-hint { display:none; } }
    @media(max-width:420px) { .support-chat-points { grid-template-columns:1fr; } .support-chat-online { display:none; } .support-chat-bubble { max-width:90%; } }
</style>

<main class="support-chat-page">
    <nav class="breadcrumb-container support-chat-breadcrumb" aria-label="Điều hướng đường dẫn">
        <a href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Trang chủ</a>
        <span class="separator">&gt;</span>
        <strong class="current" aria-current="page">Chat với cửa hàng</strong>
    </nav>
    <header class="support-chat-heading">
        <h1 class="support-chat-eyebrow"><span class="support-chat-heading-brand">ÉCRIN JEWELRY</span> · Luôn lắng nghe bạn!</h1>
        <p>Đội ngũ ÉCRIN luôn sẵn sàng tư vấn để bạn tìm được món trang sức thật sự dành cho mình.</p>
    </header>
    <section class="support-chat-layout" aria-label="Chat với cửa hàng">
        <aside class="support-chat-aside">
            <div class="support-chat-brand">
                <img src="<?php echo htmlspecialchars($appBaseUrl . '/assets/banner/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="ÉCRIN">
                <span><strong>ÉCRIN</strong><small>JEWELRY</small></span>
            </div>
            <h2>Một lời nhắn, một lựa chọn đẹp.</h2>
            <p>Nhắn tin cho chúng tôi để được hỗ trợ tận tâm về sản phẩm và đơn hàng.</p>
            <div class="support-chat-points">
                <div class="support-chat-point"><span>◇</span><span>Tư vấn chọn trang sức phù hợp</span></div>
                <div class="support-chat-point"><span>♡</span><span>Giải đáp thông tin sản phẩm</span></div>
                <div class="support-chat-point"><span>✦</span><span>Hỗ trợ đơn hàng nhanh chóng</span></div>
                <div class="support-chat-point"><span>⌂</span><span>Chăm sóc tận tình từ ÉCRIN</span></div>
            </div>
            <div class="support-chat-hours"><strong>Thời gian hỗ trợ</strong>Hằng ngày · 08:00 – 22:00</div>
        </aside>
        <section class="support-chat-panel">
            <header class="support-chat-top">
                <div class="support-chat-agent"><span class="support-chat-agent-avatar">✧</span><span><strong>Chăm sóc khách hàng ÉCRIN</strong><small>Chúng tôi thường phản hồi sớm</small></span></div>
                <span class="support-chat-online"><i></i>Đang trực tuyến</span>
            </header>
            <?php if ($isCustomerLoggedIn): ?>
                <div class="support-chat-messages" id="customer-chat-messages" aria-live="polite">
                    <div class="support-chat-welcome" id="customer-chat-welcome"><span class="support-chat-welcome-icon">✧</span><strong>Xin chào <?php echo htmlspecialchars((string) $chatUsername, ENT_QUOTES, 'UTF-8'); ?>!</strong><p>ÉCRIN rất vui được đồng hành cùng bạn. Hãy gửi câu hỏi, chúng tôi sẽ phản hồi tại đây nhé.</p></div>
                </div>
                <form class="support-chat-compose" id="customer-chat-form">
                    <p class="support-chat-note" id="customer-chat-note" role="status"></p>
                    <div class="support-chat-compose-entry">
                        <button class="support-chat-attach" id="customer-chat-attach" type="button" aria-label="Tải ảnh" title="Tải ảnh · tối đa 100 MB">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4" width="17" height="16" rx="2"></rect><circle cx="8.5" cy="9" r="1.5"></circle><path d="m20.5 15-5-5L5 20"></path></svg>
                        </button>
                        <input id="customer-chat-file" type="file" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp" hidden>
                        <textarea id="customer-chat-input" maxlength="2000" placeholder="Viết lời nhắn cho ÉCRIN..." aria-label="Tin nhắn"></textarea>
                        <button class="support-chat-submit" type="submit" id="customer-chat-send" aria-label="Gửi tin nhắn" title="Gửi tin nhắn">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 2 11 13"></path><path d="m22 2-7 20-4-9-9-4 20-7Z"></path></svg>
                        </button>
                    </div>
                    <div class="support-chat-compose-bottom">
                        <span class="support-chat-hint" style="color:#98A2AF;font-size:11px;">Enter gửi · Shift + Enter xuống dòng</span>
                    </div>
                </form>
            <?php else: ?>
                <div class="support-chat-login">
                    <span class="support-chat-login-icon">♡</span>
                    <h3>Bắt đầu trò chuyện</h3>
                    <p>Đăng nhập tài khoản để gửi tin nhắn và tiếp tục theo dõi cuộc trò chuyện với đội ngũ ÉCRIN.</p>
                    <button class="login-trigger" type="button">Đăng nhập để chat</button>
                </div>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php if ($isCustomerLoggedIn): ?>
<script>
(() => {
    const base = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;
    const messagesBox = document.getElementById('customer-chat-messages');
    const welcome = document.getElementById('customer-chat-welcome');
    const form = document.getElementById('customer-chat-form');
    const input = document.getElementById('customer-chat-input');
    const sendButton = document.getElementById('customer-chat-send');
    const attachButton = document.getElementById('customer-chat-attach');
    const fileInput = document.getElementById('customer-chat-file');
    const note = document.getElementById('customer-chat-note');
    const api = `${base}/api/chat`;
    let sharedProduct = <?php echo json_encode($chatProduct, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let latestId = 0;
    let sending = false;
    let uploading = false;

    if (sharedProduct && !input.value.trim()) {
        const productUrl = new URL(`${base}/client/product.php?id=${encodeURIComponent(sharedProduct.id)}`, window.location.origin).href;
        input.value = `Chào ÉCRIN, tôi muốn hỏi về sản phẩm "${sharedProduct.name}":\n${productUrl}`;
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }

    const formatBytes = value => {
        const bytes = Number(value) || 0;
        return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    const timeLabel = value => {
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});
    };
    async function request(url, options) {
        const response = await fetch(url, options);
        const data = await response.json().catch(() => null);
        if (!data) throw new Error('Máy chủ trả về phản hồi không hợp lệ.');
        if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Không thể kết nối máy chủ.');
        return data;
    }
    function appendMessageText(container, value) {
        const text = String(value ?? '');
        const urlPattern = /https?:\/\/[^\s<>"']+/gi;
        let cursor = 0;
        let match;
        while ((match = urlPattern.exec(text)) !== null) {
            let url = match[0];
            let punctuation = '';
            while (/[.,!?;:)\]]$/.test(url)) {
                punctuation = url.slice(-1) + punctuation;
                url = url.slice(0, -1);
            }
            container.append(document.createTextNode(text.slice(cursor, match.index)));
            if (url) {
                const link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = url;
                container.append(link);
            }
            if (punctuation) container.append(document.createTextNode(punctuation));
            cursor = match.index + match[0].length;
        }
        container.append(document.createTextNode(text.slice(cursor)));
    }
    function appendProductCard(container, message) {
        const productId = Number(message.product_id);
        const imagePath = String(message.product_image ?? '').trim();
        if (!productId || !message.product_name) return;

        const card = document.createElement('a');
        card.className = 'support-chat-product-card';
        card.href = `${base}/client/product.php?id=${encodeURIComponent(productId)}`;
        card.target = '_blank';
        card.rel = 'noopener noreferrer';
        const image = document.createElement('img');
        const fallbackImageUrl = `${base}/assets/banner/logo.png`;
        image.src = imagePath
            ? (/^https?:\/\//i.test(imagePath) ? imagePath : `${base}/${imagePath.replace(/^\/+/, '')}`)
            : fallbackImageUrl;
        image.alt = String(message.product_name);
        image.loading = 'lazy';
        image.onerror = () => {
            image.onerror = null;
            image.src = fallbackImageUrl;
        };
        const details = document.createElement('span');
        details.className = 'support-chat-product-info';
        const name = document.createElement('strong');
        name.textContent = String(message.product_name);
        const price = document.createElement('span');
        price.textContent = `${new Intl.NumberFormat('vi-VN').format(Number(message.product_price) || 0)}đ`;
        details.append(name, price);
        card.append(image, details);
        container.append(card);
    }
    function renderMessage(message) {
        const id = Number(message.id);
        if (!id || id <= latestId) return false;
        latestId = id;
        welcome.hidden = true;
        const bubble = document.createElement('article');
        bubble.className = `support-chat-bubble${message.sender === 'customer' ? ' is-customer' : ''}`;
        bubble.dataset.messageId = String(id);
        if (message.attachment_name && ['image', 'file'].includes(message.message_type)) {
            const fileUrl = `${base}/api/chat/download_attachment.php?id=${encodeURIComponent(id)}&viewer=customer`;
            if (message.message_type === 'image') {
                const link = document.createElement('a');
                link.href = fileUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                const image = document.createElement('img');
                image.className = 'support-chat-attachment-image';
                image.src = fileUrl;
                image.alt = String(message.attachment_name);
                image.loading = 'lazy';
                link.append(image);
                bubble.append(link);
            } else {
                const link = document.createElement('a');
                link.className = 'support-chat-file-link';
                link.href = fileUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                const icon = document.createElement('span');
                icon.className = 'support-chat-file-icon';
                icon.textContent = String(message.attachment_name).split('.').pop().slice(0, 4).toUpperCase();
                const details = document.createElement('span');
                details.textContent = `${String(message.attachment_name)} · ${formatBytes(message.attachment_size)}`;
                link.append(icon, details);
                bubble.append(link);
            }
        } else {
            appendMessageText(bubble, message.message);
        }
        appendProductCard(bubble, message);
        const time = document.createElement('time');
        time.textContent = timeLabel(message.created_at);
        bubble.append(time);
        messagesBox.append(bubble);
        return true;
    }
    async function loadMessages() {
        const nearBottom = messagesBox.scrollHeight - messagesBox.scrollTop - messagesBox.clientHeight < 90;
        const data = await request(`${api}/customer_get_messages.php`);
        data.messages.forEach(renderMessage);
        if (nearBottom) messagesBox.scrollTop = messagesBox.scrollHeight;
    }
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || sending || uploading) return;
        sending = true;
        sendButton.disabled = true;
        attachButton.disabled = true;
        fileInput.disabled = true;
        note.textContent = '';
        try {
            const data = await request(`${api}/customer_send_message.php`, {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({message, product_id:sharedProduct ? Number(sharedProduct.id) : null})
            });
            renderMessage(data.message);
            messagesBox.scrollTop = messagesBox.scrollHeight;
            input.value = '';
            sharedProduct = null;
        } catch (error) {
            note.textContent = error.message;
        } finally {
            sending = false;
            sendButton.disabled = false;
            attachButton.disabled = uploading;
            fileInput.disabled = uploading;
        }
    });
    attachButton.addEventListener('click', () => {
        if (!attachButton.disabled) fileInput.click();
    });
    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file || uploading) return;
        if (file.size < 1 || file.size > 100 * 1024 * 1024) {
            note.textContent = 'Ảnh phải có dung lượng từ 1 byte đến 100 MB.';
            fileInput.value = '';
            attachButton.disabled = false;
            return;
        }
        const formData = new FormData();
        formData.append('file', file);
        formData.append('viewer', 'customer');
        uploading = true;
        note.textContent = `Đang gửi ${file.name}...`;
        sendButton.disabled = true;
        attachButton.disabled = true;
        fileInput.disabled = true;
        input.disabled = true;
        try {
            const data = await request(`${base}/api/chat/upload_attachment.php`, {method:'POST', body:formData});
            renderMessage(data.message);
            messagesBox.scrollTop = messagesBox.scrollHeight;
            note.textContent = '';
        } catch (error) {
            note.textContent = error.message;
        } finally {
            uploading = false;
            fileInput.value = '';
            fileInput.disabled = false;
            input.disabled = false;
            sendButton.disabled = false;
            attachButton.disabled = false;
        }
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    loadMessages().catch(error => { note.textContent = error.message; });
    window.setInterval(() => loadMessages().catch(error => { note.textContent = error.message; }), 4000);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>
