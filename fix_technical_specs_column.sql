-- Bỏ constraint json_valid khỏi cột technical_specs
-- Chạy SQL này trong phpMyAdmin hoặc MySQL command line

ALTER TABLE `products` 
MODIFY COLUMN `technical_specs` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;
