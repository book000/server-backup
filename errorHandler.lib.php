<?php
function registErrorHandler()
{
    // エラー時に例外をスローするようにコールバック関数を登録
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        global $dpr;
        $dpr->send(":warning:<@221991565567066112> An error has occurred!:warning:\nMessage: ```$errstr``` (`$errno`)\nFile: `$errfile`  on line `$errline`");

        $filename = basename($errfile);
        $title = "[$filename:$errline] $errstr";
        $message = "**\$errorstr**: `$errstr` (`$errno`)\n**\$errfile**: `$errfile` on line `$errline`";

        processIssue($title, $message);
    });
    function GetDefineNameFromint($i)
    {
        $defines = get_defined_constants(true);
        foreach ($defines["Core"] as $key => $define) {
            if (mb_substr($key, 0, 2) != "E_") {
                continue;
            }
            if ($define == $i) {
                return $key;
            }
        }
    }
    function processIssue($title, $message)
    {
        if (!file_exists(__DIR__ . "/config.json")) {
            return;
        }
        $config = json_decode(file_get_contents(__DIR__ . "/config.json"), true);
        $token = $config["githubToken"];
        $repo = $config["repo"];

        $issue = getMatchIssue($token, $repo, $title);
        if ($issue === false) {
            echo "Error: GitHub issue get failed\n";
            return;
        }
        if ($issue === null) {
            // ない
            $bool = createIssue($token, $repo, $title, $message);
            echo "createIssue: " . ($bool ? "Success" : "Error") . "\n";
        } else {
            $bool = reopenIssue($token, $repo, $issue["number"]);
            echo "reopenIssue: " . ($bool ? "Success" : "Error") . "\n";
            $bool = commentIssue($token, $repo, $issue["number"], $message);
            echo "commentIssue: " . ($bool ? "Success" : "Error") . "\n";
        }
    }
    function getMatchIssue($token, $repo, $title)
    {
        $url = "https://api.github.com/repos/$repo/issues?state=all";
        $headers = [
            "Authorization: token $token",
            "User-Agent: ErrorHandler"
        ];
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => implode("\r\n", $headers),
                "ignore_errors" => true
            ]
        ]);
        $response = file_get_contents($url, false, $context);
        preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
        $status_code = $matches[1];
        if ($status_code != 200) {
            return false;
        }
        $response = json_decode($response, true);
        foreach ($response as $commit) {
            if ($commit["title"] == $title) {
                return $commit;
            }
        }
        return null;
    }
    function createIssue($token, $repo, $title, $message)
    {
        $url = "https://api.github.com/repos/$repo/issues";
        $headers = [
            "Content-Type: application/json",
            "Authorization: token $token",
            "User-Agent: ErrorHandler"
        ];
        $content = json_encode([
            "title" => $title,
            "body" => $message,
            "assignees" => ["book000"]
        ]);
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => implode("\r\n", $headers),
                "content" => $content,
                "ignore_errors" => true
            ]
        ]);
        $response = file_get_contents($url, false, $context);
        preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
        $status_code = $matches[1];
        if ($status_code != 200) {
            echo $response;
        }
        return $status_code == 201;
    }
    function commentIssue($token, $repo, $issueId, $message)
    {
        $url = "https://api.github.com/repos/$repo/issues/$issueId/comments";
        $headers = [
            "Content-Type: application/json",
            "Authorization: token $token",
            "User-Agent: ErrorHandler"
        ];
        $content = json_encode([
            "body" => $message
        ]);
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => implode("\r\n", $headers),
                "content" => $content,
                "ignore_errors" => true
            ]
        ]);
        $response = file_get_contents($url, false, $context);
        preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
        $status_code = $matches[1];
        if ($status_code != 200) {
            echo $response;
        }
        return $status_code == 201;
    }
    function reopenIssue($token, $repo, $issueId)
    {
        $url = "https://api.github.com/repos/$repo/issues/$issueId";
        $headers = [
            "Content-Type: application/json",
            "Authorization: token $token",
            "User-Agent: ErrorHandler"
        ];
        $content = json_encode([
            "state" => "open",
        ]);
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => implode("\r\n", $headers),
                "content" => $content,
                "ignore_errors" => true
            ]
        ]);
        $response = file_get_contents($url, false, $context);
        preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
        $status_code = $matches[1];
        if ($status_code != 200) {
            echo $response;
        }
        return $status_code == 200;
    }
}