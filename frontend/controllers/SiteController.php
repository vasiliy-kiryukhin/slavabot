<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\UserConfig;
use frontend\models\PasswordConfig;
use common\models\Webhooks;
use yii\web\Response;
use yii\helpers\Url;
use common\models\billing\Payment;


/**
 * Site controller
 */
class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup', 'repassword', 'index', 'getdata', 'ig-callback'],
                'rules' => [
                    [
                        'actions' => ['signup', 'repassword', 'getdata', 'ig-callback'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['signup', 'logout', 'index', 'ig-callback'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['get'],
                    //'getdata' => ['post']
                ],
            ],
            [
                'class' => \yii\filters\ContentNegotiator::className(),
                'only' => ['main', 'user'],
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
        ];
    }
    public function beforeAction($action)
    {
        if ($action->id == 'getdata' || $action->id == 'ig-callback') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actions()
    {
        return [
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIgCallback()
    {
        $filename = 'ig.txt';

        $data = "Body: ".Yii::$app->request->getRawBody();  // JSON формат сохраняемого значения.
        file_put_contents($filename, $data);

        echo Yii::$app->request->get('hub_challenge');
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionError()
    {
        $this->layout = 'error';
        return $this->render('404');
    }

    public function actionMain()
    {
        $file = 'webhooks.txt';

        $current = file_get_contents($file);

        $current = count(explode("\n", $current))-1;
        $connection=Yii::$app->db;
        $rez=$connection->createCommand("SELECT FROM_UNIXTIME(created_at,'%d-%m-%Y') as dateNorm, FROM_UNIXTIME(created_at,'%Y-%m-%d') as date, count(*) as cnt FROM `webhooks` GROUP BY date ORDER BY date")->queryAll();

        return array(
            'indb' => Webhooks::find()->count(),
            'vk' => Webhooks::find()->where(['social'=> 1])->count(),
            'ok' => Webhooks::find()->where(['social'=> 4])->count(),
            'fb' => Webhooks::find()->where(['social'=> 2])->count(),
            'twitter' => Webhooks::find()->where(['social'=> 3])->count(),
            'inst' => Webhooks::find()->where(['social'=> 5])->count(),
            'norm' => Webhooks::find()->where(['not', ['author_url' => null]])->count(),
            'webhooks' => $current,
            'data' => $rez
        );
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return Yii::$app->response->redirect(['site/index']);
        }

        $this->layout = 'login';

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return Yii::$app->response->redirect(['site/index']);
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return Yii::$app->response->redirect(['site/login']);
    }

    /**
     * Обработчик веб-хуков.
     * Пишем в файлик бэкап, пишем в бд
     */

    public function actionGetdata()
    {
        $file = 'webhooks.txt';
        $current = file_get_contents($file);

        if(\Yii::$app->request->isPost){
            $new = file_get_contents("php://input");
            $current .= $new."\n";
            file_put_contents($file, $current);
            $item = json_decode($new);
            Webhooks::SaveWebHook($item);
        }else{

            /**
             * Принудительный запуск записи вебхуков из файла.
             */

            $current = explode("\n", $current);
            foreach ($current as $item) {
                $item = json_decode($item);
                var_dump($item);
            }
        }
    }

    public function actionDemo()
    {
        $file = 'webhooks.txt';
        $current = file_get_contents($file);

        $current = explode("\n", $current);
        foreach ($current as $item) {
            $item = json_decode($item);
            var_dump(Webhooks::DemoWebHook($item));
        }
    }
    public function actionConfig()
    {

        $this->layout = '@app/views/layouts/simple.php';
        $usermodel = new UserConfig();
        $passwordmodel = new PasswordConfig();

        if(isset(\Yii::$app->request->post('UserConfig')['username'])){
            if ($usermodel->load(\Yii::$app->request->post()) && $usermodel->validate()) {
                if ($usermodel->save()) {
                    return true;
                } else {
                    return false;
                }
            }
        }elseif(isset(\Yii::$app->request->post('PasswordConfig')['password'])){
            if ($passwordmodel->load(\Yii::$app->request->post()) && $passwordmodel->validate()) {
                if ($passwordmodel->save()) {
                    return true;
                } else {
                    return false;
                }
            }
        }

        return $this->render('config', [
            'modelUser' => $usermodel,
            'modelPassword' => $passwordmodel,
        ]);

    }

    public function actionUser()
    {
        return \Yii::$app->user->identity;
    }

    public function actionNotifications()
    {
        return $this->render('notifications', []);
    }

    public function actionAbout()
    {
        return $this->render('about');
    }

    public function actionSignup($success = false)
    {
        if (!Yii::$app->user->isGuest) {
            return Yii::$app->response->redirect(['site/index']);
        }

        $this->layout = 'login';
        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($user = $model->signup()) {

                Payment::initDefaultTariff($user->id);

                return Yii::$app->response->redirect(Url::to(['site/signup', 'success' => true]));
            }
        }

        return $this->render('signup', [
            'model' => $model
        ]);
    }

    public function actionConfigUser()
    {

        $model = new UserConfig();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->update()) {
                return Yii::$app->response->redirect(['site/login']);
            } else {
                return Yii::$app->response->redirect(['site/login']);
            }
        }

        return $this->render('config', [
            'model' => $model,
        ]);
    }

    public function actionRequestPasswordReset()
    {
        $this->layout = 'login';
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                return Yii::$app->response->redirect(['site/login']);
            } else {
                return Yii::$app->response->redirect(['site/login']);
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    public function actionResetPassword($token)
    {
        $this->layout = 'login';
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->session->setFlash('success', 'New password saved.');

            return Yii::$app->response->redirect(['site/login']);
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }
}
