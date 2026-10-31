<x-mail::message>
<h1>{{ $copy['greeting'] }}</h1>

<p>{!! nl2br(e($copy['intro'])) !!}</p>

<p class="wb-email-verification-code"><strong>{{ $code }}</strong></p>

<p>{!! nl2br(e($copy['help'])) !!}</p>

<p>{{ $copy['salutation'] }}<br>
{{ config('app.name') }}</p>
</x-mail::message>
