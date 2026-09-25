<?php
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/blocklanguages.php';
class TestBlockLanguages extends BlockLanguages
{
    public $context, $smarty;
    public function __construct()
    {
        $this->smarty = new FixtureSmarty();
        $this->context = (object)array('smarty' => $this->smarty, 'controller' => (object)array('php_self' => 'product', 'errors' => array()), 'shop' => (object)array('id' => 1));
        Context::$current = $this->context;
    }
    public function urls($controller, $params, $shop = 1)
    {
        $this->context->controller->php_self = $controller;
        $this->context->shop->id = $shop;
        Tools::$params = $params;
        $this->_prepareHook(array());
        return $this->smarty->values['lang_rewrite_urls'];
    }
    public function shops()
    {
        return $this->getLanguageShops();
    }
}
$module = new TestBlockLanguages();
foreach (array('product' => 'id_product', 'category' => 'id_category', 'cms' => 'id_cms') as $route => $parameter) {
    $first = $module->urls($route, array($parameter => 230));
    foreach (array(2, 3) as $shop) {
        expectSame($first, $module->urls($route, array($parameter => 230), $shop), $route . ' must produce reciprocal URLs independent of source shop');
    }
}
$urls = $module->urls('product', array('id_product' => 230));
expectSame('https://example.test/fr/category-54-2-2/product-230-2-2-230.html', $urls[2], 'Product and parent category must both use target shop');
FixtureObject::$unavailable['Product:230:2'] = 'inactive';
expectSame(false, $module->urls('product', array('id_product' => 230))[2], 'Inactive product excluded');
FixtureObject::$unavailable = array('CMS:51:2' => 'unassociated');
expectSame(false, $module->urls('cms', array('id_cms' => 51))[2], 'Shipping CMS without French shop association excluded');
FixtureObject::$missing['Category:54:2'] = true;
expectSame(false, $module->urls('category', array('id_category' => 54))[2], 'Missing target translation excluded');
expectSame(false, $module->urls('product', array('id_product' => 230))[2], 'Missing parent category translation excluded');
FixtureObject::$missing = array();
expectSame(false, $module->urls('product', array())[2], 'Missing product ID must not load a default object');
expectSame('https://example.test/fr/pages/cmscategory-5-2-2-5/', $module->urls('cms', array('id_cms_category' => 5))[2], 'CMS category uses destination shop');
foreach (array('best-sales' => 'bestsales', 'new-products' => 'newproducts') as $route => $dispatcher) {
    Dispatcher::$controller = $dispatcher;
    expectSame('https://example.test/fr/' . $route . '?p=9&n=20', $module->urls($route, array('p' => 9, 'n' => 20, 'utm_source' => 'audit'))[2], 'Canonical route with pagination, no tracking');
    expectSame('https://example.test/fr/' . $route, $module->urls($route, array('p' => 1, 'n' => 10))[2], 'Default pagination omitted');
}
expectSame('https://example.test/fr/category-54-2-2-54/?p=2', $module->urls('category', array('id_category' => 54, 'p' => 2))[2], 'Category pagination preserved');
Db::$productTotals[2] = 10;
$pageTwo = $module->urls('category', array('id_category' => 54, 'p' => 2));
expectSame(false, $pageTwo[2], 'Do not advertise a French page that redirects to page one');
expectSame('https://example.test/es/category-54-3-4-54/?p=2', $pageTwo[4], 'Keep the Spanish page that exists');
Db::$productTotals[2] = 11;
expectSame('https://example.test/fr/category-54-2-2-54/?p=2', $module->urls('category', array('id_category' => 54, 'p' => 2))[2], 'One product on page two is enough');
expectSame(false, $module->urls('category', array('id_category' => 54, 'p' => 2, 'n' => 20))[2], 'Respect accepted page sizes');
expectSame('https://example.test/fr/category-54-2-2-54/?p=2&n=7', $module->urls('category', array('id_category' => 54, 'p' => 2, 'n' => 7))[2], 'Invalid size falls back to default for page availability');
Db::$productTotals[2] = 0;
expectSame(false, $module->urls('category', array('id_category' => 54, 'p' => 2))[2], 'No alternate to an empty translated page two');
expectSame('https://example.test/fr/category-54-2-2-54/', $module->urls('category', array('id_category' => 54))[2], 'Page one behavior unchanged');
Db::$productTotals[2] = 21;
Configuration::$rewriting = 0;
expectSame($urls, $module->urls('product', array('id_product' => 230)), 'Availability and target selection do not depend on URL rewriting');
Db::$rows = array(array('id_lang' => 2, 'id_shop' => 1), array('id_lang' => 2, 'id_shop' => 2), array('id_lang' => 3, 'id_shop' => 1));
expectSame(array(2 => 1, 3 => 1), $module->shops(), 'Several languages per shop; stable first shop per language');
expectSame(false, $module->urls('category', array('id_category' => 54))[4], 'Unmapped language explicitly excluded');
$module->context->controller->errors = array('404');
expectSame(null, $module->hookDisplayHeader(array()), 'Error pages must not emit alternates');
$module->context->controller->errors = array();
$module->context->controller->php_self = 'pagenotfound';
expectSame(null, $module->hookDisplayHeader(array()), 'The 404 controller must not emit alternates even without controller errors');
echo "blocklanguages regression checks passed\n";
