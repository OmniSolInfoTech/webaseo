<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>@yield('title')</title>
        <link rel="stylesheet" href="{{ asset('webaseo-assets/style.css') }}">
        <!-- JQuery -->
        <script src="{{ asset('webaseo-assets/jquery-3.7.1.min.js') }}"></script>
        <!-- Bootstrap v5.3> -->
        <link href="{{ asset('webaseo-assets/bootstrap.min.css') }}" rel="stylesheet">
        <script src="{{ asset('webaseo-assets/bootstrap.bundle.min.js') }}"></script>
        <!-- DataTables -->
        <link href="{{ asset('webaseo-assets/datatables.min.css') }}" rel="stylesheet">
        <script src="{{ asset('webaseo-assets/datatables.min.js') }}"></script>
        <!-- ApexCharts -->
        <script src="{{ asset('webaseo-assets/apexcharts-bundle/dist/apexcharts.min.js') }}"></script>
        <!-- Leaflet -->
        <link rel="stylesheet" href="{{ asset('webaseo-assets/leaflet/leaflet.css') }}">
        <script src="{{ asset('webaseo-assets/leaflet/leaflet.js') }}"></script>
        <!-- FontAwesome 6.5.1 -->
        <script src="{{ asset('webaseo-assets/fontawesome-web/css/fontawesome.min.css') }}"></script>
    </head>
    <body>
        <nav class="navbar sticky-top bg-light">
            <div class="container-fluid">
                <a class="navbar-brand" href="/webaseo/admin">WEBASEO :-)</a>
            </div>
        </nav>
        <div class="container">
            @yield('content')
            @yield('script')
        </div>
        <script src="{{ asset('webaseo-assets/custom.js')}}"></script>
    </body>
</html>