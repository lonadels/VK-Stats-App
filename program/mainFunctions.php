<?php
/**
 * Created by PhpStorm.
 * User: TheLonadels
 * Date: 01.01.2018
 * Time: 18:16
 */

function checkConnection() {
    $x = @fsockopen( 'api.vk.com', 443, $err, $ern, 60 );

    if( ! $x ) {
        for( $i = 5; $i >= 0; $i-- ) {
            stringUtils::msg( "Не удаётся подключиться к серверу", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_RED );

            for( $x = 5; $x >= 0; $x-- ) {
                sleep( 1 );
                print "\rПовторная попытка через $x...   ";
            }

            print "\n\n";
            checkConnection();
        }
        while( TRUE ) ;
    }

    fclose( $x );
}

define( "APP_DATA", getenv( "AppData" ) );
define( "APP_DIR", APP_DATA . "\VKStatsApp\\" );

if( ! is_dir( APP_DIR ) )
    mkdir( APP_DIR );

class mainFunctions {
    public $vk;
    public $db;
    public $config;

    public function __construct() {
        $this->vk = new vkApi();

        $file = APP_DIR . "db.sqlite";

        $this->db = new SQLite3( $file );
        $this->db->query( "
                            CREATE TABLE IF NOT EXISTS `users` ( 
                            /* Основная таблица с аккаунтами */
                              lastAuth   INT,
                              token      TEXT,
                              first_name TEXT,
                              last_name  TEXT,
                              id         INT NOT NULL,
                              domain     TEXT
                            );
                            
                            CREATE TABLE IF NOT EXISTS `settings` (
                            /* Таблица конфигурации */
                              param      TEXT NOT NULL,
                              value      TEXT
                            );
                            
                            CREATE TABLE IF NOT EXISTS `history` (
                            /* Таблица для хранения истории операций */
                              owner_id   INT,
                              item_id    INT,
                              body       TEXT,
                              type       TEXT,
                              time       INT,
                              repair     INT
                            );
                            
                            /* Переход со старой версии */
                            UPDATE `history` SET `type`='banUser' WHERE `type`='delSubs';
                            " );

        $columns = [];
        $q = $this->db->query( "PRAGMA table_info('history');" );
        while( $s = $q->fetchArray( SQLITE3_ASSOC ) )
            $columns[] = $s[ 'name' ];

        if( ! in_array( 'body', $columns ) )
            $this->db->query( "ALTER TABLE `history` ADD COLUMN `body` TEXT AFTER `item_id`;" );

        if( ! in_array( 'repair', $columns ) )
            $this->db->query( "ALTER TABLE `history` ADD COLUMN `repair` INT AFTER `time` DEFAULT 0;" );

        $this->config = new config( $this->db );
        stringUtils::$config = $this->config;

        $defaultConfig = [
            'use_rainbow_terminal' => TRUE,
            'sound_on_end' => TRUE,
            'anti_captcha' => FALSE,
            'first_start' => TRUE,
            'pre_build' => (bool) PRE_BUILD,
            'group_joined' => FALSE
        ];

        foreach( $defaultConfig as $option => $value )
            if( $this->config->$option === NULL )
                $this->config->$option = $value;

        $this->config->save();
    }

    public function loadMenu() {
        $this->actionMenu( [
            "Статистика диалога" => [ "dialogStats", "Анализ выбранного диалога и вывод информации о нём" ],
            //"Статистика страницы" => ["profileStats", "Анализирует и показывает статистику страницы"],
            "Очистка" => [ "clear", "Операции по массовой очистке данных" ],
            "Поиск сообщения" => [ "findMessage", "Позволяет найти сообщения по содержанию" ],
            "Лайкнуть всю стену" => [ "likeAll", "Поставить лайки на все записи сообщества или пользователя" ],
            //"Анализ стены" => [ "parseWall", "Найти лайки определённого пользователя на стене пользователя/группы" ],
            //"Анализ альбома" => [ "parseAlbum", "Поиск лайков всех или определённого пользователя в альбоме" ],
            //"Сохранить диалог" => [ "dialogDump", "Полное сохранение диалога в файл" ],
            //"Добавить из поиска" => [ "addFromSearch", "Добавить друзей из поиска" ],
            //"Парсинг диалога" => [ "dialogParse", "Анализ и сохранение вложений из диалога" ],
            //"Управление сообществом"=>["groupControl", "Функции для работы с администратируемыми сообществами"],
            "Отправка голосовой" => [ "sendVoice", "Отправить голосовое сообщение из файла" ],
            "Отправка граффити" => [ "loadAsGraffiti", "Отправить изображение в виде граффити" ],
            "Настройки" => [ "accountMenu", "Настройки программы VK Stats App" ],
        ], TRUE );
    }

    public function clear() {
        $this->actionMenu( [
            "Очистка друзей" => [ "clearFriends", "Удалить всех друзей со страницы" ],
            "Очистка подписчиков" => [ "clearSubs", "Удалить подписчиков или собачек" ],
            "Очистка стены" => [ "clearWall", "Очистка всех записей на стене" ],
            "Очистка комментариев" => [ "clearComments", "Очистка всех комментариев на стене" ],
            "Очистка фото" => [ "clearPhotos", "Очистка фотографий во всех альбомах на странице" ],
            "Очистка групп" => [ "clearGroups", "Отмена подписки на все группы" ],
            "Очистка документов" => [ "clearDocs", "Очистка всех документов на странице" ],
            "Очистка ЧС" => [ "clearBlackList", "Удаление всех пользователей из чёрного списка" ],
            "Очистка диалогов" => [ "deleteDialogs", "Полная очистка всех диалогов пользователя" ],
            "Очистка сообщений" => [ "deleteMessages", "Опциональное удаление сообщений в диалоге" ],
            "Очистка закладок" => [ "clearFavs", "Удаление закладок - поставленных лайков, ссылок, людей и т.д." ],
            //"Очистка подписок" => [ "clearOutSub", "Очистка исходящих заявок в друзья" ],
            "Восстановление" => [ "recovery", "Восстановление удалённых данных, где это возможно" ],
            "Главное меню" => [ "loadMenu", "Возврат в главное меню" ],
        ], TRUE );
    }

    public function loadAsGraffiti() {
        $id = $this->selectDialog();
        while( ! isset( $file ) || ! file_exists( $file ) ) {
            $file = stringUtils::readLn( "Путь до PNG файла:" );
            if( ! file_exists( $file ) ) {
                stringUtils::msg( "Файл не существует!", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_RED );
                stringUtils::msg( "Проверьте правильность указанного пути", MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );
            }
        }

        $server = $this->vk->method( "docs.getUploadServer", [ "type" => "graffiti" ] )->response->upload_url;

        $aPost = [
            'file' => new CURLFile( $file, 'multipart/form-data' )
        ];
        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $server );
        curl_setopt( $ch, CURLOPT_SAFE_UPLOAD, TRUE );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $aPost );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt( $ch, CURLOPT_HEADER, FALSE );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
        curl_setopt( $ch, CURLOPT_REFERER, $server );

        print "\rЗагрузка файла...";
        $res = curl_exec( $ch );
        curl_close( $ch );

        $file = json_decode( $res )->file;
        $file = $this->vk->method( "docs.save", [ "file" => $file ] )->response[ 0 ];


