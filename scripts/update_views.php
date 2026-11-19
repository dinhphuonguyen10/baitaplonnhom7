<?php
require_once __DIR__ . '/../config/db.php';

// 1. Cập nhật view_product_details
$sqlDetails = "CREATE OR REPLACE VIEW view_product_details AS
SELECT 
    p.id,
    p.name,
    p.price_sale,
    p.price_original,
    p.discount,
    p.gift,
    p.image,
    p.stock,
    p.category_id,
    p.description,
    c.slug AS category,
    c.name AS category_name,
    COALESCE((
        SELECT SUM(oi.quantity)
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_id = p.id
          AND (o.status IS NULL OR o.status NOT IN ('Đã hủy', 'huy', 'cancelled'))
    ), 0) AS sold_count,
    COALESCE((
        SELECT COUNT(*)
        FROM wishlists w
        WHERE w.product_id = p.id
    ), 0) AS wishlist_count
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
GROUP BY p.id, c.slug, c.name";

if ($conn->query($sqlDetails)) {
    echo "view_product_details updated successfully.\n";
} else {
    echo "Error updating view_product_details: " . $conn->error . "\n";
}

// 2. Cập nhật view_featured_products (Lượt mua cao nhất bao gồm cả set quà)
$sqlFeatured = "CREATE OR REPLACE VIEW view_featured_products AS
SELECT * 
FROM view_product_details
ORDER BY sold_count DESC, id DESC
LIMIT 12";

if ($conn->query($sqlFeatured)) {
    echo "view_featured_products updated successfully.\n";
} else {
    echo "Error updating view_featured_products: " . $conn->error . "\n";
}

// 3. Cập nhật view_suggested_products (Lượt tim nhiều nhất bao gồm cả set quà)
$sqlSuggested = "CREATE OR REPLACE VIEW view_suggested_products AS
SELECT * 
FROM view_product_details
ORDER BY wishlist_count DESC, id DESC
LIMIT 12";

if ($conn->query($sqlSuggested)) {
    echo "view_suggested_products updated successfully.\n";
} else {
    echo "Error updating view_suggested_products: " . $conn->error . "\n";
}

// 4. Tạo view_new_products (Sản phẩm mới nhất bao gồm cả set quà)
$sqlNew = "CREATE OR REPLACE VIEW view_new_products AS
SELECT * 
FROM view_product_details
ORDER BY id DESC
LIMIT 12";

if ($conn->query($sqlNew)) {
    echo "view_new_products created successfully.\n";
} else {
    echo "Error creating view_new_products: " . $conn->error . "\n";
}
