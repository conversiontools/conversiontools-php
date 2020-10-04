<?php

namespace ConversionTools;

class Request
{
    private function initialize($method, $url, $headers, $data = NULL)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        } else if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1); 
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        return $ch;
    }

    private function handleErrorCode($ch)
    {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $message = curl_error($ch);
        $code = curl_errno($ch);

        if ($code !== 0) {
            throw new \Exception('Request Error: ' . $message);
        }

        if ($httpCode !== 200) {
            throw new \Exception('HTTP Status Code: ' . $httpCode);
        }

        if (ConversionClient::$DEBUG === TRUE) {
            if ($code != 0) {
                print "CURL Code: $code\n";
                print "Message: $message\n";
            }
            print "HTTP Code: $httpCode\n";
            print "Response: $response\n";
        }
    }

    private static function makeRequest($method, $url, $headers, $data = NULL)
    {
        $ch = self::initialize($method, $url, $headers, $data);
        $response = curl_exec($ch);
        self::handleErrorCode($ch);
        curl_close($ch);
        return $response;
    }

    private static function makeFileDownloadRequest($method, $url, $headers, $filename)
    {
        $fh = fopen($filename, 'wb');
        if (!$fh) {
            throw new \Exception("Cannot open file $filename");
        }
        $ch = self::initialize($method, $url, $headers);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        $response = curl_exec($ch);
        fclose($fh);
        self::handleErrorCode($ch);
        curl_close($ch);

        return;
    }

    private static function prepareHeaders()
    {
        $headers = [
            'Authorization: Bearer ' . ConversionClient::getToken(),
            'User-Agent: ' . ConversionClient::getUserAgent(), 
        ];
        return $headers;
    }

    public static function requestAPI($method, $url, $data=NULL)
    {
        $headers = self::prepareHeaders();
        array_push($headers, 'Content-Type: application/json');
        $response = self::makeRequest($method, $url, $headers, $data);
        return $response;
    }
    
    public static function uploadAPI($url, $filename)
    {
        $headers = self::prepareHeaders();
        array_push($headers, 'Content-Type: multipart/form-data');
        if (function_exists('curl_file_create')) { // For PHP 5.5+
            $file = curl_file_create($filename);
        } else {
            $file = '@' . realpath($filename);
        }
        $data = ['file' => $file];
        $response = self::makeRequest('POST', $url, $headers, $data);
        return $response;
    }

    public static function downloadAPI($url, $filename)
    {
        $headers = self::prepareHeaders();
        self::makeFileDownloadRequest('GET', $url, $headers, $filename);
    }
}