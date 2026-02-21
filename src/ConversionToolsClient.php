<?php

declare(strict_types=1);

namespace ConversionTools;

use ConversionTools\Api\ConfigApi;
use ConversionTools\Api\FilesApi;
use ConversionTools\Api\TasksApi;
use ConversionTools\Http\HttpClient;
use ConversionTools\Models\Task;
use ConversionTools\Utils\Validation;

class ConversionToolsClient
{
    public const VERSION = '2.0.0';

    private const DEFAULT_BASE_URL               = 'https://api.conversiontools.io/v1';
    private const DEFAULT_TIMEOUT_MS             = 300_000;
    private const DEFAULT_RETRIES                = 3;
    private const DEFAULT_RETRY_DELAY_MS         = 1_000;
    private const DEFAULT_RETRYABLE_STATUSES     = [408, 500, 502, 503, 504];
    private const DEFAULT_POLLING_INTERVAL_MS    = 5_000;
    private const DEFAULT_MAX_POLLING_INTERVAL_MS = 30_000;
    private const DEFAULT_POLLING_BACKOFF        = 1.5;

    public readonly FilesApi $files;
    public readonly TasksApi $tasks;

    private readonly HttpClient $http;
    private readonly ConfigApi $configApi;
    private readonly array $config;

    /**
     * @param array{
     *     api_token:               string,
     *     base_url?:               string,
     *     timeout?:                float,
     *     retries?:                int,
     *     retry_delay?:            float,
     *     retryable_statuses?:     list<int>,
     *     polling_interval?:       float,
     *     max_polling_interval?:   float,
     *     polling_backoff?:        float,
     *     user_agent?:             string,
     *     webhook_url?:            string,
     *     on_upload_progress?:     callable(array): void,
     *     on_download_progress?:   callable(array): void,
     *     on_conversion_progress?: callable(array): void,
     * } $config
     */
    public function __construct(array $config)
    {
        Validation::validateApiToken($config['api_token']);

        $this->config = [
            'api_token'              => $config['api_token'],
            'base_url'               => $config['base_url']              ?? self::DEFAULT_BASE_URL,
            'timeout'                => $config['timeout']               ?? self::DEFAULT_TIMEOUT_MS,
            'retries'                => $config['retries']               ?? self::DEFAULT_RETRIES,
            'retry_delay'            => $config['retry_delay']           ?? self::DEFAULT_RETRY_DELAY_MS,
            'retryable_statuses'     => $config['retryable_statuses']    ?? self::DEFAULT_RETRYABLE_STATUSES,
            'polling_interval'       => $config['polling_interval']      ?? self::DEFAULT_POLLING_INTERVAL_MS,
            'max_polling_interval'   => $config['max_polling_interval']  ?? self::DEFAULT_MAX_POLLING_INTERVAL_MS,
            'polling_backoff'        => $config['polling_backoff']       ?? self::DEFAULT_POLLING_BACKOFF,
            'user_agent'             => $config['user_agent']            ?? null,
            'webhook_url'            => $config['webhook_url']           ?? null,
            'on_upload_progress'     => $config['on_upload_progress']    ?? null,
            'on_download_progress'   => $config['on_download_progress']  ?? null,
            'on_conversion_progress' => $config['on_conversion_progress'] ?? null,
        ];

        $this->http = new HttpClient(
            apiToken:          $this->config['api_token'],
            baseUrl:           $this->config['base_url'],
            timeoutMs:         $this->config['timeout'],
            retries:           $this->config['retries'],
            retryDelayMs:      $this->config['retry_delay'],
            retryableStatuses: $this->config['retryable_statuses'],
            userAgent:         $this->config['user_agent'] ?? ('conversiontools-php/' . self::VERSION),
        );

        $this->files     = new FilesApi($this->http);
        $this->tasks     = new TasksApi($this->http);
        $this->configApi = new ConfigApi($this->http);
    }

