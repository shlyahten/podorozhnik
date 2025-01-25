<?php
// Подключение библиотеки Telegram Bot API
require 'vendor/autoload.php';

use Telegram\Bot\Api;

$botToken = getenv('BOT_TOKEN'); // Telegram Bot Token
$bot = new Api($botToken);
date_default_timezone_set('Europe/Moscow');

// Включение отображения ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/error_log.txt');


// Сохранение или обновление токенов в файл
function saveTokenToFile($chatId, $refreshToken, $token) {
    $filePath = __DIR__ . '/db.txt';
    $newData = "$chatId:$refreshToken:$token" . PHP_EOL;
    $fileContents = file_exists($filePath) ? file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $fileContents = array_map(function($line) use ($chatId, $newData) {
        return strpos($line, "$chatId:") === 0 ? trim($newData) : $line;
    }, $fileContents);

    if (!in_array(trim($newData), $fileContents)) {
        $fileContents[] = trim($newData);
    }

    file_put_contents($filePath, implode(PHP_EOL, $fileContents) . PHP_EOL, LOCK_EX);
}

// Обновление токена
function refreshToken($refreshToken) {
    return makeApiRequest('https://mobile-vpb.orgp.spb.ru/user/refreshtoken', "Bearer $refreshToken");
}

// Чтение токенов из файла
function getTokensFromFile($chatId) {
    $filePath = __DIR__ . '/db.txt';
    if (!file_exists($filePath)) {
        return ['error' => 'Файл с токенами не найден'];
    }

    foreach (file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        list($storedChatId, $storedRefreshToken, $storedToken) = explode(':', $line, 3);
        if ($storedChatId == $chatId) {
            return ['refreshToken' => $storedRefreshToken, 'token' => $storedToken];
        }
    }

    return ['error' => 'Токены для данного чата не найдены'];
}

// Функция для сохранения токена в файл p_db.txt
function savePodorozhnikTokenToFile($chatId, $userId, $token) {
    $filePath = __DIR__ . '/p_db.txt';
    $newData = "$chatId:$userId:$token" . PHP_EOL;
    $fileContents = file_exists($filePath) ? file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $fileContents = array_map(function($line) use ($chatId, $newData) {
        return strpos($line, "$chatId:") === 0 ? trim($newData) : $line;
    }, $fileContents);

    if (!in_array(trim($newData), $fileContents)) {
        $fileContents[] = trim($newData);
    }

    file_put_contents($filePath, implode(PHP_EOL, $fileContents) . PHP_EOL, LOCK_EX);
}

// Чтение токенов из файла
function getPodorozhnikTokensFromFile($chatId) {
    $filePath = __DIR__ . '/p_db.txt';
    if (!file_exists($filePath)) {
        return ['error' => 'Файл с токенами не найден'];
    }

    foreach (file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        list($storedChatId, $storedUserId, $storedToken) = explode(':', $line, 3);
        if ($storedChatId == $chatId) {
            return ['userId' => $storedUserId, 'token' => $storedToken];
        }
    }

    return ['error' => 'Токены для данного чата не найдены'];
}

// Универсальная функция для выполнения API-запросов
function makeApiRequest($url, $authHeader = null, $data = null, $extraHeaders = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(array_filter([
        'User-Agent: okhttp/4.9.2',
        'Connection: Keep-Alive',
        'Accept: application/json, text/plain, */*',
        'Accept-Encoding: gzip',
        $authHeader ? "authorization: $authHeader" : null,
        $data ? 'Content-Type: application/json' : null
    ]), $extraHeaders));
    if ($data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['error' => $error ?: "HTTP $httpCode"];
    }

    $decodedResponse = json_decode($response, true);
    if ($decodedResponse === null) {
        return ['error' => 'Ошибка декодирования JSON'];
    }

    if ($httpCode !== 200) {
        return ['error' => "HTTP $httpCode: " . ($decodedResponse['message'] ?? 'Неизвестная ошибка')];
    }

    return $decodedResponse;
}

// Получение и обработка списка поездок
function getTrips($token) {
    $response = makeApiRequest('https://mobile-vpb.orgp.spb.ru/trips/gettrips?vpb=false', "Bearer $token");
	return $response;
}
function setTrips($response) {
	    if (isset($response['error'])) {
        return "Ошибка: {$response['error']}";
    }

    if (!isset($response['items']) || !is_array($response['items'])) {
        return "Ошибка: некорректный формат ответа сервера.";
    }

    usort($response['items'], fn($a, $b) => strtotime($a['dateTime']) - strtotime($b['dateTime']));

    $emojiMap = [
        'Троллейбус' => '🚎',
        'Автобус' => '🚌',
        'Трамвай' => '🚋'
    ];

    $result = "Список поездок:\n";
    foreach ($response['items'] as $trip) {
        $date = (new DateTime($trip['dateTime']))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m H:i');
        $route = $trip['vehicleRoute'];
        $type = $emojiMap[$trip['vehicleType']] ?? $trip['vehicleType'];
        $amount = number_format($trip['amountInMinorUnits'] / 100, 2, '.', '');
        $result .= "$date - № $route - $type - Стоимость: $amount руб.\n";
    }

    return $result;
}

