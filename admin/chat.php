<?php
require_once __DIR__ . '/../include/admin_guard.php';
$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$isManager = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Nhân viên ÉCRIN';
$adminCurrentPage = 'chat.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat khách hàng - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .page-content { padding-top:14px; padding-bottom:10px; }
        .welcome-row { margin-bottom:14px; }
        .chat-workspace,
        .chat-inbox,
        .chat-thread,
        .chat-messages,
        .chat-bubble,
        .chat-compose,
        .chat-compose textarea,
        .chat-conversation,
        .chat-conversation-main,
        .chat-conversation-preview,
        .chat-thread-empty {
            font-family:'Roboto', Arial, sans-serif;
        }
        .chat-workspace { display:grid; grid-template-columns:minmax(245px,315px) minmax(0,1fr); min-height:420px; height:calc(100vh - 200px); max-height:850px; overflow:hidden; background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:var(--shadow); }
        .chat-inbox { display:flex; min-height:0; flex-direction:column; border-right:1px solid var(--line); }
        .chat-inbox-head { padding:20px 18px 14px; }
        .chat-inbox-head h2 { font-size:17px; }
        .chat-inbox-head p { margin:5px 0 15px; color:var(--muted); font-size:12px; }
        .chat-search { width:100%; height:39px; padding:0 12px; border:1px solid var(--line); border-radius:8px; outline:0; color:var(--text); background:#F8F9FB; }
        .chat-search:focus { border-color:#B99A5C; box-shadow:0 0 0 3px rgba(196,164,90,.12); }
        .chat-conversations { min-height:0; overflow-y:auto; }
        .chat-conversation { display:flex; width:100%; gap:11px; align-items:center; padding:14px 16px; border:0; border-top:1px solid #F1F3F5; background:#fff; text-align:left; cursor:pointer; font-size:14px; line-height:1.5; }
        .chat-conversation:hover { background:#FAF8F3; }
        .chat-conversation.is-active { background:#F6F2E9; box-shadow:inset 3px 0 #C4A45A; }
        .chat-avatar { display:grid; width:42px; height:42px; flex:0 0 42px; place-items:center; border-radius:50%; background:#EAE3D3; color:#5F4D2B; font-weight:700; }
        .chat-conversation-main { min-width:0; flex:1; }
        .chat-conversation-top { display:flex; justify-content:space-between; gap:8px; align-items:center; }
        .chat-conversation-name { min-width:0; flex:1; overflow:hidden; color:var(--navy); font-size:13px; font-weight:700; text-overflow:ellipsis; white-space:nowrap; }
        .chat-conversation-time { flex:0 0 auto; color:#98A2AF; font-size:10px; }
        .chat-conversation-preview { display:block; overflow:hidden; margin-top:5px; color:#8995A4; font-size:11px; text-overflow:ellipsis; white-space:nowrap; }
        .chat-unread { display:inline-grid; min-width:19px; height:19px; flex:0 0 auto; padding:0 5px; place-items:center; border-radius:20px; background:#C4A45A; color:white; font-size:10px; font-weight:700; }
        .chat-inbox-empty { padding:26px 18px; color:var(--muted); font-size:13px; text-align:center; }
        .chat-thread { display:flex; min-width:0; min-height:0; flex-direction:column; }
        .chat-thread-head { display:flex; min-height:72px; align-items:center; justify-content:space-between; gap:12px; padding:14px 20px; border-bottom:1px solid var(--line); }
        .chat-customer { display:flex; min-width:0; align-items:center; gap:11px; }
        .chat-customer .chat-avatar { width:40px; height:40px; flex-basis:40px; }
        .chat-customer strong,.chat-customer small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .chat-customer strong { color:var(--navy); font-size:14px; }
        .chat-customer small { margin-top:4px; color:#98A2AF; font-size:11px; }
        .chat-live { color:#718198; font-size:11px; white-space:nowrap; }
        .chat-live i { display:inline-block; width:7px; height:7px; margin-right:5px; border-radius:50%; background:#6FA58C; }
        .chat-messages { display:flex; min-height:0; flex:1; flex-direction:column; gap:12px; overflow-y:auto; padding:22px; background:#F7F7F5; }
        .chat-thread-empty[hidden],.chat-messages[hidden] { display:none !important; }
        .chat-day { align-self:center; padding:5px 10px; border-radius:20px; background:#ECEBE7; color:#7E8790; font-size:10px; }
        .chat-bubble { align-self:flex-start; max-width:min(76%,540px); padding:11px 14px 8px; border-radius:14px 14px 14px 4px; background:white; color:#334155; box-shadow:0 2px 9px rgba(16,35,56,.06); overflow-wrap:anywhere; white-space:pre-wrap; font-size:14px; line-height:1.6; }
        .chat-bubble.is-admin { align-self:flex-end; border-radius:14px 14px 4px 14px; background:var(--navy); color:#fff; }
        .chat-bubble-time { display:block; margin-top:6px; color:#9CA3AF; font-size:10px; text-align:right; }
        .chat-bubble.is-admin .chat-bubble-time { color:#C5CFDA; }
        .chat-attachment-image { display:block; width:auto; max-width:min(100%,360px); max-height:290px; border-radius:9px; object-fit:contain; }
        .chat-file-link { display:flex; max-width:320px; align-items:center; gap:9px; color:inherit; font-size:12px; text-decoration:underline; text-underline-offset:2px; }
        .chat-file-icon { display:grid; width:34px; height:38px; flex:0 0 34px; place-items:center; border-radius:6px; background:rgba(196,164,90,.16); color:#9B835D; font-size:11px; font-weight:700; text-decoration:none; }
        .chat-thread-empty { display:flex; box-sizing:border-box; flex:1; align-items:center; justify-content:center; padding:25px; background:#F7F7F5; color:var(--muted); text-align:center; }
        .chat-compose { padding:9px 14px 10px; border-top:1px solid var(--line); background:white; }
        .chat-compose-entry { display:flex; align-items:center; gap:6px; }
        .chat-compose-entry textarea { display:block; box-sizing:border-box; width:100%; min-width:0; height:56px; min-height:56px; max-height:110px; flex:1; resize:vertical; padding:18px 12px; border:1px solid var(--line); border-radius:9px; outline:0; font:14px 'Roboto', Arial, sans-serif; line-height:18px; }
        .chat-compose textarea:focus { border-color:#B99A5C; box-shadow:0 0 0 3px rgba(196,164,90,.1); }
        .chat-compose-bottom { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:5px; }
        .chat-attach { display:grid; width:36px; height:36px; padding:0; place-items:center; border:0; border-radius:50%; background:transparent; color:#98A2AF; cursor:pointer; }
        .chat-attach svg { width:23px; height:23px; fill:none; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.8; }
        .chat-attach:hover { background:#F3F4F6; color:var(--navy); }
        .chat-attach:disabled { opacity:.55; cursor:wait; }
        .chat-compose-hint { color:#98A2AF; font-size:10px; }
        .chat-send { display:grid; width:38px; height:38px; flex:0 0 38px; padding:0; place-items:center; border:0; border-radius:50%; background:var(--navy); color:#fff; cursor:pointer; box-shadow:0 2px 6px rgba(16,35,56,.16); transition:background .18s ease,transform .18s ease; }
        .chat-send svg { width:20px; height:20px; fill:none; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.8; }
        .chat-send:hover { background:var(--navy-mid); transform:translateY(-1px); }
        .chat-send:disabled { opacity:.55; cursor:wait; }
        .chat-notice { min-height:16px; margin:0 0 5px; color:#A34B42; font-size:11px; }
        .chat-notice:empty { display:none; }
        .chat-loading { padding:22px; color:var(--muted); font-size:13px; text-align:center; }
        @media(max-width:900px) { .chat-workspace { height:calc(100vh - 200px); min-height:420px; grid-template-columns:260px minmax(0,1fr); } .page-content { padding-right:20px; padding-left:20px; } }
        @media(max-width:680px) { .chat-workspace { position:relative; height:calc(100vh - 205px); min-height:520px; grid-template-columns:1fr; } .chat-inbox { border-right:0; } .chat-workspace.has-selection .chat-inbox { display:none; } .chat-workspace:not(.has-selection) .chat-thread { display:none; } .chat-workspace.has-selection .chat-thread { position:absolute; inset:0; } .chat-thread-head { min-height:65px; padding:10px 14px; } .chat-back { display:inline-flex !important; } .chat-messages { padding:16px 12px; } .chat-bubble { max-width:88%; } .chat-compose { padding:10px; } .chat-compose-hint { display:none; } .chat-live { display:none; } }
        .chat-back { display:none; border:0; background:transparent; color:var(--navy); font-size:20px; cursor:pointer; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý chat</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="welcome-row">
                <div><p class="eyebrow">CHĂM SÓC KHÁCH HÀNG</p><h1>Trò chuyện</h1><p class="page-subtitle">Tư vấn và hỗ trợ khách hàng của ÉCRIN Jewelry.</p></div>
            </div>
            <section class="chat-workspace" id="admin-chat" aria-label="Trung tâm tin nhắn">
                <aside class="chat-inbox">
                    <div class="chat-inbox-head"><h2>Hộp thư</h2><p id="chat-count">Đang tải hội thoại...</p><input class="chat-search" id="chat-search" type="search" placeholder="Tìm khách hàng..." aria-label="Tìm khách hàng"></div>
                    <div class="chat-conversations" id="chat-conversations" aria-live="polite"><div class="chat-loading">Đang tải...</div></div>
                </aside>
                <section class="chat-thread" aria-label="Nội dung hội thoại">
                    <header class="chat-thread-head">
                        <div class="chat-customer"><button type="button" class="chat-back" id="chat-back" aria-label="Quay lại danh sách">‹</button><div class="chat-avatar" id="thread-avatar">E</div><div><strong id="thread-name">Chọn một hội thoại</strong><small id="thread-status">Tin nhắn được cập nhật tự động</small></div></div>
                        <span class="chat-live"><i></i>Đang hoạt động</span>
                    </header>
                    <div class="chat-thread-empty" id="thread-empty">Chọn khách hàng trong hộp thư để xem và trả lời tin nhắn.</div>
                    <div class="chat-messages" id="chat-messages" hidden aria-live="polite"></div>
                    <form class="chat-compose" id="chat-form">
                        <p class="chat-notice" id="chat-notice" role="status"></p>
                        <div class="chat-compose-entry">
                            <button class="chat-attach" id="chat-attach" type="button" aria-label="Tải ảnh" title="Tải ảnh · tối đa 100 MB" disabled>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4" width="17" height="16" rx="2"></rect><circle cx="8.5" cy="9" r="1.5"></circle><path d="m20.5 15-5-5L5 20"></path></svg>
                            </button>
                            <input id="chat-file" type="file" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp" hidden disabled>
                            <textarea id="chat-message" maxlength="2000" placeholder="Viết tin nhắn trả lời..." aria-label="Tin nhắn trả lời" disabled></textarea>
                            <button class="chat-send" id="chat-send" type="submit" aria-label="Gửi tin nhắn" title="Gửi tin nhắn" disabled>
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 2 11 13"></path><path d="m22 2-7 20-4-9-9-4 20-7Z"></path></svg>
                            </button>
                        </div>
                        <div class="chat-compose-bottom">
                            <span class="chat-compose-hint">Enter gửi · Shift + Enter xuống dòng</span>
                        </div>
                    </form>
                </section>
            </section>
        </section>
    </main>
</div>
<script>
(() => {
    const base = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;
    const api = `${base}/api/chat/admin_chat.php`;
    const workspace = document.getElementById('admin-chat');
    const conversationList = document.getElementById('chat-conversations');
    const messagesBox = document.getElementById('chat-messages');
    const emptyState = document.getElementById('thread-empty');
    const textarea = document.getElementById('chat-message');
    const sendButton = document.getElementById('chat-send');
    const attachButton = document.getElementById('chat-attach');
    const fileInput = document.getElementById('chat-file');
    const notice = document.getElementById('chat-notice');
    const search = document.getElementById('chat-search');
    let conversations = [];
    let selectedId = null;
    let latestMessageId = 0;
    let sending = false;
    let uploading = false;

    const escapeText = value => String(value ?? '');
    const formatBytes = value => {
        const bytes = Number(value) || 0;
        return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };
    const initials = name => escapeText(name).trim().split(/\s+/).slice(-2).map(part => part.charAt(0)).join('').toUpperCase() || 'KH';
    const timeLabel = value => {
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});
    };
    const dateLabel = value => {
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('vi-VN');
    };
    const request = async (url, options) => {
        const response = await fetch(url, options);
        const data = await response.json().catch(() => null);
        if (!data) throw new Error('Máy chủ trả về phản hồi không hợp lệ.');
        if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Không thể kết nối máy chủ.');
        return data;
    };

    function renderConversations() {
        const term = search.value.trim().toLocaleLowerCase('vi');
        const filtered = conversations.filter(item => escapeText(item.username).toLocaleLowerCase('vi').includes(term));
        document.getElementById('chat-count').textContent = `${conversations.length} cuộc trò chuyện`;
        conversationList.replaceChildren();
        if (!filtered.length) {
            const empty = document.createElement('div');
            empty.className = 'chat-inbox-empty';
            empty.textContent = conversations.length ? 'Không tìm thấy khách hàng phù hợp.' : 'Chưa có tin nhắn nào. Khi khách hàng nhắn tin, cuộc trò chuyện sẽ xuất hiện ở đây.';
            conversationList.append(empty);
            return;
        }
        filtered.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `chat-conversation${Number(item.user_id) === selectedId ? ' is-active' : ''}`;
            button.dataset.userId = item.user_id;
            const avatar = document.createElement('span');
            avatar.className = 'chat-avatar';
            avatar.textContent = initials(item.username);
            const body = document.createElement('span');
            body.className = 'chat-conversation-main';
            const top = document.createElement('span');
            top.className = 'chat-conversation-top';
            const name = document.createElement('span');
            name.className = 'chat-conversation-name';
            name.textContent = item.username || 'Khách hàng';
            const time = document.createElement('span');
            time.className = 'chat-conversation-time';
            time.textContent = timeLabel(item.last_at);
            const unreadCount = Number(item.unread);
            if (unreadCount > 0 && Number(item.user_id) !== selectedId) {
                const unread = document.createElement('span');
                unread.className = 'chat-unread';
                unread.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                unread.setAttribute('aria-label', `${unreadCount} tin nhắn chưa đọc`);
                top.append(name, unread, time);
            } else {
                top.append(name, time);
            }
            const preview = document.createElement('span');
            preview.className = 'chat-conversation-preview';
            preview.textContent = item.last_message || 'Chưa có nội dung';
            body.append(top, preview);
            button.append(avatar, body);
            button.addEventListener('click', () => selectConversation(Number(item.user_id)));
            conversationList.append(button);
        });
    }

    function appendMessage(message) {
        const id = Number(message.id);
        if (!id || id <= latestMessageId) return false;
        latestMessageId = id;
        const bubble = document.createElement('article');
        bubble.className = `chat-bubble${message.sender === 'admin' ? ' is-admin' : ''}`;
        bubble.dataset.messageId = String(id);
        if (message.attachment_name && ['image', 'file'].includes(message.message_type)) {
            const fileUrl = `${base}/api/chat/download_attachment.php?id=${encodeURIComponent(id)}`;
            if (message.message_type === 'image') {
                const link = document.createElement('a');
                link.href = fileUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                const image = document.createElement('img');
                image.className = 'chat-attachment-image';
                image.src = fileUrl;
                image.alt = escapeText(message.attachment_name);
                image.loading = 'lazy';
                link.append(image);
                bubble.append(link);
            } else {
                const link = document.createElement('a');
                link.className = 'chat-file-link';
                link.href = fileUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                const icon = document.createElement('span');
                icon.className = 'chat-file-icon';
                icon.textContent = escapeText(message.attachment_name).split('.').pop().slice(0, 4).toUpperCase();
                const details = document.createElement('span');
                details.textContent = `${escapeText(message.attachment_name)} · ${formatBytes(message.attachment_size)}`;
                link.append(icon, details);
                bubble.append(link);
            }
        } else {
            const text = document.createElement('span');
            text.textContent = escapeText(message.message);
            bubble.append(text);
        }
        const time = document.createElement('small');
        time.className = 'chat-bubble-time';
        time.textContent = timeLabel(message.created_at);
        bubble.append(time);
        messagesBox.append(bubble);
        return true;
    }

    async function loadMessages(keepPosition) {
        if (!selectedId) return;
        const userId = selectedId;
        const nearBottom = messagesBox.scrollHeight - messagesBox.scrollTop - messagesBox.clientHeight < 90;
        const data = await request(`${api}?action=messages&user_id=${encodeURIComponent(userId)}`);
        if (selectedId !== userId) return;
        const fresh = data.messages.filter(item => Number(item.id) > latestMessageId);
        fresh.forEach(appendMessage);
        if (!keepPosition || nearBottom) messagesBox.scrollTop = messagesBox.scrollHeight;
        const lastCustomerMessageId = data.messages.reduce(
            (lastId, item) => item.sender === 'customer' ? Math.max(lastId, Number(item.id)) : lastId,
            0
        );
        const conversation = conversations.find(item => Number(item.user_id) === userId);
        if (lastCustomerMessageId > 0
            && (fresh.some(item => item.sender === 'customer') || Number(conversation?.unread) > 0)) {
            await request(api, {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({action:'mark_read', user_id:userId, last_message_id:lastCustomerMessageId})
            });
            if (selectedId !== userId) return;
            if (conversation) conversation.unread = 0;
            renderConversations();
        }
    }

    async function selectConversation(userId) {
        selectedId = userId;
        latestMessageId = 0;
        const conversation = conversations.find(item => Number(item.user_id) === userId);
        document.getElementById('thread-name').textContent = conversation?.username || 'Khách hàng';
        document.getElementById('thread-avatar').textContent = initials(conversation?.username);
        document.getElementById('thread-status').textContent = 'Đang trò chuyện với khách hàng';
        workspace.classList.add('has-selection');
        emptyState.hidden = true;
        messagesBox.hidden = false;
        messagesBox.replaceChildren();
        textarea.disabled = uploading || sending;
        sendButton.disabled = uploading || sending;
        attachButton.disabled = uploading || sending;
        fileInput.disabled = uploading || sending;
        notice.textContent = '';
        renderConversations();
        try {
            await loadMessages(false);
            textarea.focus();
        } catch (error) {
            notice.textContent = error.message;
        }
    }

    async function refreshConversations() {
        try {
            const data = await request(`${api}?action=conversations`);
            conversations = data.conversations;
            renderConversations();
            if (selectedId && !conversations.some(item => Number(item.user_id) === selectedId)) {
                selectedId = null;
                workspace.classList.remove('has-selection');
                emptyState.hidden = false;
                messagesBox.hidden = true;
                textarea.disabled = true;
                sendButton.disabled = true;
                attachButton.disabled = true;
                fileInput.disabled = true;
            }
            if (selectedId) await loadMessages(true);
            if (!conversations.length) document.getElementById('chat-count').textContent = 'Chưa có hội thoại';
        } catch (error) {
            conversationList.replaceChildren();
            const failure = document.createElement('div');
            failure.className = 'chat-inbox-empty';
            failure.textContent = error.message;
            conversationList.append(failure);
        }
    }

    document.getElementById('chat-form').addEventListener('submit', async event => {
        event.preventDefault();
        const message = textarea.value.trim();
        if (!selectedId || !message || sending || uploading) return;
        const targetUserId = selectedId;
        sending = true;
        sendButton.disabled = true;
        attachButton.disabled = true;
        fileInput.disabled = true;
        notice.textContent = '';
        try {
            const data = await request(api, {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({action:'send', user_id:selectedId, message})
            });
            if (selectedId === targetUserId) {
                appendMessage(data.message);
                messagesBox.scrollTop = messagesBox.scrollHeight;
                textarea.value = '';
            }
            await refreshConversations();
        } catch (error) {
            notice.textContent = error.message;
        } finally {
            sending = false;
            sendButton.disabled = !selectedId || uploading;
            attachButton.disabled = !selectedId || uploading;
            fileInput.disabled = !selectedId || uploading;
            textarea.disabled = !selectedId || uploading;
        }
    });
    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file || !selectedId || uploading) return;
        if (file.size < 1 || file.size > 100 * 1024 * 1024) {
            notice.textContent = 'Ảnh phải có dung lượng từ 1 byte đến 100 MB.';
            fileInput.value = '';
            attachButton.disabled = false;
            return;
        }
        const targetUserId = selectedId;
        const formData = new FormData();
        formData.append('file', file);
        formData.append('user_id', String(targetUserId));
        uploading = true;
        notice.textContent = `Đang gửi ${file.name}...`;
        sendButton.disabled = true;
        attachButton.disabled = true;
        fileInput.disabled = true;
        textarea.disabled = true;
        try {
            const data = await request(`${base}/api/chat/upload_attachment.php`, {method:'POST', body:formData});
            if (selectedId === targetUserId) {
                appendMessage(data.message);
                messagesBox.scrollTop = messagesBox.scrollHeight;
            }
            notice.textContent = '';
            await refreshConversations();
        } catch (error) {
            notice.textContent = error.message;
        } finally {
            uploading = false;
            fileInput.value = '';
            attachButton.disabled = !selectedId;
            fileInput.disabled = !selectedId;
            textarea.disabled = !selectedId;
            sendButton.disabled = !selectedId;
        }
    });
    attachButton.addEventListener('click', () => {
        if (!attachButton.disabled) fileInput.click();
    });
    textarea.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            document.getElementById('chat-form').requestSubmit();
        }
    });
    search.addEventListener('input', renderConversations);
    document.getElementById('chat-back').addEventListener('click', () => workspace.classList.remove('has-selection'));
    refreshConversations();
    window.setInterval(refreshConversations, 5000);
})();
</script>
</body>
</html>
