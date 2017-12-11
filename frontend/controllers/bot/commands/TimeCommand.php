<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\MessageEntity;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Request;
use Symfony\Component\Yaml\Yaml;
use Libs\SalesBotApi;
use Libs\TelegramWrap;


class TimeCommand extends UserCommand
{
    protected $name = 'time';                      // Your command's name
    protected $description = 'Часовой пояс'; // Your command description
    protected $usage = '/time';                    // Usage of your command
    protected $version = '1.0.0';
    protected $need_mysql = true;
    protected $conversation;

    public function execute()
    {

        //подключаем обертку с настройками
        $telConfig = new TelegramWrap();


        try {

            $message = $this->getMessage();

            $text = trim($message->getText(true));

            $chat = $message->getChat();
            $user = $message->getFrom();


            $chat_id = $chat->getId();
            $user_id = $user->getId();

            if ($text === '' || $text === $telConfig->config['buttons']['time']['label']) {


                $db            = new \Libs\Db();
                $entityManager = $db->GetManager();

                $user = $entityManager->getRepository('Models\Users')->findOneBy([
                    'telegram_id' => $user_id,
                ]);

                $user_timezone = '';
                if ($user) {
                    $user_timezone = $user->getTimezone();
                }


                $inline_keyboard = new InlineKeyboard();
                foreach ($telConfig->config['timezones']['buttons'] as $zone =>$arButton) {

                    //добавляем галочку активности
                    $button_text = '';
                    if ( $arButton['value'] == $user_timezone ) {
                        $button_text = $telConfig->config['timezones']['active'].' ';
                    }
                    $button_text .= $arButton['label'].' ('.$zone.')';

                    //собираем кнопки
                    $inline_keyboard->addRow(
                        [
                            'text' => $button_text,
                            'callback_data' => $zone
                        ]
                    );
                }

                $data = [
                    'chat_id'      => $chat_id,
                    'user_id'      => $user_id,
                    'reply_markup' => $inline_keyboard->setSelective(true),
                    'text'         => "Выберите часовой пояс:",

                ];

                return Request::sendMessage($data);
            }

        } catch (Longman\TelegramBot\Exception\TelegramException $e) {

            $this->conversation->cancel();

            $data = [
                'chat_id'      => 339247162,
                'user_id'      => 339247162,
                'text'         => "Ошибка: ".$e->getMessage(),

            ];

            return Request::sendMessage($data);

        }
    }
}