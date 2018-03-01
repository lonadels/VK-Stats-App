<?

date_default_timezone_set( "EUROPE/MOSCOW" );

$includes = ['anticaptcha', 'imagetotext', 'arrayToTextTable', 'stringUtils', 'config', 'vkApi', 'update', 'mainFunctions'];
foreach($includes as $include)
    include "$include.php";

global $mf;
$mf = new mainFunctions();

$appVer = str_split(APP_VER );

if( $appVer[2] == 0 && $appVer[3] == 0 ) unset($appVer[2]);
if( $appVer[3] == 0 ) unset($appVer[3]);

$ver = implode( '.', $appVer );
$title = "VK STATS APP";

$width = stringUtils::getWidth();
$borders = stringUtils::color( str_repeat( "─", $width / 2 - strlen( $title ) / 2 - 1 ), ForegroundColors::DARK_GRAY );

$title = stringUtils::color( $title, ForegroundColors::LIGHT_CYAN );

if( ! array_search( "-noTitle", $argv ) )
    stringUtils::msg( "$borders $title $borders\n", MsgTypes::NEUTRAL );

cli_set_process_title( "VK Stats App $ver" );

if( $_SERVER[ 'OS' ] == "Windows_NT" && stringUtils::getOS() < 10 )
    stringUtils::msg( "В Вашей ОС не поддерживается наш метод цветного текста. Ожидайте исправления проблемы в следующих версиях или обновитесь до Windows 10.\n", MsgTypes::WARNING );

checkUpdate();

$mf->auth();

if( (bool) $mf->config->first_start ) {
    $mf->config->first_start = FALSE;
    $mf->config->save();
}

while( TRUE )
    $mf->loadMenu();