<?php

declare(strict_types=1);

function h($str = "")
{
  return htmlspecialchars((string)$str, ENT_QUOTES);
}