// Получение и обработка списка пополнений
function getPayments($token) {
    $podorozhnikHeader = [
        'x-ppa-mobile-app-request: mobile app'
    ];
    $response = makeApiRequest('https://podorozhnik.spb.ru/api/payment', "Bearer $token", null, $podorozhnikHeader);

    return $response;
}
function setPayments($response) {
	if (isset($response['error'])) {
        return "Ошибка: {$response['error']}";
    }

    if (!isset($response['items']) || !is_array($response['items'])) {
        return "Ошибка: некорректный формат ответа сервера.";
    }

    usort($response['items'], fn($a, $b) => strtotime($a['dateTime']) - strtotime($b['dateTime']));

    $result = "Список пополнений:\n";
    foreach ($response['items'] as $payment) {
        $date = (new DateTime($payment['dateTime']))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m H:i');
        $amount = number_format($payment['amountInMinor'] / 100, 2, '.', '');
        $ticketDescription = $payment['ticketTypeDescription'] ?? 'Неизвестный тип билета';
        $paymentStatus = $payment['statuses']['PaymentStatus'] ?? 'Статус неизвестен';
        $rechargeStatus = $payment['statuses']['RechargeStatus'] ?? 'Неизвестный статус';
        $resource = $payment['statuses']['Resource'] ?? 'Ресурс неизвестен';

        $result .= "$date - $ticketDescription\n";
        $result .= "  Сумма: $amount руб.\n";
        $result .= "  Статус оплаты: $paymentStatus\n";
        $result .= "  Статус пополнения: $rechargeStatus\n";
        $result .= "  Остаток: $resource\n\n";
    }

    return $result;
}

// Подсчет баланса
function calculateBalance($trips, $payments) {
    if (!isset($trips['items']) || !is_array($trips['items'])) {
        return "Ошибка: некорректный формат данных о поездках.";
    }

    if (!isset($payments['items']) || !is_array($payments['items'])) {
        return "Ошибка: некорректный формат данных о пополнениях.";
    }

    $tripsList = [];
    foreach ($trips['items'] as $trip) {
        $tripsList[] = [
            'dateTime' => (new DateTime($trip['dateTime']))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('Y-m-d H:i:s'),
            'amount' => $trip['amountInMinorUnits'] / 100 // Преобразование в рубли
        ];
    }

    $paymentsList = [];
    foreach ($payments['items'] as $payment) {
        $paymentsList[] = [
            'dateTime' => (new DateTime($payment['dateTime']))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('Y-m-d H:i:s'),
            'resource' => convertToMinorUnits($payment['statuses']['Resource']) / 100 // Преобразование в рубли
        ];
    }

    if (empty($tripsList) || empty($paymentsList)) {
        return "Ошибка: недостаточно данных для расчёта.";
    }

    $latestPayment = end($paymentsList);
    $currentBalance = $latestPayment['resource'];
    $lastPaymentDate = new DateTime($latestPayment['dateTime']);

    $calculationDetails = "Последний баланс на момент пополнения ({$lastPaymentDate->format('d.m H:i')}): {$currentBalance} руб.\n";
    $calculationDetails .= "Список поездок после пополнения:\n";

    foreach ($tripsList as $trip) {
        $tripDate = new DateTime($trip['dateTime']);
        if ($tripDate > $lastPaymentDate) {
            $currentBalance -= $trip['amount'];
            $calculationDetails .= "{$tripDate->format('d.m H:i')} - Стоимость: {$trip['amount']} руб.\n";
        }
    }

    $calculationDetails .= "\nТекущий расчетный баланс: " . number_format($currentBalance, 2, '.', '') . " руб.";
    return $calculationDetails;
}

// Преобразуем данные в amountInMinor
function convertToMinorUnits($input) {
    if (empty($input)) {
        return 0; // Если строка пустая, возвращаем 0
    }

    $numericValue = preg_replace('/[^\d.]/', '', $input);
    return (int)($numericValue * 100);
}

// Основной обработчик запросов
$update = $bot->getWebhookUpdate();

