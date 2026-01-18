#!/bin/bash
set -e

echo "=== Updating system ==="
sudo apt update -y

echo "=== Installing system packages ==="
sudo apt install -y \
  apache2 \
  mysql-server \
  php php-cli php-fpm php-mysql php-xml php-mbstring php-curl php-zip unzip curl git

echo "=== Enabling Apache rewrite ==="
sudo a2enmod rewrite
echo "ServerName localhost" | sudo tee /etc/apache2/conf-available/servername.conf
sudo a2enconf servername
sudo service apache2 restart

sudo mkdir -p /var/run/mysqld
sudo chown mysql:mysql /var/run/mysqld
sudo service mysql start

echo "=== Securing MySQL (default dev settings) ==="
sudo mysql -e "CREATE DATABASE wordpress;"
sudo mysql -e "CREATE USER 'wpuser'@'localhost' IDENTIFIED BY 'wppass';"
sudo mysql -e "GRANT ALL PRIVILEGES ON wordpress.* TO 'wpuser'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

echo "=== Installing WordPress ==="
cd /var/www/html
sudo rm -rf index.html
sudo curl -O https://wordpress.org/latest.tar.gz
sudo tar -xzf latest.tar.gz
sudo mv wordpress/* .
sudo rm -rf wordpress latest.tar.gz
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html

echo "=== Creating wp-config.php ==="
cp wp-config-sample.php wp-config.php
sed -i "s/database_name_here/wordpress/" wp-config.php
sed -i "s/username_here/wpuser/" wp-config.php
sed -i "s/password_here/wppass/" wp-config.php

echo "=== Installing WP-CLI ==="
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar --info
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

echo "=== Running WordPress installation ==="
wp core install \
  --url="http://localhost" \
  --title="SCF GraphQL Dev" \
  --admin_user="admin" \
  --admin_password="adminpass" \
  --admin_email="max.b@sparxstar.com" \
  --skip-email \
  --allow-root

echo "=== Installing Plugins ==="

# Secure Custom Fields (SCF)
wp plugin install https://github.com/securecustomfields/scf/archive/refs/heads/master.zip --activate --allow-root

# WPGraphQL
wp plugin install wp-graphql --activate --allow-root

# WPGraphQL for SCF (ACF-compatible fork)
wp plugin install https://github.com/wp-graphql/wp-graphql-acf/archive/refs/heads/main.zip --activate --allow-root

php -v
wp --info
apache2 -v
mysql --version
echo "MySQL user 'wpuser' with password 'wppass' has access to database 'wordpress'."
echo "Login: http://localhost/wp-admin"
echo "User: admin"
echo "Pass: adminpass"
echo "=== Done ==="

