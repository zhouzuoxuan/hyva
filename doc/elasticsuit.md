composer require smile/elasticsuite:2.11.18.1 --with-all-dependencies
composer require hyva-themes/magento2-smile-elasticsuite:~1.2 --with-all-dependencies

bin/magento module:enable \
Smile_ElasticsuiteCore \
Smile_ElasticsuiteCatalog \
Smile_ElasticsuiteCatalogGraphQl \
Smile_ElasticsuiteSwatches \
Smile_ElasticsuiteCatalogRule \
Smile_ElasticsuiteVirtualCategory \
Smile_ElasticsuiteThesaurus \
Smile_ElasticsuiteCatalogOptimizer \
Smile_ElasticsuiteTracker \
Smile_ElasticsuiteAnalytics \
Smile_ElasticsuiteAdminNotification \
Smile_ElasticsuiteIndices \
Hyva_SmileElasticsuite

bin/magento config:set -le smile_elasticsuite_core_base_settings/es_client/servers 127.0.0.1:9200
bin/magento config:set -le smile_elasticsuite_core_base_settings/es_client/enable_https_mode 0
bin/magento config:set -le smile_elasticsuite_core_base_settings/es_client/enable_http_auth 0
bin/magento config:set -le smile_elasticsuite_core_base_settings/es_client/http_auth_user ""
bin/magento config:set -le smile_elasticsuite_core_base_settings/es_client/http_auth_pwd ""
bin/magento config:set -le catalog/search/engine elasticsuite
bin/magento app:config:import

bin/magento setup:upgrade
bin/magento indexer:reindex
bin/magento cache:flush
bin/magento setup:di:compile
