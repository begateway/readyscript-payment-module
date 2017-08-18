<?php
namespace BeGateway\Controller\Front;

/**
* Фронт контроллер, выводит список магазинов сети постранично.
*/
class error extends \RS\Controller\Front
{
    function actionIndex()
    {
        return $this->result->setTemplate('form/payment/begateway/error.tpl');
    }
}
