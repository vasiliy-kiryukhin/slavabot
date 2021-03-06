<?php

/**
 * Created by PhpStorm.
 * User: lexgorbachev
 * Date: 31.01.2018
 * Time: 9:31
 */

namespace frontend\controllers;

use frontend\controllers\bot\libs\Logger;
use kroshilin\yakassa\widgets\Payment;
use YandexCheckout\Request\Payments\Payment\CreateCaptureResponse;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;


/**
 * Site controller
 */
class PaymentController extends Controller
{

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'index' => ['post'],
                    'info' => ['post'],
                ],
            ],
            [
                'class' => \yii\filters\ContentNegotiator::className(),
                'only' => ['index','info'],
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        if (in_array($action->id, ['info'])) {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $orderId = Yii::$app->request->post('order');
        $order =  \common\models\billing\Payment::findOne($orderId);
        if (!$order) {
            throw new NotFoundHttpException();
        }
        $payment = Yii::$app->yakassa->createPayment($order->getTotalPrice(), $order->getId());
        return [
            'redirectUrl' => $payment->getConfirmationUrl()
        ];
    }

    /**
     * В случае успешной оплаты отображаем модальное окно с сообщением.
     * В случае отсутсвия оплаты редиректим на тарифы
     *
     * @return $this
     */
    
    public function actionSuccess()
    {
        $order =  \common\models\billing\Payment::find()->where((['user_id' => \Yii::$app->user->identity->id]))->orderBy('id DESC')->one();

        if($order->active == 1){
            return Yii::$app->response->redirect('/#/payment/success');
        }else{
            return Yii::$app->response->redirect('/#/tariffs');
        }


    }

    public function actionInfo()
    {

        $request = Yii::$app->request->getRawBody();
        $info = Json::decode($request, false);
        $orderId = $info->object->metadata->orderId;
        /** @var CreateCaptureResponse $capture */
        $capture = Yii::$app->yakassa->capturePayment($info);
        if ($capture->getStatus() === 'succeeded') {
            $order =  \common\models\billing\Payment::findOne($orderId);
            $order->setActivePayment();
        }
    }

}