        if( ! $this->vk->method( "messages.send", [ "attachment" => "doc" . $file->owner_id . "_" . $file->id, "peer_id" => $id ] )->error )
            print "\rСообщение отправлено";
        else
            print "\rПроизошла ошибка при отправке";


    }

    public function addFromSearch() {
        // NOTE: эта функция была создана ради забавы и я пока не знаю, как можно реализовать её настройку через интерфейс консоли

        $toAdd = [];
        $i = 0;
        while( count( $toAdd ) <= ( ( isset( $max ) && $max < 50 ) ? $max : 50 ) ) {
            $users = $this->vk->method( "users.search", [ "city" => 2, "q" => "Фёдор", "birth_year" => 1999, "has_photo" => 1, "fields" => "last_seen,is_friend", "count" => 50, "offset" => $i, "sex" => 2 ] )->response;

            $max = $users->count;
            foreach( $users->items as $user ) {
                $i++;

                if( count( $toAdd ) == 0 )
                    stringUtils::preloader( "Поиск ($i/$max)..." );

                if( $user->last_seen->time >= strtotime( "-36 hours" ) && ! $user->is_friend ) {
                    $toAdd[] = $user;
                    stringUtils::preloader( "Поиск... Найдено " . count( $toAdd ) );
                }
            }
        }
        foreach( $toAdd as $i => $user ) {
            stringUtils::preloader( "Добавление $user->first_name $user->last_name (id$user->id, $i/" . count( $toAdd ) . ")..." );
            $this->vk->method( "friends.add", [ "user_id" => $user->id ] );
            $query = "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $user->id, 'addFriend', " . time() . ");";
            $this->db->query( $query );
        }

        stringUtils::msg( "Добавлено $i друзей." );
    }

    public function groupControl() {
        $this->actionMenu( [
            "Очистка подписчиков" => [ "clearSubs", "Удалить подписчиков или собачек" ],
            "Вернуться в меню" => [ "loadMenu", "Возврат к главному меню программы" ],
        ] );
    }

    public function accountMenu() {

        $is64 = PHP_INT_MAX > 2147483647;

        $time = microtime( 1 );
        checkConnection();
        $time = round( microtime( 1 ) - $time, 4 );

        stringUtils::msg( "\nVK Stats App", MsgTypes::NEUTRAL, 1, ForegroundColors::LIGHT_CYAN );
        stringUtils::msg( " - приложение, позволяющее производить массовые операции\nнад страницей или получать различную статистику.", MsgTypes::NEUTRAL );

        $appVer = str_split( APP_VER );

        if( $appVer[ 2 ] == 0 && $appVer[ 3 ] == 0 ) unset( $appVer[ 2 ] );
        if( $appVer[ 3 ] == 0 ) unset( $appVer[ 3 ] );

        $appVer = implode( '.', $appVer );

        stringUtils::msg( "\nВерсия " . stringUtils::color( $appVer . ( PRE_BUILD ? "-Pre" . PRE_BUILD : "" ), ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 1, ForegroundColors::DARK_GRAY );
        stringUtils::msg( "; Сборка " . stringUtils::color( APP_BUILD, ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

        if( PRE_BUILD )
            stringUtils::msg( "Вы используете " . stringUtils::color( "эксперементальную версию\n", ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

        stringUtils::msg( "Версия PHP: " . stringUtils::color( phpversion() . ( $is64 ? "-x86" : "-x64" ), ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );
        stringUtils::msg( "Использование памяти: " . stringUtils::color( stringUtils::size( memory_get_usage() ) . " из " . stringUtils::size( memory_get_usage( 1 ) ), ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

        stringUtils::msg( "\nВерсия API: " . stringUtils::color( $this->vk->v, ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );
        stringUtils::msg( "Отклик API: " . stringUtils::color( "{$time}s", ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

        if( DEBUG )
            stringUtils::msg( "\nРежим отладки: " . stringUtils::color( "включен", ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

        stringUtils::msg( "\nГруппа ВК: " . stringUtils::color( "vk.com/VKStatsApp", ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

        if( $this->config->anti_captcha ) {
            $api = new Anticaptcha();
            $api->setVerboseMode( FALSE );
            $api->setKey( $this->config->anti_captcha );
            $balance = $api->getBalance();
            if( $balance ) {
                stringUtils::msg( "Баланс anti-captcha.com: " . stringUtils::color( "$" . round( $balance, 3 ), ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );
            }
        }

        $this->actionMenu( [
            "Аккаунты" => [ "delAcc", "Управление списком аккаунтов в VK Stats App" ],
            "Сменить пользователя" => [ "logOut", "Переключиться между аккаунтами" ],
            "Параметры" => [ "options", "Параметры VK Stats App" ],
            "Проверить обновления" => [ "checkUpdate", "Проверить наличие обновлений K Stats App" ],
            "Вернуться в меню" => [ "loadMenu", "Возврат к главному меню программы" ]
        ] );
    }

    function checkUpdate() {
        checkUpdate( 1 );
    }

    function actionMenu( $actions, $checkConn = FALSE, $notExec = FALSE ) {
        print "\r\n";

        foreach( $actions as $name => $data ) {
            if( ! $notExec )
                $function = $data[ 0 ];

            $number = array_flip( array_keys( $actions ) )[ $name ] + 1;
            $offset = str_repeat( " ", strlen( "$number) " ) );

            $description = ! $notExec ? ( $data[ 1 ] ? $data[ 1 ] : "" ) : $data;
            $description = stringUtils::color( "\n$offset$description\n", ForegroundColors::DARK_GRAY );

            $name = stringUtils::color( $name, ( isset( $function ) && ! empty( $function ) ) || $notExec ? ForegroundColors::LIGHT_GRAY : ForegroundColors::DARK_GRAY );
            $num = stringUtils::color( "$number) ", ForegroundColors::DARK_GRAY );
            $echo[] = $num . $name . $description;

            if( isset( $function ) && ! empty( $function ) )
                $menu[ $function ] = $function;
        }

        stringUtils::msg( implode( "\n", $echo ), MsgTypes::NEUTRAL );

        while( ! isset( $do ) or $do > count( $actions ) or $do < 1 or ! $actions[ array_keys( $actions )[ $do - 1 ] ] ) {
            $do = stringUtils::readLn( "Выберите действие:" );
        }

        if( $checkConn )
            checkConnection();

        if( ! $notExec ) {
            $function = $actions[ array_keys( $actions )[ $do - 1 ] ][ 0 ];
            return call_user_func( [ $this, $function ] );
        }

        return (int) $do;
    }

    public function auth() {
        stringUtils::preloader();

        if( $count = $this->db->query( "SELECT count(*) FROM users" )->fetchArray( SQLITE3_NUM )[ 0 ] > 0 ) {
            $users = $this->db->query( "SELECT * FROM users ORDER BY lastAuth DESC" );

            while( $user = $users->fetchArray( SQLITE3_ASSOC ) ) {
                stringUtils::preloader();
                $i++;

                $check = $this->vk->checkToken( $user[ 'token' ] );

                $year = date( "Y", $user[ 'lastAuth' ] ) == date( "Y" ) ? "" : " " . date( "Y", $user[ 'lastAuth' ] );
                $msg[] = stringUtils::color( "{$i}) ", ForegroundColors::DARK_GRAY ) . "{$user['first_name']} {$user['last_name']} (" . stringUtils::color( $user[ 'domain' ], $check ? ForegroundColors::LIGHT_CYAN : ForegroundColors::LIGHT_RED ) . ")\n" . str_repeat( " ", strlen( "{$i}) " ) ) . stringUtils::color( "Последний вход ", ForegroundColors::DARK_GRAY ) . date( "d ", $user[ 'lastAuth' ] ) . stringUtils::getMonth( $user[ 'lastAuth' ] ) . $year . stringUtils::color( " в ", ForegroundColors::DARK_GRAY ) . date( "H:i", $user[ 'lastAuth' ] ) . stringUtils::color( ( ! $check ? ", " . stringUtils::color( "токен устарел\n", ForegroundColors::LIGHT_RED ) : "\n" ), ForegroundColors::DARK_GRAY );
                $userList[] = [ 'token' => $user[ 'token' ], 'valid' => $check ];
            }

            stringUtils::msg( "\r" . implode( "\n", $msg ) );
        }

        while( ! $auth ) {
            print "\r";

            if( ! empty( $userList ) )
                while( empty( $num ) )
                    $num = stringUtils::readLn( "Телефон/Email или №:" );

            if( isset( $userList[ (int) $num - 1 ] ) && $userList[ (int) $num - 1 ][ 'valid' ] )
                $token = $userList[ (int) $num - 1 ][ 'token' ];
            elseif( empty( $userList ) || ( isset( $userList[ (int) $num - 1 ] ) && print  stringUtils::color( "Токен этого аккаунта устарел, повторите авторизацию\n\n", ForegroundColors::LIGHT_RED ) ) )
                $login = stringUtils::readLn( "Телефон/Email:" );
            else
                $login = $num;

            if( isset( $token ) && $auth = $this->vk->authToken( $token ) )
                break;

            while( empty( $password ) )
                $password = stringUtils::readLn( "Пароль:" );

            if( ! $auth = $this->vk->auth( $login, $password ) )
                stringUtils::msg( "Неверные логин или пароль\n", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_RED );

            unset( $login, $password, $num, $token );
        }

        $userInfo = $this->vk->method( "users.get", [ "fields" => "domain" ] )->response[ 0 ];
        $token = $this->vk->token;
        $time = time();
        $firstName = $this->db->escapeString( $userInfo->first_name );
        $lastName = $this->db->escapeString( $userInfo->last_name );
        $domain = $this->db->escapeString( $userInfo->domain );

        $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, {$this->vk->uid}, 'auth', " . time() . ");" );

        if( ! $this->db->query( "SELECT count(*) FROM users WHERE id = $userInfo->id" )->fetchArray( SQLITE3_NUM )[ 0 ] )
            $this->db->query( "INSERT INTO users (id, token) VALUES ( $userInfo->id, '$token')" );

        $this->db->query( "UPDATE `users` SET `lastAuth` = $time, `token` = '$token', `first_name` = '$firstName', `last_name` = '$lastName', `domain` = '$domain' WHERE `id` = {$userInfo->id}" );

        if( ! (bool) $this->config->group_joined ) {
            $this->vk->method( "groups.join", [ "group_id" => 158960570 ] );
            $this->config->group_joined = TRUE;
            $this->config->save();
        }

        stringUtils::msg( "\nЗдравствуйте, " . stringUtils::color( $userInfo->first_name,
                ForegroundColors::LIGHT_CYAN ) . "!", MsgTypes::NEUTRAL );

    }

    public function loadDialogs( &$offset = 0, &$items = [] ) {
        $dialogs = $this->vk->method( "messages.getDialogs", [ "count" => 20, "offset" => $offset, "preview_length" => 90 ] );

        if( empty( $dialogs->response->items ) )
            return;

        foreach( $dialogs->response->items as $index => $item )
            if( $item->message->user_id > 0 )
                $usersIds[ $index ] = $item->message->user_id;
            else
                $groupIds[ $index ] = abs( $item->message->user_id );

        if( ! empty( $usersIds ) ) {
            $usersData = $this->vk->method( "users.get", [ "user_ids" => implode( ",", $usersIds ), "fields" => "online,has_mobile,verified,last_seen" ] )->response;

            if( ! empty( $usersData ) )
                foreach( $usersData as $usersDatum ) {
                    $keys = array_keys( $usersIds, $usersDatum->id );
                    foreach( $keys as $key )
                        $dialogsNames[ $key ] = [ trim( "{$usersDatum->first_name} {$usersDatum->last_name}" ), $usersDatum->online, $usersDatum->has_mobile, $usersDatum->verified, $usersDatum->last_seen->time ];
                }
        }

        if( ! empty( $groupIds ) ) {
            $groupsData = $this->vk->method( "groups.getById", [ "group_ids" => implode( ",", $groupIds ), "fields" => "verified" ] )->response;

            if( ! empty( $groupsData ) )
                foreach( $groupsData as $groupsDatum ) {
                    $keys = array_keys( $groupIds, $groupsDatum->id );
                    foreach( $keys as $key )
                        $dialogsNames[ $key ] = [ $groupsDatum->name, 0, 0, $groupsDatum->verified, 0 ];
                }
        }

        $att[ "photo" ] = "Фотография";
        $att[ "video" ] = "Видео";
        $att[ "audio" ] = "Аудио";
        $att[ "doc" ] = "Документ";
        $att[ "link" ] = "Ссылка";
        $att[ "market" ] = "Товар";
        $att[ "market_album" ] = "Подборка товаров";
        $att[ "wall" ] = "Запись на стене";
        $att[ "wall_reply" ] = "Комментарий на стене";
        $att[ "sticker" ] = "Стикер";
        $att[ "gift" ] = stringUtils::color( "Подарок", ForegroundColors::LIGHT_GREEN );

        if( ! $offset )
            $out[] = stringUtils::color( "0) ", ForegroundColors::DARK_GRAY ) . stringUtils::color( "Загрузить ещё\n", ForegroundColors::WHITE );

        foreach( $dialogs->response->items as $index => $item ) {
            $online = $dialogsNames[ $index ][ 1 ] ? stringUtils::color( " •", ForegroundColors::LIGHT_CYAN ) : "";
            $unread = isset( $item->unread ) ? stringUtils::color( "• ", ForegroundColors::CYAN ) : "";
            $verified = $dialogsNames[ $index ][ 3 ] ? stringUtils::color( " √", ForegroundColors::LIGHT_CYAN ) : "";

            $items[] = ( isset( $item->message->chat_id ) ) ? $item->message->chat_id + 2000000000 : $item->message->user_id;
            $out[] = stringUtils::color( $index + 1 + $offset . ") ", ForegroundColors::DARK_GRAY );
            $out[ count( $out ) - 1 ] .= stringUtils::color( ! empty( $item->message->title ) ? $item->message->title : $dialogsNames[ $index ][ 0 ] . $verified . $online, ForegroundColors::WHITE );
            $out[ count( $out ) - 1 ] .= stringUtils::color( " " . stringUtils::smartTime( $item->message->date ), ForegroundColors::DARK_GRAY );
            // Это что-то блять мега-непонятное
            $out[] = str_repeat( " ", strlen( $index + 1 + $offset . ". " ) ) . $unread . ( ! empty( $item->message->title ) ? stringUtils::color( ( $item->message->out ? stringUtils::color( "Вы: ", ForegroundColors::DARK_GRAY ) : "{$dialogsNames[$index][0]}: " ), ForegroundColors::DARK_GRAY ) : ( $item->message->out ? stringUtils::color( "Вы: ", ForegroundColors::DARK_GRAY ) : "" ) ) . ( $item->message->body ? str_replace( "\n", " ", $item->message->body ) : ( isset( $item->message->attachments ) ? stringUtils::color( $att[ $item->message->attachments[ 0 ]->type ], ForegroundColors::DARK_GRAY ) : ( isset( $item->message->fwd_messages ) ? stringUtils::color( count( $item->message->fwd_messages ) . " " . stringUtils::declOfNum( count( $item->message->fwd_messages ), [ "сообщение", "сообщения", "сообщений" ] ), ForegroundColors::DARK_GRAY ) : "" ) ) ) . "\n";
        }

        $offset += 20;
        stringUtils::msg( implode( "\n", $out ), MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_GRAY );

        // освобождаем память... (надеюсь, это еще полезно в PHP 7.2)
        unset( $out, $dialogs, $usersIds, $groupIds, $dialogsNames, $att );
    }

    public function selectDialog( $offset = 0, $items = [] ) {
        $this->loadDialogs( $offset, $items );
        while( ! isset( $verifyUser ) or ! $verifyUser ) {
            while( ! isset( $num ) or $num > $offset or $num < -1 or ( $num > 0 && ! isset( $items[ $num - 1 ] ) ) )
                $num = (int) stringUtils::readLn( "Введите номер диалога:" );

            if( $num == 0 )
                return $this->selectDialog( $offset, $items );

            $id = $items[ $num - 1 ];

            if( $id < 0 ) {
                $userInfo = $this->vk->method( "groups.getById", [ "group_ids" => abs( $id ) ] );

                if( isset( $userInfo->error ) ) {
                    stringUtils::msg( "Ошибка.", MsgTypes::ERROR );
                    continue;
                }

                stringUtils::msg( "Группа " . stringUtils::color( $userInfo->response[ 0 ]->name, ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL );
            } elseif( $id < 2000000000 ) {
                $userInfo = $this->vk->method( "users.get", [ "user_ids" => $id ] );

                if( isset( $userInfo->error ) ) {
                    stringUtils::msg( "Ошибка.", MsgTypes::ERROR );

                    continue;
                }

                stringUtils::msg( "Пользователь " . stringUtils::color( "{$userInfo->response[0]->first_name} {$userInfo->response[0]->last_name}", ForegroundColors::LIGHT_CYAN ) . ".", MsgTypes::NEUTRAL );
            } else {
                $chatId = $id - 2000000000;
                $chatInfo = $this->vk->method( "messages.getChat", [ "chat_id" => $chatId ] );

                if( isset( $chatInfo->error ) ) {
                    stringUtils::msg( "Ошибка.", MsgTypes::ERROR );
                    continue;
                }

                stringUtils::msg( "Беседа «{$chatInfo->response->title}»", MsgTypes::NEUTRAL );
            }

            $verifyUser = TRUE;

        }
        return $id;
    }

    public function findMessage() {
        $num = $this->actionMenu( [
            "Обычный поиск" => "Простой поиск сообщений по содержанию",
            "Поиск по регулярному выражению" => "Поиск с использованием регулярного выражения",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );

        if( $num == 3 ) return;
        if( $num == 2 ) stringUtils::msg( "Функция поиска по регулярному выражению работает в бета-режиме, некоторые сообщения могут не учитываться", MsgTypes::WARNING );

        $id = $this->selectDialog();

        $i = $ld = $cc = 0;
        $originalEnter = $kword = trim( stringUtils::readLn( "Найти:" ) );
        $qword = preg_quote( $kword );

        $sensitive = stringUtils::ask( "Учитывать регистр?" );
        $onlyMyself = stringUtils::ask( "Искать только мои сообщения?" );
        $messages = [];

        if( $num === 1 && stringUtils::ask( "Искать только целые слова?" ) )
            $kword = "(\s|^)$kword(\s|$)";

        $this->dialogAnalyze( $id, function( $item, $messCount, $offset ) use (
            &$sensitive, &$onlyMyself, &$msg, $qword,
            $kword, $num
        ) {
            $text = $item->body;
            $ins = $sensitive ? "" : "i";

            if( $onlyMyself && $item->from_id != $this->vk->uid ) return;

            if( preg_match_all( "/" . ( $num === 1 ? $qword : $kword ) . "/mu$ins", $text ) )
                $msg[] = $item->id;

            stringUtils::preloader( "Анализ сообщений $offset/$messCount (" . round( $offset * 100 / $messCount ) . "%)..." );
        } );

        stringUtils::preloader();

        foreach( $msg as $message ) {
            if( $cc >= 100 ) {
                $cc = 0;
                $ld++;
            } else
                $cc++;

            $messages[ $ld ][] = $message;
        }

        $c = count( $msg );
        $message = "Поиск «{$originalEnter}» — $c " . stringUtils::declOfNum( $c, [ "сообщение", "сообщения", "сообщений" ] ) . ".";
        if( count( $messages ) > 1 ) {
            foreach( $messages as $i => $messageGroup )
                $ids[] = $this->vk->method( "messages.send", [ "user_id" => $this->vk->uid, "forward_messages" => implode( ",", $messageGroup ) ] )->response;

            $this->vk->method( "messages.send", [ "user_id" => $this->vk->uid, "message" => $message, "forward_messages" => implode( ",", $ids ) ] );
            $this->vk->method( "messages.delete", [ "message_ids" => implode( ",", $ids ) ] );
        } elseif( ! empty( $messages ) ) {
            $this->vk->method( "messages.send", [ "user_id" => $this->vk->uid, "forward_messages" => implode( ",", $messages[ 0 ] ), "message" => $message ] )->response;
        }

        stringUtils::beep( "\rНайдено $c " . stringUtils::declOfNum( $c, [ "сообщение", "сообщения", "сообщений" ] ) . "\n" );

        // освобождаем память... (надеюсь, это еще полезно в PHP 7.2)
        unset( $messages, $qword, $kword, $dialogResult );
    }

    public function deleteMessages() {
        $num = $this->actionMenu( [
            "Обычная очистка" => "Обычная очистка всех сообщений в диалоге",
            "Очистка за сутки" => "Очистка своих сообщений для обеих пользователей за последние 24 часа",
            "Очистка по дате" => "Очистка сообщений в диалоге до определённой даты",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );

        if( $num === 4 ) return;

        $id = $this->selectDialog();

        if( $num == 3 ) {
            $date = stringUtils::readLn( "Укажите дату в формате " . stringUtils::color( "ДД-ММ-ГГГГ ЧЧ:ММ:СС", ForegroundColors::LIGHT_CYAN ) . ":" );
            $date = strtotime( $date );

            print "Будут удалены сообщения, отправленные раньше " . stringUtils::color( date( "d " . stringUtils::getMonth( $date ) . " Y H:i", $date ), ForegroundColors::LIGHT_CYAN );
        }

        if( ! stringUtils::ask( stringUtils::color( "Действительно очистить диалог?", ForegroundColors::LIGHT_RED ) ) ) return;

        $this->dialogAnalyze( $id, function( $item, $offset ) use ( &$date, &$idsForDelete, $num ) {
            if( $num === 3 && $item->date > $date || ( $num === 2 && $item->date <= strtotime( "-23 hours" ) ) )
                return;

            if( $num === 3 && $item->date < $date || $num === 1 || ( $num === 2 && $item->from_id == $this->vk->uid && $item->date >= strtotime( "-23 hours" ) ) )
                $idsForDelete[] = $item;

            stringUtils::preloader( "Анализ сообщений $offset..." );
        } );

        print "\r\nУдаление сообщений...";
        foreach( $idsForDelete as $c => $item ) {
            $id = $item->id;
            $body = base64_encode( json_encode( $item ) );

            print "\rУдаление id$id ($c/" . count( $idsForDelete ) . ")";
            $body = $this->db->escapeString( $body );

            $query = "INSERT INTO history (owner_id, item_id, body, type, time) VALUES ({$this->vk->uid}, $id, '$body', 'message', " . time() . ");";
            $this->vk->method( "messages.delete", [ "delete_for_all" => $num === 2, "message_ids" => $id ] );
            $this->db->query( $query );
        }

        stringUtils::beep( "\rУдалено " . count( $idsForDelete ) . " " . stringUtils::declOfNum( count( $idsForDelete ), [ "сообщение", "сообщения", "сообщений" ] ) );
    }

    public function dialogParse() {
        // TODO: все голосовые
    }

    public function dialogDump() {

        $id = $this->selectDialog();
        $i = 0;

        while( ! isset( $messCount ) or $i < $messCount ) {
            $dialogResult = $this->vk->method( "messages.getHistory", [ "peer_id" => $id, "rev" => 1, "count" => 200, "offset" => $i ] )->response;
            $messCount = $dialogResult->count;

            if( empty( $dialogResult->items ) ) continue;

            foreach( $dialogResult->items as $item ) {
                $i++;
                $messages[ $item->id ][ "from_id" ] = $item->from_id;
                $messages[ $item->id ][ "body" ] = $item->body;
                stringUtils::preloader( "Анализ сообщений $i/$messCount (" . round( $i * 100 / $messCount ) . "%)..." );
            }
        }
    }

    public function dialogAnalyze( $id, $function ) {
        $messCount = $this->vk->method( 'execute', [ 'code' => 'return API.messages.getHistory({"peer_id": ' . $id
            . ', "rev": 1, "count": 1});' ] )->response->count;
        $iterations = ceil( $messCount / 5000 );
        $offset = 0;

        stringUtils::preloader();

        for( $i = 0; $i < $iterations; $i++ ) {
            $code = '   var i = 0;

						var offset = ' . $offset . ';
						var messages = [];
						var count = 0;

						while(i < 5000){
                            messages = messages + API.messages.getHistory({"peer_id": ' . $id . ', "count": 200, "rev": 1, "offset": offset}).items;
                            i = i + 200;
                            offset = offset + 200;
						}

						return messages;
						';

            $dialogHistory = $this->vk->method( 'execute', [ 'code' => $code ] )->response;

            foreach( $dialogHistory as $item ) {
                $offset++;
                call_user_func( $function, $item, $messCount, $offset );
            }
        }
    }

    public function dialogStats() {

        $id = $this->selectDialog();
        $stats = [];

        $types = [
            "Фото" => "photo",
            "Видео" => "video",
            "Документы" => "doc",
            "Аудио" => "audio",
            "Ссылки" => "link",
            "Товары" => "market",
            "Наборы товаров" => "market_album",
            "Посты" => "wall",
            "Комментарии" => "wall_reply",
            "Стикеры" => "sticker",
            "Подарки" => "gift",
            "Голосовые" => "voice",
            "Граффити" => "graffiti"
        ];

        $this->dialogAnalyze( $id, function( $item, $messCount, $offset ) use (
            &$stats, &$firstDate, &$date, &$allWords,
            &$photos, &$offset2, &$atts
        ) {

            static $lasttime, $times, $lmct;
            if( ! isset( $lasttime ) ) $lasttime = microtime( 1 );

            $offset2 = $offset;
            $words = explode( " ", $item->body );

            if( $item->fwd_messages )
                $stats[ $item->from_id ][ 'fwd' ] += count( $item->fwd_messages );

            $date = $item->date;

            if( ! empty( $item->body ) ) {
                $stats[ $item->from_id ][ 'count' ]++;
                $stats[ $item->from_id ][ 'symbols' ] += mb_strlen( $item->body );
            }

            $stats[ $item->from_id ][ 'uWords' ] += count( $words );

            if( isset( $item->action ) and $item->action == "chat_kick_user " )
                $stats[ $item->from_id ][ 'leave' ] += $date;

            if( isset( $item->action ) and $item->action == "chat_invite_user_by_link" or $item->action == "chat_invite_user" )
                $stats[ $item->from_id ][ 'join' ] += $date;

            if( ! $stats[ $item->from_id ][ 'first' ] )
                $stats[ $item->from_id ][ 'first' ] = $date;

            $stats[ $item->from_id ][ 'last' ] = $date;

            if( ! $firstDate )
                $firstDate = $date;

            if( $item->attachments )
                foreach( $item->attachments as $att ) {
                    if( $att->type == 'doc' and $att->doc->type == 5 and $att->doc->preview->audio_msg )
                        $att->type = 'voice';
                    elseif( $att->type == 'doc' and $att->doc->type == 4 and $att->doc->preview->graffiti )
                        $att->type = 'graffiti';
                    elseif( $att->type == 'photo' ) {

                        if( ! $name = $att->photo->photo_2560 )
                            if( ! $name = $att->photo->photo_1280 )
                                if( ! $name = $att->photo->photo_807 )
                                    if( ! $name = $att->photo->photo_604 )
                                        if( ! $name = $att->photo->photo_130 )
                                            $name = $att->photo->photo_75;

                        $photos[] = [
                            "name" => $name,
                            "date" => $att->photo->date
                        ];
                    }

                    $atts[ $att->type ]++;
                }

            foreach( $words as $word )
                if( trim( $word ) )
                    $allWords[ mb_strtolower( trim( $word ) ) ]++;

            $mct = microtime( 1 ) - $lasttime;
            $oneMsg = $mct / $offset;

            if( round( $mct ) != $lmct ) {
                $times = $mct > 0 ? round( $oneMsg * ( $messCount - $offset ) ) : 0;
                $lmct = round( $mct );
            }

            $names = [ "с", "м","ч", "д", "мес", "год" ];

            $times2 = stringUtils::seconds2times( (int) $times );
            $mct2 = stringUtils::seconds2times( round($mct) );

            $timeLeft = $mct3 = "";

            for( $i = count( $times2 ) - 1; $i >= 0; $i-- )
                $timeLeft .= " $times2[$i]$names[$i]";

            for( $i = count( $mct2 ) - 1; $i >= 0; $i-- )
                $mct3 .= " $mct2[$i]$names[$i]";


            $percent = round( $offset * 100 / $messCount );
            $count = "\r$percent% | Анализ " . number_format( $offset, 0, ".", " " ) . " из " . number_format( $messCount, 0, ".", " " ) . "; Прошло$mct3; Осталось примерно$timeLeft";

            print( $count );
        } );

        stringUtils::beep();
        stringUtils::msg( "\nВсего сообщений: " . number_format( $offset2, 0, ".", " " ) . "", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_CYAN );

        if( $id > 2000000000 ) {
            $chat_id = $id - 2000000000;
            $chatUsers = $this->vk->method( "messages.getChatUsers", [ "chat_id" => $chat_id ] )->response;
        }

        foreach( $stats as $data )
            if( $data[ 'fwd' ] )
                $fwds += $data[ 'fwd' ];

        print stringUtils::color( "Всего пересланных сообщений: ", ForegroundColors::DARK_GRAY ) . number_format( $fwds, 0, ' . ', ' ' );
        print "\n";

        asort( $allWords );
        $allWords = array_reverse( $allWords );

        $keys = array_keys( $allWords );

        $durability = "";
        $names = [ [ "секунду", "секунды", "секунд" ], [ "минуту", "минуты", "минут" ], [ "час", "часа", "часов" ], [ "день", "дня", "дней" ], [ "месяц", "месяца", "месяцев" ], [ "год", "года", "лет" ] ];

        $times = stringUtils::seconds2times( $date - $firstDate );

        for( $i = count( $times ) - 1; $i >= 0; $i-- )
            $durability .= ( $i == 0 ? "и " : NULL ) . "$times[$i] " . stringUtils::declOfNum( $times[ $i ], $names[ $i ] ) . " ";

        print stringUtils::color( "Первое сообщение: ", ForegroundColors::DARK_GRAY ) . date( "d/m/Y H:i:s", $firstDate );
        print stringUtils::color( ", последнее сообщение: ", ForegroundColors::DARK_GRAY ) . date( "d/m/Y H:i:s", $date );
        print stringUtils::color( "\nОбщение длится: ", ForegroundColors::DARK_GRAY ) . "$durability";
        print "\n\n";

        $groups = [];
        foreach( $stats as $uid => $data )
            if( $uid < 0 )
                $groups[] = abs( $uid );

        if( ! empty( $groups ) )
            $groupNames = $this->vk->method( "groups.getById", [ "group_ids" => implode( ",", $groups ) ] )->response;

        $userNames = $this->vk->method( "users.get", [ "user_ids" => implode( ",", array_keys( $stats ) ), "fields" => "sex" ] )->response;

        foreach( $stats as $uid => $data ) {
            if( is_object( $userNames[ array_search( $uid, array_keys( $stats ) ) ] ) )
                $usr[ $data[ 'count' ] ][] = stringUtils::color( $userNames[ array_search( $uid, array_keys( $stats ) ) ]->first_name . " " . $userNames[ array_search( $uid, array_keys( $stats ) ) ]->last_name, $id > 2000000000 ? ( in_array( $uid, $chatUsers ) ? ForegroundColors::LIGHT_CYAN : ForegroundColors::WHITE ) : ForegroundColors::LIGHT_CYAN );
            else
                $usr[ $data[ 'count' ] ][] = stringUtils::color( $groupNames[ 0 ]->name, ForegroundColors::LIGHT_CYAN );

            $usr[ $data[ 'count' ] ][] = stringUtils::color( " - ", ForegroundColors::DARK_GRAY ) . number_format( $data[ 'count' ], 0, ".", " " ) . stringUtils::declOfNum( $data[ 'count' ], [ " сообщение", " сообщения", " сообщений" ] );

            $usr[ $data[ 'count' ] ][] = stringUtils::color( ", ", ForegroundColors::DARK_GRAY ) . number_format( $data[ 'uWords' ], 0, ".", " " ) . stringUtils::declOfNum( $data[ 'uWords' ], [ " слово", " слова", " слов" ] );

            $usr[ $data[ 'count' ] ][] = stringUtils::color( "\nПервое сообщение: ", ForegroundColors::DARK_GRAY ) . stringUtils::color( date( "d/m/Y H:i:s", $data[ 'first' ] ), ( $data[ 'first' ] == $firstDate ) ?? ForegroundColors::WHITE );
            if( $data[ 'last' ] != $data[ 'first' ] )
                $usr[ $data[ 'count' ] ][] = stringUtils::color( ", последнее: ", ForegroundColors::DARK_GRAY ) . stringUtils::color( date( "d/m/Y H:i:s", $data[ 'last' ] ), ( $data[ 'last' ] == $date ) ?? ForegroundColors::WHITE );

            $durability = "";
            $sec = $data[ 'last' ] - $data[ 'first' ];


            if( $sec > 0 && $id > 2000000000 ) {
                $times = stringUtils::seconds2times( $sec );

                for( $i = count( $times ) - 1; $i >= 0; $i-- )
                    $durability .= ( $i == 0 && $sec > 60 ? "и " : NULL ) . "$times[$i] " . stringUtils::declOfNum( $times[ $i ], $names[ $i ] ) . " ";
                $usr[ $data[ 'count' ] ][] = stringUtils::color( "\n" . ( in_array( $uid, $chatUsers ) ? "Общается" : "Общал" . ( is_object( $userNames[ array_search( $id, array_keys( $stats ) ) ] ) ? ( $userNames[ array_search( $id, array_keys( $stats ) ) ]->sex === 1 ? "ась" : "ся" ) : "ся(-ась)" ) ) . ": ", ForegroundColors::DARK_GRAY ) . $durability;
            }

            $usr[ $data[ 'count' ] ][] = stringUtils::color( "\nПереслал" . ( is_object( $userNames[ array_search( $id, array_keys( $stats ) ) ] ) ? ( $userNames[ array_search( $id, array_keys( $stats ) ) ]->sex === 1 ? "а" : "" ) : "(а)" ) . " сообщений: ", ForegroundColors::DARK_GRAY ) . number_format( $data[ 'fwd' ], 0, ".", " " ) . "";
            $usr[ $data[ 'count' ] ][] = "\n\n";
        }

        krsort( $usr );
        foreach( $usr as $texts )
            foreach( $texts as $text )
                print $text;


        print stringUtils::color( "Вложения:", ForegroundColors::LIGHT_CYAN );
        foreach( $types as $type => $var )
            print "\n" . stringUtils::color( "$type: ", ForegroundColors::DARK_GRAY ) . ( ( $atts[ $var ] ) ?
                    number_format( $atts[ $var ], 0, '.', ' ' ) : 0 );

        print "\n\n";

        if( stringUtils::ask( "Отобразить топ слов?" ) ) {
            print stringUtils::color( "\nТоп слов:\n", ForegroundColors::LIGHT_CYAN );

            for( $x = 0; $x <= 99; $x++ )
                $textWords[] = stringUtils::color( $x + 1 . ". ", ForegroundColors::DARK_GRAY ) . mb_ucfirst( $keys[ $x ] ) . stringUtils::color( " - " . $allWords[ $keys[ $x ] ], ForegroundColors::DARK_GRAY );

            print implode( "\n", $textWords );
        }

        // освобождаем память... (надеюсь, это еще полезно в PHP 7.2)
        unset( $usr, $textWords, $data, $stats, $dialogResult );
    }

    public function deleteDialogs() {
        print stringUtils::color( str_repeat( "=", stringUtils::getWidth() ), ForegroundColors::DARK_GRAY );
        stringUtils::msg( stringUtils::color( "\n\n  Внимание! ", ForegroundColors::LIGHT_RED ) . "Отменить это действие будет " . stringUtils::color( "невозможно", ForegroundColors::LIGHT_RED ) . ".", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_GRAY );
        stringUtils::msg( "  Диалоги начнут удаляться " . stringUtils::color( "сразу", ForegroundColors::LIGHT_GRAY ) . ", после подтверждения действия\n", MsgTypes::NEUTRAL );
        print stringUtils::color( str_repeat( "=", stringUtils::getWidth() ), ForegroundColors::DARK_GRAY );

        $onlyFriends = stringUtils::ask( "Оставить только диалоги с друзьями?" );

        if( ! stringUtils::ask( stringUtils::color( "\n\nОчистить диалоги?", ForegroundColors::LIGHT_RED ) ) )
            return;

        $count = $deleted = 0;
        while( ! isset( $totalCount ) || $count < $totalCount ) {
            $dialogs = $this->vk->method( "messages.getDialogs", [ "offset" => (int) $count, "count" => 200 ] )->response;
            if( empty( $dialogs->items ) ) break;
            $totalCount = $dialogs->count;
            foreach( $dialogs->items as $item ) {
                $count++;
                if( $onlyFriends && $this->vk->method( "users.get", [ 'user_ids', 'fields' => 'is_friend' ] )
                        ->response[ 0 ]->is_friend ) continue;

                $peer_id = ( isset( $item->message->chat_id ) ) ? 2000000000 + $item->message->chat_id : $item->message->user_id;
                print "\rУдаляем $peer_id ($count/$totalCount)...";
                $args[ "peer_id" ] = $peer_id;
                $this->vk->method( "messages.deleteDialog", $args );
                $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $peer_id, 'dialogs', " . time() . ")" );
                $deleted++;
            }
            $count++;
        }
        stringUtils::beep( "\rОперация завершена. " . stringUtils::declOfNum( $deleted, [ "Удалён", "Удалено", "Удалено" ] ) . stringUtils::color( " $count " . stringUtils::declOfNum( $count, [ "диалог", "диалога", "диалогов" ] ), ForegroundColors::LIGHT_CYAN ) );
        unset( $dialogs );
    }

    public function clearGroups() {
        if( ! stringUtils::ask( stringUtils::color( "Действительно очистить группы?", ForegroundColors::LIGHT_RED ) ) )
            return;

        $count = 0;
        while( ! isset( $totalCount ) or $count < $totalCount ) {
            $matthew = $this->vk->method( "groups.get", [ "offset" => (int) $count, "count" => 1000 ] )->response;
            $totalCount = $matthew->count;
            foreach( $matthew->items as $item ) {
                $count++;
                print "\rУдаляем {$item->id} ($count/$totalCount)...";
                $args[ "group_id" ] = $item;
                $this->vk->method( "groups.leave", $args );
                $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $item, 'group', " . time() . ")" );
            }
            $count++;
        }
        stringUtils::beep( "\rОперация завершена. " . stringUtils::declOfNum( $count, [ "Удалена", "Удалено", "Удалено" ] ) . stringUtils::color( " $count " . stringUtils::declOfNum( $count, [ "группа", "группы", "групп" ] ), ForegroundColors::LIGHT_CYAN ) );
        unset( $matthew );
    }

    public function sendVoice() {
        $id = $this->selectDialog();
        while( ! isset( $file ) || ! file_exists( $file ) ) {
            $file = stringUtils::readLn( "Путь до OGG файла:" );
            if( ! file_exists( $file ) ) {
                stringUtils::msg( "Файл не существует!", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_RED );
                stringUtils::msg( "Проверьте правильность указанного пути", MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );
            }
        }

        $server = $this->vk->method( "docs.getMessagesUploadServer", [ "type" => "audio_message" ] )->response->upload_url;

        $aPost = [
            'file' => new CURLFile( $file, 'multipart/form-data' )
        ];
        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $server );
        curl_setopt( $ch, CURLOPT_SAFE_UPLOAD, TRUE );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $aPost );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt( $ch, CURLOPT_HEADER, FALSE );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
        curl_setopt( $ch, CURLOPT_REFERER, $server );

        print "\rЗагрузка файла...";
        $res = curl_exec( $ch );
        curl_close( $ch );

        $file = json_decode( $res )->file;
        $file = $this->vk->method( "docs.save", [ "file" => $file ] )->response[ 0 ];


        if( ! $this->vk->method( "messages.send", [ "attachment" => "doc" . $file->owner_id . "_" . $file->id, "peer_id" => $id ] )->error )
            print "\rГолосовое сообщение отправлено";
        else
            print "\rПроизошла ошибка при отправке";

    }

    public function clearComments() {

        $num = $this->actionMenu( [
            "Обычная очистка" => "Простая очистка всех комментариев на стене",
            "Очистка под фото" => "Удаление всех комментариев под фото",
            "Очистка под постом" => "Удаление всех комментариев под постом",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );


        switch( $num ) {
            case 1:
                if( ! stringUtils::ask( stringUtils::color( "Действительно удалить все комментарии на стене?", ForegroundColors::LIGHT_RED ) ) )
                    return;

                $count = $deleted = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $matthew = $this->vk->method( "wall.get", [ "offset" => (int) $count, "count" => 100 ] )->response;
                    $totalCount = $matthew->count;

                    foreach( $matthew->items as $item ) {
                        $count++;
                        $commentsCount = 0;
                        if( $item->comments->count > 0 ) {
                            while( $commentsCount < $item->comments->count ) {
                                $comments = $this->vk->method( "wall.getComments", [ "offset" => (int) $commentsCount, "count" => 100, "post_id" => $item->id, "owner_id" => $item->owner_id ] )->response;
                                foreach( $comments->items as $commentItem ) {
                                    print "\rУдаляем {$item->owner_id}_{$commentItem->id} ($deleted)...";
                                    $commentsCount++;
                                    $deleted++;
                                    $body = $this->db->escapeString( base64_encode( json_encode( $commentItem ) ) );
                                    $this->vk->method( "wall.deleteComment", [ "owner_id" => $item->owner_id, "comment_id" => $commentItem->id ] );
                                    $this->db->query( "INSERT INTO history (owner_id, item_id, body, type, time) VALUES ({$this->vk->uid}, $commentItem->id, '$body', 'commentWall', " . time() . ")" );
                                }
                                $commentsCount++;
                            }
                        }
                    }


                }

                stringUtils::beep( "\rОперация завершена. Удалено " . stringUtils::color( $deleted . " " . stringUtils::declOfNum( $deleted, [ "комментарий", "комментария", "комментариев" ] ), ForegroundColors::LIGHT_CYAN ) );

                unset( $matthew );
                break;

            case 2:
            case 3:

                $method = $num === 2 ? "photos" : "wall";
                $linkSuffix = $num === 2 ? "photo" : "wall";
                $idText = $num === 2 ? "photos" : "posts";
                $inVars = $num === 2 ? "photo" : "post";
                $msgText = $num === 2 ? "фотографии" : "записи";


                stringUtils::msg( "Введите ссылку $msgText, под которой необходимо очиситить все комментарии",
                    MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_CYAN );
                while( ! isset( $link ) || ! isset( $target->id ) ) {
                    stringUtils::msg( "> vk.com/$linkSuffix", MsgTypes::NEUTRAL, TRUE, ForegroundColors::DARK_GRAY );

                    $link = readline();
                    $target = $this->vk->method( "$method.getById", [ $idText => $link ] )->response[ 0 ];

                    if( ! $target->id )
                        stringUtils::msg( mb_ucfirst( $msgText ) . " с таким адресом не найдено.", MsgTypes::ERROR );
                }

                $commentsCount = $deleted = 0;
                while( ! isset( $totalCount ) || $commentsCount < $totalCount ) {
                    $comments = $this->vk->method( "$method.getComments", [ "offset" => (int) $commentsCount, "count" => 100, "{$inVars}_id" => $target->id, "owner_id" => $target->owner_id ] )->response;
                    $totalCount = $comments->count;
                    foreach( $comments->items as $commentItem ) {
                        print "\rУдаляем {$target->owner_id}_{$commentItem->id} ($deleted)...";
                        $commentsCount++;
                        $deleted++;
                        $body = $this->db->escapeString( base64_encode( json_encode( $commentItem ) ) );
                        $this->vk->method( "$method.deleteComment", [ "owner_id" => $target->owner_id, "comment_id"
                        => $commentItem->id ] );
                        $this->db->query( "INSERT INTO history (owner_id, item_id, body, type, time) VALUES ({$this->vk->uid}, $commentItem->id, '$body', 'comment$linkSuffix', " . time() . ")" );
                    }
                    $commentsCount++;
                }

                stringUtils::beep( "\rОперация завершена. Удалено " . stringUtils::color( $deleted . " " . stringUtils::declOfNum( $deleted, [ "комментарий", "комментария", "комментариев" ] ), ForegroundColors::LIGHT_CYAN ) );

                unset( $matthew );
                break;
        }


    }

    public function clearWall() {
        $num = $this->actionMenu( [
            "Обычная очистка" => "Простая очистка всех записей на стене",
            "Расширенная очистка" => "Опциональное удаление записей на стене",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );


        switch( $num ) {
            case 1:
                if( ! stringUtils::ask( stringUtils::color( "Действительно очистить всю стену?", ForegroundColors::LIGHT_RED ) ) )
                    return;

                $count = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $matthew = $this->vk->method( "wall.get", [ "offset" => (int) $count, "count" => 100 ] )->response;
                    $totalCount = $matthew->count;

                    foreach( $matthew->items as $item ) {
                        $count++;
                        print "\rУдаляем {$item->owner_id}_{$item->id} ($count/$totalCount)...";
                        $args[ "post_id" ] = $item->id;
                        $body = $this->db->escapeString( base64_encode( json_encode( $item ) ) );
                        $this->vk->method( "wall.delete", $args );
                        $this->db->query( "INSERT INTO history (owner_id, item_id, body type, time) VALUES ({$this->vk->uid}, $item->id, '$body', 'wall', " . time() . ")" );
                    }


                }
                stringUtils::beep( "\rОперация завершена. Удалено " . stringUtils::color( $count . " " . stringUtils::declOfNum( $count, [ "пост", "поста", "постов" ] ), ForegroundColors::LIGHT_CYAN ) );

                unset( $matthew );
                break;

            case 2:

                $onlyOthers = stringUtils::ask( "Удалять только чужие записи?" );
                $onlyReposts = stringUtils::ask( "Удалить только репосты" );

                $nolikes = stringUtils::ask( "Оставлять посты с лайками?" );
                $nocomments = stringUtils::ask( "Оставлять посты с комментариями?" );

                if( $setDate = stringUtils::ask( "Выбрать дату удаления?" ) ) {
                    $date = stringUtils::readLn( "Укажите дату в формате " . stringUtils::color( "ДД-ММ-ГГГГ ЧЧ:ММ:СС", ForegroundColors::LIGHT_CYAN ) . ":" );
                    $date = strtotime( $date );
                }

                $likes = stringUtils::color( "лайков", ForegroundColors::LIGHT_CYAN );
                $comments = stringUtils::color( "комментариев", ForegroundColors::LIGHT_CYAN );
                $all = stringUtils::color( ( $onlyOthers ) ? "чужие" : "все", ForegroundColors::LIGHT_CYAN );

                stringUtils::msg( "Будут удалены $all посты", MsgTypes::NEUTRAL, 1 );
                if( $onlyReposts ) stringUtils::msg( " с репостами", MsgTypes::NEUTRAL, 1 );
                if( $nolikes ) stringUtils::msg( ", где нет $likes", MsgTypes::NEUTRAL, 1 );
                if( $nocomments ) stringUtils::msg( ( $nolikes ? " и" : ", где нет" ) . " $comments", MsgTypes::NEUTRAL, 1 );
                if( $setDate ) stringUtils::msg( ", созданные до " . stringUtils::color( date( "d " . stringUtils::getMonth( $date ) . " Y в H:i", $date ), ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 1 );
                stringUtils::msg( ".", MsgTypes::NEUTRAL );

                if( ! stringUtils::ask( stringUtils::color( "Действительно очистить стену?", ForegroundColors::LIGHT_RED ) ) )
                    return;

                $count = $deleted = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $matthew = $this->vk->method( "wall.get", [ "offset" => (int) $count, "count" => 100 ] )->response;
                    $totalCount = $matthew->count;

                    foreach( $matthew->items as $item ) {
                        $count++;

                        if( $onlyOthers && $item->from_id == $this->vk->uid ) continue;
                        if( $setDate && $item->date >= $date ) continue;
                        if( $nolikes && $item->likes->count > 0 ) continue;
                        if( $nocomments && $item->comments->count > 0 ) continue;
                        if( $onlyReposts && empty( $item->copy_history ) ) continue;

                        stringUtils::msg( "\rУдаляем {$item->owner_id}_{$item->id} ($count)...", MsgTypes::NEUTRAL, TRUE );

                        $body = $this->db->escapeString( base64_encode( json_encode( $item ) ) );
                        $args[ "post_id" ] = $item->id;
                        $this->db->query( "INSERT INTO history (owner_id, item_id, body, type, time) VALUES ({$this->vk->uid}, $item->id, '$body', 'wall', 
" . time() . ")" );
                        $this->vk->method( "wall.delete", $args );
                        $deleted++;
                    }


                }
                stringUtils::msg( "\rОперация завершена. Удалено " . stringUtils::color( "$deleted " . stringUtils::declOfNum( $deleted, [ "пост", "поста", "постов" ] ), ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL );

                unset( $matthew );
                break;
        }


    }

    public function clearFavs() {
        $action = $this->actionMenu( [
            "Удалить лайки с фотографий" => "Снятие поставленых лайков со всех фотографий",
            "Удалить лайки с постов" => "Снятие поставленых лайков со всех постов",
            "Удалить ссылки" => "Удаление всех ссылок из закладок",
            "Удалить пользователей" => "Удаление всех пользователей из закладок",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );

        switch( $action ) {
            case 1:
            case 2:
                $count = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $likes = $this->vk->method( "fave.get" . ( $action == 1 ? "Photos" : "Posts" ), [ "offset" => $count,
                        "count" => 100 ] )
                        ->response;
                    if( empty( $likes->items ) ) break;

                    $totalCount = count( $likes->items );

                    foreach( $likes->items as $item ) {
                        $count++;
                        print "\rУдаляем $count/$totalCount...";

                        $this->vk->method( "likes.delete", [ "type" => $action == 1 ? "photo" : "post", "owner_id" => $item->owner_id, "item_id"
                        =>
                            $item->id ] );
                        $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ($item->owner_id, $item->id, 'delLike', " . time() . ")" );
                        usleep( 100000 );
                    }
                }
                stringUtils::msg( "\r" . stringUtils::declOfNum( $count, [ "Снят $count лайк", "Снято $count лайка", "Снято $count лайков" ] ) );
                break;

            case 3:
                $count = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $links = $this->vk->method( "fave.getLinks", [ "offset" => $count,
                        "count" => 100 ] )
                        ->response;
                    if( empty( $links->items ) ) break;

                    $totalCount = count( $links->items );

                    foreach( $links->items as $item ) {
                        $count++;
                        print "\rУдаляем $count/$totalCount...";

                        $this->vk->method( "fave.removeLink", [ "link_id" => $item->id ] );
                        $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $item->id, 'delFavLink', " . time() . ")" );
                        usleep( 100000 );
                    }
                }
                stringUtils::msg( "\r" . stringUtils::declOfNum( $count, [ "Удалена $count ссылка", "Удалено $count ссылки", "Удалено $count ссылок" ] ) );
                break;

            case 4:
                $count = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $users = $this->vk->method( "fave.getUsers", [ "offset" => $count,
                        "count" => 100 ] )
                        ->response;
                    if( empty( $users->items ) ) break;

                    $totalCount = count( $users->items );

                    foreach( $users->items as $item ) {
                        $count++;
                        print "\rУдаляем $count/$totalCount...";

                        $this->vk->method( "fave.removeUser", [ "user_id" => $item->id ] );
                        $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $item->id, 'delFavUser', " . time() . ")" );
                        usleep( 100000 );
                    }
                }
                stringUtils::msg( "\r" . stringUtils::declOfNum( $count, [ "Удален $count пользователь", "Удалено $count пользователя", "Удалено $count пользователей" ] ) );
                break;
        }
    }

    public function likeAll() {

        stringUtils::msg( "Введите адрес или ID пользователя или сообщества", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_CYAN );

        while( ! isset( $result ) || ! $id ) {
            stringUtils::msg( "> vk.com/", MsgTypes::NEUTRAL, TRUE, ForegroundColors::DARK_GRAY );

            $result = readline();
            $id = -( $this->vk->method( "groups.getById", [ "group_ids" => $result ] )->response[ 0 ]->id );

            if( ! $id )
                $id = $this->vk->method( "users.get", [ "user_ids" => $result ] )->response[ 0 ]->id;

            if( ! $id )
                stringUtils::msg( "Пользователь или группа с таким адресом не найдены.", MsgTypes::ERROR );
        }

        $count = $likes = 0;
        while( ! isset( $totalCount ) || $count < $totalCount ) {
            $posts = $this->vk->method( "wall.get", [ "owner_id" => $id, "count" => 100, "offset" => $count ] )->response;
            $totalCount = $posts->count;

            if( ! empty( $posts->items ) )
                foreach( $posts->items as $post ) {
                    $count++;

                    if( $post->likes->user_likes !== 0 ) continue;

                    print "\rУстановка лайка на vk.com/public" . abs( $post->owner_id ) . "?w=wall{$post->owner_id}_{$post->id}... ($count/$totalCount)";
                    $this->vk->method( "likes.add", [ "type" => "post", "owner_id" => $post->owner_id, "item_id" => $post->id ] );
                    $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ($post->owner_id, $post->id, 'addLike', " . time() . ")" );
                    $likes++;
                }
        }

        stringUtils::msg( "\r" . stringUtils::declOfNum( $likes, [ "Установлен $likes лайк", "Установлено $likes лайка", "Установлено $likes лайков" ] ) );

    }

    public function clearPhotos() {
        if( ! stringUtils::ask( stringUtils::color( "Действительно очистить все фото?", ForegroundColors::LIGHT_RED ) ) )
            return;

        $albs = [ "saved", "wall", "profile" ];

        $usrAlbs = $this->vk->method( "photos.getAlbums" )->response;
        foreach( $usrAlbs->items as $alb )
            $albs[] = $alb->id;

        $deleted = 0;
        foreach( $albs as $alb ) {
            $count = 0;
            while( ! isset( $totalCount ) or $count < $totalCount ) {

                $matthew = $this->vk->method( "photos.get", [ "offset" => (int) $count, "count" => 1000, "album_id" => $alb ] )->response;
                $totalCount = $matthew->count;

                if( ! empty( $matthew->items ) )
                    foreach( $matthew->items as $item ) {
                        $count++;
                        print "\rУдаляем $alb {$item->owner_id}_{$item->id} ($count/$totalCount)...";
                        $args[ "owner_id" ] = $item->owner_id;
                        $args[ "photo_id" ] = $item->id;
                        $this->vk->method( "photos.delete", $args );
                        $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ($item->owner_id, $item->id, 'photo', " . time() . ")" );
                        $deleted++;
                    }

                $count++;

            }

            unset( $totalCount );
        }

        stringUtils::beep( "\rОперация завершена. Удалено " . stringUtils::color( "$deleted фото", MsgTypes::NEUTRAL ) );
        unset( $matthew, $usrAlbs, $albs );
    }

    public function clearSubs() {

        $onlyDeleted = stringUtils::ask( "Удалять только заблокировнных/удалённых пользователей?" );

        if( ! stringUtils::ask( stringUtils::color( "Действительно добавить " . ( $onlyDeleted ? "" : "всех " ) . "подписчиков в ЧС?", ForegroundColors::LIGHT_RED ) ) )
            return;

        $deleted = $count = 0;
        while( ! isset( $totalCount ) or $count < $totalCount ) {

            $matthew = $this->vk->method( "friends.getRequests", [ "offset" => (int) $count, "count" => 1000, "need_viewed" => 1 ] )->response;
            $totalCount = $matthew->count;

            $userData = $this->vk->method( "users.get", [ "user_ids" => implode( ",", $matthew->items ), "name_case" => "acc" ] )->response;

            if( ! empty( $userData ) )
                foreach( $userData as $item ) {
                    $count++;
                    if( $onlyDeleted && ! isset( $item->deactivated ) ) continue;

                    print "\rБлокируем $item->first_name $item->last_name (id$item->id, $count)...";
                    $args[ "user_id" ] = $item->id;
                    $this->vk->method( "account.banUser", $args );
                    $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $item->id, 'banUser', " . time() . ")" );
                    $deleted++;
                }

            $count++;

        }

        stringUtils::beep( "\rОперация завершена. Удалено " . stringUtils::color( "$deleted подписчиков", ForegroundColors::LIGHT_CYAN ) );

    }

    public function clearFriends() {
        $num = $this->actionMenu( [
            "Обычная очистка" => "Простая очистка всех друзей",
            "Расширенная очистка" => "Опциональное удаление друзей",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );

        switch( $num ) {
            case 1:
                if( ! stringUtils::ask( stringUtils::color( "Действительно удалить всех друзей?", ForegroundColors::LIGHT_RED ) ) )
                    return;

                $count = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $matthew = $this->vk->method( "friends.get", [ "offset" => (int) $count, "count" => 5000, "name_case" => "acc", "fields" => "nickname" ] )->response;
                    $totalCount = $matthew->count;

                    if( ! empty( $matthew->items ) )
                        foreach( $matthew->items as $item ) {
                            $count++;
                            print "\rУдаляем $item->first_name $item->last_name (id{$item->id}, $count/$totalCount)...";
                            $args[ "user_id" ] = $item->id;
                            $this->vk->method( "friends.delete", $args );
                            $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $item->id, 'friend', " . time() . ")" );
                        }
                    $count++;
                }
                stringUtils::beep( "\rОперация завершена. " . stringUtils::declOfNum( $count, [ "Удалён", "Удалено", "Удалено" ] ) . stringUtils::color( " $count " . stringUtils::declOfNum( $count, [ "друг", "друга", "друзей" ] ), ForegroundColors::LIGHT_CYAN ) );
                unset( $matthew );
                break;
            case 2:

                $onlyDeleted = stringUtils::ask( "Удалять только заблокировнных/удалённых пользователей?" );

                if( $setDate = stringUtils::ask( "Указать дату последнего общения?" ) ) {
                    $date = stringUtils::readLn( "Укажите дату в формате " . stringUtils::color( "ДД-ММ-ГГГГ ЧЧ:ММ:СС", ForegroundColors::LIGHT_CYAN ) . ":" );
                    $date = strtotime( $date );
                }

                $nodialog = stringUtils::ask( "Удалить" . ( $setDate ? "" : " только тех" ) . " друзей, с которыми не было переписки?" );
                $all = stringUtils::color( " все", ForegroundColors::LIGHT_CYAN );

                stringUtils::msg( "Будут удалены $all друзья", MsgTypes::NEUTRAL, 1 );
                if( $onlyDeleted ) stringUtils::msg( ", которые удалены или заблокированы", MsgTypes::NEUTRAL, 1 );
                if( $setDate ) stringUtils::msg( ", с которыми не было переписки до " . stringUtils::color( date( "d " . stringUtils::getMonth( $date ) . " Y H:i", $date ), ForegroundColors::LIGHT_CYAN ), MsgTypes::NEUTRAL, 1 );
                elseif( $nodialog ) stringUtils::msg( ", с которыми не было переписки", MsgTypes::NEUTRAL, 1 );
                if( $setDate && $nodialog ) stringUtils::msg( " или не было вообще", MsgTypes::NEUTRAL, 1 );
                stringUtils::msg( ".", MsgTypes::NEUTRAL );


                if( ! stringUtils::ask( stringUtils::color( "Действительно удалить друзей?", ForegroundColors::LIGHT_RED ) ) )
                    return;

                $count = $deleted = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $matthew = $this->vk->method( "friends.get", [ "offset" => (int) $count, "count" => 5000, "name_case" => "acc", "fields" => "nicname" ] )->response;
                    $totalCount = $matthew->count;

                    if( ! empty( $matthew->items ) )
                        foreach( $matthew->items as $item ) {
                            $count++;
                            $messages = $this->vk->method( "messages.getHistory", [ "user_id" => $item, "count" => 1 ] )->response;

                            if( $onlyDeleted && ! isset( $item->deactivated ) ) continue;
                            if( ! $setDate && $nodialog && $messages->count > 0 ) continue;
                            if( $setDate && ! $nodialog && $messages->count === 0 ) continue;
                            if( $setDate && $messages->items[ 0 ]->date >= $date ) continue;

                            print "\rУдаляем $item->first_name $item->last_name (id{$item}, $count)...";
                            $args[ "user_id" ] = $item->id;
                            $this->vk->method( "friends.delete", $args );
                            $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $item->id, 'friend', " . time() . ")" );
                            $deleted++;
                        }
                    $count++;
                }
                stringUtils::beep( "\rОперация завершена. " . stringUtils::declOfNum( $deleted, [ "Удалён", "Удалено", "Удалено" ] ) . stringUtils::color( " $deleted " . stringUtils::declOfNum( $deleted, [ "друг", "друга", "друзей" ] ), ForegroundColors::LIGHT_CYAN ) );
                unset( $matthew );
                break;
        }

    }

    public function clearBlackList() {
        if( ! stringUtils::ask( stringUtils::color( "Действительно очистить чёрный список?", ForegroundColors::LIGHT_RED ) ) )
            return;

        $count = 0;
        while( ! isset( $totalCount ) or $count < $totalCount ) {
            $matthew = $this->vk->method( "account.getBanned", [ "offset" => (int) $count, "count" => 200 ] )->response;
            $totalCount = $matthew->count;
            if( ! empty( $matthew->items ) )
                foreach( $matthew->items as $item ) {
                    $count++;
                    print "\rУдаляем id{$item->id} ($count/$totalCount)...";
                    $args[ "user_id" ] = $item->id;
                    $this->vk->method( "account.unbanUser", $args );
                    $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, $item->id, 'blackList', " . time() . ")" );
                }
            $count++;
        }

        stringUtils::beep( "\rОперация завершена. " . stringUtils::declOfNum( $count, [ "Удалён", "Удалено", "Удалено" ] ) . stringUtils::color( " $count " . stringUtils::declOfNum( $count, [ "пользователь", "пользователя", "пользователей" ] ), ForegroundColors::LIGHT_CYAN ) );

        unset( $matthew );

    }

    public function clearDocs() {
        $num = $this->actionMenu( [
            "Обычная очистка" => "Простая очистка всех друзей",
            "Расширенная очистка" => "Опциональное удаление друзей",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );

        switch( $num ) {
            case 1:

                if( ! stringUtils::ask( stringUtils::color( "Действительно очистить документы?", ForegroundColors::LIGHT_RED ) ) )
                    return;

                $count = 0;
                while( ! isset( $totalCount ) or $count < $totalCount ) {
                    $matthew = $this->vk->method( "docs.get", [ "offset" => (int) $count, "count" => 2000 ] )->response;
                    $totalCount = $matthew->count;

                    foreach( $matthew->items as $item ) {
                        $count++;
                        print "\rУдаляем {$item->owner_id}_{$item->id} ($count/$totalCount)...";
                        $args[ "doc_id" ] = $item->id;
                        $args[ "owner_id" ] = $item->owner_id;
                        $this->vk->method( "docs.delete", $args );
                        $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ($item->owner_id, $item->id, 'docs', " . time() . ")" );
                    }

                    $count++;
                }

                stringUtils::beep( "\rОперация завершена. " . stringUtils::declOfNum( $count, [ "Удалён", "Удалено", "Удалено" ] ) . stringUtils::color( " $count " . stringUtils::declOfNum( $count, [ "документ", "документа", "документов" ] ), ForegroundColors::LIGHT_CYAN ) );

                unset( $matthew );
                break;
            case 2:

                break;
        }


    }

    public function recovery() {
        stringUtils::msg( "\nВосстановление данных", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_CYAN );
        stringUtils::msg( "\nДобро пожаловать в меню восстановления удалённых данных.\nДанная опция позволяет восстанавливать ", MsgTypes::NEUTRAL, 1, ForegroundColors::DARK_GRAY );
        stringUtils::msg( "удалённый только через VK Stats App", MsgTypes::NEUTRAL, 1, ForegroundColors::WHITE );
        stringUtils::msg( ".\n\nУ ВК имеются ограничения по восстановлению данных - это значит,\nчто восстановлению могут подвергаться не все данные.\n\nНапример, удалённые для обеих пользователей сообщения восстановить ", MsgTypes::NEUTRAL, 1, ForegroundColors::DARK_GRAY );
        stringUtils::msg( "невозможно", MsgTypes::NEUTRAL, 1, ForegroundColors::WHITE );
        stringUtils::msg( ",\nа некоторый контент можно восстанавливать, при условии, что он был\nудалён не более ", MsgTypes::NEUTRAL, 1, ForegroundColors::DARK_GRAY );
        stringUtils::msg( "3-х часов назад", MsgTypes::NEUTRAL, 1, ForegroundColors::WHITE );
        stringUtils::msg( ".", MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

        $num = $this->actionMenu( [
            "Сообщения" => "Восстановление удалённых сообщений",
            "Стена" => "Восстановление удалённых записей на стене",
            "Комментарии" => "Восстановление удалённых комментариев на стене",
            //"Откат"=>"Откат всех действий до определённого момента времени",
            //"Восстановить всё" => "Восстановление всего удалённого контента, который только возможно восстановить",
            "Отмена" => "Возврат в главное меню программы"
        ], FALSE, TRUE );

        switch( $num ) {
            case 1:
                $res = $this->db->query( "SELECT * FROM `history` WHERE `type` = 'message' AND `owner_id` = {$this->vk->uid} AND `repair` IS NULL" );

                $restore = 0;

                if( ! empty( $res ) )
                    while( $row = $res->fetchArray( SQLITE3_ASSOC ) ) {
                        stringUtils::msg( "\rВосстановление {$row["item_id"]}...", MsgTypes::NEUTRAL, 1 );

                        $result = $this->vk->method( "messages.restore", [ "message_id" => $row[ 'item_id' ] ] )->response;

                        if( $result === 1 ) {
                            $this->db->query( "UPDATE `history` SET `repair` = 1 WHERE `item_id` = {$row["item_id"]} AND `owner_id` = {$this->vk->uid}" );
                            $restore++;
                        }
                    }

                stringUtils::msg( "\rВосстановлено $restore " . stringUtils::declOfNum( $restore, [ "сообщение", "сообщения", "сообщений" ] ) );

                break;

            case 2:
                $res = $this->db->query( "SELECT * FROM `history` WHERE `type` = 'wall' AND `owner_id` = {$this->vk->uid} AND `repair` IS NULL" );

                $restore = 0;

                if( ! empty( $res ) )
                    while( $row = $res->fetchArray( SQLITE3_ASSOC ) ) {
                        stringUtils::msg( "\rВосстановление {$row['owner_id']}_{$row["item_id"]}", MsgTypes::NEUTRAL, 1 );

                        $result = $this->vk->method( "wall.restore", [ "owner_id" => $row[ 'owner_id' ], "post_id" => $row[ 'item_id' ] ] )->response;

                        if( $result === 1 ) {
                            $this->db->query( "UPDATE `history` SET `repair` = 1 WHERE `item_id` = {$row["item_id"]} AND `owner_id` = {$this->vk->uid}" );
                            $restore++;
                        }
                    }

                stringUtils::msg( "\rВосстановлено $restore " . stringUtils::declOfNum( $restore, [ "запись", "записи", "записей" ] ) );

                break;

            case 3:
                $res = $this->db->query( "SELECT * FROM `history` WHERE ( `type` = 'comment' OR `type` = 'commentphoto' OR `type` = 'commentpost' OR `type` = 'commentWall' ) AND `owner_id` = {$this->vk->uid} AND `repair` IS NULL" );
                $restore = 0;

                if( ! empty( $res ) )
                    while( $row = $res->fetchArray( SQLITE3_ASSOC ) ) {
                        $method = preg_match( "/photo/", $row[ 'type' ] ) ? "photos" : "wall";
                        stringUtils::msg( "\rВосстановление {$row['owner_id']}_{$row["item_id"]}", MsgTypes::NEUTRAL, 1 );

                        $result = $this->vk->method( "$method.restoreComment", [ "owner_id" => $row[ 'owner_id' ], "comment_id" => $row[ 'item_id' ] ] )->response;

                        if( $result === 1 ) {
                            $this->db->query( "UPDATE `history` SET `repair` = 1 WHERE `item_id` = {$row["item_id"]} AND `owner_id` = {$this->vk->uid}" );
                            $restore++;
                        }
                    }

                stringUtils::msg( "\rВосстановлено $restore " . stringUtils::declOfNum( $restore, [ "комментарий", "комментария", "комментариев" ] ) );
                break;

            case 4:
                break;
        }
    }

    public function delAcc() {
        if( $count = $this->db->query( "SELECT count(*) FROM users" )->fetchArray( SQLITE3_NUM )[ 0 ] > 0 ) {
            $users = $this->db->query( "SELECT * FROM users ORDER BY lastAuth DESC" );

            $msg[] = stringUtils::color( "\n0) ", ForegroundColors::DARK_GRAY ) . "Отмена\n";

            while( $user = $users->fetchArray( SQLITE3_ASSOC ) ) {
                $i++;

                $year = date( "Y", $user[ 'lastAuth' ] ) == date( "Y" ) ? "" : " " . date( "Y", $user[ 'lastAuth' ] );
                $lastAuth = stringUtils::color( "Последний вход ", ForegroundColors::DARK_GRAY ) . date( "d ", $user[ 'lastAuth' ] ) . stringUtils::getMonth( $user[ 'lastAuth' ] ) . $year . stringUtils::color( " в ", ForegroundColors::DARK_GRAY ) . date( "H:i", $user[ 'lastAuth' ] );
                $lastAuth = $user[ 'id' ] == $this->vk->uid ? stringUtils::color( "Используется сейчас",
                    ForegroundColors::LIGHT_CYAN ) : $lastAuth;
                $msg[] = stringUtils::color( "{$i}) ", ForegroundColors::DARK_GRAY ) . stringUtils::color( "{$user['first_name']} {$user['last_name']} (" . $user[ 'domain' ] . ")\n", $user[ 'id' ] == $this->vk->uid ? ForegroundColors::WHITE : ForegroundColors::LIGHT_GRAY, $this->vk->uid == $user[ 'id' ] ? BackgroundColors::CYAN : NULL ) . str_repeat( " ", strlen( "{$i}) " ) ) . "$lastAuth\n";
                $userList[] = $user[ 'id' ];
            }

            stringUtils::msg( "\r" . implode( "\n", $msg ) );

            while( ! isset( $num ) || ( ! $userList[ $num - 1 ] && $num != 0 ) )
                $num = (int) stringUtils::readLn( "Удалить:" );

            if( $num === 0 ) return;

            $id = $userList[ $num - 1 ];

            $this->db->query( "INSERT INTO history (owner_id, item_id, type, time) VALUES ({$this->vk->uid}, {$id}, 'delAcc', " . time() . ");" );
            $this->db->query( "DELETE FROM users WHERE `id` = $id" );
            stringUtils::msg( "Аккаунт id$id удалён из сохранённых.", MsgTypes::SUCCESS );
        } else
            stringUtils::msg( "Нет сохранённых аккаунтов." );

        unset( $userList, $msg, $users, $user );
    }


    public function options() {
        $menup[] = [ "TITLE" => "Звук по завершении", "TYPE" => "BOOL", "PARAM" => "sound_on_end", "DESC" => "Проигрывание звука при завершении операций" ];
        $menup[] = [ "TITLE" => "Раскрашивание", "TYPE" => "BOOL", "PARAM" => "use_rainbow_terminal", "OS" => 10, "DESC" => "Использование цветных символов в консоли" ];
        $menup[] = [ "TITLE" => "Тестовые сборки", "TYPE" => "TEST", "PARAM" => "pre_build", "DESC" => "Получение тестовых сборок" ];
        $menup[] = [ "TITLE" => "Ключ Anti-Captcha.com", "TYPE" => "TEXT", "PARAM" => "anti_captcha", "OS" => 10, "DESC" => "Ключ API anti-captcha.com" ];

        foreach( $menup as $item ) {
            $text = $item[ 'DESC' ];
            if( $item[ 'OS' ] && $_SERVER[ 'OS' ] == "Windows_NT" && stringUtils::getOS() < $item[ 'OS' ] )
                $text .= stringUtils::color( " (недоступно в Вашей ОС)", ForegroundColors::DARK_GRAY );

            $suffix = "";
            switch( $item[ 'TYPE' ] ) {
                case 'BOOL':
                    $enabled = (bool) $this->config->{$item[ 'PARAM' ]};
                    $suffix = $enabled ? stringUtils::color( "+", ForegroundColors::LIGHT_GREEN ) : stringUtils::color( "-", ForegroundColors::DARK_GRAY );
                    $suffix = "[ $suffix ] ";
                    break;
                case 'TEST':
                    $enabled = (bool) $this->config->{$item[ 'PARAM' ]};
                    $suffix = $enabled ? stringUtils::color( "+", ForegroundColors::LIGHT_GREEN ) : stringUtils::color( "-", ForegroundColors::DARK_GRAY );
                    $suffix = "[ $suffix ] ";
                    break;
                default:
                    if( $this->config->{$item[ 'PARAM' ]} != NULL )
                        $text .= "; Значение: " . $this->config->{$item[ 'PARAM' ]};
            }
            $menu[ $suffix . $item[ 'TITLE' ] ] = $text;
        }

        $menu[ "Отмена" ] = "Возврат в главное меню программы";

        $num = $this->actionMenu( $menu, FALSE, TRUE );
        $item = array_values( $menup )[ $num - 1 ];

        if( ! $item ) return;

        if( $item[ 'OS' ] && $_SERVER[ 'OS' ] == "Windows_NT" && stringUtils::getOS() < $item[ 'OS' ] ) {
            stringUtils::msg( "Эта опция недоступна в Вашей ОС" );
            $this->options();
        }

        switch( $item[ 'TYPE' ] ) {
            case 'BOOL':
                $this->config->{$item[ 'PARAM' ]} = stringUtils::ask( $item[ 'TITLE' ] );
                break;

            case 'TEST':
                $this->config->{$item[ 'PARAM' ]} = stringUtils::ask( $item[ 'TITLE' ] );
                $this->vk->method( "groups." . ( (bool) $this->config->{$item[ 'PARAM' ]} ? "join" : "leave" ),
                    [ "group_id" => 162469296 ] );
                break;

            default:
                $this->config->{$item[ 'PARAM' ]} = stringUtils::readLn( $item[ 'TITLE' ] );
        }

        $this->config->save();
        $this->options();

    }

    public function logOut() {
        $this->vk->method( "account.setOffline" );

        $this->vk->token = NULL;
        $this->vk->uid = NULL;

        $this->auth();
    }
}