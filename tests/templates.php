<?php

/** Render the real Smarty templates, including Cardfacile's theme overrides. */
$root = $argv[1];
require $root . '/tools/smarty/Smarty.class.php';
$smarty = new Smarty();
$smarty->setCompileDir(sys_get_temp_dir());
$smarty->assign('shop_languages', array(array('id_lang' => 3, 'iso_code' => 'it', 'name' => 'Italiano'), array('id_lang' => 2, 'iso_code' => 'fr', 'name' => 'Français'), array('id_lang' => 4, 'iso_code' => 'es', 'name' => 'Español')));
$smarty->assign('lang_rewrite_urls', array(3 => 'https://example.test/list?p=9&n=20', 2 => false, 4 => 'https://example.test/es/list?p=9&n=20'));
$smarty->assign('lang_iso', 'it');
$smarty->assign('img_lang_dir', '/img/l/');
foreach (array(dirname(__DIR__) . '/views/templates/hook/', $root . '/themes/cardfacile/modules/blocklanguages/') as $dir) {
    $header = $smarty->fetch($dir . 'header.tpl');
    if (substr_count($header, '<link ') !== 2 || strpos($header, 'hreflang="fr"') !== false || strpos($header, '?p=9&amp;n=20') === false) {
        throw new RuntimeException('Invalid header output: ' . $header);
    }
    $smarty->assign('lang_rewrite_urls', array(3 => 'https://example.test/'));
    $partial = $smarty->fetch($dir . 'header.tpl');
    if (substr_count($partial, '<link ') !== 1) {
        throw new RuntimeException('Missing map entries must not fall back to invented URLs');
    }
    $smarty->assign('lang_rewrite_urls', array(3 => 'https://example.test/list?p=9&n=20', 2 => false, 4 => 'https://example.test/es/list?p=9&n=20'));
    $nav = $smarty->fetch($dir . 'blocklanguages.tpl');
    if (strpos($nav, 'href=""') !== false || strpos($nav, 'hreflang="fr"') !== false || strpos($nav, 'hreflang="es"') === false) {
        throw new RuntimeException('Unavailable navigation links must be omitted');
    }
}
echo "module and Cardfacile Smarty template checks passed\n";
