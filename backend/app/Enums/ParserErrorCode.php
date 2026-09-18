<?php

namespace App\Enums;

enum ParserErrorCode: string
{
    case SourceUnavailable = 'source_unavailable';
    case SourceBlocked = 'source_blocked';
    case SourceRateLimited = 'source_rate_limited';
    case SourceSchemaChanged = 'source_schema_changed';
    case EmptySourceResponse = 'empty_source_response';
    case InvalidSourceData = 'invalid_source_data';
}
