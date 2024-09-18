<?php

namespace BSamples;

/**
 * Кастомный провайдер корзины
 * Используется при оформлении заказа через сайт и приложение
 * Нужен для того, чтобы не использовать флаг CUSTOM_PRICE при назначении магазинной цены для товара, т.к. замороженная цена не участвует в расчете скидок по правилам корзины
 */
class CustomCatalogProvider extends \Bitrix\Catalog\Product\CatalogProvider
{
    public function GetProductData($params)
    {
        $result = parent::GetProductData($params);
        $arResults = $result->getData();
        # логика обработки, выборки данных и смены цен
//        while ($arPrice = $rsPrices->fetch()) {
//          $arResults['PRODUCT_DATA_LIST'][$elementId]['PRICE_LIST'][$priceId]['BASE_PRICE'] = $arResults['PRODUCT_DATA_LIST'][$elementId]['PRICE_LIST'][$priceId]['PRICE'] = (float)$arPrice['PRICE'];
//        }

        $result->setData($arResults);

        return $result;
    }
}