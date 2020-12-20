<?php
/**
 * microtime()をフォーマットする
 *
 * @param float $time microtime()
 * @param string|null $format フォーマット形式
 * @return string フォーマットしたテキスト
 */
function formatMicrotime($time, $format = null)
{
    if (is_string($format)) {
        $sec = (int) $time;
        $msec = (int) (($time - $sec) * 100000);
        $formated = date($format, $sec) . '.' . $msec;
    } else {
        $formated = sprintf('%0.5f', $time);
    }
    return $formated;
}

/**
 * bytesから適切な値に変換する
 *
 * @param int $size
 * @param integer $dec 小数点以下桁数
 * @param boolean $separate 3桁ごとにカンマで区切るか
 * @return void
 */
function byte_format($size, $dec = -1, $separate = false)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $digits = ($size == 0) ? 0 : floor(log($size, 1024));

    $over = false;
    $max_digit = count($units) - 1;

    if ($digits == 0) {
        $num = $size;
    } elseif (!isset($units[$digits])) {
        $num = $size / (pow(1024, $max_digit));
        $over = true;
    } else {
        $num = $size / (pow(1024, $digits));
    }

    if ($dec > -1 && $digits > 0) {
        $num = sprintf("%.{$dec}f", $num);
    }
    if ($separate && $digits > 0) {
        $num = number_format($num, $dec);
    }

    return ($over) ? $num . $units[$max_digit] : $num . $units[$digits];
}


function deleteFolder($dirpath)
{
    $files = scandir($dirpath);
    $files = array_filter($files, function ($file) {
        return $file != "." && $file != "..";
    });
    foreach ($files as $file) {
        if (is_file($dirpath . "/" . $file)) {
            unlink($dirpath . "/" . $file);
        } else {
            deleteFolder($dirpath);
        }
    }
    return rmdir($dirpath);
}