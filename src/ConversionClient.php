<?php

namespace ConversionTools;

class ConversionClient
{
    const VERSION = '1.0.2';

    public static $userAgent = 'conversiontools-php';

    public static $baseUrl = 'https://api.conversiontools.io/v1';

    public static $DEBUG = FALSE;

    private static $token;

    public function __construct($token)
    {
        self::$token = $token;
    }

    public static function getToken()
    {
        return self::$token;
    }

    public static function getUserAgent()
    {
        return self::$userAgent . '/' . self::VERSION;
    }

    public function convert($type, $fileOrUrlInput, $fileOutput, $options = [])
    {
        if (isset($fileOrUrlInput)) {
            if (is_file($fileOrUrlInput)) {
                $file_id = API::uploadFile($fileOrUrlInput);
                $options = array_merge($options, ['file_id' => $file_id]);
            }
            if (parse_url($fileOrUrlInput)) {
                $options = array_merge($options, ['url' => $fileOrUrlInput]);
            }
        }
        $task_id = API::createTask($type, $options);
        while (isset($task_id)) {
            list($status, $file_id_result) = API::getTaskStatus($task_id);
            switch ($status) {
                case 'SUCCESS':
                    API::downloadFile($file_id_result, $fileOutput);
                    return;
                case 'ERROR':
                    return;
            }
            sleep(5);
        }
    }
}