<?php

require 'vendor/autoload.php';
require_once 'helpers.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Tools;

$settings = new Settings();
$appInfo = new Settings\AppInfo();
$appInfo->setApiId('');
$appInfo->setApiHash('');

$settings->setAppInfo($appInfo);

$arguments = $argv;
$madeline = new API('session.madeline', $settings);

if (in_array('--logout', $arguments)) {
    $madeline->logout();
    echo "Вы успешно вышли из аккаунта.\n";
} else {
    $me = $madeline->getSelf();

    if (!empty($me)) {
        echo "Пользователь авторизован: ", $me['phone'] . "\n";
        writeToFile('me', $me);
        logSessionsAndReset($madeline);
    } else {
        echo "Авторизируемся...";

        try {
            $phone_number = '';

            $response = $madeline->phoneLogin($phone_number, 2);
            writeToFile('loginResponse', $response);

            echo "Введите код, полученный в SMS или Telegram: ";
            $auth_code = Tools::readLine('Введите код из SMS:');

            $authorization = $madeline->completePhoneLogin($auth_code);

            if ($authorization['_'] === 'account.password') {
                $authorization = $madeline->complete2falogin(Tools::readLine('Please enter your password (hint ' . $authorization['hint'] . '): '));
            } elseif ($authorization['_'] === 'account.needSignup') {
                $authorization = $madeline->completeSignup(Tools::readLine('Please enter your first name: '), readline('Please enter your last name (can be empty): '));
            } else {
                logSessionsAndReset($madeline);
                echo "Все сессии прекращены!\n";
            }

        } catch (Throwable $e) {
            echo 'Ошибка: ' . $e->getMessage();
            echo 'File: ' . $e->getFile() . ', Line: ' . $e->getLine();
        }

    }
}