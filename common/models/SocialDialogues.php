<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "social_dialogues".
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $social
 * @property string $type
 * @property integer $peer_id
 * @property integer $message_id
 * @property integer $edited
 * @property integer $direction
 * @property string $text
 * @property string $message
 * @property string $attaches
 * @property integer $created_at
 */
class SocialDialogues extends ActiveRecord
{
    const SOCIAL_VK = "VK"; // Вконтакте
    const SOCIAL_FB = "FB"; // facebook
    const SOCIAL_IG = "IG"; // instagram

    const TYPE_MESSAGE = 'message';

    const DIRECTION_INBOX = 1;
    const DIRECTION_OUTBOX = 2;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'social_dialogues';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'peer_id', 'text', 'type', 'social', 'message'], 'required'],
            [['user_id', 'peer_id', 'message_id', 'edited', 'direction'], 'integer'],
            [['message', 'text', 'type', 'attaches'], 'string'],
            [['social'], 'string', 'max' => 2],
            [['created_at'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'peer_id' => 'Peer ID',
            'text' => 'Text',
            'message_id' => 'Message ID',
            'edited' => 'Edited',
            'direction' => 'Direction',
            'social' => 'Social',
            'type' => 'Type',
            'message' => 'Message',
            'created_at' => 'Created At'
        ];
    }

    public function getUserValue()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    public function getPeer()
    {
        return $this->hasOne(SocialDialoguesPeer::className(), [
            'social' => 'social',
            'peer_id' => 'peer_id'
        ]);
    }

    public static function saveMessage($userId, $social, $type, array $message, $group_access_token, $access_token)
    {
        //новое сообщение
        if(isset($message[0]) && $message[0] == 4) {
            return static::newMessage($userId, $social, $type, $message, $group_access_token, $access_token);
        }
        //редактированное сообщение
        if(isset($message[0]) && $message[0] == 5) {
            return static::editedMessage($userId, $social, $type, $message, $group_access_token, $access_token);
        }

        return false;
    }

    public static function newMessage($userId, $social, $type, array $message, $group_access_token, $access_token)
    {
        if($model = static::findMessage($userId, $social, $type, $message)) {
            return $model;
        }

        $model = new static;
        $model->user_id = $userId;
        $model->social = $social;
        $model->type = $type;
        $model->direction = $model->getDirection($message[2]);
        $model->peer_id = $message[3];
        $model->message_id = $message[1];
        $model->text = $message[5];
        $model->message = json_encode($message);
        $model->attaches = json_encode($model->getAttachments($message, $group_access_token, $access_token));

        if(!$model->save(false)) {
            var_dump($model->errors);
        }

        return $model;
    }

    /**
     * @param $userId
     * @param $social
     * @param $type
     * @param array $message
     * @param $group_access_token
     * @return array|bool|null|ActiveRecord
     */
    public static function editedMessage($userId, $social, $type, array $message, $group_access_token, $access_token)
    {
        $model = static::findMessage($userId, $social, $type, $message);

        /**
         * @var $model static
         */
        if($model) {
            $model->text = $message[5];
            $model->message = json_encode($message);
            $model->attaches = json_encode($model->getAttachments($message, $group_access_token, $access_token));
            $model->edited = 1;

            if(!$model->save(false)) {
                var_dump($model->errors);
            }

            return $model;
        }

        return false;
    }

    public static function findDoubleMessage($userId, $social, $type, array $message)
    {
        $model =  static::find()
            ->andWhere([
                'user_id' => $userId,
                'social' => $social,
                'type' => $type,
                'peer_id' => $message[3],
                'message_id' => $message[1],
            ])
            ->one();

        echo $model->message.PHP_EOL;
        echo json_encode($message).PHP_EOL;

        return $model->message == json_encode($message);
    }

    /**
     * @param $userId
     * @param $social
     * @param $type
     * @param array $message
     * @return array|null|ActiveRecord
     */
    public static function findMessage($userId, $social, $type, array $message)
    {
        return static::find()
            ->andWhere([
                'user_id' => $userId,
                'social' => $social,
                'type' => $type,
                'peer_id' => $message[3],
                'message_id' => $message[1],
            ])
            ->one();
    }

    public function getDirection($flags)
    {
        $summands = [];
        foreach([1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 65536] as $number) {
            if ($flags & $number) {
                $summands[] = $number;
            }
        }
        if(in_array(2, $summands)) {
            return static::DIRECTION_OUTBOX;
        }

        return static::DIRECTION_INBOX;
    }

    protected function getAttachments($update, $group_access_token, $access_token)
    {
        $attachments = $update[6];

        $attachesArray = [];
        $attachIsset = true;
        $i = 0;
        while($attachIsset == true) {
            ++$i;
            $attachCounter = "attach{$i}";
            $typeName = $attachCounter . '_type';
            if(isset($attachments->$typeName)) {

                if($attachments->$typeName == 'photo') {
                    $attachesArray['photo'][] = $attachments->$attachCounter;
                }
                if($attachments->$typeName == 'video') {
                    $attachesArray['video'][] = $attachments->$attachCounter;
                }
                if($attachments->$typeName == 'audio') {
                    $attachesArray['audio'][] = $attachments->$attachCounter;
                }
                if($attachments->$typeName == 'doc') {
                    $attachesArray['doc'][] = $attachments->$attachCounter;
                }
                if($attachments->$typeName == 'wall') {
                    $attachesArray['wall'][] = $attachments->$attachCounter;
                }
                if($attachments->$typeName == 'link') {
                    $attachesArray['link'][] = $attachments->{$attachCounter.'_url'};
                }
                //sticker, money - не найдены методы в vk api
            } else {
                $attachIsset = false;
            }
        }

        if($update[6]->geo) {
            $attachesArray['geo']['geo'] = $update[6]->geo;
            $attachesArray['geo']['geo_provider'] = $update[6]->geo_provider;
        }

        if($attachesArray) {
            $vk = new \frontend\controllers\bot\libs\Vk([
                'access_token' => $access_token
            ]);
            if ($attachesArray['photo']) {
                try {
                    $attaches = $vk->api('photos.getById', [
                        'photos' => implode(',', $attachesArray['photo']),
                        'lang' => 0
                    ]);

                    $attachesArray['photo'] = $attaches;
                } catch (\frontend\controllers\bot\libs\VkException $e) {
                    echo $e->getMessage();
                }
            }
            if ($attachesArray['video']) {
                try {
                    $attaches = $vk->api('video.get', [
                        'videos' => implode(',', $attachesArray['video']),
                        'lang' => 0
                    ]);

                    $attachesArray['video'] = $attaches;
                } catch (\frontend\controllers\bot\libs\VkException $e) {
                    echo $e->getMessage();
                }
            }
            if ($attachesArray['doc']) {
                try {
                    $attaches = $vk->api('docs.getById', [
                        'docs' => implode(',', $attachesArray['doc']),
                        'lang' => 0
                    ]);

                    $attachesArray['doc'] = $attaches;
                } catch (\frontend\controllers\bot\libs\VkException $e) {
                    echo $e->getMessage();
                }
            }
        }

        return $attachesArray;
    }
}
