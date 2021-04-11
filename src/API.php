<?php

namespace ConversionTools;

class API
{
    public static function createTask($type, $options = [])
    {
        $data = [
           'type' => $type,
           'options' => $options,
        ];
        $result = Request::requestAPI('POST', ConversionClient::$baseUrl . '/tasks', json_encode($data));
        $json = json_decode($result, true);
        if ($json['error'] !== NULL) {
            throw new \Exception('Conversion Error: ' . $json['error']);
        }
        return $json['task_id'];
    }

    public static function getTaskStatus($task_id)
    {
        $result = Request::requestAPI('GET', ConversionClient::$baseUrl . "/tasks/$task_id");
        $json = json_decode($result, true);
        if ($json['error'] !== NULL) {
            throw new \Exception('Conversion Error: ' . $json['error']);
        }
        return [$json['status'], $json['file_id']];
    }

    public static function uploadFile($filename)
    {
        if (is_readable($filename) !== TRUE) {
            throw new \Exception('Cannot read input file');
        }
        $result = Request::uploadAPI(ConversionClient::$baseUrl . '/files', $filename);
        $json = json_decode($result, true);
        if ($json['error'] !== NULL) {
            throw new \Exception('File Upload Error: ' . $json['error']);
        }
        $file_id = $json['file_id'];
        return $file_id;
    }

    public static function downloadFile($file_id, $filename)
    {
        Request::downloadAPI(ConversionClient::$baseUrl . "/files/$file_id", $filename);
    }
}