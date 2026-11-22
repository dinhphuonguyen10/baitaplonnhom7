

CREATE TABLE `addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `address` varchar(500) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_addresses_user` (`user_id`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('staff','manager') NOT NULL DEFAULT 'staff',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','manager','2026-11-13 11:09:03');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banners`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(500) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banners`
--

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `added_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_items_cart_product` (`cart_id`,`product_id`),
  KEY `idx_cart_items_product` (`product_id`),
  CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carts_user` (`user_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (2,'Bông tai','bong-tai'),(3,'Dây chuyền','day-chuyen'),(4,'Nhẫn','nhan'),(5,'Vòng tay','vong-tay'),(6,'Set quà tặng','set-qua-tang');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `username` varchar(100) NOT NULL,
  `sender` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `message_type` enum('text','image','file') NOT NULL DEFAULT 'text',
  `message` longtext NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(127) DEFAULT NULL,
  `attachment_size` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `product_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_chat_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `image` varchar(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,74,'Vòng tay Tối Giản Tinh Tế',1,1290000.00,'assets/vòng tay/vong15.png'),(2,2,4,'Set quà tặng Trọn Vẹn Yêu Thương',28,990000.00,'assets/quà/set1.jpg'),(3,3,3,'Nhẫn Kim Cương Ánh Tuyết Éclat',24,790000.00,'assets/nhẫn/nhan1.png'),(4,4,9,'Set quà tặng Hương Sắc Nàng Thơ',20,1050000.00,'assets/quà/set2.jpg'),(5,5,2,'Dây chuyền Trái Tim Đại Dương',18,650000.00,'assets/dây chuyền/dâychuyen/day1.jpg'),(6,6,1,'Bông tai Hoa Tuyết Dạ Quang',16,390000.00,'assets/bôngtai/bong1.jpg'),(7,7,14,'Set quà tặng Tình Nồng Say Đắm',15,1110000.00,'assets/quà/set3.jpg'),(8,8,8,'Nhẫn Vương Miện Nữ Hoàng',12,860000.00,'assets/nhẫn/nhan2.png'),(9,9,10,'Vòng tay Khắc Kỷ Niệm Vĩnh Hằng',10,640000.00,'assets/vòng tay/vong2.png'),(10,10,74,'Vòng tay Tối Giản Tinh Tế',8,1290000.00,'assets/vòng tay/vong15.png'),(11,11,6,'Bông tai Giọt Sương Pha Lê',6,440000.00,'assets/bôngtai/bong2.jpg'),(12,12,7,'Dây chuyền Vầng Trăng Yêu Thương',5,710000.00,'assets/dây chuyền/dâychuyen/day2.jpg'),(13,13,13,'Nhẫn Tình Nồng Vĩnh Cửu',4,930000.00,'assets/nhẫn/nhan3.png');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `address_id` int(10) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(254) NOT NULL DEFAULT '',
  `address` varchar(500) NOT NULL,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'Chờ xác nhận',
  `payment_status` varchar(50) NOT NULL DEFAULT 'Chưa thanh toán',
  `payment_method` varchar(50) NOT NULL DEFAULT 'COD',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_address` (`address_id`),
  KEY `idx_orders_created_at` (`created_at`),
  CONSTRAINT `fk_orders_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,1,NULL,'Yến Vũ','0563658095','vuyen5325@gmail.com','ưgwtwgfw, Phường Mường Lay, Tỉnh Điện Biên',1290000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-19 13:53:58'),(2,6,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',27720000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-15 17:13:17'),(3,3,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',18960000.00,'Đã giao hàng','Đã thanh toán','COD','2026-10-31 17:13:17'),(4,7,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',21000000.00,'Đã giao hàng','Đã thanh toán','COD','2026-10-25 17:13:17'),(5,6,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',11700000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-12 17:13:17'),(6,2,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',6240000.00,'Đã giao hàng','Đã thanh toán','COD','2026-10-26 17:13:17'),(7,8,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',16650000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-13 17:13:17'),(8,4,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',10320000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-01 17:13:17'),(9,2,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',6400000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-04 17:13:17'),(10,7,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',10320000.00,'Đã giao hàng','Đã thanh toán','COD','2026-10-29 17:13:17'),(11,7,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',2640000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-18 17:13:17'),(12,7,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',3550000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-16 17:13:17'),(13,5,NULL,'Khách hàng VIP','0912345678','order@ecrin.vn','Hà Nội',3720000.00,'Đã giao hàng','Đã thanh toán','COD','2026-11-09 17:13:17');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `price_sale` decimal(12,2) NOT NULL,
  `price_original` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` varchar(50) DEFAULT NULL,
  `gift` varchar(255) DEFAULT NULL,
  `image` varchar(500) NOT NULL,
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  `category_id` int(10) unsigned NOT NULL,
  `description` text DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_suggested` tinyint(1) NOT NULL DEFAULT 0,
  `is_new` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Bông tai Hoa Tuyết Dạ Quang',390000.00,0.00,NULL,NULL,'assets/bôngtai/bong1.jpg',15,2,'Lấy cảm hứng từ những đóa tuyết đầu mùa tinh khôi, chế tác từ bạc Ý 925 mạ bạch kim cao cấp gắn đá Zirconia đa diện phát sáng lấp lánh dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh nét đài các, là phụ kiện hoàn mỹ cho nàng tỏa sáng rạng ngời trong các buổi tiệc dạ hội.',1,0,0),(2,'Dây chuyền Trái Tim Đại Dương',650000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day1.jpg',15,3,'Mặt dây chuyền hình trái tim giác cắt tinh xảo tựa như viên bảo ngọc giữa lòng đại dương xanh thẳm. Sợi dây bạc Ý 925 thanh mảnh mạ bạch kim cao cấp nhẹ nhàng tôn vinh xương quai xanh quyến rũ của nàng.',1,0,0),(3,'Nhẫn Kim Cương Ánh Tuyết Éclat',790000.00,0.00,NULL,NULL,'assets/nhẫn/nhan1.png',15,4,'Thiết kế nhẫn solitaire kinh điển với 4 chấu nâng niu viên đá chủ Zirconia giác cắt Brilliant Cut 8 trái tim 8 mũi tên lấp lánh như kim cương tự nhiên. Sự lựa chọn hoàn mỹ cho lời cầu hôn ngọt ngào hay minh chứng của tình yêu đích thực.',1,0,0),(4,'Set quà tặng Trọn Vẹn Yêu Thương',990000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set1.jpg',15,6,'Hộp quà ÉCRIN sang trọng gồm dây chuyền và đôi bông tai hoa tuyết đồng điệu. Từng món trang sức được thiết kế hài hòa, đi kèm thiệp chúc mừng cao cấp, là giải pháp quà tặng hoàn hảo đong đầy chân tình gửi gắm đến người phụ nữ bạn yêu thương nhất.',1,1,0),(5,'Vòng tay Chuỗi Ngọc Tinh Khôi',590000.00,0.00,NULL,NULL,'assets/vòng tay/vong1.png',15,5,'Sự kết hợp ăn ý giữa các hạt châu bạc bóng bẩy và những viên đá sáng trong suốt. Chiếc vòng ôm khít cổ tay yêu kiều, mang lại cảm giác nhẹ nhàng, nữ tính và cực kỳ trang nhã.',0,0,0),(6,'Bông tai Giọt Sương Pha Lê',440000.00,0.00,NULL,NULL,'assets/bôngtai/bong2.jpg',22,2,'Thiết kế giọt sương mai đọng trên cánh hoa buổi sớm mang lại cảm giác dịu dàng và thuần khiết. Các viên đá pha lê Zirconia được tán sắc tỉ mỉ theo kỹ thuật kim hoàn Paris, tạo điểm nhấn yêu kiều và thanh tú cho gương mặt nàng.',1,0,0),(7,'Dây chuyền Vầng Trăng Yêu Thương',710000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day2.jpg',22,3,'Thiết kế vầng trăng lưỡi liềm ôm trọn viên đá chủ lấp lánh như giọt sương đêm diễm lệ. Biểu tượng cho tình yêu vĩnh hằng và sự chở che dịu dàng, mang lại vẻ đẹp lãng mạn đầy mê hoặc.',0,1,0),(8,'Nhẫn Vương Miện Nữ Hoàng',860000.00,0.00,NULL,NULL,'assets/nhẫn/nhan2.png',22,4,'Tạo hình vương miện hoàng gia lộng lẫy với các đỉnh chóp đính đá tinh vi. Món trang sức tôn vinh sự quyền lực, độc lập và khí chất vương giả của người phụ nữ hiện đại làm chủ cuộc đời mình.',1,0,0),(9,'Set quà tặng Hương Sắc Nàng Thơ',1050000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set2.jpg',22,6,'Bộ quà tinh tuyển gồm nhẫn vương miện và dây chuyền trái tim đính đá cao cấp. Sự hòa quyện giữa nét kiêu kỳ và dịu dàng tôn vinh trọn vẹn vẻ đẹp nàng thơ, làm tan chảy mọi trái tim ngay từ khoảnh khắc mở hộp.',1,1,0),(10,'Vòng tay Khắc Kỷ Niệm Vĩnh Hằng',640000.00,0.00,NULL,NULL,'assets/vòng tay/vong2.png',22,5,'Thiết kế lắc tay dạng thanh mảnh hiện đại, bề mặt bóng bẩy tạo cảm giác sang trọng. Món phụ kiện lý tưởng để lưu giữ những dấu ấn kỷ niệm đáng nhớ của tuổi thanh xuân rực rỡ.',1,0,0),(11,'Bông tai Cánh Bướm Tinh Khôi',490000.00,0.00,NULL,NULL,'assets/bôngtai/bong3.jpg',11,2,'Biểu tượng của sự tự do và nét quyến rũ vĩnh cửu. Đôi cánh bướm uốn lượn mềm mại với hàng chục giác cắt phản chiếu ánh sáng lộng lẫy, vừa hiện đại vừa quý phái, dễ dàng phối hợp cùng đầm dạ tiệc lẫn âu phục công sở sang trọng.',0,1,0),(12,'Dây chuyền Thiên Cốt Khảm Ngọc',770000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day3.jpg',11,3,'Sự kết hợp tinh hoa giữa nghệ thuật chế tác kim hoàn cổ điển và hơi thở thời thượng. Đường nét thanh nhã cùng viên đá chủ lấp lánh tạo điểm nhấn sang trọng, quý phái trong mọi khung hình.',0,0,0),(13,'Nhẫn Tình Nồng Vĩnh Cửu',930000.00,0.00,NULL,NULL,'assets/nhẫn/nhan3.png',11,4,'Dải đá nạm kín viền thân nhẫn (Eternity Band) tượng trưng cho tình yêu không bao giờ phai nhạt. Bạc 925 đúc nguyên khối dày dặn đem lại cảm giác êm ái khi đeo ngón tay cả ngày dài.',1,1,0),(14,'Set quà tặng Tình Nồng Say Đắm',1110000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set3.jpg',11,6,'Combo trang sức bạc 925 gồm vòng tay charm và dây chuyền vô cực mang thông điệp yêu thương vĩnh cửu. Được đóng gói chỉn chu trong hộp quà phủ nhung mềm mại, nâng tầm giá trị cho dịp kỷ niệm lãng mạn.',1,0,0),(15,'Vòng tay Chuông Gió Thanh Âm',690000.00,0.00,NULL,NULL,'assets/vòng tay/vong3.png',11,5,'Những hạt charm lúc lắc phát ra thanh âm vui tươi dịu nhẹ theo mỗi cử động của cổ tay. Thiết kế mang lại năng lượng trẻ trung, yêu đời và may mắn ngập tràn cho người sở hữu.',0,0,0),(16,'Bông tai Trăng Khuyết Huyền Ảo',540000.00,0.00,NULL,NULL,'assets/bôngtai/bong4.jpg',18,2,'Vẻ đẹp huyền bí của vầng trăng khuyết giữa trời đêm được tái hiện qua đường cong mềm mại từ bạc 925 cao cấp kết hợp đính đá tinh xảo. Một tuyệt tác dành riêng cho những cô nàng theo đuổi phong cách thanh lịch, bí ẩn và cuốn hút.',0,1,0),(17,'Dây chuyền Hào Quang Nhật Nguyệt',830000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day4.jpg',18,3,'Giao thoa giữa ánh dương ấm áp và ánh nguyệt dịu êm. Mặt dây chuyền được đính đá tán sắc rực rỡ, giúp phái đẹp tự tin tỏa sáng với phong thái kiêu sa, đài các ở bất cứ nơi đâu.',0,0,0),(18,'Nhẫn Nụ Cười Thiên Sứ',1000000.00,0.00,NULL,NULL,'assets/nhẫn/nhan4.png',18,4,'Đường cong chữ V mềm mại ôm lấy ngón tay, tạo hiệu ứng thị giác thon dài thanh thoát. Sự thanh lịch trong từng đường nét giúp tôn vinh vẻ đẹp dịu dàng và nụ cười rạng rỡ của nàng.',0,1,0),(19,'Set quà tặng Nữ Thần Kiêu Sa',1170000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set4.jpg',18,6,'Set quà đẳng cấp gồm vòng tay cuff và bông tai ngọc nữ sang trọng. Tông màu bạch kim sáng bóng toát lên thần thái quyền quý, là món quà xứng tầm để tri ân đối tác, sếp nữ hoặc người phụ nữ thành đạt.',0,0,0),(20,'Vòng tay Duyên Ngầm Quý Phái',740000.00,0.00,NULL,NULL,'assets/vòng tay/vong4.png',18,5,'Vòng cứng dạng cuff mở đính đá hai đầu tinh xảo, chất bạc Ý cao cấp đúc tỉ mỉ từng milimet. Tôn lên nét đẹp thanh lịch, hiện đại mà không kém phần sang trọng cho các quý cô thời thượng.',0,0,0),(21,'Bông tai Nụ Hồng E Ấp',590000.00,0.00,NULL,NULL,'assets/bôngtai/bong5.jpg',25,2,'Gợi nhắc đến vẻ đẹp ngọt ngào của những đóa hồng hé nở trong sương sớm. Từng cánh hoa bạc uốn lượn đa tầng ôm trọn viên đá chủ lấp lánh, mang đến năng lượng tích cực và sự duyên dáng khó quên cho chủ nhân.',1,0,0),(22,'Dây chuyền Khóa Tình Yêu Vĩnh Hằng',890000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day5.jpg',25,3,'Biểu tượng ổ khóa tình yêu kinh điển được cách điệu mềm mại, đính dải đá CZ sáng bóng. Món trang sức mang thông điệp gắn kết bền chặt, là lời thề ước thủy chung không thể tách rời.',0,1,0),(23,'Nhẫn Đôi Cánh Khát Vọng',1070000.00,0.00,NULL,NULL,'assets/nhẫn/nhan5.png',25,4,'Thiết kế mở linh hoạt với hai đầu cánh chim dang rộng nạm đá lấp lánh. Biểu trưng cho sự vươn cao, tự do và niềm khát khao chinh phục những cột mốc rực rỡ trong cuộc sống.',0,0,0),(24,'Set quà tặng Duyên Dáng Thanh Xuân',1230000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set5.jpg',25,6,'Lưu giữ trọn vẹn nét tươi trẻ của tuổi thanh xuân với bộ đôi bông tai cánh bướm và lắc tay chuông gió. Món quà ngọt ngào, tinh tế truyền cảm hứng sống rạng rỡ và tràn đầy niềm vui mỗi ngày.',0,0,0),(25,'Vòng tay Mắt Biếc Diễm Lệ',790000.00,0.00,NULL,NULL,'assets/vòng tay/vong5.png',25,5,'Chi tiết mắt biếc đính đá sapphire nhân tạo xanh ngọc quyền quý, tượng trưng cho ánh nhìn thông tuệ và sự bảo hộ bình an. Chiếc vòng mang lại nét kiêu sa, quyến rũ cho cổ tay thon gọn.',0,1,0),(26,'Bông tai Ánh Sao Hạ Giới',640000.00,0.00,NULL,NULL,'assets/bôngtai/bong6.jpg',14,2,'Thiết kế ngôi sao đa giác đính đá vi mô tinh xảo, phản chiếu hào quang lung linh tựa như những vì tinh tú trên dải ngân hà. Đây là điểm nhấn rực rỡ giúp nàng luôn là tâm điểm của sự chú ý ở mọi không gian xuất hiện.',0,0,0),(27,'Dây chuyền Giọt Nước Thanh Khiết',950000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day6.jpg',14,3,'Giọt nước tinh khôi được đúc kết từ chất liệu bạc cao cấp và viên đá CZ hình giọt lệ hoàn hảo. Thiết kế tối giản nhưng toát lên vẻ đẹp thanh khiết, trang nhã khó rời mắt.',0,0,0),(28,'Nhẫn Đóa Hồng Tinh Tuyển',1140000.00,0.00,NULL,NULL,'assets/nhẫn/nhan6.png',14,4,'Bông hồng tình yêu được khắc họa sống động trên nền chất liệu bạc cao cấp mạ rhodium. Món trang sức mang đậm tính nghệ thuật, tôn lên vẻ đài các và sự lãng mạn ngọt ngào.',0,0,0),(29,'Set quà tặng Ánh Dương Ngày Mới',1290000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set6.jpg',14,6,'Hộp quà cao cấp mang nguồn năng lượng ấm áp từ biểu tượng mặt trời và đá pha lê sáng lấp lánh. Lời chúc thành công, may mắn và rực rỡ nhất gửi trao đến người nhận.',0,0,0),(30,'Vòng tay Nơ Xinh Thuần Khiết',840000.00,0.00,NULL,NULL,'assets/vòng tay/vong6.png',14,5,'Họa tiết chiếc nơ xinh xắn biểu trưng cho sự dịu dàng, ngọt ngào của người phụ nữ. Từng đường uốn của nơ được nạm đá CZ nhỏ nhắn, tạo nên tổng thể hài hòa, bắt mắt.',0,0,0),(31,'Bông tai Vòng Nguyệt Quế Hoàng Gia',690000.00,0.00,NULL,NULL,'assets/bôngtai/bong7.jpg',21,2,'Mang ý nghĩa của vinh quang và hạnh phúc tròn đầy, tạo hình vòng nguyệt quế đính đá baguette và pave tinh xảo mang hơi thở hoàng gia châu Âu cổ điển. Sự kết hợp hoàn hảo giữa nét vương giả và phong thái hiện đại.',0,0,0),(32,'Dây chuyền Đóa Cúc Họa Mi Bạc',1010000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day7.jpg',21,3,'Tái hiện nét ngây thơ, mộc mạc mà tràn đầy sức sống của đóa cúc họa mi chớm đông. Sợi dây mang đến cảm giác trong trẻo, dịu ngọt, là điểm nhấn duyên dáng cho phong cách hàng ngày.',0,0,0),(33,'Nhẫn Trái Tim Say Đắm',1210000.00,0.00,NULL,NULL,'assets/nhẫn/nhan8.png',21,4,'Viên đá chủ cắt hình trái tim (Heart Cut) hoàn hảo được bảo bọc bởi viền hào quang halo đá tấm rực rỡ. Một biểu tượng tình yêu nồng nàn, khiến mọi ánh nhìn đều phải xao xuyến ngay từ lần đầu chạm mắt.',0,1,0),(34,'Set quà tặng Khởi Sắc Hoàng Kim',1350000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set7.jpg',21,6,'Tuyệt phẩm quà tặng phong thủy gồm nhẫn kim tiền và dây chuyền may mắn. Biểu trưng cho sự nghiệp hanh thông, tài lộc dồi dào, là món quà biếu tặng tinh tế dịp lễ tết và khai trương.',0,0,0),(35,'Vòng tay Hồ Điệp Uyển Chuyển',890000.00,0.00,NULL,NULL,'assets/vòng tay/vong7.png',21,5,'Những chú bướm bạc xinh xắn rập rờn trên cổ tay như lạc vào khu vườn mùa xuân. Phong cách thiết kế mềm mại, lãng mạn mang đến vẻ đẹp yêu kiều cho mọi cô gái.',0,0,0),(36,'Bông tai Vũ Điệu Thiên Nga',740000.00,0.00,NULL,NULL,'assets/bôngtai/bong8.jpg',10,2,'Lấy cảm hứng từ nét kiêu hãnh của loài thiên nga trắng trên mặt hồ phẳng lặng. Từng đường nét uốn cong nhẹ nhàng ôm sát vành tai, tạo nên vẻ đẹp trang nhã, kiêu kỳ nhưng vẫn vô cùng đằm thắm.',0,0,0),(37,'Dây chuyền Thiên Thần Hộ Mệnh',1070000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day8.jpg',10,3,'Đôi cánh thiên thần dang rộng che chở mang lại bình yên và niềm tin. Từng chiếc lông vũ trên cánh bạc được chạm trổ công phu, tượng trưng cho sự may mắn và hạnh phúc viên mãn.',0,0,0),(38,'Nhẫn Nguyện Ước Dưới Trăng',1280000.00,0.00,NULL,NULL,'assets/nhẫn/nhan9.png',10,4,'Lấy cảm hứng từ những lời nguyện ước thành kính dưới ánh trăng thanh bình. Thiết kế nhẫn đôi xếp tầng độc đáo, mang đến diện mạo phong cách và đẳng cấp cho đôi bàn tay ngọc ngà.',0,0,0),(39,'Set quà tặng Nguyệt Dạ Thao Thức',1410000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set8.jpg',10,6,'Bộ trang sức vầng trăng khuyết và những vì sao huyền diệu đính đá Zirconia cao cấp. Món quà lãng mạn như một bản tình ca êm dịu, đánh thức mọi rung động ngọt ngào trong tình yêu.',0,0,0),(40,'Vòng tay Chỉ Đỏ Cát Tường',940000.00,0.00,NULL,NULL,'assets/vòng tay/vong8.png',10,5,'Sợi chỉ đỏ may mắn kết hợp charm bạc đính đá phong thủy mang lại bình an, trừ tà và hút tài lộc. Món trang sức ý nghĩa để trao tặng người thương trong những dịp đặc biệt.',0,0,0),(41,'Bông tai Ngọc Nữ Duyên Dáng',790000.00,0.00,NULL,NULL,'assets/bôngtai/bong9.jpg',17,2,'Thiết kế thả dáng thanh mảnh tạo hiệu ứng thon gọn cho khuôn mặt. Viên đá chủ giác cắt tròn hoàn hảo chuyển động nhịp nhàng theo từng bước đi, mang đến vẻ đẹp sống động và phong thái tự tin cho phái đẹp.',0,1,0),(42,'Dây chuyền Ngôi Sao Phương Bắc',1130000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day9.jpg',17,3,'Ngôi sao Polaris dẫn lối định hướng cho những ước mơ cao đẹp. Thiết kế 8 cánh sao sắc sảo cùng viên đá tâm điểm tỏa ánh sáng rực rỡ, tiếp thêm sự tự tin và năng lượng tích cực cho chủ nhân.',0,0,0),(43,'Nhẫn Vô Cực Tình Yêu',1350000.00,0.00,NULL,NULL,'assets/nhẫn/nhan10.png',17,4,'Biểu tượng vô cực (Infinity) đại diện cho tình yêu bất diệt và những khả năng vô hạn. Kết cấu thanh mảnh, tinh tế, thích hợp đeo riêng lẻ hoặc phối layer cùng nhiều mẫu nhẫn khác.',0,0,0),(44,'Set quà tặng Thuần Khiết Tinh Khôi',1470000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set9.jpg',17,6,'Tổ hợp những thiết kế tối giản tinh khôi nhất từ bạc Ý 925 nguyên chất. Tôn vinh nét đẹp chân phương mà cuốn hút, phù hợp với mọi độ tuổi và phong cách thời trang khác nhau.',0,0,0),(45,'Vòng tay Nguyệt Cát Kim Tuyến',990000.00,0.00,NULL,NULL,'assets/vòng tay/vong9.png',17,5,'Các mắt xích dẹt được phay cát lấp lánh phản quang như dải cát dưới ánh trăng. Thiết kế thanh thoát, chống oxy hóa hoàn hảo, nâng tầm phong thái tự tin trong từng cử chỉ tay.',0,0,0),(46,'Bông tai Trái Tim Vĩnh Cửu',840000.00,0.00,NULL,NULL,'assets/bôngtai/bong10.jpg',24,2,'Biểu tượng bất diệt của tình yêu đôi lứa được chạm khắc tinh xảo từ chất liệu bạc thuần khiết và đá quý lấp lánh. Món quà hoàn hảo để bày tỏ tình cảm chân thành hoặc tự thưởng cho bản thân một nét duyên ngọt ngào.',0,1,0),(47,'Dây chuyền Vòng Tròn Hạnh Phúc',1190000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day10.jpg',24,3,'Hai vòng tròn lồng ghép vào nhau không hồi kết tượng trưng cho sự gắn kết vĩnh cửu và cuộc sống trọn vẹn. Phong cách hiện đại đậm chất thời trang quốc tế, dễ dàng phối đồ mọi phong cách.',0,0,0),(48,'Nhẫn Dải Ngân Hà Lấp Lánh',1420000.00,0.00,NULL,NULL,'assets/nhẫn/nhan11.png',24,4,'Ba hàng đá Zirconia xếp lệch tầng tạo hiệu ứng ánh sáng cuộn trào như dải ngân hà rực rỡ. Bản nhẫn lớn sang trọng khẳng định phong cách quý phái và gu thẩm mỹ đỉnh cao của chủ nhân.',0,0,0),(49,'Set quà tặng Hạnh Phúc Viên Mãn',1530000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set10.jpg',24,6,'Bộ ba trang sức nhẫn, dây chuyền và khuyên tai họa tiết vòng tròn hạnh phúc. Biểu tượng của gia đình êm ấm, tình yêu trọn vẹn và cuộc sống an vui, đủ đầy muôn phần.',0,0,0),(50,'Vòng tay San Hô Biển Sâu',1040000.00,0.00,NULL,NULL,'assets/vòng tay/vong10.png',24,5,'Lấy cảm hứng từ vẻ đẹp nguyên sơ của các rạn san hô dưới đáy biển đại dương. Họa tiết khắc nổi đan xen đá quý tạo nên chiều sâu thẩm mỹ vô cùng cuốn hút và độc bản.',0,0,0),(51,'Bông tai Sương Mai Lấp Lánh',890000.00,0.00,NULL,NULL,'assets/bôngtai/bong11.jpg',13,2,'Nét đẹp thanh tân và tràn đầy sức sống của buổi sớm mai. Các chi tiết đính đá thủ công tinh tế giúp bắt sáng tuyệt đối, nâng tầm mọi set trang phục từ trang nhã thường nhật đến dạ tiệc sang trọng.',0,0,0),(52,'Dây chuyền Cánh Hạc Lưu Ly',1250000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day11.jpg',13,3,'Cánh hạc giấy mang theo ngàn lời chúc tốt lành được thổi hồn vào chất liệu kim hoàn cao cấp. Một tác phẩm nghệ thuật trang sức thanh tao, biểu trưng cho sự trường thọ và an nhiên.',0,0,0),(53,'Nhẫn Kim Tiền Phú Quý',1490000.00,0.00,NULL,NULL,'assets/nhẫn/nhan12.png',13,4,'Hàng kim tiền nối tiếp biểu trưng cho tài lộc dồi dào, tiền bạc hanh thông và may mắn ngập tràn. Chế tác tinh xảo, thanh mảnh không hề nặng nề, phù hợp đeo ngón cái hoặc ngón trỏ để chiêu tài.',0,0,0),(54,'Set quà tặng Tình Ca Mùa Hạ',1590000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set11.jpg',13,6,'Những gam màu tươi tắn cùng nét cắt đá sống động mang đến cảm giác sảng khoái của mùa hè rực nắng. Hộp quà sang trọng khẳng định tình cảm nồng nàn không bao giờ hạ nhiệt.',0,0,0),(55,'Vòng tay Băng Thanh Ngọc Khiết',1090000.00,0.00,NULL,NULL,'assets/vòng tay/vong11.png',13,5,'Tựa như những tinh thể băng giá trong suốt của mùa đông tuyết trắng. Thiết kế thanh cao, mang đến khí chất thuần khiết, thanh thoát và quý phái ngút ngàn.',0,0,0),(56,'Bông tai Đóa Lan Hoàng Kim',940000.00,0.00,NULL,NULL,'assets/bôngtai/bong12.jpg',20,2,'Tái hiện vẻ đẹp đài các, kiêu sa của đóa phong lan quý hiếm. Chế tác với kỹ thuật phủ rhodium cao cấp bảo vệ độ sáng bóng bền lâu, tôn lên thần thái thanh tao và khí chất ngút ngàn của quý cô thành đạt.',0,0,0),(57,'Dây chuyền Nguyệt Quang Kiêu Sa',1310000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day12.jpg',20,3,'Ánh trăng huyền diệu soi rọi vẻ đẹp đằm thắm của người phụ nữ. Mặt dây chuyền chế tác tinh xảo, phản chiếu ánh sáng óng ánh tựa như vầng trăng rằm tháng Tám.',0,0,0),(58,'Nhẫn Nữ Thần Tình Ái',1560000.00,0.00,NULL,NULL,'assets/nhẫn/nhan13.png',20,4,'Cảm hứng từ vẻ đẹp bất tận của nữ thần Aphrodite trong thần thoại Hy Lạp. Từng đường uốn lượn đính đá kim cương nhân tạo tạo nên kiệt tác trang sức đầy quyền rũ và mê hoặc.',0,0,0),(59,'Set quà tặng Khúc Giao Mùa Lãng Mạn',1650000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set12.jpg',20,6,'Sự kết hợp giữa nét dịu dàng của hoa cỏ mùa xuân và chiều sâu của mùa thu vàng. Set quà tinh xảo khiến người nhận cảm nhận được sự chăm chút tỉ mỉ từ người tặng trong từng chi tiết.',0,0,0),(60,'Vòng tay Ánh Dương Rạng Rỡ',1140000.00,0.00,NULL,NULL,'assets/vòng tay/vong12.png',20,5,'Biểu tượng mặt trời tỏa rạng nguồn sinh khí mới và niềm hy vọng tương lai. Chiếc vòng giúp đánh thức nét rạng ngời và thu hút vận may, sự tự tin đến với chủ nhân.',0,0,0),(61,'Bông tai Dải Ngân Hà Lộng Lẫy',990000.00,0.00,NULL,NULL,'assets/bôngtai/bong13.jpg',9,2,'Những dải đá xếp tầng uốn lượn mềm mại như dòng chảy ngân hà rực rỡ trong đêm huyền diệu. Sự tinh xảo trong từng mối nối mang lại cảm giác thoải mái và kiêu hãnh tuyệt đối khi đeo.',0,0,0),(62,'Dây chuyền Chiếc Lá Mùa Thu',1370000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day13.jpg',9,3,'Đường gân lá uốn lượn tự nhiên đính những hạt đá li ti như hạt sương mùa thu. Mang nét hoài niệm lãng mạn và thi vị, chiếc dây chuyền là món quà tuyệt mỹ dành cho tâm hồn yêu cái đẹp.',0,0,0),(63,'Nhẫn Lục Bảo Hoàng Gia',1630000.00,0.00,NULL,NULL,'assets/nhẫn/nhan14.png',9,4,'Viên đá chủ sắc lục bảo huyền bí tôn lên nét quý tộc và phong thủy tài lộc mạnh mẽ. Được ôm ấp bởi ổ nhẫn bạc đính đá trắng lấp lánh, toát lên phong thái vương giả khó cưỡng.',0,0,1),(64,'Set quà tặng Tri Kỷ Vĩnh Hằng',1710000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set13.jpg',9,6,'Dành riêng cho những người bạn tri kỷ son sắt qua năm tháng. Món trang sức bạc cao cấp bền bỉ như tình bạn chân thành, luôn đồng hành cùng nhau trên mọi chặng đường cuộc sống.',0,0,1),(65,'Vòng tay Tình Thơ Mộng Ảo',1190000.00,0.00,NULL,NULL,'assets/vòng tay/vong13.png',9,5,'Những charm trái tim nhỏ xinh xâu chuỗi nhịp nhàng trên sợi lắc bạc đan kép. Thiết kế thơ mộng, đong đầy yêu thương, khiến mỗi ngày trôi qua đều ngập tràn hạnh phúc.',0,0,1),(66,'Bông tai Bồ Công Anh Bay Trong Gió',1040000.00,0.00,NULL,NULL,'assets/bôngtai/bong14.jpg',16,2,'Lấy ý tưởng từ cánh bồ công anh mang theo những nguyện ước bay xa. Thiết kế bông tròn đính đá đa diện siêu nhỏ tạo cảm giác bồng bềnh, thanh thoát và vô cùng tinh tế trên gương mặt.',0,0,1),(67,'Dây chuyền Giấc Mơ Pha Lê',1430000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day14.jpg',16,3,'Viên pha lê đa sắc chuyển màu theo từng nhịp ánh sáng, gợi nhắc những giấc mơ cổ tích thời thanh xuân. Thiết kế bay bổng, lãng mạn tôn vinh trọn vẹn nét nữ tính, kiều diễm.',0,0,1),(68,'Nhẫn Ngôi Sao Hy Vọng',1700000.00,0.00,NULL,NULL,'assets/nhẫn/nhan15.png',16,4,'Thiết kế chấu nhẫn đan chéo nâng đỡ viên đá chủ cao nổi bật như ngôi sao hy vọng soi đường. Lớp mạ platinum chống xỉn màu giữ trọn độ sáng bóng bóng bẩy qua năm tháng.',0,0,1),(69,'Set quà tặng Giáng Sinh An Lành',1770000.00,0.00,NULL,'Hộp quà ÉCRIN','assets/quà/set14.jpg',16,6,'Hộp quà mùa lễ hội đặc biệt với thiết kế bông tuyết và ánh sao lung linh huyền diệu. Đem lại không khí ấm áp, an lành và những khoảnh khắc kỷ niệm khó quên cho mùa đông yêu thương.',0,0,1),(70,'Vòng tay Giọt Sương Pha Lê',1240000.00,0.00,NULL,NULL,'assets/vòng tay/vong14.png',16,5,'Từng viên đá pha lê Zirconia tròn trong vắt được nối kết tinh vi theo phong cách Tennis Bracelet kinh điển. Món trang sức đẳng cấp không thể thiếu trong tủ đồ của những quý cô chuộng phong cách thượng lưu.',0,0,1),(71,'Bông tai Cỏ Bốn Lá May Mắn',1090000.00,0.00,NULL,NULL,'assets/bôngtai/bong15.jpg',23,2,'Biểu tượng may mắn huyền thoại với 4 cánh cỏ cách điệu đính đá CZ tinh xảo. Không chỉ là phụ kiện thời trang đẳng cấp tôn vinh nét thanh lịch, đây còn là lá bùa bình an, may mắn và hạnh phúc song hành cùng nàng mỗi ngày.',0,0,1),(72,'Dây chuyền Kim Tiền Chiêu Tài',1490000.00,0.00,NULL,NULL,'assets/dây chuyền/dâychuyen/day15.jpg',23,3,'Thiết kế đồng kim tiền đan xen liền mạch mang ý nghĩa phong thủy vượng tài, may mắn và thịnh vượng bền lâu. Chất liệu bạc mạ vàng trắng sang trọng, vừa mang tính tâm linh cát tường vừa là phụ kiện thời thượng.',0,0,1),(73,'Nhẫn Uyên Ương Trọn Đời',1770000.00,0.00,NULL,NULL,'assets/nhẫn/nhan16.png',23,4,'Hai dải bạc xoắn lượn quyện chặt vào nhau như hai tâm hồn đồng điệu cùng chung nhịp đập. Mẫu nhẫn biểu tượng cho sự gắn kết thủy chung trọn đời, một lựa chọn tuyệt mỹ cho kỷ niệm ngày cưới.',0,0,1),(74,'Vòng tay Tối Giản Tinh Tế',1290000.00,0.00,NULL,NULL,'assets/vòng tay/vong15.png',22,5,'Đỉnh cao của phong cách Minimalism tinh tế. Thân vòng trơn láng bóng mượt ôm sát cổ tay với điểm nhấn khóa bấm giấu kín thông minh, vừa tiện dụng vừa thể hiện gu thẩm mỹ sang chảnh, tinh tế.',1,0,1);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_images`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `review_id` int(10) unsigned NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_review_images_review` (`review_id`),
  CONSTRAINT `fk_review_images_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_images`
--

LOCK TABLES `review_images` WRITE;
/*!40000 ALTER TABLE `review_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_replies`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_replies` (
  `review_id` int(10) unsigned NOT NULL,
  `admin_username` varchar(100) NOT NULL,
  `reply` text NOT NULL,
  `replied_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`),
  CONSTRAINT `fk_review_replies_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_replies`
--

LOCK TABLES `review_replies` WRITE;
/*!40000 ALTER TABLE `review_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_replies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `comment` text DEFAULT NULL,
  `edit_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reviews_product_user` (`product_id`,`user_id`),
  KEY `idx_reviews_user` (`user_id`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stores`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `address` varchar(500) NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `phone` varchar(30) NOT NULL DEFAULT '',
  `hours` varchar(100) NOT NULL DEFAULT '08:00 - 22:00',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stores`
--

LOCK TABLES `stores` WRITE;
/*!40000 ALTER TABLE `stores` DISABLE KEYS */;
INSERT INTO `stores` VALUES (1,'ÉCRIN JEWELRY Cầu Giấy','123 Xuân Thủy, Dịch Vọng, Cầu Giấy, Hà Nội',NULL,'024.1234.5678','08:00 - 22:00','active'),(2,'ÉCRIN JEWELRY Hoàn Kiếm','45 Hàng Đào, Hoàn Kiếm, Hà Nội',NULL,'024.8888.9999','08:00 - 22:00','active'),(3,'ÉCRIN JEWELRY Quận 1','89 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. HCM',NULL,'028.1111.2222','08:00 - 22:00','active'),(4,'ÉCRIN JEWELRY Quận 10','256 Sư Vạn Hạnh, Phường 12, Quận 10, TP. HCM',NULL,'028.4444.5555','08:00 - 22:00','active'),(5,'ÉCRIN JEWELRY Đà Nẵng','12 Lê Duẩn, Hải Châu, Đà Nẵng',NULL,'0236.777.888','08:00 - 22:00','active');
/*!40000 ALTER TABLE `stores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'vuyen5325@gmail.com','$2y$10$nSsTL49wEXRiVNcclJuyEeazZ1xfqaTC1WjwQgdWA35DTsxk/9nrm','0563658095','2026-11-19 11:55:26'),(2,'nguyenmai','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345671','2026-11-19 17:13:17'),(3,'tranlong','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345672','2026-11-19 17:13:17'),(4,'leanh','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345673','2026-11-19 17:13:17'),(5,'hoangyen','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345674','2026-11-19 17:13:17'),(6,'phamvu','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345675','2026-11-19 17:13:17'),(7,'doquyen','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345676','2026-11-19 17:13:17'),(8,'bichtram','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345677','2026-11-19 17:13:17'),(9,'minhtriet','$2y$10$yWDPc1Bwyh7apV8CJZoIMuMlIJcGoMGreiRUsNb1JyB7wkfF5ZmRm','0912345678','2026-11-19 17:13:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `view_product_details`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `view_product_details` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `price_sale`,
  1 AS `price_original`,
  1 AS `discount`,
  1 AS `gift`,
  1 AS `image`,
  1 AS `stock`,
  1 AS `category_id`,
  1 AS `description`,
  1 AS `is_featured`,
  1 AS `is_suggested`,
  1 AS `is_new`,
  1 AS `category`,
  1 AS `category_name`,
  1 AS `sold_count`,
  1 AS `wishlist_count`,
  1 AS `average_rating`,
  1 AS `review_count` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `view_top_rated_products`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `view_top_rated_products` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `price_sale`,
  1 AS `price_original`,
  1 AS `discount`,
  1 AS `gift`,
  1 AS `image`,
  1 AS `stock`,
  1 AS `category_id`,
  1 AS `description`,
  1 AS `category`,
  1 AS `category_name`,
  1 AS `sold_count`,
  1 AS `wishlist_count`,
  1 AS `avg_rating`,
  1 AS `review_count` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `web_config`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `web_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `config_key` varchar(150) NOT NULL,
  `config_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_web_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `web_config`
--

LOCK TABLES `web_config` WRITE;
/*!40000 ALTER TABLE `web_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `web_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wishlists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_product` (`user_id`,`product_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_wishlists_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlists`
--

LOCK TABLES `wishlists` WRITE;
/*!40000 ALTER TABLE `wishlists` DISABLE KEYS */;
INSERT INTO `wishlists` VALUES (73,2,9,'2026-11-19 17:13:17'),(74,3,9,'2026-11-19 17:13:17'),(75,4,9,'2026-11-19 17:13:17'),(76,5,9,'2026-11-19 17:13:17'),(77,6,9,'2026-11-19 17:13:17'),(78,7,9,'2026-11-19 17:13:17'),(80,2,13,'2026-11-19 17:13:17'),(81,3,13,'2026-11-19 17:13:17'),(82,4,13,'2026-11-19 17:13:17'),(83,5,13,'2026-11-19 17:13:17'),(84,6,13,'2026-11-19 17:13:17'),(86,2,4,'2026-11-19 17:13:18'),(87,3,4,'2026-11-19 17:13:18'),(88,4,4,'2026-11-19 17:13:18'),(89,5,4,'2026-11-19 17:13:18'),(91,2,7,'2026-11-19 17:13:18'),(92,3,7,'2026-11-19 17:13:18'),(93,4,7,'2026-11-19 17:13:18'),(94,5,7,'2026-11-19 17:13:18'),(96,2,19,'2026-11-19 17:13:18'),(97,3,19,'2026-11-19 17:13:18'),(98,4,19,'2026-11-19 17:13:18'),(100,2,11,'2026-11-19 17:13:18'),(101,3,11,'2026-11-19 17:13:18'),(102,4,11,'2026-11-19 17:13:18'),(104,2,15,'2026-11-19 17:13:18'),(105,3,15,'2026-11-19 17:13:18'),(107,2,34,'2026-11-19 17:13:18'),(108,3,34,'2026-11-19 17:13:18'),(110,2,8,'2026-11-19 17:13:18'),(111,3,8,'2026-11-19 17:13:18'),(113,2,12,'2026-11-19 17:13:18'),(177,1,12,'2026-11-19 17:28:15'),(178,1,34,'2026-11-19 17:28:15'),(179,1,15,'2026-11-19 17:28:15'),(180,1,11,'2026-11-19 17:28:15'),(181,1,19,'2026-11-19 17:28:15'),(182,1,7,'2026-11-19 17:28:15'),(183,1,13,'2026-11-19 17:28:15'),(184,1,9,'2026-11-19 17:28:15');
/*!40000 ALTER TABLE `wishlists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `view_product_details`
--

/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_product_details` AS select `p`.`id` AS `id`,`p`.`name` AS `name`,`p`.`price_sale` AS `price_sale`,`p`.`price_original` AS `price_original`,`p`.`discount` AS `discount`,`p`.`gift` AS `gift`,`p`.`image` AS `image`,`p`.`stock` AS `stock`,`p`.`category_id` AS `category_id`,`p`.`description` AS `description`,`p`.`is_featured` AS `is_featured`,`p`.`is_suggested` AS `is_suggested`,`p`.`is_new` AS `is_new`,`c`.`slug` AS `category`,`c`.`name` AS `category_name`,coalesce((select sum(`oi`.`quantity`) from (`order_items` `oi` join `orders` `o` on(`o`.`id` = `oi`.`order_id`)) where `oi`.`product_id` = `p`.`id` and (`o`.`status` is null or `o`.`status` not in ('Đã hủy','huy','cancelled'))),0) AS `sold_count`,coalesce((select count(0) from `wishlists` `w` where `w`.`product_id` = `p`.`id`),0) AS `wishlist_count`,coalesce((select round(avg(`r`.`rating`),1) from `reviews` `r` where `r`.`product_id` = `p`.`id` and `r`.`rating` between 1 and 5),0) AS `average_rating`,coalesce((select count(0) from `reviews` `r` where `r`.`product_id` = `p`.`id` and `r`.`rating` between 1 and 5),0) AS `review_count` from (`products` `p` left join `categories` `c` on(`c`.`id` = `p`.`category_id`)) group by `p`.`id`,`c`.`slug`,`c`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `view_top_rated_products`
--

/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_top_rated_products` AS select `vpd`.`id` AS `id`,`vpd`.`name` AS `name`,`vpd`.`price_sale` AS `price_sale`,`vpd`.`price_original` AS `price_original`,`vpd`.`discount` AS `discount`,`vpd`.`gift` AS `gift`,`vpd`.`image` AS `image`,`vpd`.`stock` AS `stock`,`vpd`.`category_id` AS `category_id`,`vpd`.`description` AS `description`,`vpd`.`category` AS `category`,`vpd`.`category_name` AS `category_name`,`vpd`.`sold_count` AS `sold_count`,`vpd`.`wishlist_count` AS `wishlist_count`,coalesce(`rs`.`avg_rating`,0) AS `avg_rating`,coalesce(`rs`.`review_count`,0) AS `review_count` from (`view_product_details` `vpd` left join (select `reviews`.`product_id` AS `product_id`,avg(`reviews`.`rating`) AS `avg_rating`,count(0) AS `review_count` from `reviews` where `reviews`.`rating` between 1 and 5 group by `reviews`.`product_id`) `rs` on(`rs`.`product_id` = `vpd`.`id`)) where `rs`.`review_count` > 0 order by `rs`.`avg_rating` desc,`rs`.`review_count` desc,`vpd`.`id` desc limit 12 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-21 16:08:20
