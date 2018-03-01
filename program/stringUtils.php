<?php
/**
 * Created by PhpStorm.
 * User: TheLonadels
 * Date: 12/17/2017
 * Time: 5:28 PM
 */

/**
 * @param string $string
 * @param string $enc
 *
 * @return string
 */
function mb_ucfirst( string $string, string $enc = 'UTF-8' ) : string {
    return mb_strtoupper( mb_substr( $string, 0, 1, $enc ), $enc ) .
        mb_substr( $string, 1, mb_strlen( $string, $enc ), $enc );
}

class stringUtils {
    public static $config;

    public static function msg( String $text, Int $type = MsgTypes::NEUTRAL, Bool $noBr = FALSE, String $foregroundColor = NULL, String $backgroundColor = NULL, Bool $fullLine = false ) {
        $text = self::color( $text, $foregroundColor, $backgroundColor );

        $suffix = [ "ИНФО", "УСПЕШНО", "ВНИМАНИЕ", "ОШИБКА", "" ][ $type ];
        $color = [ ForegroundColors::LIGHT_CYAN, ForegroundColors::LIGHT_GREEN, ForegroundColors::YELLOW, ForegroundColors::LIGHT_RED, NULL ][ $type ];

        if( $suffix )
            $suffix = self::color( $suffix, $color );

        $fullText = ( $suffix ? "[$suffix] " : "" ) . $text;
        $width = self::getWidth() - strlen( $fullText );

        print $fullText . ( $noBr ?  ( $width > 0 && $fullLine ? str_repeat( ' ', $width ) : "" ) : ( $width <= 0 ? "" : str_repeat( ' ', $width ) ) . "\n" ) ;
    }

    public static function readLn( String $text = "", $force = FALSE ) {
        if( $text )
            self::msg( "$text ", MsgTypes::NEUTRAL, TRUE, ForegroundColors::DARK_GRAY );

        while( ! isset( $result ) or ( ! trim( $result ) && $force ) )
            $result = readline();

        return $result;
    }

    public static function getWidth() {
        static $width;
        if( isset($width) )
            return $width;

        $mode = shell_exec( "mode" );
        return $width = (int) preg_match( '/columns:\s*([0-9]*)/i', $mode, $matches ) ? $matches[ 1 ] : '';
    }

    public static function ask( String $text = "", String $foregroundColor = ForegroundColors::DARK_GRAY ) {
        while( ! isset( $ask ) or ( $ask != 'y' and $ask != 'n' ) )
            $ask = mb_strtolower( stringUtils::readLn( stringUtils::color( $text, $foregroundColor ) . stringUtils::color(
                    " [Y/n]", ForegroundColors::DARK_GRAY ) ) );
        return $ask == 'y';
    }

    public static function getOS() {
        static $res;
        if( $res ) return $res;

        if( mb_strtolower( $_SERVER[ 'OS' ] ) !== "windows_nt" )
            $res = $_SERVER[ 'OS' ];
        else {
            $cmd = exec( 'ver' );
            preg_match( "/Microsoft Windows \[Version (.*)\]/i", $cmd, $ver );

            $res = explode( '.', $ver[ 1 ] );
            $res = (double) "$res[0].$res[1]";
        }

        return $res;
    }

    public static function size( $size, $round = 1 ) {
        $filesizename = [ " Байт", " КБ", " МБ", " ГБ", " ТБ", " ПБ", " ЕБ", " ЗБ", " ЙБ" ];
        return $size ? number_format( round( $size / pow( 1024, ( $i = floor( log( $size, 1024 ) ) ) ), $round ), 1, '.', " " ) . $filesizename[ (int) $i ] : '0 Байт';
    }

    public static function getMonth( $time ) {
        $m = date( "m", $time );
        return [ "янв", "фев", "мар", "апр", "май", "июн", "июл", "авг", "сен", "окт", "ноя", "дек" ][ $m - 1 ];
    }

    /**
     * Преобразование секунд в секунды/минуты/часы/дни/месяцы/годы
     *
     * @param int $seconds - секунды для преобразования
     *
     * @return array $times:
     *        $times[0] - секунды
     *        $times[1] - минуты
     *        $times[2] - часы
     *        $times[3] - дни
     *        $times[4] - месяцы
     *        $times[5] - годы
     *
     */
    public static function seconds2times( $seconds ) : array {
        $times = [];
        $count_zero = FALSE;
        $periods = [ 60, 3600, 86400, 2629743, 31536000 ];

        for( $i = 4; $i >= 0; $i-- ) {
            $period = floor( $seconds / $periods[ $i ] );
            if( ( $period > 0 ) || ( $period == 0 && $count_zero ) ) {
                $times[ $i + 1 ] = $period;
                $seconds -= $period * $periods[ $i ];

                $count_zero = TRUE;
            }
        }

        $times[ 0 ] = $seconds;
        return $times;
    }

    public static function smartTime( int $time ) : string {
        $diff = time() - $time;

        $hours = floor( $diff / 3600 );
        $days = floor( $diff / 86400 );
        $months = floor( $diff / 2629743 );

        if( $hours <= 24 )
            return date( "H:i", $time );
        elseif( $days == 1 )
            return "вчера";
        elseif( $months <= 12 )
            return date( "d " . self::getMonth( $time ), $time );
        else
            return date( "d " . self::getMonth( $time ) . " Y", $time );
    }

