<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Contract\Controllerable;

use Aero\Main\Iblock\Shops;
use Aero\Main\Sale\Product;
use Aero\Main\Util;
use Bitrix\Main;
use Bitrix\Main\Localization\Loc as Loc;
use Bitrix\Sale;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Internals\DiscountCouponTable;
use Bitrix\Sale\Order;

class Oneclick extends CBitrixComponent implements Controllerable
{
    function getUserData()
    {
        global $USER;
        $userData = [
            'id' => null,
            'name' => '',
            'email' => ''
        ];
        if ($USER->IsAuthorized()) {
            $userData['name'] = $USER->GetFullName();
            $userData['email'] = $USER->GetEmail();
            $userData['id'] = $USER->GetID();
        }

        return $userData;
    }

    public function executeComponent()
    {
        $this->arResult = [
            'userData' => $this->getUserData()
        ];

        $this->includeComponentTemplate();
    }

    public function configureActions()
    {
        return [
            'ajaxRequest' => [
                'prefilters'  => [
                    new ActionFilter\HttpMethod(
                        array(ActionFilter\HttpMethod::METHOD_GET, ActionFilter\HttpMethod::METHOD_POST)
                    ),
                ],
                'postfilters' => [],
            ],
        ];
    }

    public function ajaxRequestAction($param = '')
    {
        $result = [
            'isSuccess' => false,
            'msg' => 'Нет контроллера для обработки запроса',
        ];
        if (!empty($param)) {
            if ($param['add']) {
                try {
                    $arOrder = self::createOrder(
                        '',
                        '',
                        '',
                        $param['prodId'],
                        1,
                        htmlspecialchars($param['comment']),
                        ''
                    );

                    $result = [
                        'isSuccess' => $arOrder['ID'] > 0,
                        'order' => $arOrder,
                        'msg' => 'Заказ успешно создан',
                    ];
                } catch (Exception $e) {
                    throw new Bitrix\Main\SystemException($e->getMessage());
                }
            }

        } else {
            throw new Bitrix\Main\ArgumentNullException('Empty params');
        }

        return $result;
    }

    protected static function createOrder(
        $userName,
        $userEmail,
        $userPhone,
        $productID,
        $quantity = 1,
        $comment = ''
    ) {
        if (!is_numeric($productID) || !is_numeric($quantity) && $quantity <= 0) {
            return false;
        }

        global $USER;

        $fromBasket = ($productID == 1);
        $arProducts = [];

        if (!(\Bitrix\Main\Loader::IncludeModule('sale') && \Bitrix\Main\Loader::IncludeModule('iblock'))) {
            return false;
        }

        $siteId = \Bitrix\Main\Context::getCurrent()->getSite();

        $fio = htmlspecialcharsbx(trim($userName));
        $phone = htmlspecialcharsbx(trim($userPhone));
        $email = htmlspecialcharsbx(trim($userEmail));
        $comment = htmlspecialcharsbx(trim($comment));

        $currencyCode = \Bitrix\Main\Config\Option::get('sale', 'default_currency', 'RUB');

        \Bitrix\Sale\DiscountCouponsManager::init();

        if (!$fromBasket) {
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
                \Bitrix\Sale\Fuser::getIdByUserId($USER->GetID()),
                $siteId
            );
            if (!$basket->isEmpty()) {
                foreach ($basket->getOrderableItems() as $basketItem) {
                    if ($basketItem->getProductId() == $productID) {
                        $quantity = $basketItem->getQuantity();
                        break;
                    }
                }
                unset($basketItem);
            }
            unset($basket);

            $basket = \Bitrix\Sale\Basket::create($siteId);
        } else {
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
                \Bitrix\Sale\Fuser::getIdByUserId($USER->GetID()),
                $siteId
            )->getOrderableItems();

