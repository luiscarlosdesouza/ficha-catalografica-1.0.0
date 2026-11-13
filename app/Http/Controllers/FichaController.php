<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Config;
use App\Models\Ficha;
use Cezpdf;


class FichaController extends Controller
{

    public function montaTextoFicha($request){
        $orientadora = $request['sou_orientadora'] ? 'a' : ''; //se for do gênero feminino

        $coorientadora = $request['sou_coorientadora'] ? 'a' : ''; //se for do gênero feminino

        $codigo1 = substr($request['pessoa_ultimonome'],0,1);
        // separa o título por espaços em branco e verifica a primeira palavra
        // se a primeira palavra for uma stopword, o $codigo2 será a primeira letra da segunda palavra do título
        
        $vetitulo = explode (" ",$request['titulo_trabalho']);
        
        $stopwords = array ("o", "a", "os", "as", "um", "uns", "uma", "umas", "de", "do", "da", "dos", "das", "no", "na", "nos", "nas", "ao", "aos", "à", "às", "pelo", "pela", "pelos", "pelas", "duma", "dumas", "dum", "duns", "num", "numa", "nuns", "numas", "com", "por", "em");
    
        if (in_array (strtolower($vetitulo[0]), $stopwords))
            $codigo2 = strtolower(substr($vetitulo[1],0,1));
        else
            $codigo2 = strtolower(substr($vetitulo[0],0,1));

        // Se tem Subtítulo 
        if ($request['subtitulo_trabalho'] != '')
            $titulo_subtitulo = $request['titulo_trabalho'] . ': ' . $request['subtitulo_trabalho'];
        else
            $titulo_subtitulo = $request['titulo_trabalho'];
    
        // monta o Código Cutter
        // $codigo = $codigo1.$request['cod_cutter'].$codigo2;
        //$codigo = $request['cod_cutter'];
        $aux_orientador = "; orientador$orientadora, ".$request['orientador_nome']." ".$request['orientador_ultimonome'];
        $aux_coorientador = "; coorientador$coorientadora, ".$request['coorientador_nome']." ".$request['coorientador_ultimonome'];
        $orientador_texto = isset($request['orientador_nome']) && strlen($request['orientador_nome']) > 0 && isset($request['orientador_ultimonome']) && strlen($request['orientador_ultimonome']) > 0  ? $aux_orientador : "";
        $coorientador_texto = isset($request['coorientador_nome']) && strlen($request['coorientador_nome']) > 0 && isset($request['coorientador_ultimonome']) && strlen($request['coorientador_ultimonome']) > 0  ? $aux_coorientador : "";
        // monta informações da ficha catalográfica
        
        // Ilustrações e Complemento 
        if ($request['ilustracoes'] == 'Sim') 
            $ilustracoes = ': il.';
        else
            $ilustracoes = '';

        // if ($request['complemento'] != '')
        //     $complemento = " + ".$request['complemento'].".";
        // else
        //     $complemento = '';
        
        $request['cidade'] = "São Paulo";
        $texto = $request['pessoa_ultimonome'].", ".$request['pessoa_nome']."\n   ".$titulo_subtitulo." / ".$request['pessoa_nome']." ".$request['pessoa_ultimonome'].$orientador_texto.$coorientador_texto.". - ".$request['cidade'].", ".$request['ano'].".\n   ".$request['no_paginas']." p.".$ilustracoes."\n\n\n   ".$request['grau']; 
        

        $departamento_texto = isset($request['departamento']) && strlen($request['departamento']) > 0 && $request['grau'] == 'Trabalho de Conclusão de Curso (Graduação)' ? "".$request['departamento'] : "";  
        $area_texto = isset($request['area_concentracao']) && strlen($request['area_concentracao']) > 0 && $request['grau'] != 'Trabalho de Conclusão de Curso (Graduação)' ? "".$request['area_concentracao']." " : " ";
        if($request['grau'] == 'Dissertação (Mestrado Profissional)') $area_texto = ""."Programa de Pós-Graduação Mestrado Profissional em Ensino de Matemática ";
        $texto .= " - ";



        // if ($departamento_texto == 'Departamento de . ');
        //     $departamento_texto = '';

        // if ($area_texto == 'Programa de Pós-Graduação em ')
        //     $area_texto = '';
        
        $texto .= $departamento_texto.$area_texto."/ "."Instituto de Matemática, Estatística e Ciência da Computação / Universidade de São Paulo"."."."\n   Bibliografia\n"."   ".$request['versao_trabalho']."\n\n\n   1. ".$request['assunto1'].". "; 

        if (!empty ($request['assunto2'])) 
            $texto .= "2. ".$request['assunto2'].". "; 
    
        if (!empty ($request['assunto3'])) 
            $texto .= "3. ".$request['assunto3'].". "; 
    
        if (!empty ($request['assunto4'])) 
            $texto .= "4. ".$request['assunto4'].". "; 
    
        if (!empty ($request['assunto5'])) 
            $texto .= "5. ".$request['assunto5'].". ";
        
        if (isset($request['orientador_nome']) && strlen($request['orientador_nome']) > 0 && isset($request['orientador_ultimonome']) && strlen($request['orientador_ultimonome']) > 0)
            $texto .= "I. ".$request['orientador_ultimonome'].", ".$request['orientador_nome'].". II. Título.";

        return $texto;
    }

