{if isset($shop_languages) && count($shop_languages) > 1}
  {foreach $shop_languages as $language}
    {assign var=language_id value=$language.id_lang}
    {if isset($lang_rewrite_urls.$language_id)}
      <link rel="alternate" hreflang="{$language.iso_code|escape:'html':'UTF-8'}" href="{$lang_rewrite_urls.$language_id|escape:'html':'UTF-8'}">
    {else}
      <link rel="alternate" hreflang="{$language.iso_code|escape:'html':'UTF-8'}" href="{$link->getLanguageLink($language_id)|escape:'html':'UTF-8'}">
    {/if}
  {/foreach}
{/if}