    public static function timeAgo( int $time ) : string {
        $diff = time() - $time;

        $seconds = $diff;
        $minutes = round( $diff / 60 );
        $hours = round( $diff / 3600 );
        $days = round( $diff / 86400 );
        $months = round( $diff / 2419200 );

        if( $seconds <= 5 )
            return "$seconds " . self::declOfNum( $seconds, [ "секунду", "секунды", "секунд" ] ) . " назад";
        elseif( $seconds <= 60 ) {
            return "меньше минуты назад";
        } elseif( $minutes <= 60 ) {
            if( $minutes == 1 )
                return "минуту назад";
            else
                return "$minutes " . self::declOfNum( $minutes, [ "минуту", "минуты", "минут" ] ) . " назад";
        } elseif( $hours <= 24 ) {
            if( $hours == 1 )
                return "час назад";
            elseif( $hours == 2 )
                return "два часа назад";
            elseif( $hours <= 12 )
                return "$hours " . self::declOfNum( $hours, [ "час", "часа", "часов" ] ) . " назад";
            else
                return "сегодня в " . date( "H:i", $time );
        } elseif( $days <= 30 ) {
            if( $days == 1 )
                return "вчера в " . date( "H:i", $time );
            else
                return date( "d " . self::getMonth( $time ) . " в H:i", $time );
        } elseif( $months <= 12 )
            return date( "d ", $time ) . self::getMonth( $time );
        else
            return date( "d " . self::getMonth( $time ) . " Y", $time );

    }

    public static function declOfNum( $number, $titles ) {
        $cases = [ 2, 0, 1, 1, 1, 2 ];
        return $titles[ ( $number % 100 > 4 && $number % 100 < 20 ) ? 2 : $cases[ min( $number % 10, 5 ) ] ];
    }

    public static function get_curl( String $url ) : String {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt( $ch, CURLOPT_HEADER, FALSE );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_REFERER, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        $result = curl_exec( $ch );
        curl_close( $ch );
        return $result;
    }

    public static function replaceSl( $s ) {
        return str_replace( "\\", "/", $s );
    }

    public static function replaceSr( $s ) {
        return str_replace( "/", "\\", $s );
    }

    function execInBackground( $cmd ) {
        if( substr( php_uname(), 0, 7 ) == "Windows" ) {
            pclose( popen( "start /B " . $cmd, "r" ) );
        } else {
            exec( $cmd . " > /dev/null &" );
        }
    }

    public static function preloader( $text = "Подождите" ) {
        static $i, $lastTime;

        $loaders = [ "|", "/", "-", "\\" ];

        if( $lastTime < microtime( TRUE ) - 0.06 ) {
            $i++;
            $lastTime = microtime( TRUE );
        }

        if( $i >= count( $loaders ) )
            $i = 0;

        print "\r( $loaders[$i] ) $text";
    }

    public static function color( String $string, String $foregroundColor = NULL, Int $backgroundColor = NULL ) {
        $coloredString = "";

        if( ! self::$config->use_rainbow_terminal || ( mb_strtolower( $_SERVER[ 'OS' ] ) == "windows_nt" && self::getOS() < 10 ) )
            return $string;

        if( isset( $foregroundColor ) )
            $coloredString .= "\033[{$foregroundColor}m";

        if( isset( $backgroundColor ) )
            $coloredString .= "\033[{$backgroundColor}m";

        $coloredString .= "$string\033[0m";

        return $coloredString;
    }

    public static function baseNameNoExt( $file ) {
        $file = explode( '.', $file );
        unset( $file[ count( $file ) - 1 ] );
        return implode( '.', $file );
    }

    public static function setAppName( $name ) {
        if( $_SERVER[ 'OS' ] == "Windows_NT" && self::getOS() >= 10 )
            print "\033]0;{$name}\007";
    }

    public static function setTitle( $title ) {
        if( $_SERVER[ 'OS' ] == "Windows_NT" && self::getOS() >= 10 )
            print "\033]2;{$title}\007";
        elseif( $_SERVER[ 'OS' ] == "Windows_NT" )
            exec( "title $title" );
    }

    public static function setIcon( $path ) {
        if( $_SERVER[ 'OS' ] == "Windows_NT" && self::getOS() >= 10 )
            print "\033]1;{$path}\007";
    }

    public static function beep( $msg = NULL ) {
        if( ! empty( $msg ) )
            self::msg( $msg );

        if( self::$config->sound_on_end ) return;

        print "\007";
    }

    public static function clear() {
        print "\033c";
    }

}

class MsgTypes {
    public const INFO = 0;
    public const SUCCESS = 1;
    public const WARNING = 2;
    public const ERROR = 3;
    public const NEUTRAL = 4;
}

class ForegroundColors {
    public const BLACK = '0;30';
    public const DARK_GRAY = '1;30';
    public const BLUE = '0;34';
    public const LIGHT_BLUE = '1;34';
    public const GREEN = '0;32';
    public const LIGHT_GREEN = '1;32';
    public const CYAN = '0;36';
    public const LIGHT_CYAN = '1;36';
    public const RED = '0;31';
    public const LIGHT_RED = '1;31';
    public const PURPLE = '0;35';
    public const LIGHT_PURPLE = '1;35';
    public const BROWN = '0;33';
    public const YELLOW = '1;33';
    public const LIGHT_GRAY = '0;37';
    public const WHITE = '1;37';
}

class BackgroundColors {
    public const BLACK = 40;
    public const RED = 41;
    public const GREEN = 42;
    public const YELLOW = 43;
    public const BLUE = 44;
    public const MAGENTA = 45;
    public const CYAN = 46;
    public const LIGHT_GRAY = 46;
}