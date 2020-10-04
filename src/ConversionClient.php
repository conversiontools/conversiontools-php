<?php

namespace ConversionTools;

class ConversionClient
{
    const VERSION = '1.0.0';

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

    public function convert($type, $fileInput, $fileOutput, $options = NULL)
    {
        $file_id = API::uploadFile($fileInput);
        $task_id = API::createTask($type, $file_id, $options);
        while (TRUE) {
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