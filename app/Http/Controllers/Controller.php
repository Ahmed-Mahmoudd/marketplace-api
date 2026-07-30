<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use ApiResponses, AuthorizesRequests;
}
