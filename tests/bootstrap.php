<?php

/** Minimal fixtures; URL generation uses the real PrestaShop 1.6 Link class. */
define('_PS_VERSION_', '1.6.1.24');
define('_DB_PREFIX_', 'ps_');
class Module {}
class Language
{
    public static function getLanguages($active)
    {
        return array(array('id_lang' => 3, 'iso_code' => 'it'), array('id_lang' => 2, 'iso_code' => 'fr'), array('id_lang' => 4, 'iso_code' => 'es'));
    }
}
class Db
{
    public static $productTotals = array(1 => 21, 2 => 21, 3 => 21);
    public static $rows = array(array('id_lang' => 2, 'id_shop' => 2), array('id_lang' => 3, 'id_shop' => 1), array('id_lang' => 4, 'id_shop' => 3));
    public static function getInstance()
    {
        return new self();
    }
    public function executeS($sql)
    {
        return self::$rows;
    }
    public function getValue($sql)
    {
        if (!preg_match('/ps\.`id_shop` = (\d+)/', $sql, $match)) {
            throw new RuntimeException('Product count requires an explicit target shop');
        }
        return self::$productTotals[(int)$match[1]];
    }
}
class Tools
{
    public static $params = array();
    public static function getValue($key)
    {
        return isset(self::$params[$key]) ? self::$params[$key] : false;
    }
    public static function str2url($value)
    {
        return $value;
    }
    public static function strReplaceFirst($search, $replace, $value)
    {
        return preg_replace('/' . preg_quote($search, '/') . '/', $replace, $value, 1);
    }
    public static function url($url, $query)
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
    }
}
class Configuration
{
    public static $rewriting = 1;
    public static function get($name)
    {
        return $name === 'PS_PRODUCTS_PER_PAGE' ? 10 : self::$rewriting;
    }
}
class Validate
{
    public static function isLoadedObject($object)
    {
        return !empty($object->id);
    }
}
class Context
{
    public static $current;
    public static function getContext()
    {
        return self::$current;
    }
}
class FixtureObject
{
    public static $unavailable = array();
    public static $missing = array();
    public $id, $shop, $link_rewrite, $active = 1, $meta_keywords = '', $meta_title = '';
    public function __construct($id, $lang, $shop)
    {
        $this->id = $id;
        $this->shop = $shop;
        $key = get_class($this) . ':' . $id . ':' . $shop;
        $this->link_rewrite = isset(self::$missing[$key]) ? '' : strtolower(get_class($this)) . '-' . $id . '-' . $shop . '-' . $lang;
        $this->active = !isset(self::$unavailable[$key]) || self::$unavailable[$key] !== 'inactive';
    }
    public function isAssociatedToShop($shop)
    {
        return !isset(self::$unavailable[get_class($this) . ':' . $this->id . ':' . $shop]);
    }
    public function getFieldByLang($field)
    {
        return $this->$field;
    }
}
class Category extends FixtureObject {}
class CMS extends FixtureObject {}
class CMSCategory extends FixtureObject {}
class Product extends FixtureObject
{
    public $category, $id_category_default = 54, $ean13 = '';
    public function __construct($id, $full, $lang, $shop)
    {
        parent::__construct($id, $lang, $shop);
        // Reproduce the core's source-shop category lookup, even with a target shop.
        $this->category = 'stale-category-from-shop-' . Context::$current->shop->id;
    }
}
class Dispatcher
{
    public static $controller = 'product';
    public static function getInstance()
    {
        return new self();
    }
    public function getController()
    {
        return self::$controller;
    }
    public function hasKeyword($route, $lang, $keyword, $shop)
    {
        return $route === 'product_rule' && $keyword === 'category';
    }
    public function createUrl($route, $lang, $params, $force = false, $anchor = '', $shop = null)
    {
        if ($route === 'product_rule') {
            return $params['category'] . '/' . $params['rewrite'] . '-' . $params['id'] . '.html';
        }
        if ($route === 'category_rule') {
            return $params['rewrite'] . '-' . $params['id'] . '/';
        }
        if ($route === 'cms_rule') {
            return 'pages/' . $params['rewrite'] . '-' . $params['id'] . '.html';
        }
        if ($route === 'cms_category_rule') {
            return 'pages/' . $params['rewrite'] . '-' . $params['id'] . '/';
        }
        if ($route === 'bestsales' || $route === 'newproducts') {
            return 'index.php?controller=' . $route;
        }
        return $route;
    }
}
$root = isset($argv[1]) ? $argv[1] : getenv('PRESTASHOP_ROOT');
if (!$root || !is_file($root . '/classes/Link.php')) {
    throw new RuntimeException('Pass the PrestaShop 1.6 root as the first argument.');
}
require $root . '/classes/Link.php';
class Link extends LinkCore
{
    public function __construct()
    {
        $this->allow = 1;
    }
    public function getBaseLink($id_shop = null, $ssl = null, $relative_protocol = false)
    {
        return 'https://example.test/' . array(1 => '', 2 => 'fr/', 3 => 'es/')[$id_shop];
    }
    public function getLangLink($id_lang = null, Context $context = null, $id_shop = null)
    {
        return '';
    }
}
class FixtureSmarty
{
    public $values = array();
    public function assign($key, $value)
    {
        $this->values[$key] = $value;
    }
}
function expectSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}
