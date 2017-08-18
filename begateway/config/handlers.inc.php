<?php
namespace BeGateway\Config;
use \RS\Orm\Type as OrmType;

/**
* Класс предназначен для объявления событий, которые будет прослушивать данный модуль и обработчиков этих событий.
*/
class Handlers extends \RS\Event\HandlerAbstract
{
    function init()
    {
        $this
			->bind('getroute')
            ->bind('payment.gettypes');
    }

    /**
    * Добавляем новый вид оплаты - BeGateway
    *
    * @param array $list - массив уже существующих типов оплаты
    * @return array
    */
    public static function paymentGetTypes($list)
    {
        $list[] = new \BeGateway\Model\PaymentType\BeGateway(); // BeGateway
        return $list;
    }

    public static function getRoute(array $routes)
    {
        $routes[] = new \RS\Router\Route('begateway-front-error',
        array(
            '/begateway/error'
        ), null, t('Ошибка инициализации'));

        $routes[] = new \RS\Router\Route('begateway-front-onlinepayment',
        array('/begateway/waiting'), null, t('Страница с результатом платежа'));

        return $routes;
    }
}
