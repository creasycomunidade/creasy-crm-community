<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    /** Publish the canonical Portuguese legal documents used by the SaaS. */
    public function run(): void
    {
        $app = e(config('app.name', 'CREASY CRM'));
        $email = e(config('saas.support_email', ''));
        $contact = $email !== ''
            ? "pelo e-mail <a href=\"mailto:{$email}\">{$email}</a>"
            : 'pelo <a href="/contact">formulário de contato</a>';
        $updated = '30 de agosto de 2026';

        $pages = [
            'privacy' => [
                'title' => 'Política de Privacidade',
                'meta_title' => "Política de Privacidade e LGPD — {$app}",
                'meta_description' => "Saiba como o {$app} trata e protege dados pessoais conforme a LGPD.",
                'content' => <<<HTML
<p><strong>Última atualização:</strong> {$updated}</p>
<p>Esta Política explica como o <strong>{$app}</strong> trata dados pessoais em seu site, aplicativo e serviços. Adotamos os princípios de finalidade, adequação, necessidade, livre acesso, qualidade, transparência, segurança, prevenção, não discriminação e responsabilização previstos na Lei nº 13.709/2018 (“LGPD”).</p>
<h2>1. Papéis no tratamento de dados</h2>
<p>Para dados de cadastro, contratação, faturamento, segurança e uso da plataforma, o {$app} atua como <strong>controlador</strong>. Quando uma empresa cliente utiliza a plataforma para tratar dados de seus contatos, leads, consumidores ou colaboradores, essa empresa é normalmente a controladora e o {$app} atua como <strong>operador</strong>, seguindo suas instruções e o contrato.</p>
<p>Solicitações sobre dados inseridos por uma empresa cliente devem ser dirigidas primeiro a essa empresa. Prestaremos a cooperação tecnicamente cabível para atendimento dos direitos do titular.</p>
<h2>2. Dados tratados</h2>
<ul><li><strong>Conta:</strong> nome, e-mail, telefone, empresa, cargo, credenciais protegidas e preferências.</li><li><strong>Cobrança:</strong> plano, pagamentos, documentos fiscais e identificadores do processador. Dados completos de cartão são tratados pelo provedor de pagamento.</li><li><strong>Uso e segurança:</strong> IP, dispositivo, navegador, registros de acesso, auditoria, cookies e eventos de segurança.</li><li><strong>Conteúdo do cliente:</strong> contatos, conversas, arquivos, áudios, imagens, campanhas, tarefas, agendas, integrações e dados sincronizados.</li><li><strong>Suporte:</strong> mensagens, anexos e dados necessários para resolver solicitações.</li></ul>
<h2>3. Finalidades e bases legais</h2>
<p>Tratamos dados para administrar contas; executar o contrato; entregar mensagens, integrações e automações; processar cobranças; prevenir fraude; manter segurança, disponibilidade e backups; prestar suporte; cumprir obrigações legais; exercer direitos; melhorar o serviço; e, quando autorizado, enviar comunicações comerciais.</p>
<p>As bases legais incluem execução de contrato, procedimentos preliminares, obrigação legal, exercício regular de direitos, legítimo interesse após avaliação, proteção do crédito e consentimento, conforme aplicável. O consentimento pode ser revogado sem afetar tratamentos anteriores legítimos.</p>
<h2>4. Inteligência artificial</h2>
<p>Recursos de IA podem analisar conteúdo fornecido pelo cliente para resumir conversas, sugerir respostas, executar tarefas autorizadas e gerar materiais. As saídas podem conter erros e devem ser revisadas por pessoa qualificada antes de decisões relevantes. Quando aplicável, o titular pode solicitar informações e revisão de decisões automatizadas nos termos da LGPD.</p>
<h2>5. Compartilhamento</h2>
<p>Podemos compartilhar dados necessários com provedores de hospedagem, backup, e-mail, monitoramento, IA, atendimento, prevenção a fraude e pagamento; plataformas conectadas pelo cliente; autoridades, por obrigação legal; ou em reorganização societária com salvaguardas. Não vendemos dados pessoais.</p>
<h2>6. Transferências internacionais</h2>
<p>Fornecedores podem processar dados fora do Brasil. Adotamos mecanismos admitidos pela LGPD e pela regulamentação da ANPD, medidas contratuais, técnicas e avaliação do fornecedor, conforme aplicável.</p>
<h2>7. Retenção e eliminação</h2>
<p>Mantemos dados durante a relação contratual e pelo período necessário às finalidades, obrigações legais, prevenção de fraude e exercício de direitos. Após o encerramento, dados são eliminados ou anonimizados segundo o ciclo técnico; cópias residuais podem permanecer em backups protegidos até sua rotação.</p>
<h2>8. Segurança e incidentes</h2>
<p>Aplicamos controle de acesso, segregação lógica entre empresas, criptografia quando adequada, auditoria, backups, monitoramento e resposta a incidentes. Incidentes relevantes serão avaliados e comunicados à ANPD e aos titulares quando exigido.</p>
<h2>9. Direitos do titular</h2>
<p>Nos termos do art. 18 da LGPD, o titular pode solicitar confirmação e acesso; correção; anonimização, bloqueio ou eliminação; portabilidade quando regulamentada; informação sobre compartilhamentos e consentimento; revogação; oposição; revisão de decisões automatizadas; e petição perante a ANPD. Para segurança, poderemos confirmar a identidade do solicitante.</p>
<h2>10. Crianças e adolescentes</h2>
<p>O serviço é destinado a empresas e não é direcionado diretamente a crianças. O cliente que inserir dados de crianças ou adolescentes deverá observar o melhor interesse e os requisitos do art. 14 da LGPD.</p>
<h2>11. Encarregado e contato</h2>
<p>Dúvidas e solicitações de titulares podem ser enviadas {$contact}. Informe “LGPD” no assunto e, se os dados foram inseridos por uma empresa cliente, identifique essa empresa.</p>
<h2>12. Atualizações</h2>
<p>Esta Política poderá ser atualizada por mudanças legais, técnicas ou operacionais. Alterações relevantes serão destacadas na plataforma ou comunicadas pelos canais disponíveis.</p>
<p>Referências: <a href="https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm" rel="noopener noreferrer">LGPD</a> e <a href="https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes" rel="noopener noreferrer">orientações da ANPD</a>.</p>
HTML,
            ],
            'terms' => [
                'title' => 'Termos de Uso e Serviço',
                'meta_title' => "Termos de Uso e Serviço — {$app}",
                'meta_description' => "Condições para contratação e uso empresarial do {$app}.",
                'content' => <<<HTML
<p><strong>Última atualização:</strong> {$updated}</p>
<p>Estes Termos regulam a contratação e o uso do <strong>{$app}</strong>. Ao criar conta ou contratar um plano, a pessoa declara ter capacidade legal e, quando agir por empresa, poderes para vinculá-la.</p>
<h2>1. Serviço e conta</h2><p>O {$app} oferece atendimento, canais de comunicação, automação, agenda, CRM, marketing, integrações e IA conforme o plano. O cliente deve manter dados corretos, proteger credenciais e configurar permissões.</p>
<h2>2. Uso permitido</h2><p>É proibido usar o serviço para atividade ilícita, fraude, assédio, discriminação, violação de direitos, spam, coleta indevida, malware ou interferência na segurança. O cliente deve possuir base legal, respeitar opt-in, opt-out, bloqueios e políticas de cada canal.</p>
<h2>3. Serviços de terceiros</h2><p>Integrações dependem de APIs, contas e regras de terceiros. Alterações, limites ou bloqueios desses provedores podem afetar funções. O cliente é responsável por suas contas externas e autoriza o tratamento necessário à integração.</p>
<h2>4. Inteligência artificial</h2><p>Resultados de IA são probabilísticos e não substituem avaliação jurídica, médica, financeira ou técnica. O cliente deve revisar saídas e configurar aprovações antes de ações externas, respondendo pelas instruções e permissões concedidas.</p>
<h2>5. Dados pessoais</h2><p>As partes cumprirão a LGPD. Para dados dos contatos do cliente, este atua normalmente como controlador e o {$app} como operador. O cliente garante a legitimidade da coleta e das comunicações. A Política de Privacidade integra estes Termos.</p>
<h2>6. Planos e cobrança</h2><p>Recursos, franquias, usuários, conexões e preços variam por plano. Salvo indicação diversa, assinaturas são cobradas antecipadamente e renovadas no ciclo escolhido. A falta de pagamento pode gerar nova tentativa, limitação ou suspensão.</p>
<p>Cancelamentos e reembolsos seguem a oferta contratada e os direitos obrigatórios do Código de Defesa do Consumidor, quando aplicável. Condições empresariais específicas podem constar de proposta própria.</p>
<h2>7. Testes e mudanças de plano</h2><p>Períodos gratuitos podem ter limites. Upgrade pode gerar cobrança proporcional; downgrade pode valer no ciclo seguinte e limitar recursos. O cliente deve ajustar ou exportar dados antes de reduzir limites.</p>
<h2>8. Conteúdo e propriedade intelectual</h2><p>O cliente mantém direitos sobre seu conteúdo e concede licença limitada para hospedagem, processamento e transmissão necessários ao serviço. Software, marcas e documentação do {$app} permanecem protegidos.</p>
<h2>9. Segurança e confidencialidade</h2><p>Cada parte protegerá informações confidenciais. O {$app} mantém medidas técnicas e administrativas, monitoramento e backups. O cliente deve usar senhas fortes, autenticação adicional e menor privilégio.</p>
<h2>10. Disponibilidade</h2><p>Buscamos operação contínua, mas manutenção, incidentes, internet e terceiros podem causar indisponibilidade. Níveis de serviço garantidos somente existem em contrato específico.</p>
<h2>11. Suspensão e encerramento</h2><p>Podemos suspender acesso para proteger a plataforma, cumprir lei, responder a risco, inadimplência ou violação. Sempre que possível, haverá oportunidade de correção. Após encerramento, o cliente deve exportar dados no prazo disponibilizado.</p>
<h2>12. Responsabilidade</h2><p>Cada parte responde pelos danos diretos que causar, nos limites legais. Não excluímos responsabilidades que a lei não permita afastar. O {$app} não responde por decisão tomada sem revisão de IA, conteúdo ou uso ilícito do cliente, falha de terceiro ou evento fora de controle razoável, ressalvada sua responsabilidade legal.</p>
<h2>13. Alterações</h2><p>Mudanças materiais serão comunicadas com antecedência razoável quando afetarem direitos essenciais. O uso após a vigência indica aceitação, sem prejuízo de direitos legais.</p>
<h2>14. Lei e conflitos</h2><p>Aplicam-se as leis brasileiras. As partes buscarão solução amigável. O foro será definido pela legislação, preservado o foro do consumidor quando houver relação de consumo.</p>
<h2>15. Contato</h2><p>Dúvidas contratuais podem ser enviadas {$contact}.</p>
HTML,
            ],
            'cookies' => [
                'title' => 'Política de Cookies',
                'meta_title' => "Política de Cookies — {$app}",
                'meta_description' => "Entenda e controle os cookies usados pelo {$app}.",
                'content' => <<<HTML
<p><strong>Última atualização:</strong> {$updated}</p>
<p>Esta Política explica como o <strong>{$app}</strong> utiliza cookies e tecnologias semelhantes para permitir funções, lembrar preferências e, quando autorizado, medir o uso.</p>
<h2>1. Categorias</h2>
<ul><li><strong>Necessários:</strong> autenticação, segurança, prevenção a fraude, sessão e registro da escolha de privacidade. Não podem ser desativados sem prejudicar o serviço.</li><li><strong>Preferências:</strong> idioma, tema, moeda e outras escolhas solicitadas.</li><li><strong>Análise:</strong> medição agregada de audiência, desempenho e navegação. Ativados após autorização quando exigido.</li><li><strong>Marketing:</strong> atribuição de campanhas, personalização publicitária e medição entre sites. Ativados mediante consentimento.</li></ul>
<h2>2. Bases legais</h2><p>Cookies necessários são usados para executar e proteger o serviço. Cookies opcionais dependem de consentimento livre, informado e revogável, conforme a LGPD e a orientação da ANPD.</p>
<h2>3. Duração</h2><p>Cookies de sessão expiram ao fechar o navegador. Persistentes permanecem pelo prazo do fornecedor ou até exclusão, observada retenção mínima. O registro de consentimento é mantido para respeitar a escolha e renovado em mudanças relevantes.</p>
<h2>4. Terceiros</h2><p>Integrações e conteúdos externos podem definir cookies próprios. O {$app} busca bloquear tecnologias opcionais antes da autorização. Empresas clientes que conectem ferramentas próprias devem informar seus usuários.</p>
<h2>5. Gerenciamento</h2><p>“Rejeitar opcionais” mantém somente cookies necessários. “Personalizar” permite escolher categorias. A preferência pode ser alterada limpando o armazenamento do site ou pelo gerenciador disponibilizado. Bloquear cookies no navegador pode afetar login e funções.</p>
<h2>6. Contato</h2><p>Dúvidas podem ser enviadas {$contact}. Consulte nossa <a href="/p/privacy">Política de Privacidade</a>.</p>
<p>Referência: <a href="https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-cookies-e-protecao-de-dados-pessoais.pdf" rel="noopener noreferrer">Guia de Cookies da ANPD</a>.</p>
HTML,
            ],
        ];

        foreach ($pages as $slug => $page) {
            CmsPage::updateOrCreate(
                ['slug' => $slug],
                [...$page, 'published' => true, 'layout' => 'legal'],
            );
        }
    }
}
