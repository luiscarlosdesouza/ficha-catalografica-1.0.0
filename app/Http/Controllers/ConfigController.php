<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Config;
use App\Models\Ficha;

class ConfigController extends Controller
{

    public function mostrarConfigs(){
        $this->authorize('admin');
        $config = Config::orderByDesc('created_at')->first();
        if(!$config) $config =  new Config;
        
        //montar um vetor para guardar o total de fichas geradas em cada mês do ano
        $num_fichas = [];
        $fichas = Ficha::select('*')->orderBy('created_at', 'asc')->get();

        $totalFichas = 0;
        foreach($fichas as $ficha){
            $totalFichas++;

            $ano = $ficha['created_at']->format('Y');
            $mes = intval($ficha['created_at']->format('m'));

            if(!array_key_exists($ano, $num_fichas)){
                $num_fichas[$ano] = array_fill(0, 12, 0);
                $num_fichas[$ano]['total'] = 0;
            }

            $num_fichas[$ano][$mes-1]++;
            $num_fichas[$ano]['total']++;
        }


        return view('config.configs', [
            'config' => $config,
            'num_fichas' => $num_fichas,
            'totalFichas' => $totalFichas,
            'anos' => array_keys($num_fichas),
            'meses' => ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
        ]);
    }

    public function edit()
    {
        $this->authorize('admin');
        $config = Config::orderByDesc('created_at')->first();
        if(!$config) $config =  new Config;
        return view('config.edit', [
            'config' => $config
        ]);
    }

    public function save(Request $request)
    {
        $this->authorize('admin');
        $config = [];
        $config['cabecalho'] = $request->cabecalho;
        $config['rodape'] = $request->rodape;

        //verifica se já existe uma configuração no banco para apenas atualizá-la,
        //e não criar uma a cada nova atualização
        $oldConfig = Config::orderByDesc('created_at')->first();
        if(!$oldConfig) Config::create($config);
        else $oldConfig->update($config);
        
        request()->session()->flash('alert-info', 'Configurações salvas com sucesso!');
        return redirect('/configs');
    }
}
