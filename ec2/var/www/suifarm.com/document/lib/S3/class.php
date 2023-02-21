<?php

declare(strict_types=1);

class S3
{
    static private function request(string $function, array $argument): bool|null|Aws\Result
    {
        $try_count = 3;
        $code = $message = null;

        while (--$try_count > 0) {
            try {
                return self::client()->{$function}(...$argument);
            } catch (Aws\S3\Exception\S3Exception $e) {
                $code = $e->getAwsErrorCode();

                if ("NoSuchKey" === $code || in_array($function, ["getObject", "headObject", "doesObjectExist"], true)) {
                    return null;
                }

                break;
            } catch (Aws\Exception\AwsException $e) {
                $code = $e->getAwsErrorCode();

                if ($code === "InternalError") {
                    sleep(1);
                    continue;
                }

                $message = $e->getMessage();

                break;
            }
        }

        new Discord("error", [
            "content" => implode("\n", [
                "```\n" . json_encode($argument, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```",
                $code,
                $message,
                __CLASS__ . "::" . __FUNCTION__ . "()",
            ]),
        ]);

        http_response_code(500);
        exit;
    }

    static private function client(?array $credentials = null): Aws\S3\S3Client
    {
        static $client = null;
        if ($client !== null && $credentials === null) return $client;

        if (!class_exists("Aws\Sdk")) {
            require __DIR__ . "/../../../lib/aws/vendor/autoload.php";
        }

        $config = [
            "version" => "latest",
            "region" => "ap-northeast-1",
        ];

        if ($credentials) {
            $config["credentials"] = $credentials;
        }

        return $client = (new Aws\Sdk($config))->createS3();
    }

    static public function listObjects(string $bucket, string $prefix)
    {
        return self::request(__FUNCTION__, [
            [
                "Bucket" => $bucket,
                // "Key" => $key,
                'Prefix' => $prefix,
                // 'Marker' => $marker,
                // 'Delimiter' => $delimiter
            ],
        ]);
    }

    static public function deleteObjects(string $bucket, array $keys)
    {
        return self::request(__FUNCTION__, [
            [
                "Bucket" => $bucket,
                'Delete' => [
                    'Objects' => $keys,/*array_map(fn(string $key) => [
						"Key" => $key,
					], $keys),*/
                ],
            ],
        ]);
    }

    static public function getObject(string $bucket, string $key): bool|null|Aws\Result
    {
        return self::request(__FUNCTION__, [
            [
                "Bucket" => $bucket,
                "Key" => $key,
            ],
        ]);
    }

    static public function doesObjectExist(string $bucket, string $key): bool|null
    {
        return self::request(__FUNCTION__, [$bucket, $key]);
    }

    static public function getObjectBody(string $bucket, string $key): string|null
    {
        if (($response = self::getObject($bucket, $key)) === false) return false;
        return ($body = $response ? $response["Body"] ?? null : null) ? (string)$body : null;
    }

    static public function deleteObject(string $bucket, string $key)
    {
        return self::request(__FUNCTION__, [
            [
                "Bucket" => $bucket,
                "Key" => $key,
            ],
        ]);
    }

    static public function headObject(string $bucket, string $key): null|Aws\Result
    {
        return self::request(__FUNCTION__, [
            [
                "Bucket" => $bucket,
                "Key" => $key,
            ],
        ]);
    }

    static public function putObject(string $bucket, string $key, array $Body = []): Aws\Result
    {
        return self::request(__FUNCTION__, [
            array_merge([
                "Bucket" => $bucket,
                "Key" => $key,
            ], $Body),
        ]);
    }

    static public function copyObject(string $src, string $dst): bool|null|Aws\Result
    {
        $tokens = explode("/", $dst);
        $bucket = array_shift($tokens);

        return self::request(__FUNCTION__, [
            [
                "Bucket" => $bucket,
                "CopySource" => $src,
                "Key" => implode("/", $tokens),
            ],
        ]);
    }

    static public function createPresignedRequest(string $bucket, string $command, string $key): bool|string
    {
        // case "PutObject":
        // case "GetObject":
        $path = Secret::get("/s3/{$command}.json");
        $credentials = file_exists($path) ? json_decode(file_get_contents($path), true) : null;

        $code = null;
        $try_count = 0;

        while (3 > ++$try_count) {
            try {
                $cmd = self::client($credentials)->getCommand($command, [
                    'Bucket' => $bucket,
                    'Key' => $key,
                ]);

                return (string)self::client()->{__FUNCTION__}($cmd, "+1 minutes")->getUri();
            } catch (Aws\S3\Exception\S3Exception $e) {
                $code = $e->getAwsErrorCode();
                break;
            } catch (Aws\Exception\AwsException $e) {
                $code = $e->getAwsErrorCode();

                if ($code === "InternalError") {
                    sleep(1);
                    continue;
                }

                break;
            }
        }

        new Discord("error", [
            "content" => implode("\n", [
                "```\n" .
                    json_encode([
                        'Bucket' => $bucket,
                        'Key' => $key,
                        'Code' => $code,
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    . "\n```",
                __CLASS__ . "::" . __FUNCTION__,
            ])
        ]);

        http_response_code(500);
        exit;
    }
}
