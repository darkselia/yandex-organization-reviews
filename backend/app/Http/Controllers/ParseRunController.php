<?php

namespace App\Http\Controllers;

use App\Http\Resources\ParseRunResource;
use App\Models\ParseRun;

class ParseRunController extends Controller
{
    public function show(ParseRun $parseRun): ParseRunResource
    {
        return ParseRunResource::make($parseRun);
    }
}
