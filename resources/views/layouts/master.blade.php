<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <link rel=icon href=/images/favicon.ico>
    {{-- ?v = time the file was uploaded: every deploy changes the address, so no browser keeps an old copy --}}
    <link rel="stylesheet" href="/css/master.css?v={{ @filemtime(public_path('css/master.css')) ?: '4.0.7' }}">

    <title>Dashboard</title>
  </head>

  <body class="text-left">
    <noscript>
      <strong>
        We're sorry but the system doesn't work properly without JavaScript
        enabled. Please enable it to continue.</strong
      >
    </noscript>

    <!-- built files will be auto injected -->
    <div class="loading_wrap" id="loading_wrap">
      <div class="loader_logo">
      <img src="/images/logo.png" class="" alt="logo" />

      </div>

      <div class="loading"></div>
    </div>
    <div id="app">
    </div>

    <script>
      // build of the JS this page loads, compared later to detect a new version (see App.vue)
      window.APP_BUILD = "{{ @filemtime(public_path('js/main.min.js')) ?: '' }}";
      window.config = {
        "ModulesEnabled" : @json($ModulesEnabled),
        "ModulesInstalled" : @json($ModulesInstalled),
      };
    </script>
<!-- 
   @foreach ($ModulesInstalled as $moduleName)
        <script src="{{ url("/modules/{$moduleName}.js") }}"></script>

    @endforeach -->

    <script src="/js/main.min.js?v={{ @filemtime(public_path('js/main.min.js')) ?: '4.0.7' }}"></script>

  </body>
</html>
