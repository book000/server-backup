<?php
$options = getopt("s:t:");
$SERVER_NAME = $options["s"];
$BACKUP_TYPE = $options["t"];

$TODAY = date("Y-m-d");
$BASE_DIR = dirname(__DIR__);
$LOG_DIR = $BASE_DIR . "/logs/" . $SERVER_NAME . "/";
if (!file_exists($LOG_DIR)) {
    mkdir($LOG_DIR, 0777, true);
}
$LOG_PATH = $LOG_DIR . $TODAY . ".log";

$BACKUP_DIR = $BASE_DIR . "/backups/" . $SERVER_NAME . "/" . $TODAY . "/";
if (!file_exists($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0777, true);
}

require_once($BASE_DIR . "/DiscordProgressReporter.class.php");
require_once($BASE_DIR . "/errorHandler.lib.php");
require_once($BASE_DIR . "/libs.inc.php");

if (!file_exists($BASE_DIR . "/config.json")) {
    echo "Config file does not exist.\n";
    exit(1);
}
$config = file_get_contents($BASE_DIR . "/config.json");
$config = json_decode($config, true);
if (json_last_error() != JSON_ERROR_NONE) {
    echo "JSON parse error: " . json_last_error_msg() . "\n";
    exit(1);
}

$discordToken = $config["discordToken"];
$discordChannel = $config["discordChannel"];
$dpr = new DiscordProgressReporter($discordToken, $discordChannel);
$dpr->setLogger($LOG_PATH);
$dpr->setPrefix("$BACKUP_TYPE:$SERVER_NAME");

if (!isset($config[$BACKUP_TYPE][$SERVER_NAME])) {
    $dpr->pp("The specified server and type could not be found.");
    exit(1);
}

$hostname = $config[$BACKUP_TYPE][$SERVER_NAME]["hostname"];
$port = $config[$BACKUP_TYPE][$SERVER_NAME]["port"];
$username = $config[$BACKUP_TYPE][$SERVER_NAME]["username"];
$password = $config[$BACKUP_TYPE][$SERVER_NAME]["password"];

$DATA_HOLD_SEC = $config[$BACKUP_TYPE]["DATA-HOLD-SEC"];

// --- Get Databases and Tables ------------------------ //

$options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
];

$dsn = "mysql:host=$hostname;port=$port;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    echo "Database connection error. " . $e->getMessage() . "\n";
    exit(1);
}

$stmt = $pdo->prepare("SELECT * FROM INFORMATION_SCHEMA.TABLES");
$stmt->execute();

$tables = [];
while ($row = $stmt->fetch()) {
    $databaseName = $row["TABLE_SCHEMA"];
    $tableName = $row["TABLE_NAME"];

    if (!isset($tables[$databaseName])) {
        $tables[$databaseName] = [];
    }

    $tables[$databaseName][] = $tableName;
}

// --- Create conf file -------------------------------- //

$dpr->pp("Creating conf file.");

$conf = <<<CONF
[mysqldump]
host=$hostname
port=$port
user=$username
password=$password
CONF;

$CONF_PATH = $BASE_DIR . "/conf";
file_put_contents($CONF_PATH, $conf);

$dpr->pp("Created.");
$dpr->pp("Started backup.");

foreach ($tables as $databaseName => $tableNames) {
    $dpr->pp("**Database**: `$databaseName`");
    foreach ($tableNames as $tableName) {
        $dpr->pp("**Table**: `$tableName`");

        $start_time = microtime(true);

        $BACKUP_PATH = $BACKUP_DIR . "$databaseName-$tableName.sql.gz";
        $cmd = "/usr/bin/mysqldump --defaults-file=$CONF_PATH $databaseName $tableName | gzip > $BACKUP_PATH";
        $dpr->pp("**\$cmd** = `$cmd`");
        $cmd = "/usr/bin/bash -c \"$cmd\"";
        system($cmd, $ret);
        $dpr->pp("**\$ret** = `$ret`");

        $filesize = file_exists($BACKUP_PATH) ? filesize($BACKUP_PATH) : "0";
        $formattedFilesize = byte_format($filesize, 2);

        $finished_time = microtime(true);
        $process_time = $finished_time - $start_time;
        $process_formattedtime = sprintf("%02d分%02d秒", $process_time / 60, $process_time % 60);
        $start_time_formatted = formatMicrotime($start_time);
        $finished_time_formatted = formatMicrotime($finished_time);

        $dpr->pp("**\$process_formattedtime**: $process_formattedtime");
        $dpr->pp("**Backup file size**: $formattedFilesize ($filesize)");
    }
}

$dpr->pp("Finished backup.");
$dpr->flush();

if(date("d") <= 7){
    // 1週目
    $tarOutputPath = "/mnt/WHITEBOX/DataTomachi/backup/Server-Backup/$BACKUP_TYPE/$SERVER_NAME/$TODAY.tar.gz";
    if(!file_exists(dirname($tarOutputPath))){
        mkdir(dirname($tarOutputPath), 0777, true);
    }
    system("/usr/bin/tar cvf $tarOutputPath $BACKUP_DIR");
}

$dpr->pp("Delete old backups.");
$files = scandir($BASE_DIR . "/backups/" . $SERVER_NAME);
$files = array_filter($files, function ($file) {
    global $BASE_DIR, $SERVER_NAME;
    return $file != "." && $file != ".." && is_dir($BASE_DIR . "/backups/" . $SERVER_NAME . "/" . $file);
});
foreach ($files as $file) {
    $unixtime = strtotime(mb_substr($file, 0, 10));
    if ($unixtime == -1) {
        continue;
    }
    if ($unixtime >= time() - $DATA_HOLD_SEC) {
        continue;
    }

    $dpr->pp("**dir**: `$file`");
    $bool = deleteFolder($BASE_DIR . "/backups/" . $SERVER_NAME . "/" . $file);
    $dpr->pp("**Deleted**: " . var_export($bool, true));
}

$dpr->pp("Delete old backups Finished.");
$dpr->flush();