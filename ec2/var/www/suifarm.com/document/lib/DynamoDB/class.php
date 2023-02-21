<?php

declare(strict_types=1);

class DynamoDB
{
    static private function getClient(): Aws\DynamoDb\DynamoDbClient
    {
        static $client = null;

        if ($client === null) {
            if (!class_exists("Aws\Sdk")) {
                require __DIR__ . "/../../../lib/aws/vendor/autoload.php";
            }

            $client = (new Aws\Sdk([
                "version" => "latest",
                "region"  => "ap-northeast-1",
            ]))->createDynamoDb();
        }

        return $client;
    }

    static private function request(string $function, array $data): Aws\Result
    {
        // normalize data
        if (isset($data["TableName"])) $data["TableName"] = _DOMAIN_ . "-" . $data["TableName"];
        if (isset($data["ExpressionAttributeValues"])) $data["ExpressionAttributeValues"] = array_map("self::encode", $data["ExpressionAttributeValues"]);

        if (isset($data["Item"])) {
            if ($data["Item"]["sort"] ?? null) $data["Item"]["sort"] = (string)$data["Item"]["sort"];
            $data["Item"] = array_map("self::encode", $data["Item"]);
        }

        if (isset($data["Key"])) $data["Key"] = array_map("self::encode", $data["Key"]);

        $try_count = 0;

        while (3 > ++$try_count) {
            try {
                $response = self::getClient()->{$function}($data);
                $code = $response["@metadata"]["statusCode"] ?? 0;
                if ($code === 200) return $response;
                break;
            } catch (Aws\DynamoDb\Exception\DynamoDbException $e) {
                $code = $e->getAwsErrorCode();
                $can_retry = true;

                switch ($code) {
                    case "AccessDeniedException":
                    case "IncompleteSignatureException":
                    case "MissingAuthenticationTokenException":
                    case "ResourceInUseException":
                    case "ResourceNotFoundException":
                    case "ValidationException":
                    case "UnrecognizedClientException":
                    case "ConditionalCheckFailedException":
                        $can_retry = false;
                        break;
                }

                if ($can_retry) {
                    sleep(1);
                    continue;
                }
                break;
            } catch (Exception $e) {
                $code = $e->getMessage();
                break;
            }
        }

        new Discord("error", [
            "content" => implode("\n", [
                "```\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```",
                $code,
                __CLASS__ . "::{$function}()",
            ]),
        ]);

        http_response_code(500);
        exit;
    }

    // query request
    static public function query(array $data): array
    {
        $response = self::request(__FUNCTION__, $data);

        return $response && isset($response["Items"]) && is_array($response["Items"]) ? array_map(function (array $item) {
            return array_map("self::decode", $item);
        }, $response["Items"]) : [];
    }

    // putItem request
    static public function putItem(array $data): Aws\Result
    {
        return self::request(__FUNCTION__, $data);
    }

    // updateItem request
    static public function updateItem(array $data): Aws\Result
    {
        return self::request(__FUNCTION__, $data);
    }

    // deleteItem request
    static public function deleteItem(array $data): Aws\Result
    {
        return self::request(__FUNCTION__, $data);
    }

    // getItem request
    static public function getItem(array $data): array|null
    {
        $response = self::request(__FUNCTION__, $data);
        return $response && isset($response["Item"]) && $response["Item"] && is_array($response["Item"]) ? array_map("self::decode", $response["Item"]) : null;
    }

    static private function encode(array|int|string $value): array
    {
        if (is_array($value)) return $value;
        if (($type = is_int($value) ? "N" : "S") === "S" && strlen($value) > 512) $type = "B";
        return [$type => ($type === "B" ? gzencode($value, 2) : (string)$value),];
    }

    static private function decode(array $data): string|int|bool
    {
        $value = array_values($data)[0];

        switch (array_keys($data)[0]) {
            case "S":
                return (string)$value;
            case "N":
                return (int)$value;
            case "B":
                return gzdecode($value);
        }

        return false;
    }
}
