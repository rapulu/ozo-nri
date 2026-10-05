<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\MemberPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    MemberPanelProvider::class,
];
