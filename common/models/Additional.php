<?php

namespace common\models;

use Yii;
use common\models\ADTypes;

/**
 * Модель для работы с контактами приходящими с вебхуками.
 *
 * @property integer $id
 * @property integer $type - тип контакта (модель ADTypes)
 * @property integer $webhook - id вебхука которому принадлежит контакт (модель Webhooks)
 * @property string $value - содержимое контакта (непосредственно номер, почта и т.п.)
 */

class Additional extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */

    public static function tableName()
    {
        return 'additional_parameters';
    }

    /**
     * @inheritdoc
     */

    public function rules()
    {
        return [
            [['type'], 'integer'],
            [['webhook'], 'integer'],
            [['value'], 'string']

        ];
    }

    /**
     * @inheritdoc
     */

    public function attributeLabels()
    {
        return [
            'type' => 'type',
            'webhook' => 'webhook',
            'value' => 'value'
        ];
    }

    /**
     *  Дергаем данные из модели ADTypes (типы контактов)
     */

    public function getContactsType()
    {
        return $this->hasOne(ADTypes::className(), ['id' => 'type']);
    }

    /***
     * Указываем поля, которые нам необходимо вернуть.
     * name - берем из модели ADTypes название типа контакта
     * @return array
     */
    public function fields()
    {
        return [
                'type',
                'value',
                'name' => function(){
                    return $this->contactsType->name;
                },
                'type' => function(){
                    return $this->contactsType->code;
                }
            ];
    }

    /**
     * Проверка существования контактов относящихся к тому же вебхуку и имеющих тот же тип.
     */

    public static function checkReference($type, $webhook)
    {
        $id = static::findOne(['type' => $type, 'webhook' => $webhook]);

        if($id){
            return $id;
        }else{
            return new Additional();
        }
    }

    /**
     * Сохраняем контакты, проверив их на существование
     */

    public static function saveReference($item, $id)
    {

        if($item->category->id) {
            foreach ($item->additional_parameters as $code => $param) {

                $additional_type = ADTypes::checkMLG((int)$code);

                if($param) {
                    $loc = self::checkReference($additional_type, $id);

                    $loc->type = $additional_type;
                    $loc->webhook = $id;
                    $loc->value = $param;

                    $loc->save(false);

                    $res[] = $loc->id;
                }
            }
            return $res;
        }else {
            return false;
        }
    }
}
