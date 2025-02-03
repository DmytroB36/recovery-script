<?php

require 'vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Tools;

$settings = new Settings();
$appInfo = new Settings\AppInfo();
$appInfo->setApiId('');
$appInfo->setApiHash('');

$settings->setAppInfo($appInfo);

$arguments = $argv;
$madeline = new API('session.madeline', $settings);

/**
 * @param API $madeline
 * @return void
 */
function logSessionsAndReset(API $madeline): void
{
    $mySessions = $madeline->account->getAuthorizations();

    $sessionsJSON = json_encode($mySessions['authorizations'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents('sessions.json', $sessionsJSON);

    foreach ($mySessions['authorizations'] as $session) {
        if ($session['current']) continue;
        echo "Вылогиневваем " . $session['hash'] . '[' . $session['device_model'] . "]\n";
        $madeline->account->resetAuthorization([
            'hash' => $session['hash'],
        ]);
    }
}

if (in_array('--logout', $arguments)) {
    $madeline->logout();
    echo "Вы успешно вышли из аккаунта.\n";
} else {
    $me = $madeline->getSelf();

    if (!empty($me)) {
        echo "Пользователь авторизован: ", $me['phone'] . "\n";

        $meJSON = json_encode($me, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents('me.json', $meJSON);

        logSessionsAndReset($madeline);

    } else {
        echo "Авторизируемся...";

        try {
            $phone_number = '';

            $response = $madeline->phoneLogin($phone_number);
            $loginResponseJSON = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            file_put_contents('loginResponse.json', $loginResponseJSON);

            sleep(600);

            if (!empty($response['phone_code_hash'])) {

                try {
                    $resendResponse = $madeline->auth->resendCode([
                        'phone_number' => $phone_number,
                        'phone_code_hash' => $response['phone_code_hash'],
                    ]);

                    echo "Код повторно отправлен.\n";

                } catch (RPCErrorException $e) {
                    if ($e->getMessage() === 'SEND_CODE_UNAVAILABLE') {
                        $loginErrorResponseJSON = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        file_put_contents('loginErrorResponse.json', $loginResponseJSON);

                        if ($e->rpc === 'SEND_CODE_UNAVAILABLE' && isset($e->extra['wait'])) {
                            $waitTime = intval($e->extra['wait']);
                            echo "Повторная отправка кода недоступна. Подождите $waitTime секунд.\n";
                            sleep($waitTime);
                            $resendResponse = $madeline->auth->resendCode([
                                'phone_number' => $phone_number,
                                'phone_code_hash' => $phone_code_hash,
                            ]);

                            echo "Код повторно отправлен. Проверьте SMS или Telegram.\n";

                        } else {
                            echo "Повторная отправка кода временно недоступна. Пожалуйста, повторите попытку позже.\n";
                        }
                    } else {
                        throw $e;
                    }
                }
            }

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