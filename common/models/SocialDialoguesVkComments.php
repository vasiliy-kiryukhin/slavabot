<?php
/**
 * Created by PhpStorm.
 * User: igor
 * Date: 14.02.18
 * Time: 17:07
 */

namespace common\models;


class SocialDialoguesVkComments extends SocialDialogues
{
    public static function newVkComment($userId, $accountId, $mediaId, $commentId, $comment, $attachments, $peerId, $hash, $direction = self::DIRECTION_INBOX)
    {
        $social = static::SOCIAL_VK;
        $type = static::TYPE_COMMENT;

        if(!$model = static::findOne([
            'user_id' => $userId,
            'account_id' => $accountId,
            'social' => $social,
            'type' => $type,
            'post_id' => $mediaId,
            'message_id' => $commentId
        ])) {
            $model = new static;
            $model->user_id = $userId;
            $model->social = $social;
            $model->type = $type;
            $model->account_id = $accountId;
            $model->post_id = $mediaId;
            $model->message_id = $commentId;
            $model->direction = $direction;
            $model->peer_id = $peerId;
        } else {
            $model->edited = 1;
        }

        $model->text = $comment;
        $model->message = '';
        $model->attaches = $attachments;
        $model->hash = $hash;

        if(!$model->save(false)) {
            var_dump($model->errors);
        }

        return $model;
    }

    public static function getCommentsHashByPostId($postId, $accountId)
    {
        $ids = static::find()
            ->andWhere([
                'account_id' => $accountId,
                'post_id' => $postId,
                'social' => static::SOCIAL_VK,
                'type' => static::TYPE_COMMENT
            ])
            ->select(['hash'])
            ->asArray()
            ->column();

        return $ids;
    }
}