            foreach ($basket as $basketItem) {
                $arProducts[$basketItem->getProductId()] = $basketItem->getQuantity();
            }
            unset($basketItem);
        }

        $total = 0;

        $ids = $result = $productsToPush = [];

        $arFilter = ['IBLOCK_ID' => \IBLOCK_ID_FOR_CATALOG, 'ID' => $fromBasket ? array_keys($arProducts) : $productID];

        $fields = [
            'ID',
            'IBLOCK_ID',
            'CODE',
            'NAME',
            'XML_ID',
            'IBLOCK_SECTION_ID',
        ];

        $properties = [
            # набор свойств для передачи в аналитику
        ];

        $priceId = 4; # вычисляем id типа цены в зависимости от выбранного пользователем региона ...

        $obItems = \Bitrix\Iblock\ElementTable::getList(
            [
                'filter' => $arFilter,
                'select' => $fields,
            ]
        );

        while ($arItem = $obItems->fetch()) {
            $ids[] = $arItem['ID'];
            $result[$arItem['ID']] = $arItem;
            $result[$arItem['ID']]['PROPERTIES'] = [];
        }

        \CIBlockElement::GetPropertyValuesArray(
            $result,
            \IBLOCK_ID_FOR_CATALOG,
            ['ID' => $ids],
            ['CODE' => $properties],
            ['GET_RAW_DATA' => 'Y']
        );

        $bUserHaveCard = $userId = false;
        if ($USER->IsAuthorized()) {
            $userId = $USER->GetID();
            $arUser = \Bitrix\Main\UserTable::getRow([
                'filter' => ['ID' => $userId],
                'select' => ['ID', 'UF_CARD_NUM'],
            ]);
            $bUserHaveCard = $arUser && !empty($arUser['UF_CARD_NUM']);
        }

        if (!empty($result)) {

            foreach ($result as $id => $item) {

                $price = \CCatalogProduct::GetOptimalPrice($id);
                $priceByCard = false;
                if ($bUserHaveCard) {
                    $priceByCard = empty($item['PROPERTIES']['PRICE_BY_CARD']['VALUE']) ? false : (float)$item['PROPERTIES']['PRICE_BY_CARD']['VALUE'];
                }

                $product = [
                    'PRODUCT_ID' => $id,
                    'PRODUCT_XML_ID' => $item['XML_ID'],
                    'NAME' => $item['NAME'],
                    'PRICE' => $priceByCard ?: $item['RESULT_PRICE']['DISCOUNT_PRICE'],
                    'CURRENCY' => $currencyCode,
                    'QUANTITY' => $arProducts[$id] ?? $quantity,
                ];

                $total += ($arProducts[$id] ?? $quantity) * ($priceByCard ?: $price['RESULT_PRICE']['DISCOUNT_PRICE']);

                $productsToPush[] = [
                    'name' => $item['NAME'],
                    'id' => $id,
                    'price' => $priceByCard ?: $price['RESULT_PRICE']['DISCOUNT_PRICE'],
                    'quantity' => $arProducts[$id] ?? $quantity
                    # и др.
                ];

                if (!$fromBasket) {

                    $basketItem = $basket->createItem('catalog', $product['PRODUCT_ID']);
                    $basketItem->setFields([
                        'QUANTITY' => $product['QUANTITY'],
                        'CURRENCY' => $currencyCode,
                        'LID' => $siteId,
                        'PRODUCT_XML_ID' => $product['PRODUCT_XML_ID'],
                        'PRODUCT_PROVIDER_CLASS' => \BSamples\CustomCatalogProvider::class,
                        'CUSTOM_PRICE' => 'N',
                    ]);

                    if ($priceByCard && $basketItem->getPrice() > $priceByCard) {
                        $basketItem->setFields([
                            'CUSTOM_PRICE' => 'Y',
                            'PRICE' => $priceByCard,
                        ]);
                    }

                } elseif ($basket && $priceByCard) {

                    /** @var BasketItem $basketItem */
                    foreach ($basket as $basketItem) {
                        if ($basketItem->getProductId() != $id) {
                            continue;
                        }
                        $arSetFields = [];

                        if (empty($basketItem->getField('PRODUCT_PROVIDER_CLASS'))) {
                            $arSetFields['PRODUCT_PROVIDER_CLASS'] = \BSamples\CustomCatalogProvider::class;
                        }

                        if ($basketItem->getPrice() > $priceByCard) {
                            $arSetFields['CUSTOM_PRICE'] = 'Y';
                            $arSetFields['PRICE'] = $priceByCard;
                        }

                        if (!empty($arSetFields)) {
                            $basketItem->setFields($arSetFields);
                        }
                    }
                }
            }

            unset($result, $id, $item, $product);

            if ($userId) {
                $order = \Bitrix\Sale\Order::create($siteId, $userId);
            } else {
                $order = \Bitrix\Sale\Order::create($siteId, \BSamples\HelperClass::getUserId([
                    'name'  => $fio,
                    'phone' => $phone,
                ]));
            }

            $order->setPersonTypeId(1);

            $order->setField('USER_DESCRIPTION', $comment);

            $order->setBasket($basket);

            /*Shipment*/
            $shipmentCollection = $order->getShipmentCollection();
            $shipment = $shipmentCollection->createItem();
            $shipmentItemCollection = $shipment->getShipmentItemCollection();
            $shipment->setField('CURRENCY', $order->getCurrency());
            foreach ($order->getBasket() as $item) {
                $shipmentItem = $shipmentItemCollection->createItem($item);
                $shipmentItem->setQuantity($item->getQuantity());
            }
            $arDeliveryServiceAll = \Bitrix\Sale\Delivery\Services\Manager::getRestrictedObjectsList($shipment);
            $shipmentCollection = $shipment->getCollection();

            if (!empty($arDeliveryServiceAll)) {
                reset($arDeliveryServiceAll);
                $deliveryObj = current($arDeliveryServiceAll);

                if ($deliveryObj->isProfile()) {
                    $name = $deliveryObj->getNameWithParent();
                } else {
                    $name = $deliveryObj->getName();
                }

                $shipment->setFields(
                    array(
                        'DELIVERY_ID'   => $deliveryObj->getId(),
                        'DELIVERY_NAME' => $name,
                        'CURRENCY'      => $order->getCurrency(),
                    )
                );

                $shipmentCollection->calculateDelivery();
            }
            /**/

            /*Payment*/
            $arPaySystemServiceAll = [];
            $paySystemId = 1;
            $paymentCollection = $order->getPaymentCollection();

            $remainingSum = $order->getPrice() - $paymentCollection->getSum();
            if ($remainingSum > 0 || $order->getPrice() == 0) {
                $extPayment = $paymentCollection->createItem();
                $extPayment->setField('SUM', $remainingSum);
                $arPaySystemServices = \Bitrix\Sale\PaySystem\Manager::getListWithRestrictions($extPayment);

                $arPaySystemServiceAll += $arPaySystemServices;

                if (array_key_exists($paySystemId, $arPaySystemServiceAll)) {
                    $arPaySystem = $arPaySystemServiceAll[$paySystemId];
                } else {
                    reset($arPaySystemServiceAll);

                    $arPaySystem = current($arPaySystemServiceAll);
                }

                if (!empty($arPaySystem)) {
                    $extPayment->setFields(
                        array(
                            'PAY_SYSTEM_ID'   => $arPaySystem["ID"],
                            'PAY_SYSTEM_NAME' => $arPaySystem["NAME"],
                        )
                    );
                } else {
                    $extPayment->delete();
                }
            }

            $order->doFinalAction(true);

            # $propertyCollection = $order->getPropertyCollection();
            # установка всяких свойств заказа ...

            $order->save();

            $orderId = $order->GetId();

            if ($orderId > 0) {

                return [
                    'ID' => $orderId,
                    'TOTAL' => $total,
                    'PRODUCTS' => $productsToPush
                ];
            }
        }

        return false;
    }


}