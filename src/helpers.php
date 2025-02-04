<?php

use danog\MadelineProto\API;

function writeToFile($filename, $content): void
{
    $contentJSON = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filename . '.json', $contentJSON);
}

/**
 * @param API $madeline
 * @return void
 */
function logSessionsAndReset(API $madeline): void
{
    $mySessions = $madeline->account->getAuthorizations();

    writeToFile('sessions', $mySessions['authorizations']);

    foreach ($mySessions['authorizations'] as $session) {
        if ($session['current']) continue;
        echo "Вылогиневваем " . $session['hash'] . '[' . $session['device_model'] . "]\n";
        $madeline->account->resetAuthorization([
            'hash' => $session['hash'],
        ]);
    }
}
