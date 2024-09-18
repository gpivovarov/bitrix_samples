<?php

namespace BSamples\ORM;

use Bitrix\Main\Entity\{DataManager, DatetimeField, IntegerField, TextField, AddResult};
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;

Loc::loadMessages(__FILE__);

/**
 * Class LogsTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> UF_TIMESTAMP datetime optional
 * <li> UF_USER int optional
 * <li> UF_BACKTRACE text optional
 * <li> UF_ORDER text optional
 * <li> UF_LABEL text optional
 * <li> UF_TEXT text optional
 * <li> UF_VARIABLES text optional
 * <li> UF_BUYER text optional
 * </ul>
 *
 * @package BSamples\ORM
 **/
class CustomLogTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'bs_custom_log';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMap()
    {
        return [
            'ID'           => new IntegerField(
                'ID',
                [
                    'primary'      => true,
                    'autocomplete' => true,
                    'title'        => Loc::getMessage('LOGS_ENTITY_ID_FIELD'),
                ]
            ),
            'UF_TIMESTAMP' => new DatetimeField(
                'UF_TIMESTAMP',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_TIMESTAMP_FIELD'),
                ]
            ),
            'UF_USER'      => new IntegerField(
                'UF_USER',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_USER_FIELD'),
                ]
            ),
            'UF_BACKTRACE' => new TextField(
                'UF_BACKTRACE',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_BACKTRACE_FIELD'),
                ]
            ),
            'UF_ORDER'     => new TextField(
                'UF_ORDER',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_ORDER_FIELD'),
                ]
            ),
            'UF_LABEL'     => new TextField(
                'UF_LABEL',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_LABEL_FIELD'),
                ]
            ),
            'UF_TEXT'      => new TextField(
                'UF_TEXT',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_TEXT_FIELD'),
                ]
            ),
            'UF_VARIABLES' => new TextField(
                'UF_VARIABLES',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_VARIABLES_FIELD'),
                ]
            ),
            'UF_BUYER'     => new TextField(
                'UF_BUYER',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_BUYER_FIELD'),
                ]
            ),
            'UF_DEAL'      => new TextField(
                'UF_DEAL',
                [
                    'title' => Loc::getMessage('LOGS_ENTITY_UF_DEAL_FIELD'),
                ]
            ),

        ];
    }

    /**
     * Вставка записи в таблицу с подготовкой полей
     * @param array $arParams
     * @return AddResult
     */
    public static function AddLog($arParams = [])
    {
        $result = new AddResult();
        $arFields = [
            'UF_TIMESTAMP' => new DateTime(),
            'UF_USER'      => self::getUserID(),
            'UF_BACKTRACE' => self::getBackTrace(),
            'UF_LABEL'     => $arParams['LABEL'],
            'UF_TEXT'      => $arParams['TEXT'],
            'UF_ORDER'     => $arParams['ORDER'],
            'UF_DEAL'      => $arParams['DEAL'],
            'UF_VARIABLES' => is_array($arParams['VARIABLES']) ? self::prepareVariables(
                $arParams['VARIABLES']
            ) : $arParams['VARIABLES']
        ];
        try {
            return self::add($arFields);
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }

        return $result;
    }

    /**
     * Получение внутреннего id пользователя
     * @return int
     */
    private static function getUserID()
    {
        global $USER;

        return ($USER instanceof \CUser) ? $USER->GetId() : 0;
    }

    /**
     * Получение стека вызовов
     * @param int $limit
     * @param null $options
     * @param int $skip
     * @return string
     */
    public static function getBackTrace($limit = 0, $options = null, $skip = 1)
    {
        $result = '';
        if ($options === null) {
            $options = ~DEBUG_BACKTRACE_PROVIDE_OBJECT;
        }
        $trace = array_slice(debug_backtrace($options, ($limit > 0 ? $limit + 1 : 0)), $skip);

        foreach ($trace as $arItem) {
            $result .= $arItem['class'].$arItem['type'].$arItem['function'].' '.$arItem['file'].':'.$arItem['line'].PHP_EOL;
        }

        return $result;
    }

    /**
     * Вернет данные в строчном виде для записи
     * @param $arItems
     * @return string|null
     */
    public static function prepareVariables($arItems)
    {
        return var_export($arItems, true);
    }
}