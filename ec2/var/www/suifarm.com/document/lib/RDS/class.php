<?php

declare(strict_types=1);

class RDS
{
    static public function fetch(...$args): array|null
    {
        $response = self::execute(...$args)->{__FUNCTION__}(PDO::FETCH_ASSOC);
        return $response ? $response : null;
    }

    static public function fetchColumn(...$args): string|int|bool|null
    {
        return self::execute(...$args)->{__FUNCTION__}();
    }

    static public function fetchAll(...$args): array
    {
        return self::execute(...$args)->{__FUNCTION__}(PDO::FETCH_ASSOC);
    }

    static public function insert(...$args): int
    {
        self::execute(...$args);
        return (int)self::client()->lastInsertId();
    }

    static public function execute(string $sql = "", ?array $values = []): PDOStatement
    {
        try {
            $stmt = self::client()->prepare($sql);

            if ($values) {
                for ($i = 0, $l = count($values); $l > $i; $i++) {
                    if (is_string($values[$i]) || is_float($values[$i])) {
                        $stmt->bindParam(($i + 1), $values[$i], PDO::PARAM_STR);
                    } elseif (is_int($values[$i])) {
                        $stmt->bindParam(($i + 1), $values[$i], PDO::PARAM_INT);
                    } elseif (is_null($values[$i])) {
                        $stmt->bindParam(($i + 1), $values[$i], PDO::PARAM_NULL);
                    }
                }
            }

            if ($stmt->execute() !== false) {
                return $stmt;
            }
        } catch (PDOException $e) {
            $e_message = $e->getMessage();
        }

        new Discord("error", [
            "content" => implode("\n", [
                substr("```\n" . $sql . "\n```", 0, 900),
                substr("```\n" . "[" . implode(",", array_map(function ($value) {
                    return gettype($value) . ":" . json_encode($value);
                }, $values)) . "] (" . count($values) . ")" . "\n```", 0, 900),
                $e_message ?? "unknown",
                __CLASS__ . "::" . __FUNCTION__ . "()",
                _PATH_,
            ]),
        ]);

        http_response_code(500);
        exit;
    }

    static private function client(): PDO
    {
        static $pdo = null;

        if (!$pdo) {
            $credentials = Secret::get("/rds/credentials.json");
            $pdo = new PDO("mysql:host=" . $credentials["host"] . ";dbname=" . $credentials["dbname"] . ";charset=utf8mb4;", $credentials["user"], $credentials["password"]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        }

        return $pdo;
    }
};
