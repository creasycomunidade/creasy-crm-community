@include('errors.layout', [
    'code'    => '500',
    'title'   => 'Erro no servidor',
    'message' => 'Não foi possível concluir esta operação. Nossa equipe já pode consultar o registro técnico; tente novamente em alguns instantes.',
])
