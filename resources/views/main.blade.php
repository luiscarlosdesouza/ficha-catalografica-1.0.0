<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Catalográfica</title>

    <link rel="stylesheet" href="{{ asset('site/style.css') }}">
</head>
<body>
    @extends('laravel-usp-theme::master')

    @section('flash')
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="flash-message">
        @foreach (['danger', 'warning', 'success', 'info'] as $msg)
            @if (Session::has('alert-' . $msg))
                <p class="alert alert-{{ $msg }}">{{ Session::get('alert-' . $msg) }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </p>
            @endif
        @endforeach
    </div>
    @endsection

        
    <script src="{{ asset('public/jquery.js') }}"></script>
    <script src="{{ asset('public/bootstrap.js') }}"></script>

    
    
</body>
</html>