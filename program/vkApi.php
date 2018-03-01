<?php
/**
 * Created by PhpStorm.
 * User: TheLonadels
 * Date: 12/17/2017
 * Time: 5:27 PM
 */

class vkApi {
    public $token;
    public $uid;
    public $v = "5.73";

    private $appId = 2274003;
    private $appSecret = "hHbZxrka2uZ6jB1inYsH";

    private $authUrl = "https://oauth.vk.com/token?";
    private $methodUrl = "https://api.vk.com/method/";

    public function getService() {

        $pars[ 'grant_type' ] = "client_credentials";
        $pars[ 'client_id' ] = $this->appId;
        $pars[ 'client_secret' ] = $this->appSecret;
        $pars[ 'v' ] = $this->v;

        $query = http_build_query( $pars );
        $url = $this->authUrl . $query;

        $result = stringUtils::get_curl( $url );
        $obj = json_decode( $result );

        if( ! isset( $obj->error ) ) {
            $this->token = $obj->access_token;
            return TRUE;
        }

        return FALSE;
    }

    public function checkToken( $token ) {
        if( ! $this->token )
            $this->getService();

        $res = $this->method( "secure.checkToken", [ "token" => $token ] );

        if( isset( $res->error ) )
            return FALSE;

        if( $res->response->success )
            return $res;

        return FALSE;
    }

    public function authToken( $token ) {
        $res = $this->checkToken( $token );

        if( $res ) {
            $this->uid = $res->response->user_id;
            $this->token = $token;
            return TRUE;
        }

        return FALSE;
    }

    public function auth( $login, $password, $params = [] ) {

        $params[ 'grant_type' ] = "password";
        $params[ 'client_id' ] = $this->appId;
        $params[ 'client_secret' ] = $this->appSecret;
        $params[ 'username' ] = $login;
        $params[ 'password' ] = $password;
        $params[ 'scope' ] = "messages,wall,offline";
        $params[ 'v' ] = $this->v;
        $params[ '2fa_supported' ] = 1;

        $query = http_build_query( $params );
        $url = $this->authUrl . $query;

        $result = stringUtils::get_curl( $url );
        $time = time();
        $obj = json_decode( $result );

        if( ! isset( $obj->error ) ) {
            $this->token = $obj->access_token;
            $this->uid = $obj->user_id;
            return TRUE;
        }

        switch( $obj->error ) {
            case "need_validation":
                $obj->validation_type = ( $params[ 'force_sms' ] ) ? "2fa_sms" : $obj->validation_type;
                switch( $obj->validation_type ) {
                    case "2fa_sms":
                        $phone = stringUtils::color( $obj->phone_mask, ForegroundColors::LIGHT_CYAN );
                        stringUtils::msg( "\nНа номер $phone отправлено СМС с кодом для подтверждения авторизации.", MsgTypes::NEUTRAL, 0 );
                        stringUtils::msg( "Если СМС не пришло, для повторной отправки введите 0 вместо кода.\n", MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );
                        break;

                    case "2fa_app":
                        stringUtils::msg( "\nПодтвердите авторизацию с помощью кода в сообщении от Администрации или из приложения генерации кодов.", MsgTypes::NEUTRAL, 0 );
                        stringUtils::msg( "Или введите 0 вместо кода, чтобы получить бесплатное СМС с кодом для подтверждения.\n", MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );
                        break;
                }

                $params[ 'force_sms' ] = NULL;
                while( TRUE ) {
                    while( ! isset( $code ) || trim( $code ) === "" )
                        $code = stringUtils::readLn( "Введите код:" );
                    $code = (int) $code;

                    if( ( $obj->validation_type == "2fa_app" && $code === 0 ) || ( $obj->validation_type == "2fa_sms" && strtotime( "-1 min" ) >= $time && $code === 0 ) ) {
                        $params[ 'force_sms' ] = TRUE;
                        break;
                    } elseif( strtotime( "-1 min" ) < $time && $obj->validation_type == "2fa_sms" && $code === 0 ) {
                        stringUtils::msg( "Повторная отпарвка будет доступна через " . stringUtils::color( strtotime( "+1 min", $time ) - time() . " сек.", ForegroundColors::WHITE ), MsgTypes::NEUTRAL );
                        unset( $code );
                        continue;
                    } else {
                        $params[ 'code' ] = $code;
                        break;
                    }
                }

                return $this->auth( $login, $password, $params );
                break;

            case "need_captcha":
                file_put_contents( APP_DIR . "captcha.jpg", stringUtils::get_curl( $obj->captcha_img ) );
                shell_exec( "\"" . stringUtils::replaceSr( APP_DIR ) . "\"captcha.jpg" );

                $params[ "captcha_sid" ] = $obj->captcha_sid;
                $params[ "captcha_key" ] = stringUtils::readLn( "\rКапча:" );

                return $this->auth( $login, $password, $params );
                break;
        }

        return FALSE;
    }

    function method( $method, $params = [] ) {

        global $mf;

        $params[ "client_secret" ] = $this->appSecret;
        $params[ "access_token" ] = $this->token;
        $params[ "v" ] = $this->v;

        if( $response = stringUtils::get_curl( $this->methodUrl . $method . "?" . http_build_query( $params ) ) ) {
            $response = json_decode( $response );
            if( isset( $response->error ) and $response->error->error_code == 14 ) {
                file_put_contents( APP_DIR . "captcha.jpg", stringUtils::get_curl( $response->error->captcha_img ) );

                if( $mf->config->anti_captcha ) {
                    $params[ "captcha_sid" ] = $response->error->captcha_sid;

                    $api = new ImageToText();
                    $api->setVerboseMode( FALSE );

                    $api->setKey( $mf->config->anti_captcha );
                    $api->setFile( APP_DIR . "\captcha.jpg" );

                    if( ! $api->createTask() )
                        $params[ "captcha_key" ] = stringUtils::readLn( "\rКапча:" );

                    $api->getTaskId();

                    if( ! $api->waitForResult() )
                        $params[ "captcha_key" ] = stringUtils::readLn( "\rКапча:" );
                    else {
                        $params[ "captcha_key" ] = $api->getTaskSolution();
                        stringUtils::msg( "\rБыл использован ключ Anti-Captcha", MsgTypes::NEUTRAL, 0,
                            ForegroundColors::LIGHT_CYAN );
                    }

                } else {
                    shell_exec( "\"" . stringUtils::replaceSr( APP_DIR ) . "\"captcha.jpg" );
                    $params[ "captcha_key" ] = stringUtils::readLn( "\rКапча:" );
                }

                if( is_writeable( APP_DIR . "captcha.jpg" ) )
                    unlink( APP_DIR . "captcha.jpg" );

                return $this->method( $method, $params );
            }
            return $response;
        }

        return FALSE;

    }
}