    /**
     * Convert a file in one call: upload → create task → wait → download.
     *
     * @param  string       $conversionType  e.g. 'convert.pdf_to_word'
     * @param  string|array $input           File path, ['url' => ...], ['file_id' => ...],
     *                                       ['buffer' => string, 'filename'? => string], or
     *                                       ['resource' => resource, 'filename'? => string]
     * @param  string|null  $output          Output file path (optional)
     * @param  array        $options         Conversion-specific options. Pass 'sandbox' => true
     *                                       to run in sandbox mode (no quota consumed).
     * @param  bool         $wait            If false, returns task ID immediately without waiting
     * @param  string|null  $callbackUrl     Webhook URL for this task
     * @param  array        $polling         Override polling: interval, max_interval
     *
     * @return string  Output file path (if $wait=true), or task ID (if $wait=false)
     */
    public function convert(
        string $conversionType,
        string|array $input,
        ?string $output = null,
        array $options = [],
        bool $wait = true,
        ?string $callbackUrl = null,
        array $polling = [],
    ): string {
        $inputInfo   = Validation::validateConversionInput($input);
        $taskOptions = $options;

        if ($inputInfo['type'] === 'file_id') {
            $taskOptions['file_id'] = $inputInfo['value'];
        } elseif ($inputInfo['type'] === 'url') {
            $taskOptions['url'] = $inputInfo['value'];
        } else {
            $uploadInput = match ($inputInfo['type']) {
                'path'     => $inputInfo['value'],
                'buffer'   => ['buffer'   => $inputInfo['value'], 'filename' => $inputInfo['filename']],
                'resource' => ['resource' => $inputInfo['value'], 'filename' => $inputInfo['filename']],
                default    => $inputInfo['value'],
            };

            $uploadOptions = [];
            if ($this->config['on_upload_progress'] !== null) {
                $uploadOptions['on_progress'] = $this->config['on_upload_progress'];
            }

            $fileId                 = $this->files->upload($uploadInput, $uploadOptions);
            $taskOptions['file_id'] = $fileId;
        }

        $task = $this->createTask($conversionType, $taskOptions, $callbackUrl);

        if (!$wait) {
            return $task->id;
        }

        $waitOptions = [
            'polling_interval'     => $polling['interval']     ?? $this->config['polling_interval'],
            'max_polling_interval' => $polling['max_interval'] ?? $this->config['max_polling_interval'],
        ];

        if ($this->config['on_conversion_progress'] !== null) {
            $waitOptions['on_progress'] = function (array $status) use ($task): void {
                ($this->config['on_conversion_progress'])([
                    'loaded'  => $status['conversionProgress'],
                    'total'   => 100,
                    'percent' => $status['conversionProgress'],
                    'status'  => $status['status'],
                    'task_id' => $task->id,
                ]);
            };
        }

        $task->wait($waitOptions);

        return $task->downloadTo($output, $this->config['on_download_progress']);
    }

    /**
     * Create a conversion task without waiting for it to finish.
     */
    public function createTask(
        string $conversionType,
        array $options,
        ?string $callbackUrl = null,
    ): Task {
        $request = [
            'type'    => $conversionType,
            'options' => $options,
        ];

        if ($callbackUrl !== null) {
            $request['callbackUrl'] = $callbackUrl;
        } elseif ($this->config['webhook_url'] !== null) {
            $request['callbackUrl'] = $this->config['webhook_url'];
        }

        $response = $this->tasks->create($request);

        return new Task(
            id:             $response['task_id'],
            type:           $conversionType,
            tasksApi:       $this->tasks,
            filesApi:       $this->files,
            defaultPolling: [
                'interval'     => $this->config['polling_interval'],
                'max_interval' => $this->config['max_polling_interval'],
                'backoff'      => $this->config['polling_backoff'],
            ],
        );
    }

    /**
     * Retrieve an existing task by ID.
     */
    public function getTask(string $taskId): Task
    {
        $response = $this->tasks->getStatus($taskId);

        return new Task(
            id:                 $taskId,
            type:               '',
            tasksApi:           $this->tasks,
            filesApi:           $this->files,
            status:             $response['status'],
            fileId:             $response['file_id'] ?? null,
            error:              $response['error'] ?? null,
            conversionProgress: $response['conversionProgress'] ?? 0,
            defaultPolling:     [
                'interval'     => $this->config['polling_interval'],
                'max_interval' => $this->config['max_polling_interval'],
                'backoff'      => $this->config['polling_backoff'],
            ],
        );
    }

    /**
     * Return the rate limits captured from the last API response.
     */
    public function getRateLimits(): ?array
    {
        return $this->http->getLastRateLimits();
    }

    /**
     * Return the authenticated user's info.
     */
    public function getUser(): array
    {
        return $this->configApi->getUserInfo();
    }

    /**
     * Return the API configuration (available conversion types).
     */
    public function getConfig(): array
    {
        return $this->configApi->getConfig();
    }
}
