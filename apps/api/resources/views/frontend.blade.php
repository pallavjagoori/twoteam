<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; connect-src 'self' https: wss:; font-src 'self' data:; img-src 'self' data: https:; script-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'">
    <title>{{ config('app.name') }}</title>
    @foreach ($styles as $style)
      <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    <script id="twoteam-runtime-config" type="application/json">{!! $runtimeConfig !!}</script>
    <script type="module" src="{{ $script }}"></script>
  </head>
  <body class="text-slate-600">
    <div id="app"></div>
    <noscript>This app works best with JavaScript enabled.</noscript>
  </body>
</html>
