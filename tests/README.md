# Hreflang regression checks

Run with PHP 7.1 and a PrestaShop 1.6 checkout (no database or application bootstrap):

```sh
php tests/regression.php /path/to/cardfacile
php tests/templates.php /path/to/cardfacile
```

`regression.php` uses fixture objects and the checkout's real `LinkCore`. It covers destination-shop product/category/CMS URLs, reciprocal sets across source shops, the product constructor's source-shop category lookup, unavailable translations and shop associations, normalized listing routes, pagination, and error pages. The fixtures do not replace an end-to-end database test.

`templates.php` renders both the module templates and Cardfacile's theme overrides with the checkout's real Smarty. It checks that unavailable languages do not produce empty links and that query strings are escaped.

The module chooses the lowest active shop ID associated with each language, consistently across requests. Cardfacile has one shop per active language. Sites needing several regional destinations for the same language require a separate mapping policy.

Review/deployment notes:

- The tracked Cardfacile theme header overrides this module's header: include its corresponding change.
- Deploy the separate `bnseourls` change to align listing canonical URLs with pagination.
- No translations, product activation, or shop associations are changed by these patches.
- In the production audit, CMS 51 had French content but no French shop association. This remains excluded until its publication is explicitly decided.
- After deployment, recrawl the URLs from the Semrush report to verify final HTTP 200 targets, self references and reciprocal tags. The local tests do not deploy anything or verify a deployed release.
