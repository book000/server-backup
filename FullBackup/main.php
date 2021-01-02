<?php
$options = getopt("s:");
$SERVER_NAME = $options["s"];
$BACKUP_TYPE = "FullBackup";

chdir(__DIR__);

$TODAY = date("Y-m-d");
$BASE_DIR = dirname(__DIR__);
$LOG_DIR = "$BASE_DIR/logs/$SERVER_NAME/$BACKUP_TYPE/";
if (!file_exists($LOG_DIR)) {
    mkdir($LOG_DIR, 0777, true);
}
$LOG_PATH = $LOG_DIR . $TODAY . ".log";

$BACKUP_DIR = "$BASE_DIR/backups/$SERVER_NAME/$BACKUP_TYPE/";
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
$identity = $config[$BACKUP_TYPE][$SERVER_NAME]["identity"];
$passphrase = $config[$BACKUP_TYPE][$SERVER_NAME]["passphrase"];
$from = $config[$BACKUP_TYPE][$SERVER_NAME]["from"];

$DATA_HOLD_SEC = $config[$BACKUP_TYPE]["DATA-HOLD-SEC"];

$cmd = "/bin/bash " . __DIR__ . "/rsync.sh -h '$hostname' -r '$port' -u '$username' -i '$identity' -p '$passphrase' -f '$from' -o '$BACKUP_DIR' 2>&1 | tee $LOG_PATH";

$start_time = microtime(true);
system($cmd, $ret);
$finished_time = microtime(true);
$process_time = $finished_time - $start_time;
$process_formattedtime = sprintf("%02d分%02d秒", $process_time / 60, $process_time % 60);
$start_time_formatted = formatMicrotime($start_time);
$finished_time_formatted = formatMicrotime($finished_time);
$TODAY_DIR = date("Ymd");

$dpr->pp("**\$process_formattedtime**: $process_formattedtime");

$dpr->pp("Finished backup.");
$latest_size = calcSize("{$BACKUP_DIR}latest/");
$latest_formattedSize = byte_format($latest_size, 2);
$today_size = calcSize("{$BACKUP_DIR}{$TODAY_DIR}/");
$today_formattedSize = byte_format($today_size, 2);
$dpr->send("[" . date("Y/m/d H:i:s") . "] **$SERVER_NAME: $BACKUP_TYPE** successful. (size: `$latest_formattedSize` / `$today_formattedSize`)", "793611473162207262");
$dpr->flush();

if (date("d") <= 7) {
    // 1週目
    $tarOutputPath = "/mnt/WHITEBOX/DataTomachi/backup/Server-Backup/$BACKUP_TYPE/$SERVER_NAME/$TODAY.tar.gz";
    if (!file_exists(dirname($tarOutputPath))) {
        mkdir(dirname($tarOutputPath), 0777, true);
    }
    system("/usr/bin/tar cvf $tarOutputPath -C $BACKUP_DIR/latest .", $ret);
    $size = filesize($tarOutputPath);
    $formattedSize = byte_format($size, 2);
    $dpr->send("[" . date("Y/m/d H:i:s") . "] **$SERVER_NAME: $BACKUP_TYPE** " . ($ret == 0 ? "Successful" : "**Failed**") . " moved to WhiteBox (size: `$formattedSize`)", "793611473162207262");
}

$dpr->pp("Delete old backups.");
$files = scandir("$BASE_DIR/backups/$SERVER_NAME/$BACKUP_TYPE/");
$files = array_filter($files, function ($file) use ($BASE_DIR, $SERVER_NAME, $BACKUP_TYPE) {
    return $file != "." && $file != ".." && is_dir("$BASE_DIR/backups/$SERVER_NAME/$BACKUP_TYPE/$file") && mb_strlen($file) == 8;
});
foreach ($files as $file) {
    $unixtime = mktime(0, 0, 0, mb_substr($file, 4, 2), mb_substr($file, 6, 2), mb_substr($file, 0, 4));
    if ($unixtime == -1) {
        continue;
    }
    if ($unixtime >= time() - $DATA_HOLD_SEC) {
        continue;
    }

    $dpr->pp("**dir**: `$file`");
    $bool = deleteFolder("$BASE_DIR/backups/$SERVER_NAME/$BACKUP_TYPE/$file");
    $dpr->pp("**Deleted**: " . var_export($bool, true));
}

$dpr->pp("Delete old backups Finished.");
$dpr->flush();