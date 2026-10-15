@if(app()->environment('local', 'staging'))
    @php
        $panelId = filament()->getCurrentPanel()?->getId();
        $isMemberPanel = $panelId === 'member';
    @endphp
    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-700">
        @if($isMemberPanel)
            <p class="font-semibold">Member portal ({{ app()->environment() }} only)</p>
            <p class="mt-1">Members sign in with the login the secretary created on their profile. Demo: <code class="font-mono">member@nzena-ozo.local</code> / <code class="font-mono">password</code> (seeded).</p>
        @else
            <p class="font-semibold">Secretary demo ({{ app()->environment() }} only)</p>
            <p class="mt-1">Email: <code class="font-mono">secretary@nzena-ozo.local</code></p>
            <p>Password: <code class="font-mono">password</code></p>
        @endif
        <p class="mt-1 text-xs text-gray-500">Hidden automatically in production.</p>
    </div>
@endif
