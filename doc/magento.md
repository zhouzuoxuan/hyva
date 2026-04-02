Normal
php bin/magento setup:install --base-url=https://lencarta.local/ --db-host=127.0.0.1 --db-name=prod --db-user=lencartalive --db-password='aZq@GU9xHVn5d52qr' --admin-firstname=zx --admin-lastname=z --admin-email=xuanphp@163.com --admin-user=zzx --admin-password='admin123' --language=en_GB --currency=USD --timezone=Europe/London --use-rewrites=1 --search-engine=elasticsearch8 --elasticsearch-host=127.0.0.1 --elasticsearch-port=9200 --elasticsearch-index-prefix=magento2 --elasticsearch-timeout=15

es8
php bin/magento setup:install --base-url=http://localhost --db-host=127.0.0.1 --db-name=preview --db-user=root --db-password='admin123' --admin-firstname=zx --admin-lastname=z --admin-email=xuanphp@163.com --admin-user=zzx --admin-password='Admin@123456' --language=en_US --currency=USD --timezone=America/Chicago --use-rewrites=1 --search-engine=elasticsearch8 --elasticsearch-host=127.0.0.1 --elasticsearch-port=9200 --elasticsearch-enable-auth=1 --elasticsearch-username=elastic --elasticsearch-password='0+aZqGU9xHVn5d52qor-' --elasticsearch-index-prefix=magento2 --elasticsearch-timeout=15

Elasticsuit
php bin/magento setup:install --base-url=http://3.222.51.157/ --db-host=127.0.0.1 --db-name=energy --db-user=energy --db-password='3wxceZ06?1P6uILH' --admin-firstname=zx --admin-lastname=z --admin-email=xuanphp@163.com --admin-user=zzx --admin-password='admin123' --language=en_US --currency=USD --timezone=America/New_York --use-rewrites=1 --search-engine=elasticsuite --elasticsearch-host=127.0.0.1 --elasticsearch-port=9200



config
bin/magento config:set web/secure/base_url https://preview.lencarta.com/
bin/magento config:set web/unsecure/base_url https://preview.lencarta.com/

nginx
client_max_body_size 20M;
