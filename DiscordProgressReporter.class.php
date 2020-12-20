<?php
class DiscordProgressReporter
{
    private $token;
    private $channel; // Channel ID
    private $loggerFile = null;
    private $prefix = null;
    private $reportmsg = "";

    public function __construct($token, $channel)
    {
        $this->token = $token;
        $this->channel = $channel;

        $this->autoCompress();
    }
    public function setLogger($file)
    {
        $this->loggerFile = $file;
    }
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
    public function pp($str = "", $n = "\n", $date = true, $noprefix = false)
    {
        $dateAndPrefix = ($date || (!$noprefix && $this->prefix != null) ? "[`" : "") .
            ($date ? date("H:i:s") : "") .
            (!$noprefix && $this->prefix != null ? "|" . $this->prefix . "`] " : "`] ");
        if ($this->loggerFile != null) {
            file_put_contents($this->loggerFile, $dateAndPrefix . $str . PHP_EOL, FILE_APPEND);
        }
        if (mb_strlen($dateAndPrefix . $str . $n) >= 1950) {
            $this->flush();
            $arr = $this->mb_str_split($str, 1900, mb_internal_encoding());
            foreach ($arr as $one) {
                $this->pp($one);
            }
            return;
        }
        if ((mb_strlen($this->reportmsg) + mb_strlen($dateAndPrefix . $str . $n)) >= 1950) {
            $this->flush();
        }
        cli_set_process_title($dateAndPrefix . $str);
        echo $dateAndPrefix . $str . $n;
        $this->reportmsg .= $dateAndPrefix . $str . $n;
    }
    public function flush()
    {
        $this->send($this->reportmsg);
        $this->reportmsg = "";
    }
    public function send($str, $channel = null)
    {
        if ($channel == null) {
            $channel = $this->channel;
        }
        $data = array(
            "content" => $str
        );
        $header = array(
            "Content-Type: application/json",
            "Content-Length: " . strlen(json_encode($data)),
            "Authorization: Bot " . $this->token,
            "User-Agent: DiscordBot (https://jaoafa.com, v0.0.1)"
        );

        $context = array(
            "http" => array(
                "method" => "POST",
                "header" => implode("\r\n", $header),
                "content" => json_encode($data),
                "ignore_errors" => true
            )
        );
        $context = stream_context_create($context);
        $contents = file_get_contents("https://discordapp.com/api/channels/" . $channel . "/messages", false, $context);
        preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
        $status_code = $matches[1];
        if ($status_code != 200) {
            echo "\n\nDiscordSend Error: " . $status_code . " : " . $contents . "\n\n";
        }
        sleep(1);
    }
    public function __destruct()
    {
        if (mb_strlen($this->reportmsg) != 0) {
            $this->send($this->reportmsg);
        }
    }
    public function mb_str_split($string, $split_length = 1, $encoding = null)
    {
        if ($split_length < 1) {
            return false;
        }
        if (func_num_args() < 3) {
            $encoding = mb_internal_encoding();
        }
        $ret = array();
        $len = mb_strlen($string, $encoding);
        for ($i = 0; $i < $len; $i += $split_length) {
            $ret[] = mb_substr($string, $i, $split_length, $encoding);
        }
        if (!$ret) {
            $ret[] = '';
        }
        return $ret;
    }
    public function autoCompress()
    {
        global $dpr;
        $dir = dirname($this->loggerFile);
        if(!file_exists($dir)){
            return;
        }
        $files = scandir($dir);
        $files = array_filter($files, function ($file) {
            global $dir;
            return $file != "." && $file != ".." && is_file($dir . "/" . $file) && mb_substr($file, -3) != ".gz";
        });
        if (isset($dpr)) {
            $dpr->pp("autoCompress: found " . count($files) . " files.");
        } else {
            echo "autoCompress: found " . count($files) . " files.\n";
        }
        foreach ($files as $file) {
            if (realpath($this->loggerFile) == realpath($dir . "/" . $file)) {
                continue;
            }
            $path = $dir . "/" . $file;
            if (isset($dpr)) {
                $dpr->pp("autoCompress: gzip $file start");
            } else {
                echo "autoCompress: gzip $file start\n";
            }
            system("gzip $path", $ret);
            if (isset($dpr)) {
                $dpr->pp("autoCompress: gzip $file finished -> \$ret = $ret");
            } else {
                echo "autoCompress: gzip $file finished -> \$ret = $ret\n";
            }
        }
    }
}
