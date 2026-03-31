Export
mysqldump --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 \
-u root -p prod | gzip > prod2.sql.gz

Import
mysqldump -u root -p --triggers checkout > checkout.sql

gunzip -c prod2.sql.gz | mysql -u root -p'admin123' production

Create database
mysql -u root -p'aZq@GU9xHVn5d52qr' -e "CREATE DATABASE IF NOT EXISTS \`prod\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

FOREIGN
SET FOREIGN_KEY_CHECKS = 1;

Create User
CREATE USER 'lencartalive'@'localhost' IDENTIFIED BY 'aZq@GU9xHVn5d52qr';

GRANT ALL PRIVILEGES ON `prod`.* TO 'lencartalive'@'localhost';

FLUSH PRIVILEGES;
