<?php
namespace BeGateway\Controller\Front;

use \RS\Application\Auth as AppAuth;

/**
 * Контроллер для обработки Online-платежей
 */
class OnlinePayment extends \RS\Controller\Front {

  /**
   * Страница извещения об ожидании платежа
   *
   */
  function actionIndex() {
    $request = $this->url;
    $payment_type = 'begateway';
    $transactionApi = new \Shop\Model\TransactionApi();
    try {
      $transaction = new \Shop\Model\Orm\Transaction($request->get('transaction', TYPE_INTEGER));
      if (!$transaction->id) {
        throw new \RS\Exception(t("Транзакция с идентификатором %0 не найдена", array($transaction_id)));
      }
    }
    catch (\Exception $e) {
      return $e->getMessage();       // Вывод ошибки
    }
    $this->view->assign('transaction', $transaction);
    return $this->result->setTemplate('form/payment/begateway/success.tpl');
  }

}
?>
