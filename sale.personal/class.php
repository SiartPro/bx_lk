<?php
/**
 * Created by PhpStorm.
 * @author Karikh Dmitriy <demoriz@gmail.com>
 * @date 19.08.2020
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Sale\Order;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Basket;
use Bitrix\Main\Loader;
use Bitrix\Catalog\ProductTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Iblock\Component\Tools;
use Bitrix\Main\NotSupportedException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Sale\Internals\StatusLangTable;
use Bitrix\Main\ArgumentOutOfRangeException;

Loader::includeModule('sale');
Loader::includeModule('catalog');
Loader::includeModule('currency');


/**
 * Class CSiartPersonalArea
 */
class CSiartPersonalArea extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        if (empty($arParams['SEF_DEFAULT_TEMPLATE'])) {
            $arParams['SEF_DEFAULT_TEMPLATE'] = 'profile';
        }

        return $arParams;
    }

    public function executeComponent()
    {
        global $APPLICATION;

        // дефолтные страницы
        $arDefaultTemplatesUrls = array(
            'orders' => 'orders/',
            'profile' => 'profile/',
        );

        // получаем имя шаблона по url и настройкам
        $arTemplatesUrls = CComponentEngine::makeComponentUrlTemplates($arDefaultTemplatesUrls, $this->arParams['SEF_URL_TEMPLATES']);
        $arVariables = array();
        $strPage = CComponentEngine::ParseComponentPath($this->arParams['SEF_FOLDER'], $arTemplatesUrls, $arVariables);

        if (empty($strPage)) $strPage = $this->arParams['SEF_DEFAULT_TEMPLATE'];

        $this->arResult['ERROR'] = array();

        // ищем соответствующий шаблону метод, если нет то 404
        $strMethodName = $strPage . 'Action';
        if (method_exists($this, $strMethodName)) {
            $this->$strMethodName();

        } else {
            Tools::process404('', true, true, true);
        }

        // если ajax то вернём json
        if ($this->request->isAjaxRequest()) {
            $APPLICATION->RestartBuffer();
            header("Content-type:application/json");
            $this->arResult['STATUS'] = empty($this->arResult['ERROR']);
            echo json_encode($this->arResult);
            die();
        }

        $this->IncludeComponentTemplate($strPage);
    }

    /**
     * Метод обновляет данные пользователя
     *
     * @param $arFields
     */
    private function updateProfile($arFields)
    {
        global $USER;

        $user = new CUser;

        $isOk = $user->Update($USER->GetID(), $arFields);

        if ($isOk === false) {
            $this->arResult['ERROR'][] = $user->LAST_ERROR;
        }
    }

    /**
     * Обработчик страницы профиля
     */
    private function profileAction()
    {
        global $USER;

        // данные пользователя
        $dbUser = CUser::GetByID($USER->GetID());
        $arUser = $dbUser->Fetch();

        $this->arResult['FIELDS'] = array(
            'HIDDEN' => md5(__CLASS__),
            'TYPES' => array(
                'PERSONAL' => array(
                    'TYPE' => array(
                        'CODE' => 'type',
                        'VALUE' => 'personal'
                    ),
                    'FIRST_NAME' => array(
                        'CODE' => 'first_name',
                        'VALUE' => $arUser['NAME']
                    ),
                    'LAST_NAME' => array(
                        'CODE' => 'last_name',
                        'VALUE' => $arUser['LAST_NAME']
                    ),
                    'EMAIL' => array(
                        'CODE' => 'email',
                        'VALUE' => $arUser['EMAIL']
                    ),
                    'PHONE' => array(
                        'CODE' => 'phone',
                        'VALUE' => $arUser['PERSONAL_PHONE']
                    ),
                    'ADDRESS' => array(
                        'CODE' => 'address',
                        'VALUE' => $arUser['PERSONAL_STREET']
                    )
                ),
                'PASSWD' => array(
                    'TYPE' => array(
                        'CODE' => 'type',
                        'VALUE' => 'passwd'
                    ),
                    'CURRENT_PASSWORD' => array(
                        'CODE' => 'current_password'
                    ),
                    'NEW_PASSWORD' => array(
                        'CODE' => 'new_password'
                    ),
                    'CONFIRM_PASSWORD' => array(
                        'CODE' => 'confirm_password'
                    )
                ),
            )
        );

        $strMode = $this->request->get('mode');

        if ($strMode == $this->arResult['FIELDS']['HIDDEN'] && check_bitrix_sessid()) {
            if ($this->request->get($this->arResult['FIELDS']['TYPES']['PERSONAL']['TYPE']['CODE']) == $this->arResult['FIELDS']['TYPES']['PERSONAL']['TYPE']['VALUE']) {
                // смена полей
                $arFields = array(
                    'NAME' => $this->request->get($this->arResult['FIELDS']['TYPES']['PERSONAL']['FIRST_NAME']['CODE']),
                    'LAST_NAME' => $this->request->get($this->arResult['FIELDS']['TYPES']['PERSONAL']['LAST_NAME']['CODE']),
                    'LOGIN' => $this->request->get($this->arResult['FIELDS']['TYPES']['PERSONAL']['EMAIL']['CODE']),
                    'EMAIL' => $this->request->get($this->arResult['FIELDS']['TYPES']['PERSONAL']['EMAIL']['CODE']),
                    'PERSONAL_PHONE' => $this->request->get($this->arResult['FIELDS']['TYPES']['PERSONAL']['PHONE']['CODE']),
                    'PERSONAL_STREET' => $this->request->get($this->arResult['FIELDS']['TYPES']['PERSONAL']['ADDRESS']['CODE'])
                );
                $this->updateProfile($arFields);
            }

            if ($this->request->get($this->arResult['FIELDS']['TYPES']['PASSWD']['TYPE']['CODE']) == $this->arResult['FIELDS']['TYPES']['PASSWD']['TYPE']['VALUE']) {
                // смена пароля
                $arAuthResult = $USER->Login($arUser['LOGIN'], $this->request->get($this->arResult['FIELDS']['TYPES']['PASSWD']['CURRENT_PASSWORD']['CODE']));
                if ($arAuthResult['TYPE'] == 'ERROR') {
                    $this->arResult['ERROR'][] = 'Текущий пароль не верный!';
                }
                if (empty($this->arResult['ERROR'])) {
                    $arFields = array(
                        'PASSWORD' => $this->request->get($this->arResult['FIELDS']['TYPES']['PASSWD']['NEW_PASSWORD']['CODE']),
                        'CONFIRM_PASSWORD' => $this->request->get($this->arResult['FIELDS']['TYPES']['PASSWD']['CONFIRM_PASSWORD']['CODE'])
                    );
                    $this->updateProfile($arFields);
                }
            }
        }
    }

    /**
     * Обработчик страницы заказов
     *
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws NotImplementedException
     */
    private function ordersAction()
    {
        global $USER;

        $this->arResult['FIELDS'] = array(
            'HIDDEN' => md5(__CLASS__)
        );

        $strMode = $this->request->get('mode');

        if ($strMode == $this->arResult['FIELDS']['HIDDEN'] && check_bitrix_sessid()) {

            try {
                $order = Order::load($this->request->get('order_id'));
                $orderBasket = $order->getBasket();
                $currentBasket = Basket::loadItemsForFUser(Fuser::getId(), $this->getSiteId());
                // чистим корзину
                foreach ($currentBasket as $item) $item->delete();
                $currentBasket->save();
                // добавляем товар из заказа
                foreach ($orderBasket as $item) {
                    if (ProductTable::isExistProduct($item->getProductId())) {
                        $newItem = $currentBasket->createItem('catalog', $item->getProductId());
                        $newItem->setFields(array(
                            'QUANTITY' => $item->getQuantity(),
                            'CURRENCY' => $order->getCurrency(),
                            'LID' => $this->getSiteId(),
                            'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
                        ));
                    }
                }
                $currentBasket->save();

            } catch (ArgumentNullException $exception) {
                $this->arResult['ERROR'][] = $exception->getMessage();

            } catch (ArgumentOutOfRangeException $exception) {
                $this->arResult['ERROR'][] = $exception->getMessage();

            } catch (ArgumentTypeException $exception) {
                $this->arResult['ERROR'][] = $exception->getMessage();

            } catch (ArgumentException $exception) {
                $this->arResult['ERROR'][] = $exception->getMessage();

            } catch (NotImplementedException $exception) {
                $this->arResult['ERROR'][] = $exception->getMessage();

            } catch (NotSupportedException $exception) {
                $this->arResult['ERROR'][] = $exception->getMessage();

            } catch (ObjectNotFoundException $exception) {
                $this->arResult['ERROR'][] = $exception->getMessage();
            }

        } else {
            // статусы заказа
            $arStatus = array();
            $dbStatus = StatusLangTable::getList(array(
                'order' => array('STATUS.SORT' => 'ASC'),
                'filter' => array('STATUS.TYPE' => 'O', 'LID' => LANGUAGE_ID),
                'select' => array('STATUS_ID', 'NAME'),
            ));

            while ($arFields = $dbStatus->fetch()) {
                $arStatus[$arFields['STATUS_ID']] = $arFields['NAME'];
            }

            // заказы пользователя
            $dbOrders = Order::getList(array(
                'order' => array('ID' => 'ASC'),
                'filter' => array('USER_ID' => $USER->GetID()),
                'select' => array(
                    'ID',
                    'DATE_INSERT',
                    'PRICE',
                    'CURRENCY',
                    'STATUS_ID'
                )
            ));

            $arOrders = $dbOrders->fetchAll();

            // обрабатываем полученный список заказов
            $this->arResult['ITEMS'] = array();
            foreach ($arOrders as $arFields) {
                /** @var \Bitrix\Main\Type\DateTime $date */
                $date = $arFields['DATE_INSERT'];
                $arFields['DATE_INSERT'] = $date->format('d.m.Y');
                $arFields['PRICE'] = CCurrencyLang::CurrencyFormat($arFields['PRICE'], $arFields['CURRENCY']);
                $arFields['STATUS'] = $arStatus[$arFields['STATUS_ID']];

                // возможно ли повторить заказ
                $arFields['REPEAT'] = true;
                $order = Order::load($arFields['ID']);
                $basket = $order->getBasket();
                foreach ($basket as $item) {
                    if (!ProductTable::isExistProduct($item->getProductId())) {
                        $arFields['REPEAT'] = false;
                        break;
                    }
                }

                $this->arResult['ITEMS'][] = $arFields;
            }
        }
    }
}
