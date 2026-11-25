<?php

use App\Core\View;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Địa chỉ nhận hàng</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <main>
        <h1>Địa chỉ nhận hàng</h1>
        <?php if ($message !== ''): ?>
            <p role="status" class="<?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
                <?php echo View::e($message); ?>
            </p>
        <?php endif; ?>

        <section aria-labelledby="saved-addresses-title">
            <h2 id="saved-addresses-title">Địa chỉ đã lưu</h2>
            <?php if ($addresses === []): ?>
                <p>Bạn chưa lưu địa chỉ nhận hàng nào.</p>
            <?php else: ?>
                <?php foreach ($addresses as $address_row): ?>
                    <article>
                        <h3><?php echo View::e($address_row['recipient_name']); ?>
                            <?php if ((int) $address_row['is_default'] === 1): ?><small>(Mặc định)</small><?php endif; ?>
                        </h3>
                        <p><?php echo View::e($address_row['phone']); ?></p>
                        <p><?php echo View::e($address_row['address']); ?></p>
                        <?php if ((int) $address_row['is_default'] !== 1): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="default">
                                <input type="hidden" name="address_id" value="<?php echo (int) $address_row['id']; ?>">
                                <button type="submit">Đặt làm mặc định</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa địa chỉ này?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="address_id" value="<?php echo (int) $address_row['id']; ?>">
                            <button type="submit">Xóa địa chỉ</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section aria-labelledby="add-address-title">
            <h2 id="add-address-title">Thêm địa chỉ mới</h2>
            <form method="post">
                <input type="hidden" name="action" value="add">
                <label for="recipient_name">Tên người nhận</label>
                <input id="recipient_name" name="recipient_name" maxlength="150" required>
                <label for="phone">Số điện thoại</label>
                <input id="phone" name="phone" type="tel" maxlength="20" required>
                <label for="address">Địa chỉ</label>
                <textarea id="address" name="address" maxlength="500" required></textarea>
                <label>
                    <input type="checkbox" name="is_default" value="1">
                    Đặt làm địa chỉ mặc định
                </label>
                <button type="submit">Lưu địa chỉ</button>
            </form>
        </section>
    </main>
</body>
</html>
