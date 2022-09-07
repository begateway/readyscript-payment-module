<?php
/**
 * eComCharge (https://begateway.com)
 *
 * @copyright Copyright (c) eComCharge Ltd SIA (https://begateway.com)
 * @License: GPLv3
 * @License URI: http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace BeGateway\Model\PaymentType;
use \RS\Orm\Type;
use RS\Orm\FormObject;
use \Shop\Model\Orm\Transaction;
use Shop\Model\ChangeTransaction;
use Shop\Model\Exception as ShopException;
use RS\Exception as RSException;
use RS\Application\Application as Application;

require_once __DIR__ . '/../../include/begateway-api-php/lib/BeGateway.php';

/**
* Способ оплаты - BeGateway
*/
class BeGateway extends \Shop\Model\PaymentType\AbstractType
{

    /**
    * Возвращает название расчетного модуля (типа доставки)
    *
    * @return string
    */
    function getTitle()
    {
        return t('BeGateway');
    }

    /**
    * Возвращает описание типа оплаты. Возможен HTML
    *
    * @return string
    */
    function getDescription()
    {
        return t('Приём платежей через BeGateway');
    }

    /**
    * Возвращает идентификатор данного типа оплаты. (только англ. буквы)
    *
    * @return string
    */
    function getShortName()
    {
        return 'begateway';
    }

    /**
    * Отправка данных с помощью POST?
    *
    */
    function isPostQuery()
    {
        return true;
    }

    /**
    * Возвращает ORM объект для генерации формы или null
    *
    * @return \RS\Orm\FormObject | null
    */
    function getFormObject()
    {
        $properties = new \RS\Orm\PropertyIterator([
            'begateway_shop_id' => new Type\Varchar([
                'maxLength' => 255,
                'description' => t('Shop Id - идентификатор терминала'),
                'default' => '361'
            ]),
            'begateway_shop_key' => new Type\Varchar([
                'maxLength' => 255,
                'description' => t('Shop Key - секретный ключ терминала'),
                'default' => 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d'
            ]),
            'begateway_domain_checkout' => new Type\Varchar([
                'maxLength' => 255,
                'description' => t('Домен страницы оплаты'),
                'default' => 'checkout.begateway.com'
            ]),
            'begateway_transaction_type' => new Type\Varchar([
                 'description' => t('Тип операции'),
                 'listFromArray' => [
                    [
                      0 => t('Оплата'),
                      1 => t('Авторизация')
                    ]
                  ],
                 'default' => 0
            ]),
            'test_mode' => new Type\Integer([
              'maxLength' => 1,
              'description' => t('Включить тестовый режим'),
              'checkboxview' => [1, 0]
            ]),
            'enable_card' => new Type\Integer([
              'maxLength' => 1,
              'description' => t('Включить оплату банковскими картами'),
              'checkboxview' => [1, 0]
            ]),
            'enable_card_halva' => new Type\Integer([
              'maxLength' => 1,
              'description' => t('Включить оплату картой Халва'),
              'checkboxview' => [1, 0]
            ]),
            'enable_erip' => new Type\Integer([
              'maxLength' => 1,
              'description' => t('Включить оплату через ЕРИП'),
              'checkboxview' => [1, 0]
            ]),
            'erip_service_no' => new Type\Varchar([
                'maxLength' => 10,
                'description' => t('Код услуги ЕРИП'),
            ]),
        ]);

        $form_object = new FormObject($properties);
        $form_object->setParentObject($this);
        $form_object->setParentParamMethod('Form');
        return $form_object;
    }


    /**
    * Возвращает true, если данный тип поддерживает проведение платежа через интернет
    *
    * @return bool
    */
    function canOnlinePay()
    {
        return true;
    }

