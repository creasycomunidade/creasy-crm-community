# Registro de instalações — CREASY CRM Community

Esta edição envia um registro à CREASY Tecnologias e Soluções quando a instalação é concluída. A coleta é informada na etapa de criação do administrador e não possui botão de desativação na interface.

## Dados enviados e finalidade

- Identificador aleatório da instalação, domínio configurado e versão: contabilizar instalações e domínios distintos.
- Nome e e-mail do administrador criado no instalador: identificar o responsável pela instalação.
- Um segredo técnico aleatório da instalação: impedir que outro registro com o mesmo identificador substitua o original. Não é a senha do administrador.

Não são enviados senhas de usuários, credenciais do banco ou de APIs, contatos, conversas, mensagens ou anexos. Não há varredura de administradores adicionais. Não é um monitoramento de uso diário nem comprovação de instalações ativas.

O destino é `https://telemetria.creasy.com.br/api/installations.php`, por HTTPS. O receptor recebe tecnicamente o endereço IP da conexão, mas a tabela de instalações não o armazena. Para limitar abuso, mantém um hash temporário da origem por até uma hora. A hospedagem pode manter seus próprios logs de acesso; a CREASY deve configurar o acesso e a retenção desses logs.

Se o receptor estiver indisponível, o CRM mantém a solicitação criptografada em armazenamento privado e tenta novamente em uma visita posterior, com intervalo mínimo de uma hora. Após a confirmação, remove a cópia local do nome/e-mail e guarda apenas o identificador e a data do registro. A indisponibilidade do receptor não impede usar o CRM.

## Acesso e exclusão

Os registros ficam no painel privado da CREASY, protegidos por chave administrativa. O operador pode excluir registros pelo painel. A CREASY é responsável por definir sua política de retenção e atender solicitações sobre os dados; utilize os canais oficiais em https://creasy.com.br/ para contato. Este registro não autoriza envio de publicidade.

O código é aberto: versões modificadas podem alterar ou remover o registro. As contagens são de registros autodeclarados recebidos, não de todas as cópias existentes. Instalações clonadas após o registro podem compartilhar o mesmo identificador; reinstalações limpas geram outro.