    public function trocaQuebraLinhaLaTex($texto){
        $texto = str_replace("\n   ", "<br><br>", $texto);
        $texto = str_replace("\n", "<br><br>\\vspace{1\baselineskip}<br>", $texto);
        return $texto;
    }

    public function montarFicha(Request $request){

        if($request['button'] == "latex"){
            $latex = "% Este codigo depende das packages calc, setspace e ragged2e <br>
            % (ja incluidas no modelo LaTeX do IME-USP) <br>
            \begingroup\centering\singlespacing\small <br>
            \hyphenrules{nohyphenation}\hbadness=10000 <br>
            Ficha catalográfica elaborada com dados inseridos pelo(a) autor(a)\\\\ <br>
            Biblioteca Carlos Benjamin de Lyra\\\\ <br>
            Instituto de Matemática, Estatística e Ciência da Computação\\\\ <br>
            Universidade de São Paulo\par <br>
            \\vspace{2\baselineskip}\hrule\\vspace{.8\baselineskip} <br>
            \\RaggedRightRightskip 0pt plus 30pt minus 0pt\\relax <br>
            \\RaggedRightParfillskip 20pt plus 40pt minus 10pt\\relax <br>
            \\ttfamily\hspace{2em}\begin{minipage}[t]{125mm} <br>
            \\RaggedRight\sloppy\setlength{\parindent}{\widthof{123}} <br> <br>
            
            \\noindent ";

            $texto = $this->montaTextoFicha($request);
            $latex .= $this->trocaQuebraLinhaLaTex($texto);

            $latex .= " <br> <br>
            \\end{minipage}\par <br>
            \\vspace{1\baselineskip}\hrule\\vspace{.5\baselineskip}\\rmfamily <br>
            Bibliotecárias do Serviço de Informação e Biblioteca\\\\ <br>
            Carlos Benjamin de Lyra do IME-USP, responsáveis pela\\\\ <br>
            estrutura de catalogação da publicação de acordo com a AACR2:\\\\ <br>
            Maria Lúcia Ribeiro CRB-8/2766; Stela do Nascimento Madruga CRB 8/7534. <br>
            \par\\endgroup";

            return view('ficha.fichaLatex', [
                'latex' => $latex
            ]);
        }
        else{
            $config = Config::orderByDesc('created_at')->first();

            //caso não exista configurações criadas, a ficha não é criada e é dado um aviso
            if(!$config){
                request()->session()->flash('alert-danger', 'Não existem configurações salvas.');
                return back();
            }
            
            //cria uma ficha nova no banco
            Ficha::create();

            $texto = $this->montaTextoFicha($request);

            $ficha = array(array('cod' => '', 'ficha' => $texto));
                
            //Gera a ficha em pdf
            $pdf = new Cezpdf('a4','portrait','color',[255,255,255]);

            $pdf->selectFont('Times-Roman');
            
            $pdf->ezText (str_replace('\n', PHP_EOL, $config->cabecalho) . "\n", 10, array('justification' => 'center'));

            //$pdf->ezText (str_replace('\n', PHP_EOL,$request['descricao_ficha']) . "\n\n", 10, array('justification' => 'center'));
            
            $pdf->selectFont('Courier');

            $pdf->ezText ("________________________________________________________________________________\n", 10, array('justification' => 'center')); 

            $pdf->ezTable ($ficha,'','', array ('fontSize' => 9,'showHeadings'=>0, 'showLines'=>0, 'width'=>345, 'cols' =>array('cod'=>array('width'=>0))));

            $pdf->ezText ("________________________________________________________________________________\n", 10, array('justification' => 'center')); 

            $pdf->selectFont('Courier'); 
            
            $pdf->ezText ($config->rodape, 9, array('justification' => 'center'));
        

            return response($pdf->ezStream(), 200)
                    ->header('Content-Type', 'application/pdf');
        }

        
    }

}
