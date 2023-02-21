<?php

declare(strict_types=1);

class Secret
{
  static public function get(string $path)
  {
    $path = __DIR__ . "/../../../secret{$path}";

    if (!file_exists($path)) {
      http_response_code(500);
      exit;
    }

    $body = file_get_contents($path);

    return '.json' === substr($path, -5) ? json_decode($body, true) : $body;
  }
}
