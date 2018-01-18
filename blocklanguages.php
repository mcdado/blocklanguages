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
        $this->version = '1.5.1';
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

        $sql = 'SELECT l.`id_lang`, ls.`id_shop` FROM `'._DB_PREFIX_.'lang` as l
            JOIN `'._DB_PREFIX_.'lang_shop` as ls ON l.`id_lang` = ls.`id_lang`
            WHERE l.`active` = 1 GROUP BY ls.`id_shop` ORDER BY l.`id_lang`';

        $shop_for_language = array();
        $set = Db::getInstance()->executeS($sql);
        foreach ($set as $record) {
            $shop_for_lang[(string)$record['id_lang']] = (int)$record['id_shop'];
        }

        $controller = Dispatcher::getInstance()->getController();

        if ((int)Configuration::get('PS_REWRITING_SETTINGS')) {
            $default_rewrite = array();
            if ($controller == 'product' && ($id_product = (int)Tools::getValue('id_product'))) {
                $rewrite_infos = Product::getUrlRewriteInformations((int)$id_product);
                foreach ($rewrite_infos as $infos) {
                    $default_rewrite[$infos['id_lang']] = $link->getProductLink(
                        (int)$id_product,
                        $infos['link_rewrite'],
                        $infos['category_rewrite'],
                        $infos['ean13'],
                        (int)$infos['id_lang'],
                        $shop_for_lang[$infos['id_lang']]
                    );
                }
            } elseif ($controller == 'category' && ($id_category = (int)Tools::getValue('id_category'))) {
                $rewrite_infos = Category::getUrlRewriteInformations((int)$id_category);
                foreach ($rewrite_infos as $infos) {
                    $default_rewrite[$infos['id_lang']] = $link->getCategoryLink(
                        (int)$id_category,
                        $infos['link_rewrite'],
                        $infos['id_lang'],
                        null,
                        $shop_for_lang[$infos['id_lang']]
                    );
                }
            } elseif ($controller == 'cms' && (($id_cms = (int)Tools::getValue('id_cms')) || ($id_cms_category = (int)Tools::getValue('id_cms_category')))) {
                $rewrite_infos = (isset($id_cms) && !isset($id_cms_category)) ? CMS::getUrlRewriteInformations($id_cms) : CMSCategory::getUrlRewriteInformations($id_cms_category);
                foreach ($rewrite_infos as $infos) {
                    $arr_link = (isset($id_cms) && !isset($id_cms_category)) ?
                        $link->getCMSLink($id_cms, $infos['link_rewrite'], null, $infos['id_lang'], $shop_for_lang[$infos['id_lang']]) :
                        $link->getCMSCategoryLink($id_cms_category, $infos['link_rewrite'], $infos['id_lang'], $shop_for_lang[$infos['id_lang']]);
                    $default_rewrite[$infos['id_lang']] = $arr_link;
                }
            } else {
                foreach ($shop_for_lang as $id_lang => $id_shop) {
                    $default_rewrite[$id_lang] = $link->getPageLink($controller, null, $id_lang, null, false, $id_shop);
                }

            }
            $this->smarty->assign('lang_rewrite_urls', $default_rewrite);
        }

        $this->context->smarty->assign('shop_languages', $languages);

        return true;
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
        $this->context->controller->addCSS($this->_path . 'views/css/blocklanguages.css', 'all');
    }
}
