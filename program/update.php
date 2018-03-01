<?php
/**
 * Created by PhpStorm.
 * User: TheLonadels
 * Date: 02.01.2018
 * Time: 16:48
 */

/* VERSION INFO */

define( "APP_VER", 1100 );
define( "APP_BUILD", 0 );
define( "PRE_BUILD", 15 );
define( "DEBUG", FALSE );

function checkUpdate( $forceMsg = 0 ) {
    global $mf, $argv;

    checkConnection();

    $tester = (bool) $mf->config->pre_build;
    $data = explode( "\n", stringUtils::get_curl( "http://swiftof.ru/vkstatsapp/checkversion.php?v=" . APP_VER . "&build=" . urlencode( APP_BUILD ) . "&pre=" . (int) $tester ) );

    $latestVer = (int) $data[ 0 ];
    $latestBuild = (double) $data[ count( $data ) - 1 ];

    if( $latestVer > APP_VER || ( APP_BUILD > 0 && $tester && $latestBuild > APP_BUILD ) ) {
        $fullVer = trim( $data[ 0 ] );
        $latestVer = implode( '.', str_split( trim( $data[ 0 ] ) ) );

        $updateType = (int) $data[ 2 ];

        if( ! array_search( '-forceUpd', $argv ) ) {

            stringUtils::msg( "\rДоступна новая версия (v$latestVer" . ( $tester ? "-pre build$latestBuild" : "" ) . ")", MsgTypes::NEUTRAL, 0,
                ForegroundColors::LIGHT_CYAN );

            $continue = $updateType === 1 ? TRUE : ( $updateType === 2 ? FALSE : stringUtils::ask( "Загрузить обновление?" ) );

            if( $updateType === 2 )
                stringUtils::msg( "Загрузите обновление вручную: $data[1]Группа ВК: vk.com/vkStatsApp\n" );

            if( ! $continue )
                return;
        }

        $dir = $tester ? "preview" : "release";

        unset( $data[ count( $data ) - 1 ], $data[ 0 ], $data[ 1 ], $data[ 2 ] );

        foreach( $data as $datum ) {

            $md5 = explode( '|', $datum )[ 0 ];
            $datum = explode( '|', $datum )[ 1 ];

            if( ! is_dir( APP_DIR . "update" ) )
                mkdir( APP_DIR . "update" );

            $updDir = APP_DIR . "update\\$fullVer-build$latestBuild\\";
            if( ! is_dir( $updDir ) )
                mkdir( $updDir );

            $file = $updDir . stringUtils::basenameNoExt( $datum ) . ".php";
            $files[] = $file;

            $need = dirname( __FILE__ ) . "\\" . basename( $file );

            if( ! DEBUG && ( ( file_exists( $need ) && md5_file( $need ) == $md5 ) || ( file_exists( $file ) && md5_file( $file ) == $md5 ) ) )
                continue;

            $req_url = trim( "/vkstatsapp/files/$dir/" . basename( $datum ) );
            $server = "swiftof.ru";

            //if( DEBUG )
            //    print "\n\nF: $file\nN: $need\nMD5: $md5\nURL: $req_url";

            $request = "GET {$req_url} HTTP/1.0\r\nHost: {$server}\r\n\r\n\r\n";
            $fp = fsockopen( $server, 80, $errno, $errstr, 30 );

            if( file_exists( $file ) )
                unlink( $file );

            if( $fp ) {

                fputs( $fp, $request );
                $buf = '';

                while( ! feof( $fp ) ) {
                    $buf .= fgets( $fp, 256 );
                    if( ! isset( $size ) and strpos( $buf, "\r\n\r\n" ) ) {
                        $headers = substr( $buf, 0, strpos( $buf, "\r\n\r\n" ) + 4 );
                        preg_match( "/content-length:\s*([0-9]*)/i", $headers, $size );
                        $size = $size[ 1 ];
                    }
                    $length = strlen( substr( $buf, strpos( $buf, "\r\n\r\n" ) + 4 ) );
                    stringUtils::msg( "\rЗагрузка " . basename( $file ) . " - " . stringUtils::size( $length ) . " из " . stringUtils::size( $size ) . ( $size > 0 ? " (" . round( $length * 100 / $size ) . "%)" : "" ), MsgTypes::NEUTRAL, 1 );

                }

                unset( $size );

                fclose( $fp );
                $buf = substr( $buf, strpos( $buf, "\r\n\r\n" ) + 4 );

                $fh = fopen( $file, "a" );
                fwrite( $fh, $buf );
                fclose( $fh );
            }
        }

        if( ! array_search( '-forceUpd', $argv ) )
            print "\n";

        $path = dirname( dirname( __FILE__ ) . ".." );
        $cmd = "start \"$path\" \"$path\\php.exe\" -f \"$path\program\main.php\"";

        if( ! is_writeable( dirname( __FILE__ ) ) ) {
            if( array_search( '-forceUpd', $argv ) ) {
                stringUtils::msg( "Не удалось получить доступ к корневой папке приложения.\n\nОбновление загружено в папку " . stringUtils::color( $updDir, ForegroundColors::WHITE ) . ".\nПоместите файлы из этой папки в папку " . stringUtils::color( dirname( __FILE__ . "\n\n" ), ForegroundColors::WHITE ) );
                while( ! $pUpd ) {
                    $pUpd = TRUE;
                    foreach( $files as $file )
                        if( md5_file( $file ) !== md5_file( dirname( __FILE__ ) . "\\" . basename( $file ) ) ) {
                            $pUpd = FALSE;
                            break;
                        }
                }
                pclose( popen( $cmd, "r" ) );
            }

            stringUtils::msg( "Сейчас будут запрошены ", MsgTypes::NEUTRAL, 1 );
            stringUtils::msg( "права администратора ", MsgTypes::NEUTRAL, 1, ForegroundColors::LIGHT_CYAN );
            stringUtils::msg( "для обновления файлов. ", MsgTypes::NEUTRAL, 1 );
            stringUtils::msg( "Разрешите их!", MsgTypes::NEUTRAL, 1, ForegroundColors::LIGHT_CYAN );

            sleep( 1 );

            file_put_contents( APP_DIR . "\\runAsAdm.cmd", '@if (1==1) @if(1==0) @ELSE
                @echo off&SETLOCAL ENABLEEXTENSIONS
                            >nul 2>&1 "%SYSTEMROOT%\system32\cacls.exe" "%SYSTEMROOT%\system32\config\system"||(
                            cscript //E:JScript //nologo "%~f0"
                    @goto :EOF
                )
                echo.Performing admin tasks...
                REM call foo.exe
                @goto :EOF
                @end @ELSE
                ShA=new ActiveXObject("Shell.Application")
                ShA.ShellExecute("' . str_replace( "\\", "\\\\", $path ) . '\\\\php.exe","\\"' . str_replace( "\\", "\\\\", $path ) . '\\\\program\\\\main.php\\" -forceUpd -noTitle","","runas",5);
                @end' );

            pclose( popen( "\"" . APP_DIR . "runAsAdm.cmd\"", "r" ) );

            exit();
        } elseif( ! DEBUG ) {
            if( ! array_search( '-forceUpd', $argv ) )
                stringUtils::msg( "Обновление VK Stats App..." );

            foreach( $files as $file )
                if( is_file( $file ) )
                    copy( $file, dirname( __FILE__ ) . "\\" . basename( $file ) );

            removeDir( APP_DIR . "update" );

            if( ! array_search( '-forceUpd', $argv ) ) {
                stringUtils::msg( "VK Stats App обновлен", MsgTypes::NEUTRAL, 0, ForegroundColors::LIGHT_CYAN );
                stringUtils::msg( "Сейчас программа будет перезапущена.\n", MsgTypes::NEUTRAL, 0, ForegroundColors::DARK_GRAY );

                sleep( 1 );
            }

            pclose( popen( $cmd, "r" ) );
            exit;
        }
    } elseif( $forceMsg )
        stringUtils::msg( "Обновлений не обнаружено." );
}

function removeDir( $path ) {
    if( ! is_dir( $path ) )
        throw new InvalidArgumentException( "$path must be a directory" );

    if( substr( $path, strlen( $path ) - 1, 1 ) != '/' )
        $path .= '/';

    $files = glob( $path . '*', GLOB_MARK );
    foreach( $files as $file )
        if( is_dir( $file ) )
            removeDir( $file );
        else
            unlink( $file );

    rmdir( $path );
}