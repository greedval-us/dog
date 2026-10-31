<?php

namespace App\Modules\Telegram\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class TelegramUnavailable extends Exception implements ShouldntReport
{
    //
}