if (isset($update['message']['text'])) {
    $message = $update['message']['text'];
    $chatId = $update['message']['chat']['id'];

    if (strpos($message, ':') !== false) {
    list($login, $password) = explode(':', $message, 2);

    // Первый запрос
    $vpbData = [
        'email' => $login,
        'password' => $password,
        'device' => '1e1d1d1aee11e1aa...11a1b...aa1tk'
    ];
    $vpbResponse = makeApiRequest('https://mobile-vpb.orgp.spb.ru/user/login', null, $vpbData);

    if (isset($vpbResponse['userData'], $vpbResponse['token'], $vpbResponse['refresh'])) {
        saveTokenToFile($chatId, $vpbResponse['refresh'], $vpbResponse['token']);
        $reply1 = "Код ответа сервера: 200\n" .
                  "userId: {$vpbResponse['userData']['userId']}\n" .
                  "email: {$vpbResponse['userData']['email']}\n" .
                  "Имя: {$vpbResponse['userData']['firstName']}\n" .
                  "Фамилия: {$vpbResponse['userData']['surname']}\n" .
                  "Токен: {$vpbResponse['token']}\n" .
                  "Рефреш токен: {$vpbResponse['refresh']}";
    } else {
        $reply1 = "Ошибка: не удалось получить данные пользователя. Ответ сервера: " . print_r($vpbResponse, true);
    }

    // Второй запрос
	$podorozhnikHeader = [
    'x-ppa-mobile-app-request: mobile app'
    ];
    $podorozhnikData = [
        'login' => $login,
        'password' => $password
    ];
    $podorozhnikResponse = makeApiRequest(
        'https://podorozhnik.spb.ru/api/auth/login',
        "Bearer", // Заголовок "Bearer" нужен, он пустой на этом этапе
        $podorozhnikData,
        $podorozhnikHeader
    );

    if (isset($podorozhnikResponse['token'], $podorozhnikResponse['id'])) {
        savePodorozhnikTokenToFile($chatId, $podorozhnikResponse['id'], $podorozhnikResponse['token']);
        $reply2 = "Успешная авторизация:\n" .
                  "ID: {$podorozhnikResponse['id']}\n" .
                  "Email: {$podorozhnikResponse['email']}\n" .
                  "Имя: {$podorozhnikResponse['firstName']}\n" .
                  "Фамилия: {$podorozhnikResponse['surname']}\n" .
                  "Токен: {$podorozhnikResponse['token']}";
    } else {
        $reply2 = "Ошибка: не удалось авторизоваться. Ответ сервера: " . print_r($podorozhnikResponse, true);
    }

    $reply = "{$reply1}\n--------------------\n{$reply2}";
} elseif (strpos($message, '/refresh') === 0) {
        $tokens = getTokensFromFile($chatId);

        if (isset($tokens['error'])) {
            $reply = "Ошибка: {$tokens['error']}";
        } else {
            $newTokens = refreshToken($tokens['refreshToken']);

            if (isset($newTokens['error'])) {
                $reply = "Ошибка обновления токена: {$newTokens['error']}";
            } else {
                saveTokenToFile($chatId, $newTokens['refresh'], $newTokens['token']);
                $reply = "Токены успешно обновлены:\nНовый токен: {$newTokens['token']}\nНовый рефреш токен: {$newTokens['refresh']}";
            }
        }
    } elseif (strpos($message, '/trips') === 0) {
        $tokens = getTokensFromFile($chatId);

        if (isset($tokens['error'])) {
            $reply = "Ошибка: {$tokens['error']}";
        } else {
            $response = getTrips($tokens['token']);
			$reply = setTrips($response);
        }
    } elseif (strpos($message, '/payments') === 0) {
        $tokens = getPodorozhnikTokensFromFile($chatId);

        if (isset($tokens['error'])) {
            $reply = "Ошибка: {$tokens['error']}";
        } else {
            $response = getPayments($tokens['token']);
			$reply = setPayments($response);
        }
   } elseif (strpos($message, '/calc') === 0) {
    $vpbTokens = getTokensFromFile($chatId);
    $podorozhnikTokens = getPodorozhnikTokensFromFile($chatId);

    if (isset($vpbTokens['error']) || isset($podorozhnikTokens['error'])) {
        $reply = "Ошибка: Не удалось получить токены для выполнения запроса.";
    } else {
        $trips = getTrips($vpbTokens['token']);
        $payments = getPayments($podorozhnikTokens['token']);

            $reply = calculateBalance($trips, $payments);
    }
} else {
        $reply = "Пожалуйста, отправьте данные в формате логин:пароль для обновления токена.";
    }

    $bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $reply
    ]);
}
?>
