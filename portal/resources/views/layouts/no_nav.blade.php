@include("layouts.partials.head")
<body style="background-color: #773fa4">
    @include('layouts.partials.errors')
    @include('layouts.partials.success')
    @yield('content')
    @include('layouts.partials.js')
</body>
</html>