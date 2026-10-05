@if(app()->environment('local', 'staging'))
    @php
        $panelId = filament()->getCurrentPanel()?->getId();
        $isMemberPanel = $panelId === 'member';
        $demoEmail = $isMemberPanel ? 'member@nzena-ozo.local' : 'secretary@nzena-ozo.local';
        $demoPassword = 'password';
        $portalLabel = $isMemberPanel ? 'Member portal demo' : 'Secretary demo';
    @endphp
    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-700">
        <p class="font-semibold">Demo login ({{ $portalLabel }} — {{ app()->environment() }} only)</p>
        <p class="mt-1">Email: <code class="font-mono">{{ $demoEmail }}</code></p>
        <p>Password: <code class="font-mono">{{ $demoPassword }}</code></p>
        <p class="mt-1 text-xs text-gray-500">Hidden automatically in production.</p>
    </div>
@endif
