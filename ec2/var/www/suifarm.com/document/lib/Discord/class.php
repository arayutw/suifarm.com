<?php

declare(strict_types=1);

class Discord
{
  public function __construct(
    public string $channel,
    public array $message
  ) {
  }

  public function __destruct()
  {
    $this->send();
  }

  private function send(): void
  {
    $endpoint = Secret::get('/discord/webhook.json')[$this->channel] ?? null;

    if ($endpoint) {
      $ch = curl_init($endpoint);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->message));
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

      curl_exec($ch);
      curl_close($ch);
    }
  }
}
