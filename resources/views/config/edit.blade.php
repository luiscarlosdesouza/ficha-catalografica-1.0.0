@extends('main')

@section('content')
<form action="{{ url('/configs/save') }}" method="POST">
    @csrf
    <div class="container">
        <div style="border: 1px; border-style: solid; border-radius: 5px; background-color: #FFFFFF; border-color: #A9A9A9; padding: 10px; margin-bottom: 10px;">
            <div class="form-group">
                <label for="cabecalho"><b>Cabeçalho</b></label>
                <textarea class="form-control" name="cabecalho" id="cabecalho" cols="30" rows="10" required>{{old('cabecalho', $config->cabecalho)}}</textarea>
            </div>

            <div class="form-group">
                <label for="rodape"><b>Rodapé</b></label>
                <textarea class="form-control" name="rodape" id="rodape" cols="30" rows="10" required>{{old('rodape', $config->rodape)}}</textarea>
            </div>

            <button type="submit" class="btn btn-outline-dark">Salvar</button>
        </div>
    </div>

</form>
@endsection
