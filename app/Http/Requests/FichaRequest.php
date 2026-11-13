<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FichaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'nusp' => 'required',
            'email' => 'required|email|confirmed',
            'pessoa_nome' => 'required',
            'pessoa_ultimonome' => 'required',
            'titulo_trabalho' => 'required',
            'grau' => 'required',
            'departamento' => 'required_if:grau,Trabalho de Conclusão de Curso (Graduação)',
            'area_concentracao' => 'required_unless:grau,Trabalho de Conclusão de Curso (Graduação),Dissertação (Mestrado Profissional)',
            'versao_trabalho' => 'required_unless:grau,Trabalho de Conclusão de Curso (Graduação)',
            'orientador_nome' => 'required',
            'orientador_ultimonome' => 'required',
            'sou_orientadora' => 'nullable|boolean',
            'coorientador_nome' => 'required',
            'coorientador_ultimonome' => 'required',
            'sou_coorientadora' => 'nullable|boolean',
            'ano' => 'required|numeric',
            'no_paginas' => 'required|numeric',
            'ilustracoes' => 'required',
            'assunto1' => 'required',
            'assunto2' => 'nullable',
            'assunto3' => 'nullable',
            'assunto4' => 'nullable',
            'assunto5' => 'nullable'
        ];
    }
}
