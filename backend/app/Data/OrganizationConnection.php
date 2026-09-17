<?php

namespace App\Data;

use App\Models\Organization;
use App\Models\ParseRun;

final readonly class OrganizationConnection
{
    public function __construct(
        public Organization $organization,
        public ParseRun $parseRun,
    ) {}
}