    /**
    * Возвращает URL для перехода на сайт сервиса оплаты
    *
    * @param Transaction $transaction - ORM объект транзакции
    * @return string
    */
    function getPayUrl(\Shop\Model\Orm\Transaction $transaction)
    {
  		$order = $transaction->getOrder();     // Данные о заказе
  		$user = $order->getUser();            // Пользователь который должен оплатить
      $site_config = \RS\Config\Loader::getSiteConfig(); // Настройки текущего сайта

      $token = new \BeGateway\GetPaymentToken();
      $token->money->setAmount($transaction->cost);
      $token->money->setCurrency($this->getPaymentCurrency());

      $token->setTrackingId($transaction->id . '|' . $order['order_num']);
      $token->setDescription(t('Оплата заказа № %0', [$order['order_num']]));
      $token->setLanguage(\RS\Language\Core::getCurrentLang());

      $router = \RS\Router\Manager::obj();

      $success_url = $router->getUrl('shop-front-onlinepay', [
              'Act' => 'success',
              'PaymentType' => $this->getShortName(),
              'transaction' => $transaction->id,
              'id_order' => $order['order_num']
      ], true);

      $fail_url = $router->getUrl('shop-front-onlinepay', [
              'Act' => 'fail', 
              'PaymentType' => $this->getShortName(),
              'transaction' => $transaction->id,
              'id_order' => $order['order_num']
      ], true);

      $notify_url = $router->getUrl('shop-front-onlinepay', [
              'Act' => 'result', 
              'PaymentType' => $this->getShortName(),
              'transaction' => $transaction->id,
      ], true);
      $notify_url = str_replace('readyscript.local','webhook.begateway.com:8443', $notify_url);

      $token->setNotificationUrl($notify_url);
      $token->setSuccessUrl($success_url);
      $token->setDeclineUrl($fail_url);
      $token->setFailUrl($fail_url);

      $token->customer->setEmail($user['e_mail']);

      if ($this->getOption('enable_card')) {
        $cc = new \BeGateway\PaymentMethod\CreditCard;
        $token->addPaymentMethod($cc);
      }

      if ($this->getOption('enable_card_halva')) {
        $halva = new \BeGateway\PaymentMethod\CreditCardHalva;
        $token->addPaymentMethod($halva);
      }

      if ($this->getOption('enable_erip')) {
        $order_id = $order['order_num'];
        $erip = new \BeGateway\PaymentMethod\Erip([
          'order_id' => $order_id,
          'account_number' => strval($order_id),
          'service_no' => $this->getOption('erip_service_no'),
        ]);
        $token->addPaymentMethod($erip);
      }

      if ($this->getOption('test_mode')) {
        $token->setTestMode(true);
      }

      if ($this->getOption('begateway_transaction_type')) {
        $token->setAuthorizationTransactionType();
      }

      \BeGateway\Settings::$shopId = $this->getOption('begateway_shop_id', '');
      \BeGateway\Settings::$shopKey = $this->getOption('begateway_shop_key', '');
      \BeGateway\Settings::$checkoutBase = 'https://' . $this->getOption('begateway_domain_checkout', '');

      $response = $token->submit();

  		if($response->isSuccess()){
  			return $response->getRedirectUrl();
  		} else {
  			return $router->getRootUrl() . 'begateway/error';
  		}
    }

    /**
    * Получает трех символьный код базовой валюты в которой ведётся оплата
    *
    */
    private function getPaymentCurrency()
    {
       /**
       * @var \Catalog\Model\Orm\Currency
       */
       $currency = \RS\Orm\Request::make()
                        ->from(new \Catalog\Model\Orm\Currency())
                        ->where([
                           'public'  => 1,
                           'is_base'  => 1,
                        ])
                        ->object();
       return $currency ? $currency->title : false;
    }
    /**
    * Обработка запросов от BeGateway
    *
    * @param \Shop\Model\Orm\Transaction $transaction - объект транзакции
    * @param \RS\Http\Request $request - объект запросов
    * @return string
    * @throws ResultException
    * @throws ShopException
    */
    // function onResult(\Shop\Model\Orm\Transaction $transaction, \RS\Http\Request $request)
    function onResult(\Shop\Model\Orm\Transaction $transaction, \RS\Http\Request $request)
    {
        \BeGateway\Settings::$shopId = $this->getOption('begateway_shop_id', '');
        \BeGateway\Settings::$shopKey = $this->getOption('begateway_shop_key', '');

        $webhook = new \BeGateway\Webhook;

        if (!$webhook->isAuthorized()) {
          $this->error_message('E006', t('Требуется авторизация'));
        }

        // Запрос авторизирован
        list($transaction_id, $order_num) = explode('|', $webhook->getTrackingId());
        
        // получаем данные заказа
        $order = $transaction->getOrder();
        if (empty($order)) {
            $this->error_message('E001', t('Заказ не найден'));
        }

        if ($transaction->id != $transaction_id) {
            $this->error_message('E002', t('Не верный номер операции'));
        }

        if ((string) $order['order_num'] != (string) $order_num) {
            $this->error_message('E003', t('Не верный номер заказа'));
        }

        $money = new \BeGateway\Money;
        $money->setAmount($transaction->cost);
        $money->setCurrency($this->getPaymentCurrency());

        // проверяем сумму оплаты
        if ($money->getCents() != $webhook->getResponse()->transaction->amount) {
          $this->error_message('E004', t('Не верная суммы оплаты'));
        }

        if (!$webhook->isSuccess()) {
            $this->error_message('E005', t('Не успешный статус оплаты'));
        }

        if (class_exists(ChangeTransaction)) {
            $change = new ChangeTransaction($transaction);
            $changelog = '';

            $change->setNewStatus(Transaction::STATUS_SUCCESS);

            if ($transaction['status'] == Transaction::STATUS_HOLD) {
                $changelog .= t('Холдирование %0 успешно завершено на сумму %1', [
                    $webhook->getUid(),
                    $money->getAmount()
                ]);
            } else {
                $changelog .= t('Платёж %0 успешно выполнен', [$webhook->getUid()]);
            }

            $change->setChangelog($changelog);
            $change->applyChanges();
        } else {
            $change = $this->changeTransactionStatusV5($transaction, $webhook);
        }

        return $change;
    }

