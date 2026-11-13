@extends('main')

@section('content')

<div class="container">
    <p><b>Cabeçalho e rodapé: </b> <a href="/configs/edit" class="btn btn-outline-dark">Editar</a></p>
    
    
    <div id="table-wrapper">
        <div id="table-scroll">
            <caption><b>Fichas geradas:</b></caption>
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                    <th></th>
                    @foreach($anos as $ano)
                       <th>{{$ano}}</th>     
                    @endforeach
                    <th></th>
                    </tr>  
                </thead>
                <tbody>
                    @for ($i=0; $i < 12; $i++)
                        <tr>
                        <td><b>{{$meses[$i]}}</b></td>
                        @foreach($anos as $ano)
                            <td>{{$num_fichas[$ano][$i]}}</td>
                        @endforeach
                        <td></td>
                        </tr>           
                    @endfor

                        <tr style="background-color: lightGray">
                            <td><b>FICHAS GERADAS POR ANO</b></td>
                            @foreach($anos as $ano)
                                <td>{{$num_fichas[$ano]['total']}}</td>
                            @endforeach
                            <td><b>TOTAL DE FICHAS GERADAS: {{$totalFichas}}</b></td>
                        </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection