-- ============================================================================
--  Campus Canteen — database schema + demo data
--  MariaDB / MySQL 5.7+  (developed on XAMPP, MariaDB 10.4)
--
--  Import:
--    1. Start Apache + MySQL in the XAMPP Control Panel
--    2. Open http://localhost/phpmyadmin
--    3. Create a database named `campus_canteen` (utf8mb4_general_ci)
--    4. Select it, open the "Import" tab and upload this file
--
--  Demo accounts (password for all: password123)
--    admin@canteen.com     -> admin
--    store1@canteen.com … store6@canteen.com -> staff of shop 1…6
--    student@example.com   -> student
--
--  Change these passwords before putting the project online.
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `rating` varchar(10) DEFAULT '4.5',
  `status` varchar(20) DEFAULT 'Open',
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 50,
  `status` varchar(20) DEFAULT 'Available',
  `price_regular` decimal(10,2) DEFAULT 0.00,
  `price_medium` decimal(10,2) DEFAULT 0.00,
  `price_large` decimal(10,2) DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT 'images/placeholder.jpg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_store` (`store_id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `store_id` int(11) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orders_store` (`store_id`,`status`),
  KEY `idx_orders_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `size` varchar(50) NOT NULL DEFAULT 'Standard',
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_menu` (`menu_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'student',
  `store_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token` (`token_hash`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `shops` VALUES (1,'Zyllexs Kan.anan','Street Food','Canteen Shop 1','4.5','Open','images/kan.anan.jpg'),(2,'Mo-Selog','Pares & Silog','Canteen Shop 2','4.7','Open','images/pares.jpg'),(3,'Karron, Uri Burger!','Burger Shop','Canteen Shop 3','4.6','Open','images/burger.jpg'),(4,'Xanderg Sipsip','Milktea','Canteen Shop 4','4.8','Open','images/milktea.jpg'),(5,'Jeos Ko \'To!','Fresh Shakes & Juices','Canteen Shop 5','4.4','Open','images/shake.jpg'),(6,'Pan de Cham','Bread & Donuts','Canteen Shop 6','4.3','Open','images/donuts.webp');
INSERT INTO `menu_items` VALUES (6,1,'Pork Siomai Rice','',45.00,50,'Available',45.00,0.00,0.00,'images/1792946143_1792749146_pork siomai rice.jpg','2026-11-04 16:10:50'),(7,1,'Beef Siomai Rice','',45.00,48,'Available',45.00,0.00,0.00,'images/1792946158_1792749160_beef siomai.webp','2026-11-04 16:10:50'),(8,1,'Baguio Beans','',25.00,49,'Available',25.00,0.00,0.00,'images/1792946175_1792749176_Baguio Beans.jpg','2026-11-04 16:10:50'),(9,1,'Pancit','',15.00,50,'Available',15.00,0.00,0.00,'images/1792946190_1792749468_pancit.jpg','2026-11-04 16:10:50'),(10,1,'Chop Suey','',30.00,50,'Available',30.00,0.00,0.00,'images/1792946199_1792749335_ChopSuey.jpg','2026-11-04 16:10:50'),(11,1,'Chicken Curry','',50.00,49,'Available',50.00,0.00,0.00,'images/1792946211_1792749274_ce.jpg','2026-11-04 16:10:50'),(12,1,'Chicken Buffalo','',60.00,48,'Available',60.00,0.00,0.00,'images/1792946222_1792749379_buffalo.jpg','2026-11-04 16:10:50'),(13,1,'Ginaling','',15.00,49,'Available',15.00,0.00,0.00,'images/1792946233_1792749212_ginaling.jpg','2026-11-04 16:10:50'),(14,1,'Bicol Express','',25.02,50,'Available',25.02,0.00,0.00,'images/1792944553_1792749189_bicol.webp','2026-11-04 16:10:50'),(16,2,'Topsilog','',65.00,49,'Available',65.00,0.00,0.00,'images/1793203485_1792750344_top.jpg','2026-11-04 16:10:50'),(17,2,'Spamsilog','',70.00,49,'Available',70.00,0.00,0.00,'images/1793203515_1792750077_spam.jpg','2026-11-04 16:10:50'),(18,2,'Lumpsilog','',55.00,50,'Available',55.00,0.00,0.00,'images/1793203528_1792750064_lump.jpg','2026-11-04 16:10:50'),(19,2,'Longsilog','',55.00,50,'Available',55.00,0.00,0.00,'images/1793203561_1792750118_long.jpg','2026-11-04 16:10:50'),(20,2,'Porksilog','',90.00,50,'Available',90.00,0.00,0.00,'images/1793203590_1792750189_pork.webp','2026-11-04 16:10:50'),(21,2,'Bacsilog','',80.00,50,'Available',80.00,0.00,0.00,'images/1793203603_1792750053_bac.jpg','2026-11-04 16:10:50'),(22,2,'Tocisilog','',75.00,50,'Available',75.00,0.00,0.00,'images/1793203617_1792750305_to.jpg','2026-11-04 16:10:50'),(23,3,'Classic Burger','',60.01,49,'Available',60.01,0.00,0.00,'images/1793203715_1792748950_1.jpg','2026-11-04 16:10:50'),(24,3,'Cheese Burger','',75.00,50,'Available',75.00,0.00,0.00,'images/1793203734_1792748962_2 (2).jpg','2026-11-04 16:10:50'),(25,3,'Classic Beef Burger','',80.00,50,'Available',80.00,0.00,0.00,'images/1793203746_1792748976_3.jpg','2026-11-04 16:10:50'),(26,3,'Chicken Burger','',95.00,50,'Available',95.00,0.00,0.00,'images/1793203762_1792748986_4.jpg','2026-11-04 16:10:50'),(27,3,'Egg Burger','',60.00,50,'Available',60.00,0.00,0.00,'images/1793203775_1792749037_8.webp','2026-11-04 16:10:50'),(28,3,'Spicy Burger','',70.00,50,'Available',70.00,0.00,0.00,'images/1793203793_1792749011_6.jpg','2026-11-04 16:10:50'),(29,4,'Matcha Milktea','',60.00,50,'Available',60.00,75.00,90.00,'images/1793203847_1792750790_23.jpeg','2026-11-04 16:10:50'),(30,4,'Cookis n Cream','',60.00,50,'Available',60.00,75.00,90.00,'images/1793203870_1792751826_sww.jpg','2026-11-04 16:10:50'),(31,4,'Caramel Milktea','',60.00,50,'Available',60.00,75.00,90.00,'images/1793203893_1792750946_ca.jpg','2026-11-04 16:10:50'),(32,4,'Okinawa Milktea','',60.00,50,'Available',60.00,75.00,90.00,'images/1793203909_1792751323_oki.jpg','2026-11-04 16:10:50'),(33,4,'Taro Milktea','',60.00,50,'Available',60.00,75.00,90.00,'images/1793203924_1792751244_tae.jpg','2026-11-04 16:10:50'),(34,4,'Wintermelon Milktea','',60.00,50,'Available',60.00,75.00,90.00,'images/1793203992_1792751496_tt.webp','2026-11-04 16:10:50'),(35,5,'Apple Juice','',20.00,50,'Available',20.00,30.00,40.00,'images/1793204058_1792752309_ju.webp','2026-11-04 16:10:50'),(36,5,'Orange Juice','',20.00,49,'Available',20.00,30.00,40.00,'images/1793204080_1792752360_r4.jpg','2026-11-04 16:10:50'),(37,5,'Lemon Juice','',20.00,50,'Available',20.00,30.00,40.00,'images/1793204109_1792752337_le.jpg','2026-11-04 16:10:50'),(38,5,'Lemon Juice','',20.00,50,'Available',20.00,30.00,40.00,'images/1793204138_1793204109_1792752337_le.jpg','2026-11-04 16:10:50'),(39,5,'Apple Shake','',55.00,50,'Available',55.00,75.00,85.00,'images/1793204160_1792752077_app.jpg','2026-11-04 16:10:50'),(40,5,'Banana Shake','',55.00,50,'Available',55.00,65.00,75.00,'images/1793204174_1792752127_ba.webp','2026-11-04 16:10:50'),(41,5,'Avocado Shake','',55.00,50,'Available',55.00,65.00,75.00,'images/1793204186_1792752098_av.png','2026-11-04 16:10:50'),(42,5,'Strawberry Banana Shake','',75.00,50,'Available',75.00,85.00,90.00,'images/1793204210_1792752215_st.jpg','2026-11-04 16:10:50'),(43,5,'mango','',55.00,50,'Available',55.00,65.00,75.00,'images/1793204225_1792752249_ff.webp','2026-11-04 16:10:50'),(44,6,'Croissant','',45.00,50,'Available',45.00,0.00,0.00,'images/1793204303_1792748475_a.jpg','2026-11-04 16:10:50'),(45,6,'Pretzel','',45.00,50,'Available',45.00,0.00,0.00,'images/1793204313_1792748535_c.jpg','2026-11-04 16:10:50'),(46,6,'Bagel','',45.00,50,'Available',45.00,0.00,0.00,'images/1793204356_1792748512_b.jpg','2026-11-04 16:10:50'),(47,6,'Strawberry Filled Donut','',25.00,50,'Available',25.00,0.00,0.00,'images/1793204380_1792748675_i.png','2026-11-04 16:10:50'),(48,6,'Vanilla Sprinkle Donut','',25.00,50,'Available',25.00,0.00,0.00,'images/1793204390_1792748651_h.jpg','2026-11-04 16:10:50'),(49,6,'Vanilla Marble Donut','',25.00,50,'Available',25.00,0.00,0.00,'images/1793204402_1792748607_d.jpg','2026-11-04 16:10:50');

-- ----------------------------------------------------------------------------
-- Users
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `store_id`) VALUES
(1, 'System Admin',          'admin@canteen.com',  '$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'admin',   NULL),
(2, 'Zylle''s Kainan Staff', 'store1@canteen.com', '$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'staff',   1),
(3, 'Mo-Selog Staff',        'store2@canteen.com', '$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'staff',   2),
(4, 'Karron Burger Staff',   'store3@canteen.com', '$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'staff',   3),
(5, 'Xanderg Sipsip Staff',  'store4@canteen.com', '$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'staff',   4),
(6, 'Jeos Ko Staff',         'store5@canteen.com', '$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'staff',   5),
(7, 'Pan de Cham Staff',     'store6@canteen.com', '$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'staff',   6),
(8, 'Demo Student',          'student@example.com','$2y$10$oLkpQWS9DuLnvP03F/xsgeLmcJcMi77eDeBW4ToMhfLszc17BB3B2', 'student', NULL);

-- ----------------------------------------------------------------------------
-- Reset AUTO_INCREMENT counters
-- ----------------------------------------------------------------------------
ALTER TABLE `shops`      AUTO_INCREMENT = 7;
ALTER TABLE `menu_items` AUTO_INCREMENT = 50;
ALTER TABLE `orders`     AUTO_INCREMENT = 1;
ALTER TABLE `order_items` AUTO_INCREMENT = 1;
ALTER TABLE `users`      AUTO_INCREMENT = 9;

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;