    /**
     * Возвращает ID заказа исходя из REQUEST-параметров соотвествующего типа оплаты
     * Используется только для Online-платежей
     *
     * @return mixed
     */
    function getTransactionIdFromRequest(\RS\Http\Request $request) {
      if($request->request('transaction', TYPE_INTEGER, false)){
        return $request->request('transaction', TYPE_INTEGER, false);
      }
      return $request->request('id_order', TYPE_INTEGER, false);
    }

    /**
     * Вызывается при переходе на страницу успеха, после совершения платежа
     *
     * @return mixed
     */
    function onSuccess(\Shop\Model\Orm\Transaction $transaction, \RS\Http\Request $request) {
      $params = $this->getOption();
      // получаем заказ
      $order = $transaction->getOrder();
      if (empty($order)) {
        $this->error_message('E001', t('Заказ не найден'));
      }

      $router = \RS\Router\Manager::obj();
      $url = \RS\Http\Request::commonInstance();

      if ($order->is_payed == 0) {
        $redirect = $router->getUrl('begateway-front-onlinepayment', [
          'transaction' => $transaction->id
        ], true);
      } else {
        $redirect = $router->getUrl('shop-front-onlinepay', [
          'PaymentType' => $this->getShortName(),
          'Act' => 'status',
          'transaction' => $transaction['id']
        ], true);
      }

      Application::getInstance()->redirect($redirect);
    }

    /**
    * Вызывается при открытии страницы неуспешного проведения платежа
    * Используется только для Online-платежей
    *
    * @param \Shop\Model\Orm\Transaction $transaction
    * @param \RS\Http\Request $request
    * @return void
    */
    function onFail(\Shop\Model\Orm\Transaction $transaction, \RS\Http\Request $request)
    {
        $transaction['status'] = $transaction::STATUS_FAIL;
        $transaction->update();
    }

    /**
     * Возвращает сообщение об ошибке в ответ вебхука и останавливает работу скрипта
     * @param string $code код ошибки
     * @param string $message описание ошибки
     * @return ResultException
     */

     private function error_message($code, $message)
     {
        $message = sprintf("%s %s", $code, $message);
        throw new ResultException($message, 1);
     }

     /**
      * Обновляет статус транзакции для версий 5 и ниже
      * @param \Shop\Model\Orm\Transaction $transaction - объект транзакции
      * @param \BeGateway\Webhook $webhook объек вебхука с результатом операции
      * @return string
      * @throws ResultException
      * @throws ShopException
     */

     protected function changeTransactionStatusV5(\Shop\Model\Orm\Transaction $transaction, \BeGateway\Webhook $webhook)
     {
        // получаем данные заказа
        $order = $transaction->getOrder();
        if (empty($order)) {
            $this->error_message('E001', t('Заказ не найден'));
        }

        $transaction['status'] = \Shop\Model\Orm\Transaction::STATUS_SUCCESS;
        $transaction->update();
        return $webhook->getUid();
      }
}
