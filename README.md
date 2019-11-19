# Nosto ITP Handling extension for Magento 2

- Note: This extension is experimental. Install only for development purposes.

## Installing

Require the extension with composer:
```bash
composer require --no-update nosto/module-nosto-itp && composer update --no-dev
```

Enable the extension with:
```bash
bin/magento module:enable --clear-static-content Nosto_Itp
bin/magento setup:upgrade
bin/magento cache:clean
bin/magento setup:di:compile
```
