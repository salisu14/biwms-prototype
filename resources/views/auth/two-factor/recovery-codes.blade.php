@component('auth.two-factor.layout', ['title' => 'Recovery Codes'])
    <h1>Recovery Codes</h1>
    <p>Store these recovery codes securely. They will not be shown again, and each code can only be used once.</p>

    <ol class="codes">
        @foreach ($codes as $code)
            <li><code>{{ $code }}</code></li>
        @endforeach
    </ol>

    <a class="button" href="{{ $continueUrl }}">Continue</a>
@endcomponent
