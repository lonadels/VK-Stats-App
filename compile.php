<?php

function start() {
    debug("Начинаем сборку...\n");

    $appDir = "program";
    $build = BUILD_TIME;
    $buildDir = "build";

    $files[ 'ext' ] = [ "com_dotnet", "curl", "mbstring", "sqlite3" ];
    $files[ 'lib' ] = [ "libcrypto-1_1", "libcrypto-1_1-x64", "libssh2", "libssl-1_1", "libssl-1_1-x64", "nghttp2", "php7ts" ];
    $files[ 'other' ] = [ "php.exe", "php.ini", "vk.ico" ];

    $ex = [ "changeLog.md" ];

    debug("Создание папок...", "\e[1;36m");
    $dirs = [ $buildDir, "$buildDir/$appDir", "$buildDir/ext", "site" ];
    foreach( $dirs as $dir ) {
        if( is_dir( $dir ) )
            deleteDir( $dir );

        debug("Создание $dir...");
        mkdir( $dir );
    }

    print "\n";

    debug("Копирование файлов...", "\e[1;36m");
    $progFiles = scandir( $appDir );

    foreach( $progFiles as $file ) {
        if( ! is_file( "$appDir/$file" ) or in_array($file, $ex) ) continue;

        $fileNoExt = baseNameNoExt($file);
        debug("Копирование $file...");

        copy( "$appDir/$file", "$buildDir/$appDir/$file" );
        copy( "$appDir/$file", "site/". $fileNoExt .".upd" );

        if( $file == "update.php" ) {
            $fileData = preg_replace_callback(
                '/define\(\s?"APP_BUILD",\s?([0-9]*)\s?\);/',
                function( $matches ) use ( $build ) {
                    return str_ireplace( $matches[ 1 ], $build, $matches[ 0 ] );
                }, file_get_contents( "$appDir/$file" ) );

            file_put_contents( "$buildDir/$appDir/$file", $fileData );
            file_put_contents( "site/". $fileNoExt .".upd", $fileData );
        }
    }

    $typeNames = ["ext"=>"расширений", "lib"=>"библиотек", "other"=>"доп. файлов"];

    foreach( $files as $type => $fls ) {
        print "\n";
        debug("Копирование {$typeNames[$type]}...", "\e[1;36m");
        foreach( $fls as $file ) {
            $suffix = NULL;
            if( in_array( $type, [ "ext", "lib" ] ) )
                $suffix = ".dll";

            $file = "$file$suffix";

            if( $type == "ext" )
                $file = "ext/php_$file";

            debug("Копирование $file...");
            copy( $file, "$buildDir/$file" );
        }
    }
}

function baseNameNoExt( $file ) {
    $file = explode( '.', $file );
    unset( $file[ count( $file ) - 1 ] );
    return implode( '.', $file );
}

function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

function debug($m, $s=null){
    print "{$s}[" . date("d/m/y H:i:s") . "] $m\e[0m\n";
}

date_default_timezone_set( "EUROPE/MOSCOW" );
define('BUILD_TIME', date( "ymd.Hi" ));

$time = microtime(1);
start();
$time = round(microtime(1) - $time, 3 );
print "\n";
debug("Сборка ".BUILD_TIME." завершена за {$time}s", "\e[1;32m");

readline();