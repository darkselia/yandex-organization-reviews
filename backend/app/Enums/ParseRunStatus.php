<?php

namespace App\Enums;

enum ParseRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Retrying = 'retrying';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
