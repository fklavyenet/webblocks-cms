<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"></head>
<body>
@foreach ($messages as $message)
<section>
<h2>{{ $message->subject }}</h2>
<p>{{ $message->name }} &lt;{{ $message->email }}&gt;</p>
<p>{!! nl2br(e($message->message)) !!}</p>
</section>
@endforeach
</body>
</html>
