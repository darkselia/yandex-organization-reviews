<?php

namespace App\Contracts;

use App\Data\ParsedOrganization;
use App\Data\ProgressCallback;

interface OrganizationParser
{
    public function parse(string $url, ProgressCallback $progress): ParsedOrganization;
}
