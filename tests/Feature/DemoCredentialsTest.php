<?php

namespace Tests\Feature;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Tests\TestCase;

class DemoCredentialsTest extends TestCase
{
    public function test_login_hook_is_registered(): void
    {
        $this->assertTrue(
            FilamentView::hasRenderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
            'Demo credentials render hook should be registered on the login form.'
        );
    }

    public function test_demo_view_only_targets_local_and_staging(): void
    {
        $path = resource_path('views/filament/demo-credentials.blade.php');

        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString("app()->environment('local', 'staging')", $contents);
        $this->assertStringContainsString('secretary@nzena-ozo.local', $contents);
        $this->assertStringContainsString('member@nzena-ozo.local', $contents);
    }

    public function test_demo_view_hidden_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $html = view('filament.demo-credentials')->render();

        $this->assertSame('', trim($html));
    }

    public function test_demo_view_visible_in_local(): void
    {
        app()->detectEnvironment(fn (): string => 'local');

        $html = view('filament.demo-credentials')->render();

        $this->assertStringContainsString('Demo login', $html);
        $this->assertStringContainsString('secretary@nzena-ozo.local', $html);
    }
}
