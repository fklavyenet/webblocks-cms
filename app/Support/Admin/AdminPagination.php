<?php

namespace App\Support\Admin;

use App\Support\System\SystemSettings;

class AdminPagination
{
    public static function perPage(): int
    {
        return app(SystemSettings::class)->adminListingPerPage();
    }
}
