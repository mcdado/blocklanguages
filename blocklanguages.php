<?php
/*
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2016 PrestaShop SA
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class BlockLanguages extends Module
{
    public function __construct()
    {
        $this->name = 'blocklanguages';
        $this->tab = 'front_office_features';
        $this->version = '11.6.2';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Language selector block');
        $this->description = $this->l('Adds a block allowing customers to select a language for your store\'s content.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99.99');
    }

    public function install()
    {
        return (parent::install() && $this->registerHook('displayNav') && $this->registerHook('displayHeader'));
    }

    protected function _prepareHook($params)
    {
        $languages = Language::getLanguages(true);
        if (!count($languages)) {
            return false;
        }

        $link = new Link();
        $shop_for_lang = $this->getLanguageShops();
        // Dispatcher returns class names such as "bestsales"; URL routes use
        // the controller's php_self ("best-sales", "new-products", etc.).
        $controller = !empty($this->context->controller->php_self)
            ? $this->context->controller->php_self
            : Dispatcher::getInstance()->getController();

        $lang_urls = array();
        foreach ($languages as $language) {
            $id_lang = (int)$language['id_lang'];
            // Explicit false prevents templates from inventing an alternate
            // in the current shop when the destination is unavailable.
            $lang_urls[$id_lang] = false;
            if (isset($shop_for_lang[$id_lang])) {
                $lang_urls[$id_lang] = $this->getAlternateUrl(
                    $link,
                    $controller,
                    $id_lang,
                    $shop_for_lang[$id_lang]
                );
            }
        }

        $this->smarty->assign('lang_rewrite_urls', $lang_urls);
        $this->context->smarty->assign('shop_languages', $languages);

        return true;
    }

    /**
     * Choose a stable destination per language, independent of the source shop.
     * Do not GROUP BY shop: a shop may support more than one language.
     *
     * @return array Language ID => shop ID
     */
    protected function getLanguageShops()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT l.`id_lang`, ls.`id_shop` FROM `' . _DB_PREFIX_ . 'lang` l
            JOIN `' . _DB_PREFIX_ . 'lang_shop` ls ON ls.`id_lang` = l.`id_lang`
            JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = ls.`id_shop`
            WHERE l.`active` = 1 AND s.`active` = 1 AND s.`deleted` = 0
            ORDER BY l.`id_lang`, ls.`id_shop`'
        );
        $shops = array();
        foreach ($rows as $row) {
            if (!isset($shops[(int)$row['id_lang']])) {
                $shops[(int)$row['id_lang']] = (int)$row['id_shop'];
            }
        }
        return $shops;
    }

    /**
     * Load localized objects in the destination shop, not the current context.
     * The same IDs connect translations; absent/inactive versions stay absent.
     *
     * @param Link $link
     * @param string $controller Canonical route name
     * @param int $id_lang
     * @param int $id_shop
     * @return string|false
     */
    protected function getAlternateUrl($link, $controller, $id_lang, $id_shop)
    {
        switch ($controller) {
            case 'product':
                $id = (int)Tools::getValue('id_product');
                if (!$id) {
                    return false;
                }
                $product = new Product($id, false, $id_lang, $id_shop);
                if (!$this->isAvailable($product, $id_shop)) {
                    return false;
                }
                // Product::__construct() resolves category via the current shop,
                // and Link prefers product->category over its category argument.
                $category = new Category((int)$product->id_category_default, $id_lang, $id_shop);
                if (!Validate::isLoadedObject($category) || empty($category->link_rewrite)) {
                    return false;
                }
                $product->category = $category->link_rewrite;
                return $link->getProductLink($product, null, null, null, $id_lang, $id_shop);

            case 'category':
                $id = (int)Tools::getValue('id_category');
                if (!$id) {
                    return false;
                }
                $category = new Category($id, $id_lang, $id_shop);
                if (!$this->isAvailable($category, $id_shop)) {
                    return false;
                }
                if (!$this->categoryPageExists($id, $id_shop)) {
                    return false;
                }
                return $this->addPaginationParameters(
                    $link->getCategoryLink($category, null, $id_lang, null, $id_shop)
                );

            case 'cms':
                $id_category = (int)Tools::getValue('id_cms_category');
                $id = $id_category ? $id_category : (int)Tools::getValue('id_cms');
                if (!$id) {
                    return false;
                }
                $cms = $id_category ? new CMSCategory($id, $id_lang, $id_shop) : new CMS($id, $id_lang, $id_shop);
                if (!$this->isAvailable($cms, $id_shop)) {
                    return false;
                }
                return $id_category
                    ? $link->getCMSCategoryLink($cms, null, $id_lang, $id_shop)
                    : $link->getCMSLink($cms, null, null, $id_lang, $id_shop);

            default:
                $url = $link->getPageLink($controller, null, $id_lang, null, false, $id_shop);
                return in_array($controller, array('best-sales', 'new-products', 'prices-drop'))
                    ? $this->addPaginationParameters($url) : $url;
        }
    }

    /**
     * @param ObjectModel $object
     * @param int $id_shop
     * @return bool
     */
    protected function isAvailable($object, $id_shop)
    {
        return Validate::isLoadedObject($object) && $object->active
            && $object->isAssociatedToShop($id_shop) && !empty($object->link_rewrite);
    }

    /**
     * A translated category may have fewer catalog products than the source.
     * Count in the target shop explicitly, without changing the global context.
     */
    protected function categoryPageExists($id_category, $id_shop)
    {
        $page = (int)Tools::getValue('p');
        if ($page < 2) {
            return true;
        }

        $total = Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category_product` cp
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = cp.`id_product`
            WHERE cp.`id_category` = ' . (int)$id_category . '
            AND ps.`id_shop` = ' . (int)$id_shop . '
            AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog")'
        );
        if ($total === false) {
            return false;
        }

        // Match FrontController::pagination for a fresh visit to the target URL.
        $default_size = max(1, (int)Configuration::get('PS_PRODUCTS_PER_PAGE', null, null, $id_shop));
        $size = (int)Tools::getValue('n');
        if (!in_array($size, array($default_size, $default_size * 2, $default_size * 5, (int)$total)) || $size < 1) {
            $size = $default_size;
        }
        return (int)$total > ($page - 1) * $size;
    }

    /**
     * Keep listing alternates on the requested page, without tracking parameters.
     * Match bnseourls' normalization of the current page and page size.
     *
     * @param string $url
     * @return string
     */
    protected function addPaginationParameters($url)
    {
        $page = (int)Tools::getValue('p');
        if ($page > 1) {
            $url = Tools::url($url, 'p=' . $page);
        }
        $size = (int)Tools::getValue('n');
        $default_size = max(1, (int)Configuration::get('PS_PRODUCTS_PER_PAGE'));
        if ($size >= 1 && $size !== $default_size) {
            $url = Tools::url($url, 'n=' . $size);
        }
        return $url;
    }

    /**
     * Returns module content for header
     *
     * @param array $params Parameters
     * @return string Content
     */
    public function hookDisplayTop($params)
    {
        if (!$this->_prepareHook($params)) {
            return;
        }
        return $this->display(__FILE__, 'blocklanguages.tpl');
    }

    public function hookDisplayNav($params)
    {
        return $this->hookDisplayTop($params);
    }

    public function hookDisplayHeader($params)
    {
        // PageNotFoundController returns HTTP 404 without populating errors.
        if (
            $this->context->controller->php_self === 'pagenotfound'
            || !empty($this->context->controller->errors)
        ) {
            return;
        }
        $this->context->controller->addCSS($this->_path . 'views/css/blocklanguages.css', 'all');
        if (!$this->_prepareHook($params)) {
            return;
        }
        return $this->display(__FILE__, 'header.tpl');
    }
}
