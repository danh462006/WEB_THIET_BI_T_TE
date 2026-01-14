-- Cập nhật bảng users để thêm cột Avatar và Address
ALTER TABLE `users` 
ADD COLUMN `Avatar` VARCHAR(255) NULL DEFAULT 'hinh-anh/hinh-tk-macdinh.png' AFTER `Position`,
ADD COLUMN `Address` TEXT NULL AFTER `Avatar`;

-- Tạo thư mục avatars trong hinh-anh (thực hiện trực tiếp trên server)
