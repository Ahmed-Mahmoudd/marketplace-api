<?php

namespace App\Enums;

enum IdempotencyStatus: string
{
  case InProgress = 'in_progress';
  case Completed = 'completed';
}
