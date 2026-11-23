# No Days Off Club — MVP (PHP + MySQL + XAMPP)

Sistema web responsivo (feito para o celular, instalável como app/PWA) para os pacientes do
Gabriel. A ideia central é um ciclo simples:

**ação → pontos → ranking do mês → troca por brinde → post no feed → volta no dia seguinte.**

> ⚠️ **Dados reais:** este MVP foi feito para rodar no seu computador com **pacientes fictícios**.
> Antes de colocar pacientes reais, o desenvolvedor precisa revisar a segurança e um(a)
> advogado(a) precisa revisar o Termo de Consentimento (tela `/termos`). Veja a seção 9.

---

## 1. O que o MVP entrega

### Para o paciente (no celular)
| Módulo | O que faz |
|---|---|
| **Login e primeiro acesso** | Entra com e-mail e senha. No primeiro acesso troca a senha provisória e aceita o termo de consentimento. |
| **Início** | Sequência de dias seguidos, semana, pontos, posição no ranking, nível, contador de renovação, aviso fixo do Gabriel, atalhos. |
| **Check-in de 10 segundos** | Sono, água e dieta em toques. Vale pontos 1× por dia e alimenta a sequência (streak). |
| **Seu plano e renovação** | Cartão com o **plano** (Trimestral, Semestral, Anual), **dias restantes**, barra de progresso e "válido até". Selo verde (mais de 15 dias), amarelo (8 a 15) e vermelho (7 ou menos / vencido) + botão **Renovar pelo WhatsApp** (a mensagem já leva o nome do plano). No vermelho, uma **faixa de aviso** aparece em todas as telas. O paciente **nunca vê valores em R$**. |
| **Treino** | Treinos gerais (A/B/C) e treinos individuais criados pelo Gabriel; botão **Treino feito** + calendário do mês. |
| **Dieta** | Link (WebDiet ou outro) e/ou PDF privado + recados do Gabriel. |
| **Feed da comunidade** | Posts com foto, curtidas, comentários, tema do dia, denúncia, regras de convivência, mural fixo do Gabriel. |
| **Pontos, extrato e níveis** | Extrato completo; níveis Bronze / Prata / Ouro / Black (nunca rebaixam). |
| **Ranking mensal** | Soma dos pontos do mês; zera todo mês. **Não** mexe no saldo da loja. |
| **Loja** | Troca pontos por brindes; o Gabriel aprova/entrega manualmente. |
| **Evolução** | Peso e cintura com gráfico (só o paciente e a equipe veem). |
| **Perfil** | Foto, dados, trocar senha. |
| **Perfil esportivo** | Para quem treina: modalidades (Corrida, Musculação, Hyrox, Natação...), se tem assessoria, se compete e a próxima prova. O paciente edita o dele e escolhe se mostra as **modalidades** no perfil da comunidade (assessoria e provas nunca aparecem para outros). |

### Para o Gabriel (painel `/admin`, funciona no celular e no computador)
**Painel** (tela inicial, duas abas: **Operação**, que abre primeiro, com o que fazer hoje: parcelas para cobrar, renovações, sumidos do app e pendências; e **Visão executiva**: pontos de atenção, pacientes em carteira, vendido, recebido, taxa de renovação, evolução financeira, carteira por plano/sexo/modalidade, crescimento e equipe) · **Aniversários** (data de nascimento opcional na ficha, aniversariantes de hoje e dos próximos dias com botão de WhatsApp e mensagem pronta e editável, caixinha de autorização para receber mensagens) · Painel com indicadores e **quanto está para renovar nos próximos 30 dias (R$)** · **risco de renovação** (quem vence logo e quem sumiu há 7+ dias, com botão de
WhatsApp) · **planos e valores** (só o admin vê R$) · pacientes (cadastro com plano e "valor combinado",
renovar com a duração do plano e bônus, pontos manuais, dieta, senha provisória, exclusão LGPD) · **lista de pacientes completa** (status Ativo/Inativo, vencimento, consultas "x de y" por plano, responsável da equipe, ordenar clicando na coluna, filtros por vencimento 15/30 dias, status, responsável e consultas, atribuição em lote) · **responsáveis** ("modo babá": quem cuida de quais pacientes) · **importação de pacientes por planilha** (modelo oficial em Excel, prévia antes de salvar, duplicados e erros) ·
**atletas** (etiquetas de modalidade nos pacientes, filtros Atletas / Competem / Sem assessoria / por modalidade,
cartão **Próximas provas** no painel, tela Modalidades) · **financeiro** (pagamento já no cadastro do paciente, vendido × já caiu × falta cair, cartão que cai sozinho em dia útil com taxa
e valor líquido, Pix para cobrar com WhatsApp e comprovante, agenda de recebíveis dos próximos 12 meses, e valores em R$, US$ e €) · treinos · moderação do feed e denúncias · mural fixado · loja e
resgates · regras de pontos · ranking · configurações (limites dos selos, WhatsApp, níveis, versão
do termo) · auditoria.

### Segurança já incluída
Senhas com `password_hash` · proteção contra CSRF em todo formulário · limite de tentativas de login ·
sessão com cookie protegido · consultas preparadas (PDO) contra SQL injection · escape de toda saída
(XSS) · CSP sem JavaScript inline · fotos e PDFs **privados** (só abrem logado; dieta só para o dono
e o admin) · fotos reprocessadas (remove dados de GPS/EXIF) · pastas internas bloqueadas pelo `.htaccess`.

---

### Identidade visual (tema claro)
Todo o visual fica em um único arquivo: `assets/css/app.css`. As cores estão no começo dele (bloco `:root`):
`--bg` (fundo off-white), `--card` (cartões), `--ink` (preto da logo), `--mute` (texto de apoio) e as cores de
status `--ok`, `--warn`, `--bad`. Para mudar o clima do app inteiro, troque esses valores. As logos têm
versão escura (`logo-*.svg`, usadas hoje) e branca (`logo-*-branco.svg`, para fundos pretos).

**Painel admin (desde a 08.3):** usa as cores da logo circular (vinho `#670001`, greige `#9D897B`, creme `#F6F3E3`). Elas estão no bloco *Atualização 08.3* no fim de `assets/css/app.css`, dentro de `body.admin { ... }` — por isso só valem no `/admin`; o app do paciente segue com as cores do bloco `:root` acima.

## 2. Como o código está organizado (pronto para crescer)

```
nodaysoffclub/
├─ index.php               ← porta de entrada (tudo passa por aqui)
├─ .htaccess               ← URLs limpas + bloqueios de segurança
├─ config/config.php       ← banco, fuso, modo debug (NÃO vai para o GitHub)
├─ database/
│   ├─ schema.sql          ← cria o banco, as tabelas e os dados iniciais
│   └─ demo_data.sql       ← pacientes FICTÍCIOS para testar
├─ app/
│   ├─ routes.php          ← mapa de URLs (uma linha por página)
│   ├─ Core/               ← motor: DB, Router, Auth, View, Upload, Middleware, Xlsx (leitor de planilha), Charts (gráficos SVG do painel)
│   ├─ Services/           ← regras de negócio: Points, Ranking, Levels, Streak, Renewal, Settings, Audit, PatientImport (importação), ImportTemplate, Insights (números do painel gerencial), Birthdays (aniversariantes)
│   ├─ Resources/          ← modelo-importacao-pacientes.xlsx (modelo oficial; só baixa pelo painel admin)
│   ├─ Controllers/        ← uma classe por módulo (+ Admin/)
│   └─ Views/              ← telas (HTML + PHP)
├─ assets/                 ← css, js, logo, ícones, fontes (tudo local, sem CDN)
├─ storage/                ← uploads privados e logs (fora do acesso público)
├─ manifest.webmanifest, sw.js, offline.html   ← PWA
```

**Regras de ouro do projeto (já estão no código):**
- `points_ledger` (extrato) é a **fonte da verdade**. Saldo = soma do extrato; ranking do mês = soma
  só do mês. Nada de "campo de saldo" que possa ficar errado.
- Todo ponto nasce de uma **regra** (tabela `point_rules`) com limite por dia — o Gabriel ajusta em
  *Admin → Regras de pontos* sem mexer em código.
- Cada pessoa só pontua uma vez por ação/post (evita "farmar" ponto).

### Como adicionar uma página nova (exemplo)
1. Linha em `app/routes.php`: `$router->get('/desafios', 'ChallengeController@index', ['auth','patient']);`
2. Classe `app/Controllers/ChallengeController.php` com o método `index()`.
3. Tela em `app/Views/patient/challenges.php`.
4. Se tiver regra de pontos nova: um registro em *Admin → Regras de pontos* (não precisa de código).

### Migrar para Laravel no futuro
A estrutura (Controllers / Services / Views / rotas separadas) foi pensada para isso: os Services
viram Services/Actions do Laravel, as Views viram Blade, `schema.sql` vira migrations e o painel
admin pode virar Filament. Nada do MVP precisa ser jogado fora.

---

## 3. Instalação no XAMPP (passo a passo)

**Requisitos:** XAMPP com **PHP 8.1 ou mais novo** (baixe a versão mais recente em apachefriends.org).

1. **Copie a pasta** `nodaysoffclub` inteira para `C:\xampp\htdocs\` (Mac: `/Applications/XAMPP/htdocs/`).
   Ficará `C:\xampp\htdocs\nodaysoffclub\index.php`.
2. Abra o **XAMPP Control Panel** e clique em **Start** em **Apache** e em **MySQL**.
3. Habilite as URLs limpas (só se ainda não estiver): em `C:\xampp\apache\conf\httpd.conf` confirme que existe a linha
   `LoadModule rewrite_module modules/mod_rewrite.so` (sem `#` na frente) e que, na seção
   `<Directory "C:/xampp/htdocs">`, está `AllowOverride All`. Se mudar algo, reinicie o Apache.
   *(Na instalação padrão do XAMPP isso já vem pronto.)*
4. Abra **http://localhost/phpmyadmin** → aba **Importar** → escolha `database/schema.sql` → **Importar**.
   Isso cria o banco `nodaysoffclub`, todas as tabelas e o usuário admin.
5. Ainda no phpMyAdmin: selecione o banco `nodaysoffclub` (menu da esquerda) → **Importar** →
   `database/demo_data.sql` → **Importar**. (São os pacientes de mentira para testar.)
   *(Os planos Trimestral, Semestral e Anual já vêm criados, sem valores. Você preenche em* **Admin → Planos**.*)*
6. Confira `config/config.php`: no XAMPP padrão (usuário `root`, senha vazia) **não precisa mudar nada**.
   Se o seu MySQL tiver senha, coloque em `'pass'`.
7. Abra **http://localhost/nodaysoffclub/** no navegador. Deve aparecer a tela de login preta com a logo.

### Já instalou antes? Atualização 01 (planos e valores)
Quem já importou o `schema.sql` **não precisa refazer o banco** (e não perde nenhum dado):
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → clique no banco **nodaysoffclub** (menu da esquerda) → aba **Importar** →
   escolha `database/atualizacao_01_planos.sql` → **Executar**. Pode rodar de novo sem problema.
   *(Funciona no MariaDB que vem no XAMPP. Se usar MySQL 8 puro, avise o desenvolvedor.)*
3. Abra o app com **Ctrl + F5** (limpa o cache) e vá em **Admin → Planos** preencher os valores.

### Atualização 02 (perfil esportivo / atletas)
Mesmo processo da atualização 01, **depois** dela (se ainda não rodou a 01, rode primeiro):
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → banco **nodaysoffclub** → aba **Importar** → `database/atualizacao_02_atletas.sql` → **Executar**.
   Cria as tabelas de modalidades e perfil esportivo e as 4 modalidades iniciais. Não apaga nada.
   *(O final do arquivo tem um bloco opcional que transforma alguns pacientes de teste em atletas, para você ver na tela.)*
3. **Ctrl + F5** e abra **Admin → Modalidades**.

### Atualização 03 (financeiro: moedas, parcelas e recebíveis)
Mesmo processo, **depois** das atualizações 01 e 02:
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → banco **nodaysoffclub** → aba **Importar** → `database/atualizacao_03_financeiro.sql` → **Executar**.
   Cria as tabelas de pagamentos e parcelas e acrescenta a moeda aos valores. Não apaga nada.
3. *(Opcional, só para ver as telas cheias)* importe também `database/demo_financeiro.sql`: cria pagamentos **fictícios** só para os pacientes de teste. Rode uma vez.
4. **Ctrl + F5** e abra **Admin → Financeiro**.

> As regras do Financeiro foram refeitas na **Atualização 04** (logo abaixo). Leia a 04 para saber como funciona hoje.

### Atualização 04 (Financeiro v2: pagamento no cadastro, cartão automático, Pix para cobrar, taxas)
Mesmo processo, **depois** das atualizações 01, 02 e 03:
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → clique no banco **nodaysoffclub** (menu da esquerda) → aba **Importar** → `database/atualizacao_04_financeiro_v2.sql` → **Executar**.
   Acrescenta as colunas de bandeira/taxa/líquido/comprovante e cria a tabela de taxas. Não apaga nada.
   **Importe antes de abrir o sistema**, senão as telas de pagamento dão erro.
3. *(Opcional, só para ver as telas cheias)* importe `database/demo_financeiro.sql`. **Atenção:** esta versão **apaga e recria** os pagamentos dos pacientes de teste (`...teste@exemplo.com`) com datas relativas a hoje. Só os fictícios são mexidos.
4. **Ctrl + F5** e abra **Admin → Financeiro**.
5. Abra **Financeiro → Taxas do cartão** e preencha as porcentagens do contrato da maquininha (veja abaixo). Vem **vazia** de propósito: eu não chuto percentual.

**Como o Financeiro funciona agora**
- **Novo paciente já com pagamento:** em *Novo paciente* há a seção **Pagamento** (valor, forma, parcelas, data da 1ª e, no cartão, bandeira). Ao salvar, o contrato e as parcelas já aparecem em Financeiro. Se escolher "Só cadastrar", nada é lançado.
- **Vendido** (faturamento do mês) = o valor **cheio** dos contratos vendidos no mês. **Já caiu** = o que realmente entrou no mês (**líquido**). **Falta cair** = o que ainda vai entrar no mês. Zero aparece como "—" para não confundir.
- **Cartão de crédito (em reais):** cai **sozinho** na data do vencimento. Se a data cai em sábado, domingo ou feriado nacional, passa para o **próximo dia útil**. Não tem botão "Recebi" nem "Desfazer". O valor mostrado nos recebíveis é o **líquido** (já descontada a taxa).
- **Taxas do cartão:** tela *Financeiro → Taxas do cartão*, por bandeira (Visa, Mastercard, Elo, Amex, Hipercard, Outra) e faixa (à vista, 2–6x, 7–12x). Ao lançar uma venda no cartão, o sistema sugere a taxa da tabela; você pode digitar outra na hora. A taxa fica **gravada na venda**: mudar a tabela depois não altera vendas antigas.
- **Pix (à vista ou parcelado):** combine com o paciente o dia de cada parcela. No dia, a parcela aparece em **Pix para cobrar e conferir** (e como número no menu **Financeiro** e no Painel). Botão **Cobrar** abre o WhatsApp com a mensagem pronta; quando cair, **Recebi** (com **comprovante** em foto ou PDF, opcional). O contador mostra, por exemplo, "3 de 6 pago".
- **Dólar/euro, Wise, dinheiro, outro:** continuam manuais ("Recebi"). Em dólar/euro você informa **quantos reais caíram**; nunca somamos US$ ou € com R$.
- **Cancelar parcela:** para chargeback ou paciente que parou de pagar. A parcela sai de todos os totais e pode ser **Reativada**.
- Feriados considerados: só os **nacionais** (mais Carnaval, Sexta-feira Santa e Corpus Christi). Feriado de cidade não entra.
- **Notificação push** (aviso no celular no dia da cobrança) ainda **não existe**: por enquanto a tarefa do dia aparece no Painel e no número do menu Financeiro.
- **Reajuste de plano:** se você mudar o valor de um plano, quem já está nele **mantém o valor antigo até a próxima renovação**.
- Tudo isso é **só do admin**. O paciente nunca vê valores, parcelas, taxas nem comprovantes. Ao excluir um paciente (LGPD), os pagamentos e os arquivos de comprovante dele também são apagados.
- Se algum contrato de cartão já estava vencido antes da atualização, as parcelas vencidas viram "recebidas" sozinhas no primeiro acesso ao Financeiro. É esperado.

### Atualização 05 (Pacientes v2: status, vencimento, consultas e responsáveis)
Mesmo processo, **depois** das atualizações 01 a 04:
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → clique no banco **nodaysoffclub** (menu da esquerda) → aba **Importar** → `database/atualizacao_05_pacientes.sql` → **Executar**.
   Acrescenta a cota de consultas nos planos, o campo "responsável" no paciente e as tabelas de consultas e responsáveis. Não apaga nada.
   **Importe antes de abrir o sistema.**
3. *(Opcional, só para ver as telas cheias)* importe `database/demo_pacientes.sql`: cria 3 responsáveis **fictícios**, atribui os pacientes de teste e lança consultas de exemplo.
   Ele também preenche a cota de consultas dos planos **só onde estiver em branco** (Trimestral 2, Semestral 4, Anual 8: é exemplo, troque pelos números reais).
4. **Ctrl + F5**, abra **Admin → Planos** → **Editar** e preencha **"Consultas presenciais incluídas"** de cada plano (o Gabriel sabe os números).
5. Abra **Admin → Responsáveis** e cadastre as 3 estagiárias.

**Como a lista de pacientes funciona agora**
- **Colunas:** Paciente · Status · Plano (com valor) · Vencimento (data + quantos dias faltam) · Consultas ("2 de 4", restam, ou "completa") · Responsável. **Pontos** ficam no Ranking e na ficha; **Visto por último** fica na ficha do paciente (etiqueta no topo, amarela se ele sumiu). Quem não abre o app há 7 dias ou mais ganha o aviso **sumido** ao lado do status, e o botão **Sumidos** continua filtrando.
- **Status:** *Ativo* = plano em dia (vence hoje ou depois). *Inativo* = o plano venceu e não renovou. *Desativado* = conta bloqueada pelo Gabriel (não consegue entrar). *Sem data* = ainda sem vencimento. É calculado sozinho pela data de vencimento.
- **Ordenar:** clique no título da coluna (clicar de novo inverte; a setinha mostra o sentido). Também dá para escolher em **Ordenar** (fica útil no celular). Padrão: nome de A a Z.
- **Filtros** (combinam entre si e com os botões Em dia, Vencem logo, Atletas...): **Status**, **Vencimento** (vencidos, 0 a 7, 8 a 15, 16 a 30, 31 a 60, mais de 60 dias, sem data: serve para o plano de ação "vence em 15 dias, 30 dias..."), **Responsável** (cada estagiária ou "sem responsável"), **Consultas** (ainda têm a fazer, cota completa, nenhuma feita, plano sem cota), além de Plano e Modalidade. "Limpar filtros" volta ao começo.
- **Consultas presenciais:** cada plano tem uma **cota** (Admin → Planos). Na ficha do paciente, aba **Acompanhamento** → painel **Consultas presenciais**, você registra cada consulta com a data e uma observação opcional (dá para lançar várias de uma vez, útil para quem já fez consultas antes do sistema). Ao **renovar** o plano o contador recomeça do zero; as consultas antigas ficam no histórico como "anterior". Registrou errado? **Apagar**.
- **Responsáveis (modo babá):** por enquanto as estagiárias **não entram no sistema**: é só uma etiqueta. Para atribuir em massa: na lista, marque os pacientes (a caixinha do topo marca todos os que aparecem), escolha a estagiária em **"Atribuir os marcados a…"** e clique **Aplicar**. Dica: filtre por "Sem responsável" e marque todos para distribuir de uma vez. Também dá para escolher o responsável no cadastro e na ficha de cada paciente. Responsável não é apagado, só desativado.
- **Privacidade:** responsável e consultas são só do painel do admin. O paciente nunca vê. Ao excluir um paciente (LGPD), as consultas dele também são apagadas.
- **Ainda não existe:** login próprio para as estagiárias (cada uma ver só os pacientes dela, sem valores). Fica para uma próxima etapa, junto com a revisão de segurança.

### Atualização 06 (Importação de pacientes por planilha)
Mesmo processo, **depois** das atualizações 01 a 05:
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → clique no banco **nodaysoffclub** (menu da esquerda) → aba **Importar** → `database/atualizacao_06_importacao.sql` → **Executar**.
   Acrescenta um único campo (`app_access`): quem pode entrar no app. Todos os pacientes que já existem continuam entrando normalmente. Não apaga nada.
   **Importe antes de abrir o sistema.**
3. **Ctrl + F5** e abra **Admin → Pacientes → Importar planilha**.

**Como importar (5 passos)**
1. Clique em **Baixar modelo (.xlsx)**. É a planilha oficial do NDO: aba **Instruções**, aba **Pacientes** (vazia, só com os cabeçalhos e listas de escolha), aba **Exemplos** (5 pacientes fictícios) e aba **Listas** (os planos, modalidades e responsáveis que existem hoje no seu sistema).
2. Preencha a aba **Pacientes**, um paciente por linha. Só **Nome completo** e **E-mail** são obrigatórios. Datas como `20/02/2027`, valores como `2400` ou `2.400,50`.
3. Volte em **Importar planilha**, escolha o arquivo e clique em **Validar planilha**. **Nada é salvo nesse momento.**
4. Confira a **prévia**: cada linha aparece como **Pronto**, **Aviso**, **Duplicado** ou **Erro**, com a explicação em português. Dá para filtrar ("Precisam de atenção", "Erros"...) e baixar um **relatório dos problemas (.csv)** para corrigir a planilha.
5. Clique em **Confirmar e importar**. Se houver avisos, marque antes "Conferi os avisos". Só as linhas Pronto/Aviso entram; as com erro e as duplicadas ficam de fora.

**O que o sistema confere (e por quê)**
- **Obrigatórios:** nome e e-mail válido. E-mail do modelo de exemplo (`@exemplo.invalid`) é recusado de propósito.
- **Duplicados (a linha é pulada):** e-mail que já existe (ou aparece duas vezes na planilha); mesmo nome **e** mesmo WhatsApp que um paciente já cadastrado. Só o nome igual, ou só o WhatsApp igual, vira **aviso** para você conferir.
- **Erros (a linha não entra):** plano, responsável ou modalidade que não existe ou está desativado; data inválida; forma de pagamento desconhecida; parcelas fora de 1 a 12; "parcelas recebidas" maior que o total; dólar/euro já recebido sem o valor em reais; taxa fora de 0 a 30; data da venda faltando ou no futuro; dados de pagamento sem a forma de pagamento; consultas fora de 0 a 20.
- **Avisos (entra, mas confira):** plano já vencido (o paciente entra como Inativo); plano sem vencimento ("sem data"); WhatsApp com poucos números; cartão sem bandeira nem taxa (líquido ficará igual ao bruto); coluna ignorada por não se aplicar (ex.: "parcelas recebidas" em cartão em reais, que cai sozinho).
- **Pagamento:** as mesmas regras do cadastro manual. Se a coluna **Forma de pagamento** estiver vazia, só o paciente é cadastrado, sem pagamento. Se preencher, a **Data da venda** é obrigatória (ela define em que mês entra no faturamento). Valor em branco = usa o valor do plano. Valor igual ao do plano não vira "valor combinado".
- **Consultas já feitas:** entram no plano atual com a data da importação e a observação "Importado da planilha (data real não informada)". O contador "x de y" já fica certo; você pode apagar e relançar com a data real, se quiser.
- **Limites:** até **500 pacientes** por arquivo e **2 MB**. Aceita `.xlsx` (Excel) e `.csv` (Excel em português, vírgula ou ponto e vírgula). A ordem das colunas pode mudar; colunas que não fazem parte do modelo são ignoradas (a prévia avisa quais).

**Paciente importado não ganha senha nem acesso ao app.** Ele entra "sem acesso ao app": não consegue entrar, não aparece no ranking nem para outros pacientes, e não conta como "sumido". Para liberar, abra a ficha dele → aba **App do paciente** → **Liberar acesso e gerar senha provisória** → envie pelo WhatsApp. Na lista, o botão **Sem acesso ao app** mostra todos que ainda faltam liberar.

**Privacidade (LGPD):** a planilha tem dados pessoais. Não envie por WhatsApp ou e-mail e apague o arquivo do computador depois de importar (o sistema não guarda o arquivo, só o resultado). Valores (plano, combinado, pagamentos) continuam **só do admin**. Antes de usar com pacientes **reais**, faça um teste com 2 ou 3 pacientes fictícios.

### Atualização 07 (Painel gerencial)
Mesmo processo, **depois** das atualizações 01 a 06 (se ainda não aplicou a 05 e a 06, aplique-as **nesta ordem**: 05 → 06 → 07):
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → clique no banco **nodaysoffclub** (menu da esquerda) → aba **Importar** → `database/atualizacao_07_painel.sql` → **Executar**.
   Acrescenta um único campo (`joined_on`, "Paciente desde") e preenche com a data de cadastro dos pacientes que já existem. Não apaga nada. **Importe antes de abrir o sistema.**
3. **Ctrl + F5** e abra **Admin** (a tela inicial é a **Operação**; a **Visão executiva** é a segunda aba).

**O que o painel mostra** (tudo calculado a partir do que já existe no sistema; nada inventado)
- **Período:** Últimos 30 dias (padrão), Este mês, Mês passado, Este ano. Cada indicador compara com o período anterior (↑ ↓).
- **Pacientes em carteira (antes "ativos"):** status Ativo **e** plano não vencido (igual ao filtro "Ativo" da lista).
- **Vendido:** soma das vendas pela **data da venda**. **Recebido:** o que realmente caiu, pela data em que caiu. **A receber:** parcelas pendentes que ainda não venceram (cartão já líquido da taxa). **Inadimplência:** parcelas pendentes **vencidas, a partir de 1 dia de atraso**.
- **Novos pacientes:** pela data **"Paciente desde"** (campo novo no cadastro e na ficha; se ficar em branco, vale a data do cadastro). Serve para a carga dos pacientes antigos não aparecer como "170 novos de uma vez".
- **Taxa de renovação:** dos planos que **venceram no período**, quantos **renovaram**. Sem plano vencido no período, aparece "—" (e não 0%).
- **Carteira:** distribuição por plano (rosca) e por modalidade (barras). **Crescimento:** pacientes ativos e novos mês a mês.
- **Desempenho dos responsáveis:** por responsável, pacientes ativos, consultas feitas ÷ cota (%) e taxa de renovação. A linha **"Sem responsável (sem acompanhamento)"** mostra pacientes ativos que ninguém está cuidando. Para o **Gabriel** aparecer na tabela, cadastre-o em **Admin → Responsáveis**.
- **Alertas e acompanhamento:** renovações em 7 dias, planos vencidos, sumidos 7+ dias, em risco, inadimplentes, sem responsável, próxima prova, moderação e resgates. Cada alerta leva para a lista já filtrada.
- **Comercial · Leads:** aparece como **"Em breve"**, sem números. A aba de Leads (Instagram, WhatsApp, indicação) é a próxima atualização.
- **Privacidade:** os valores (R$) e a equipe ficam **só no painel do admin**; nenhuma tela do paciente mostra ou acessa isso.
- Na **importação por planilha** há a coluna nova **Paciente desde** (opcional; vazia = data de hoje). Se você baixou o modelo antes, baixe de novo.

### Atualização 08 (Aniversários)
Mesmo processo, **depois** das atualizações 01 a 07 (se ainda não aplicou 05, 06 e 07, aplique **nesta ordem**: 05 → 06 → 07 → 08):
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → clique no banco **nodaysoffclub** (menu da esquerda) → aba **Importar** → `database/atualizacao_08_aniversarios.sql` → **Executar**.
   Acrescenta 3 campos (`birth_date`, `msg_optin`, `msg_optin_at`) e o texto padrão da mensagem de aniversário. Não apaga nada. **Importe antes de abrir o sistema.**
3. **Ctrl + F5** e abra **Admin → Aniversários**.

**Como funciona**
- **Data de nascimento** (opcional): no cadastro do paciente e na ficha. Só o admin vê; o paciente nunca vê. Não pode ser futura nem antes de 1900.
- **Caixinha "Autorizou receber mensagens (aniversário e novidades)"**: começa **desmarcada**. Marque só se o paciente disse que aceita; o sistema guarda o **dia** em que foi marcada. Ela ainda não bloqueia nada (o botão de WhatsApp funciona para todos); vai servir de trava quando o envio passar a ser automático.
- **Admin → Aniversários**: escolha **Hoje**, **Próximos 7 dias**, **Próximos 30 dias**, **Este mês** ou **Mês que vem**. Aparece quem tem plano em dia (ou sem data de vencimento); plano vencido e conta desativada não entram. Mostra a idade que a pessoa faz, se autorizou, e o botão **Dar parabéns**, que abre o WhatsApp com a mensagem pronta e o primeiro nome. Você confere e envia.
- Quem nasceu em **29/02** é lembrado em **28/02** nos anos que não são bissextos.
- **Painel → aba Operação** mostra os aniversariantes de hoje e dos próximos 7 dias. A **Visão executiva** tem o ponto de atenção "aniversariantes hoje" (só aparece quando há aniversariante).
- **Texto da mensagem**: Admin → Configurações → **Mensagem de aniversário**. Use `{nome}` para o primeiro nome. Em branco = texto padrão.
- **Planilha de importação**: nova coluna **Data de nascimento** (opcional; baixe o modelo de novo). A autorização de mensagens **não** vem da planilha, de propósito: marque na ficha de cada paciente.
- **Ainda não existe:** envio **automático** de e-mail e de WhatsApp. Pede hospedagem (tarefa agendada no servidor), serviço de e-mail e a API oficial do WhatsApp (que exige autorização do paciente e cobra por mensagem). Por isso a Atualização 08 é o botão com a mensagem pronta.
- **LGPD:** confirme com a revisão jurídica a base legal para mensagens de aniversário (legítimo interesse com opção de sair, ou consentimento) e acrescente isso ao Termo (`/termos`).

### Atualização 08.1 (Cadastro: sexo, CPF, Instagram e endereço; telas mais limpas)
Mesmo processo, **depois** da Atualização 08 (ordem: ... → 07 → 08 → 08.1):
1. Atualize os arquivos do projeto (`git pull`, ou copie a pasta nova por cima).
2. phpMyAdmin → clique no banco **nodaysoffclub** → aba **Importar** → `database/atualizacao_08b_cadastro.sql` → **Executar**.
   Acrescenta 11 campos na tabela de usuários (sexo, CPF, Instagram e endereço) e um índice do CPF. Não apaga nada. **Importe antes de abrir o sistema.**
3. **Ctrl + F5**.

**O que mudou**
- **Cadastro e ficha do paciente** ficaram em blocos: *Dados do paciente*, *Plano e acompanhamento*, *Pagamento* e *Acesso ao app*. Campos novos (todos opcionais, **só o admin vê**):
  - **Sexo** (Feminino ou Masculino): alimenta o gráfico **Por sexo** na carteira do painel (para saber qual público é maior e ajudar na compra dos brindes).
  - **CPF** (para recibo e nota fiscal): o sistema confere os dígitos, guarda só os números, mostra formatado e **não deixa cadastrar o mesmo CPF duas vezes** (diz de quem já é).
  - **Instagram**: digite `@usuario` ou cole o link do perfil; guarda só o usuário e, na ficha, vira link clicável.
  - **Endereço completo** (bloco que abre ao clicar): CEP, rua, número, complemento, bairro, cidade, estado e **país**. Em branco = Brasil. No Brasil, o CEP tem 8 números e o estado é a sigla; para quem mora fora, CEP e estado ficam livres.
- **Painel → Visão executiva → Distribuição da carteira**: novo gráfico **Por sexo**. A porcentagem é sobre quem **tem** o sexo informado; quem ficou sem informar aparece numa nota. Enquanto ninguém tiver sexo preenchido, o gráfico avisa para preencher.
- **Lista de pacientes**: novo filtro **Cadastro incompleto** (sem CPF ou sem endereço), útil para cobrar o que falta dos pacientes antigos.
- **Planos, Responsáveis e Modalidades**: saiu a coluna **Status** (e o "≈ por mês" dos Planos). Item desativado aparece em cinza com "(desativado)"; para desativar ou reativar, abra o **Editar** e use a caixinha "Ativo".
- **Nomes**: "Pacientes ativos" virou **Pacientes em carteira** (Planos, Responsáveis e Painel) e "Atletas ativos" virou **Atletas**. Só o rótulo mudou; a regra continua a mesma (plano em dia).
- **Planilha de importação**: modelo **versão 4**, com as colunas novas (Sexo, CPF, Instagram, CEP, Rua, Número, Complemento, Bairro, Cidade, Estado, País). **Baixe o modelo de novo.** CPF repetido (no sistema ou dentro da planilha) vira "Duplicado". Planilhas antigas (versão 3) continuam funcionando.
- **Proteção extra**: se uma aba da ficha ficou aberta de antes da atualização e você salvar nela, os dados novos **não** são apagados.
- **LGPD:** CPF e endereço são dados pessoais. Peça à revisão jurídica para incluir no Termo (`/termos`) a finalidade (emitir recibo e nota fiscal; envio de brindes) e confirme com o dev como o CPF deve ser protegido no banco em produção.

### Atualização 08.2 (Ficha do paciente em abas; menu em grupos)
**Não tem SQL.** Só atualize os arquivos (`git pull`, ou copie a pasta nova por cima) e aperte **Ctrl + F5**. Ordem das atualizações de banco, para quem ainda não fez todas: ... → 07 → 08 → 08.1 (a 08.2 não mexe no banco).

**O que mudou** (nada foi apagado; só mudou de lugar):
- **Ficha do paciente em 4 abas**, para separar o trabalho do dia a dia da equipe do que é do app do paciente:
  - **Resumo** (abre sempre por aqui): *Dados do paciente* (cadastro, plano, vencimento, responsável, acesso), *Perfil esportivo* e, no fim, *Privacidade e exclusão (LGPD)*.
  - **Plano e financeiro**: *Pagamentos* (parcelas, Recebi, Cobrar, comprovante) e *Renovar acompanhamento*. Quando há **parcela atrasada**, a aba mostra uma bolinha vermelha com o número.
  - **Acompanhamento**: *Consultas presenciais*, *Dieta* e *Medidas registradas* (peso e cintura que o paciente lança no app).
  - **App do paciente**: dias seguidos, saldo de pontos, pontos no mês, ranking do mês, treinos no mês, último check-in, **nível**, **Pontos** (lançar no extrato e ver o extrato), **Treinos deste paciente** e **Acesso ao app** (senha provisória, liberar acesso).
- **Topo da ficha** ficou só com o que a equipe usa: situação, dias para vencer, "Visto…", modalidades, consultas "x de y", responsável, Instagram e plano com valor. O **nível** (Bronze/Prata/Ouro/Black) foi para a aba App do paciente.
- **Cada botão volta para a aba onde você estava** (por exemplo, ao registrar um pagamento você continua em Plano e financeiro; ao lançar pontos, em App do paciente). A mensagem de boas-vindas (senha provisória) continua aparecendo no alto da ficha, em qualquer aba, e só uma vez.
- **Etiqueta "Sem acesso ao app"** (paciente importado) agora é um link que leva direto à aba App do paciente, onde fica o botão de liberar.
- **Menu lateral em grupos:** **Dia a dia** (Painel, Pacientes, Aniversários, Financeiro) · **Cadastros** (Planos, Responsáveis, Modalidades) · **App do paciente** (Treinos, Ranking, Loja, Resgates, Regras de pontos, Moderação, Ver o feed) · **Sistema** (Configurações, Auditoria). O item da tela em que você está continua destacado, também no celular (☰).
- **Celular:** as abas da ficha rolam para o lado quando não cabem; as abas Plano e Acompanhamento não ficam mais largas que a tela; o menu ficou mais compacto para caber sem rolar.
- **Privacidade e regras:** nada mudou. Só o admin vê a ficha; o paciente continua sem ver valores, CPF, endereço, sexo, nascimento nem nada do financeiro.
- **Próximo passo:** a Atualização 09 (**Leads**) entra no grupo **Dia a dia** do menu.

### Atualização 08.3 (Pacientes: tela nova e cores da logo no painel)
**Não tem SQL.** Só atualize os arquivos (`git pull`, ou copie a pasta nova por cima) e aperte **Ctrl + F5** (o navegador troca para a versão `ndo-v14` do app). Ordem das atualizações de banco, para quem ainda não fez todas: ... → 07 → 08 → 08.1 (as 08.2 e 08.3 não mexem no banco).

**O que mudou**
- **Identidade da logo em todo o painel admin:** menu lateral **vinho** com a logo circular clara, fundo **creme** e botões/destaques em vinho (`#670001`), com o greige (`#9D897B`) só em detalhes. O **app do paciente continua com as cores de antes** (as cores novas só valem dentro do `/admin`).
- **Frase institucional** *"Constância vence a perfeição porque todo dia conta."* no alto das telas do painel (computador) e, discreta, no rodapé do menu lateral. O botão **Sair** foi para o menu do seu nome, no canto de cima.
- **Tela Pacientes refeita:**
  - **5 indicadores no topo**, com as mesmas contas do Painel: *Pacientes em carteira* (planos em dia hoje), *Novos no mês*, *Faturamento contratado* (vendido no mês em reais; dólar e euro aparecem à parte), *Renovações próximas* (planos que vencem nos próximos 15 dias, com "N vencem em até 7 dias") e *Taxa de renovação*. As setas comparam com o mês passado até o mesmo dia.
  - **Busca e filtros numa área só:** busca por **nome, e-mail, CPF ou WhatsApp** + Plano, Modalidade, Status e Vencimento. O que sobrou (Responsável, Consultas, Ordenar por, Sumidos, Sem acesso ao app, Cadastro incompleto, Atletas, Competem, Sem assessoria…) está em **Mais filtros** (abre sozinho quando há filtro ali dentro).
  - **Atalhos com contagem:** *Todos · Ativos · Inativos · Em atraso · Renovação próxima*. **Em atraso** = plano vencido **ou** parcela já vencida e não recebida. As contagens respeitam a busca e os outros filtros.
  - **Tabela enxuta:** avatar, nome e e-mail, plano, modalidade, vencimento (data e dias), status (*Ativo, Renovação próxima, Inativo, Desativado, Sem data* e, quando for o caso, *Parcela atrasada*), responsável e ações. **Saíram da tabela:** pontos, "visto por último", valores em dinheiro, "x de y" de consultas e a etiqueta "sumido" (tudo continua na **ficha** e nos filtros).
  - **Ações com ícone:** 👁 ver a ficha · ✏️ editar os dados · ⋯ mais opções (Plano e financeiro, Acompanhamento, App do paciente e WhatsApp, quando há telefone).
  - **Seleção múltipla e ações em lote** continuam. **"Selecionar todos" marca só os pacientes da página que está na tela.**
  - **Paginação:** 25 por página (escolha 10, 25, 50 ou 100 em *Mostrar*), com "Mostrando 1–25 de N pacientes". Filtros, busca e ordem se mantêm ao trocar de página.
  - **Celular e telas menores:** cada paciente vira um **cartão** (nome, plano, modalidade, vencimento, status e responsável), com os mesmos ícones de ação.
- **Ajuste 08.3.1:** a tabela agora tem colunas em porcentagem e nunca passa da largura do quadro (antes, em algumas telas, a coluna **Ações** ficava cortada na direita). Nomes, e-mails e planos muito longos ganham "..." ou quebram de linha; o e-mail inteiro aparece ao passar o mouse. Em telas abaixo de 1360 px de largura, a tela usa os cartões.
- **Não foi incluído, de propósito:** sino de notificações (o sistema ainda não tem notificações) e botão **Exportar** (dado de saúde e CPF; vale decidir com a LGPD em mente).
- **Próximo passo:** a Atualização 09 (**Leads**) entra no grupo **Dia a dia** do menu.

### Atualização 08.4 (Painel mais limpo: Operação primeiro e Visão executiva enxuta)
**Não tem SQL.** Só atualize os arquivos (`git pull`, ou copie a pasta nova por cima) e aperte **Ctrl + F5** (o navegador troca para a versão `ndo-v15` do app). Ordem das atualizações de banco, para quem ainda não fez todas: ... → 07 → 08 → 08.1 (da 08.2 em diante não mexem no banco).

**Princípio:** cada aba responde **uma pergunta**. **Nenhum número ou conta mudou** (são os mesmos de antes, conferidos pelos testes); só a apresentação.

**Aba Operação — "o que eu faço hoje?" (agora é a que abre primeiro)**
- **4 cartões de tarefa no alto**, cada um leva para a lista certa: **parcelas para cobrar ou conferir** (com quantas estão atrasadas e o valor), **vencem em até N dias** (com quantos já venceram), **sem abrir o app há 7 dias ou mais** e **pendências no app** (resgates + denúncias, cada um com seu link). O cartão inteiro é clicável; número zerado fica apagado.
- Uma linha de resumo: pacientes em carteira, abriram o app em 7 dias, check-ins hoje e atletas.
- **Risco de renovação** (lista enxuta de 3 colunas: Paciente com plano, valor e "visto por último"; Renovação; Ação com WhatsApp e ícone de abrir a ficha) com a **previsão "a renovar em 30 dias"** logo acima. **Aniversariantes** e **Próximas provas** ficam numa coluna ao lado (no celular, uma embaixo da outra).

**Aba Visão executiva — "como está o negócio?"**
- **Pontos de atenção no topo**, numa faixa curta: só aparece o que tem número (vence em até 7 dias, planos vencidos, parcelas em atraso, sem responsável, sem abrir o app, renovações em risco, aniversariantes hoje). Sem nada, diz "Nada pedindo atenção agora".
- **4 números grandes:** pacientes em carteira (com "N novos pacientes no período"), vendido, recebido e taxa de renovação. Saíram os mini gráficos dos cartões e as caixinhas que repetiam os mesmos valores.
- **Evolução financeira** é o gráfico principal: os números do topo são **vendido no ano** e **a receber**; a **inadimplência** virou uma linha só, em vermelho, que leva ao Financeiro (ou "nenhuma parcela em atraso"). Os valores do último mês ficam à direita do gráfico **sem se sobrepor** (antes, valores parecidos como R$ 9,3 mil e R$ 9,1 mil colavam).
- **Distribuição da carteira** mostra **um gráfico por vez**, com as abas **Plano | Sexo | Modalidade**. **Crescimento da carteira** mostra o gráfico e o "+X% em 6 meses" no título; a tabela fica em **Ver em tabela**. A tabela dos **responsáveis** ocupa a largura toda embaixo.
- **Uma paleta só:** **vinho** para destaque e **tons de vinho/greige** para categorias (a rosca deixou de ser verde); **verde só para o que é bom** (setas para cima, crescimento) e **vermelho só para alerta**. A linha "Recebido" do gráfico agora é greige.
- **Saiu o bloco "Comercial · Leads — Em breve"** (volta na Atualização 09, já com dados reais). "Sem base para comparar" virou um traço discreto "—" e o anel de 0% não aparece vazio.
- **Endereços antigos continuam funcionando:** `/admin?aba=operacao` abre a Operação e `/admin?p=mes&g=12` abre a Visão executiva.
- **Ajuste 08.4.1 (venda cancelada some do "Vendido"):** antes, cancelar as parcelas de uma venda (por exemplo uma venda em **dólar** lançada por engano) **não** tirava o valor do **Vendido** do Painel, do Financeiro e do gráfico: a venda continuava somando. Agora o Vendido = **total da venda menos as parcelas canceladas** (cancelar 1 de 3 tira só aquela parcela; cancelar todas tira a venda inteira e o US$ ou € some do Painel; **Reativar** devolve o valor). A venda cancelada continua aparecendo na ficha, como cancelada. O texto do campo **Valor combinado** agora avisa que ele só vale para a previsão e para a próxima renovação e **não muda vendas já registradas**. *Para trocar uma venda de dólar para real:* cancele as parcelas da venda em dólar (Plano e financeiro > Pagamentos) e registre uma nova venda em R$.
- **Próximo passo:** a Atualização 09 (**Leads**).

### Acessos para teste
| Perfil | E-mail | Senha |
|---|---|---|
| Admin (Gabriel) | `admin@ndoclub.local` | `Trocar@123` (o sistema obriga a trocar no 1º acesso) |
| Pacientes fictícios | `ana.teste@exemplo.com`, `bruno.teste@…`, `carla.teste@…`, … `lucas.teste@exemplo.com` | `Demo@123` |
| Paciente novo (1º acesso) | `novo.teste@exemplo.com` | `Demo@123` (será obrigada a trocar a senha e aceitar o termo) |

Pacientes de demonstração úteis: **Ana** (nível Ouro, 21 dias seguidos, treinos e medidas),
**Heitor** (renovação vermelha, vence em 3 dias), **Felipe** (renovação amarela, ainda sem check-in hoje),
**João** (renovação vencida e "sumido" no painel).

> As datas da demonstração são relativas a **hoje** (importe os dados no dia em que for testar).

### Testar no celular (mesmo Wi-Fi)
1. No PC, abra o Prompt de Comando e digite `ipconfig`; anote o **Endereço IPv4** (ex.: `192.168.0.15`).
2. No celular (no mesmo Wi-Fi) abra `http://192.168.0.15/nodaysoffclub/`.
3. Se não abrir, o **Firewall do Windows** está bloqueando o Apache: permita o Apache HTTP Server em
   redes privadas (ao iniciar o Apache o Windows costuma perguntar).
4. **Instalar na tela inicial:** por HTTP em rede local o navegador só cria um *atalho*. O modo PWA
   completo (instalação, funcionar offline, ícone próprio) exige **HTTPS** — isso vem quando o sistema
   for para uma hospedagem de verdade. Em `localhost` no próprio PC o PWA já funciona.

### Se algo der errado
| Sintoma | Causa provável | Solução |
|---|---|---|
| "Não foi possível conectar ao banco" | MySQL parado ou senha diferente | Start no MySQL do XAMPP; confira `config/config.php`. |
| Página de login abre, mas os outros links dão 404 | `mod_rewrite`/`AllowOverride` desativados | Passo 3 da instalação. |
| Imagens/estilo não aparecem | Pasta com nome diferente de `nodaysoffclub` ou copiada dentro de outra pasta | `index.php` precisa estar direto em `htdocs/nodaysoffclub/`. |
| Erro "Unknown database" | Passo 4 não foi feito | Importe `schema.sql`. |
| Tela em branco | PHP antigo | Use PHP 8.1+ (`php -v` no terminal do XAMPP). |
| Upload de foto falha | Extensão GD desligada | Em `php.ini` habilite `extension=gd` e reinicie o Apache. |

---

## 4. Roteiro de testes (marque ✅ ao passar)

Use **duas janelas**: uma normal (paciente) e uma anônima (admin), para ver os dois lados ao mesmo tempo.
Ideal: faça no celular e depois no computador.

### A. Entrada e segurança
- [ ] Abrir `/` sem estar logado → vai para o login.
- [ ] Login com senha errada → mensagem de erro genérica (sem dizer se o e-mail existe).
- [ ] Errar a senha 5 vezes seguidas → aparece aviso de "muitas tentativas, aguarde".
- [ ] Entrar com `novo.teste@exemplo.com` → obrigada a ir para **Primeiro acesso** (nova senha com 8+ caracteres + aceite do termo). Sem aceitar, não entra no app.
- [ ] Depois de entrar, tentar abrir `/admin` como paciente → "Acesso negado" (403).
- [ ] Sair (Perfil → Sair da conta) e tentar voltar para `/home` → volta ao login.

### B. Paciente — dia a dia (use a **Felipe**)
- [ ] **Início:** mostra dias seguidos, semana, pontos, nível, renovação em **amarelo** e o aviso fixo do Gabriel.
- [ ] **Check-in:** escolher sono, água e dieta → salvar → ganha **+5 pontos**; a semana marca o dia de hoje.
- [ ] Tentar fazer o check-in de novo no mesmo dia → bloqueado, **sem** ganhar pontos de novo.
- [ ] **Treino:** clicar **Marcar como feito hoje** em um treino → **+20 pontos**; o botão vira "Feito hoje"; o calendário marca o dia. Clicar de novo não pontua.
- [ ] **Feed:** publicar um post (com e sem foto) → **+10**. Curtir um post de outra pessoa → o coração acende sem recarregar a página. Comentar → **+3**.
- [ ] Postar mais de 3 vezes no mesmo dia → o post publica, mas os pontos param no limite diário (confirme no Extrato).
- [ ] **Denunciar** um post (menu "Mais") → mensagem de confirmação.
- [ ] **Extrato:** cada ação aparece com data/hora, motivo e +pontos; resgates aparecem em vermelho (−).
- [ ] **Ranking:** mostra o mês atual, sua linha destacada em branco e "O ranking zera em X dias". Botão `‹` abre o mês anterior.
- [ ] **Dieta:** abre o link e/ou baixa o PDF (a Ana tem link e recados).
- [ ] **Evolução:** registrar peso e cintura de hoje → entra no gráfico; registrar de novo na mesma data **atualiza** o valor; "Remover" apaga.
- [ ] **Perfil → Editar:** trocar foto (JPG/PNG), nome, objetivo → salva. Trocar senha (senha atual errada deve ser recusada).

### C. Loja e resgate (use a **Ana**, saldo ~2.200)
- [ ] Loja mostra os brindes com preço, estoque e o saldo.
- [ ] Resgatar um item → **pontos descontados na hora**, estoque −1, aparece em "Meus resgates" como *Aguardando aprovação*.
- [ ] Tentar resgatar um item mais caro que o saldo → recusado, nada é descontado.
- [ ] **Ranking não muda** ao gastar pontos (o resgate não entra no ranking do mês).

### D. Renovação
- [ ] **Heitor** (vence em 3 dias): selo **Urgente** vermelho + caixa de alerta + botão **Renovar pelo WhatsApp** (abre `wa.me` com mensagem pronta).
- [ ] **João**: mostra "dias de atraso".
- [ ] **Ana** (45 dias): selo **Em dia** verde, sem botão de alerta; cartão mostra **Seu plano Anual**, dias restantes e "Válido até".
- [ ] **Heitor** (vermelho): ao abrir o **Feed**, **Treino**, **Ranking** ou **Loja**, aparece a **faixa vermelha** "Seu plano Trimestral vence em 3 dias" com botão **Renovar** (no Início e no Perfil a faixa não repete, pois o cartão já avisa).
- [ ] No admin, renovar o Heitor (+90 dias) → selo volta a verde e entra o **bônus de renovação (+100)** no extrato dele.

### E. Admin (Gabriel) — use a janela anônima
- [ ] Login com `admin@ndoclub.local` / `Trocar@123` → obrigado a criar nova senha (anote!).
- [ ] **Painel:** números de pacientes ativos, check-ins de hoje, vencem em até 15 dias, vencidos, resgates pendentes, denúncias abertas; tabela **Risco de renovação** com João marcado como *sumido*.
- [ ] **Pacientes:** buscar por nome; abrir um; editar dados; **Novo paciente** (anote a senha provisória que aparece **uma vez**); gerar nova senha provisória.
- [ ] **Pontos manuais:** lançar +50 na Ana → aparece no extrato dela. Lançar −10 → desconta.
- [ ] **Dieta:** colocar link e/ou subir um PDF para uma paciente → ela vê em *Dieta*. Outra paciente **não** consegue abrir o PDF (teste colando o endereço em outra conta → bloqueado).
- [ ] **Treinos:** criar um treino geral e um individual para uma paciente → aparecem para ela.
- [ ] **Moderação:** remover um post → some do feed e os pontos dele são estornados no extrato. Fixar um aviso no **Mural do Gabriel** → aparece no Início de todos. Resolver a denúncia aberta.
- [ ] **Loja:** criar/editar brinde (preço, estoque, foto). **Resgates:** aprovar/entregar e **recusar** (recusar devolve os pontos e o estoque).
- [ ] **Regras de pontos:** mudar check-in de 5 para 8 → próximo check-in rende 8. Desativar uma regra → ela deixa de pontuar.
- [ ] **Configurações:** mudar o limite do selo amarelo (ex.: 20 dias) → selos recalculam. Trocar o WhatsApp. Subir a **versão do termo** → todos os pacientes precisam aceitar de novo no próximo acesso.
- [ ] **Auditoria:** as ações acima aparecem registradas (quem fez, o quê, quando).

### E2. Planos e valores (só o Gabriel vê R$)
- [ ] **Admin → Planos:** abre com Trimestral (90 dias), Semestral (180) e Anual (365). Preencher os valores (ex.: 450, 800, 1400) → aparecem como `R$ 450,00`.
- [ ] Digitar `abc` no valor → recusa com mensagem. Criar um plano novo (ex.: Mensal, 30 dias) → aparece na lista. **Desativar** um plano → some das escolhas, mas quem já está nele continua normal. (Planos não são apagados.)
- [ ] **Novo paciente:** escolher o plano → a data de vencimento é preenchida sozinha (hoje + duração). Preencher **Valor combinado** (ex.: 399,90) só se esse paciente tiver preço diferente.
- [ ] **Ficha do paciente:** mostra plano e valor (com "combinado" quando for o caso). Trocar o plano e apagar o valor combinado → passa a valer o valor do plano.
- [ ] **Renovar:** a duração do plano já vem selecionada e o valor sugerido; renovar guarda o valor no **histórico de renovações**. Valor é opcional.
- [ ] **Painel:** cartão **"a renovar em 30 dias"** com o total em R$ (usa o valor combinado, ou o do plano) e aviso de quantos pacientes estão **sem valor definido**. A tabela de risco mostra o plano e o valor de cada um.
- [ ] **Privacidade (importante):** entrar como paciente e passar por Início, Perfil, Extrato, Dieta, Feed, Treino, Ranking, Loja e Evolução → **nenhum R$ aparece em lugar nenhum**. Tentar abrir `/admin/planos` como paciente → bloqueado.

### E3. Atletas e perfil esportivo
- [ ] **Admin → Modalidades:** aparecem Corrida, Musculação, Hyrox e Natação. Criar uma nova (ex.: Ciclismo). Tentar criar "corrida" de novo → recusa (já existe). Desativar uma → some das escolhas, mas quem já tem continua com ela.
- [ ] **Ficha de um paciente → Perfil esportivo:** ligar **É atleta**, marcar modalidades, assessoria (com nome), "participa de provas" (nome e data) → **Salvar**. Desligar "É atleta" **guarda** os dados (se ligar de novo, voltam).
- [ ] **Lista de pacientes:** chips de modalidade embaixo do nome; filtros **Atletas**, **Competem**, **Sem assessoria** e o seletor de **modalidade** (dá para combinar). Ao usar um desses filtros, a lista ganha as colunas **Prova** (nome, data e "em N dias") e **Assessoria**.
- [ ] **Painel:** número de **atletas ativos** e cartão **Próximas provas** em ordem de data (provas que já passaram saem sozinhas).
- [ ] **Paciente (ex.: Carla):** em **Perfil** aparece o cartão **Perfil esportivo** com **Editar**; mudar modalidades e marcar/desmarcar **Mostrar minhas modalidades no meu perfil da comunidade**.
- [ ] **Privacidade (importante):** entrar como **outro** paciente e abrir o perfil público da Carla (pelo Feed → nome dela): só aparecem as **modalidades**, e só se ela marcou mostrar. **Assessoria, prova e data nunca aparecem** para outros pacientes.

### E4. Financeiro (só o Gabriel vê)
- [ ] **Taxas do cartão:** Admin → Financeiro → **Taxas do cartão** → preencher Visa (à vista, 2–6x, 7–12x) → Salvar. Voltar à tela e ver os números gravados.
- [ ] **Novo paciente com cartão:** *Novo paciente* → seção **Pagamento** → valor, forma **Cartão**, **12x**, bandeira Visa → Cadastrar. Na ficha, aba **Plano e financeiro** → painel **Pagamentos** mostra "Cartão · 12x", a bandeira, a taxa e 12 parcelas com valor líquido.
- [ ] **Cartão cai sozinho:** a 1ª parcela fica na data certa (se cair em fim de semana ou feriado nacional, passa para o próximo dia útil). **Não existe** botão Recebi/Desfazer no cartão. Parcela vencida aparece como recebida.
- [ ] **Novo paciente com Pix parcelado:** forma **Pix**, 6x, data da 1ª combinada → as parcelas aparecem com "Parcela 1/6 de R$ …".
- [ ] **Pix para cobrar e conferir:** parcela com vencimento hoje aparece na seção com botão **Cobrar** (abre o WhatsApp com a mensagem pronta) e **Recebi**. O número no menu Financeiro e o cartão do Painel batem com a lista.
- [ ] **Recebi com comprovante:** clicar no clipe, escolher foto ou PDF, **Recebi**. A parcela vira "Recebida" e o contador sobe ("3 de 6 pago"). **Comprovante** abre o arquivo; **Anexar** permite colocar depois.
- [ ] **Vendido × Já caiu × Falta cair:** vender um anual hoje → **Vendido** sobe o valor cheio, **Já caiu** não muda (cartão só cai nas datas das parcelas). Pix à vista já recebido sobe nos dois.
- [ ] **Cancelar / Reativar** uma parcela (chargeback): ela sai dos totais; **Reativar** devolve.
- [ ] **Dólar/euro:** forma **Wise**, moeda **US$**; ao marcar "Recebi", informar o **R$ que caiu**. O "já caiu" usa esse R$.
- [ ] **Registrar pagamento sem renovar:** na ficha, aba **Plano e financeiro**, contrato antigo com algumas parcelas já recebidas.
- [ ] **Planos:** coluna clicar no número de **Pacientes em carteira** abre a lista filtrada. (A coluna "≈ por mês" foi removida na 08.1.) Mudar o valor de um plano → aviso de quantos pacientes mantêm o valor antigo até renovar.
- [ ] **Privacidade (importante):** entrar como paciente e passar pelas telas → **nenhum R$, US$ ou € em lugar nenhum**. Abrir `/admin/financeiro` e o endereço de um comprovante como paciente → bloqueado.
- [ ] **Excluir paciente de teste** (LGPD): os pagamentos dele somem do Financeiro.

### E5. Lista de pacientes, consultas e responsáveis
- [ ] **Planos:** Admin → Planos → Editar → preencher **Consultas presenciais incluídas** (ex.: 4). A tabela de Planos mostra a coluna **Consultas**. Número inválido (letras, 61 ou mais) é recusado; em branco = "definir".
- [ ] **Responsáveis:** Admin → Responsáveis → Novo responsável. Nome repetido é recusado. Desativar não apaga.
- [ ] **Atribuir em lote:** Pacientes → marcar 3 pacientes → escolher a estagiária em *Ações em lote* → **Aplicar**. O aviso diz quantos mudaram e a coluna **Responsável** mostra o nome. Em **Mais filtros**, o filtro **Responsável** mostra só os dela e **Sem responsável** mostra quem ficou sem.
- [ ] **Status:** paciente com vencimento no futuro aparece **Ativo**; com data passada aparece **Inativo**; conta bloqueada (ficha → Acesso → Desativado) aparece **Desativado**.
- [ ] **Vencimento:** filtro **16 a 30 dias** mostra só quem vence nessa faixa. Conferir a data na coluna Vencimento.
- [ ] **Ordenar:** clicar em **Vencimento** (mais próximo primeiro), clicar de novo (mais distante primeiro). Clicar em **Plano**, **Status**, **Responsável**; em **Mais filtros → Ordenar por** também há *Consultas feitas*. O filtro escolhido não se perde ao ordenar.
- [ ] **Sumido:** em **Mais filtros → Sumidos** aparecem só os pacientes que não abrem o app há 7+ dias. Na ficha, a etiqueta "Visto ..." mostra a última vez que ele entrou.
- [ ] **Registrar consulta:** ficha → aba **Acompanhamento** → **Consultas presenciais** → data de hoje → **Registrar consulta**. Mostra "1 de 4", "restam 3". Data futura é recusada. Quantidade 2 lança duas de uma vez. **Apagar** remove.
- [ ] **Cota completa:** ao chegar em "4 de 4" aparece **cota completa** na ficha; em **Mais filtros → Consultas → Cota de consultas completa** o paciente aparece na lista.
- [ ] **Renovar zera o contador:** renovar o paciente → volta para "0 de 4"; o histórico mostra as consultas antigas como **anterior**.
- [ ] **Privacidade (importante):** entrar como paciente → nenhuma tela mostra responsável nem consultas. Abrir `/admin/responsaveis` como paciente → bloqueado.

### E6. Importação de pacientes por planilha
- [ ] **Modelo:** Pacientes → **Importar planilha** → **Baixar modelo (.xlsx)**. Abre no Excel com 4 abas. Em **Pacientes**, a coluna **Plano** tem lista suspensa com os seus planos.
- [ ] **Teste fictício:** copie 2 linhas da aba **Exemplos** para **Pacientes**, troque nome e e-mail (`@exemplo.invalid` é recusado), salve e envie. A prévia mostra **Pronto**.
- [ ] **Nada salvo antes de confirmar:** depois de validar, abra **Pacientes**: os fictícios ainda não estão lá. **Descartar** apaga a prévia.
- [ ] **Erro:** apague o e-mail de uma linha e envie de novo → **Erro** em vermelho, com o motivo. Baixe o **relatório dos problemas**.
- [ ] **Duplicado:** envie a mesma planilha duas vezes (depois de importar a primeira) → todas **Duplicado**, nada novo é criado.
- [ ] **Confirmar:** os pacientes aparecem em **Pacientes → Sem acesso ao app**. Pagamentos informados aparecem em **Financeiro**.
- [ ] **Liberar acesso:** ficha do paciente → aba **App do paciente** → **Liberar acesso e gerar senha provisória** → entrar com ela numa janela anônima → pede o primeiro acesso.
- [ ] **Privacidade (importante):** entrar como paciente → importados sem acesso não aparecem no ranking nem em `/u/número`; nenhuma tela mostra valores. Como paciente, abrir `/admin/importar` → bloqueado.
- [ ] **Excluir paciente importado de teste** (LGPD): consultas, pagamentos e perfil esportivo dele somem junto.

### E7. Painel gerencial
- [ ] **Abrir Admin:** a tela inicial mostra a **Operação** (o que fazer hoje). A segunda aba, **Visão executiva**, mostra os pontos de atenção, os 4 números grandes, a evolução financeira, a carteira, o crescimento e os responsáveis (veja o roteiro E8.4).
- [ ] **Períodos:** troque entre Últimos 30 dias, Este mês, Mês passado e Este ano: os números mudam e as setas comparam com o período anterior. Gráfico: 6 meses, 12 meses ou ano.
- [ ] **Conferir um número:** o **Vendido** do mês bate com **Financeiro** (vendido do mês). O **A receber** bate com a agenda de recebíveis.
- [ ] **Paciente desde:** cadastre um paciente novo (vem preenchido com hoje) → **Novos pacientes** sobe 1. Edite na ficha para uma data antiga → ele sai de "Novos".
- [ ] **Equipe:** cadastre o Gabriel em **Responsáveis**, atribua alguns pacientes → aparece na tabela com % de consultas e renovação. Pacientes sem responsável aparecem na linha de alerta.
- [ ] **Alertas:** clique num alerta → abre a lista de pacientes já filtrada.
- [ ] **Leads:** mostra "Em breve" (sem números inventados).
- [ ] **Celular:** nada corta nem rola para o lado; gráficos legíveis; "Ver em tabela" abre os números.
- [ ] **Privacidade (importante):** entrar como paciente → nenhuma tela mostra valores, responsável ou desempenho da equipe. Abrir `/admin` como paciente → bloqueado.

### E8. Aniversários
- [ ] **Cadastro:** Novo paciente → preencha **Data de nascimento** (ex.: o seu aniversário) → criar. Abra a ficha: a data está lá. A caixinha de autorização vem **desmarcada**.
- [ ] **Datas inválidas:** tente uma data no futuro ou antes de 1900 → o sistema recusa e explica.
- [ ] **Autorização:** marque a caixinha e salve → aparece "Marcado em <data>". Desmarque e salve → o registro some.
- [ ] **Tela Aniversários:** menu **Aniversários**. Teste os 5 botões (Hoje, 7 dias, 30 dias, Este mês, Mês que vem). Quem faz aniversário em cada período aparece com a idade.
- [ ] **Quem não aparece:** paciente com plano **vencido**, conta **desativada** ou **sem data de nascimento** não aparece (embaixo da lista há o aviso de quantos estão sem data).
- [ ] **WhatsApp:** **Dar parabéns** abre o WhatsApp com a mensagem pronta e o primeiro nome (sem enviar nada sozinho). Paciente sem telefone mostra "sem WhatsApp".
- [ ] **Mensagem:** Configurações → **Mensagem de aniversário** → mude o texto (com `{nome}`) → salve → o botão passa a usar o novo texto.
- [ ] **Painel:** aba **Operação** mostra os aniversariantes dos próximos 7 dias; a **Visão executiva** mostra "aniversariantes hoje" nos Pontos de atenção (quando houver).
- [ ] **Celular:** cada aniversariante vira um cartão, sem rolagem para o lado, e o botão **Dar parabéns** aparece inteiro.
- [ ] **Importação:** baixe o modelo de novo: tem a coluna **Data de nascimento**. Teste com 2 pacientes fictícios.
- [ ] **Privacidade (importante):** entrar como paciente → nenhuma tela mostra data de nascimento, autorização ou aniversariantes. Abrir `/admin/aniversarios` como paciente → bloqueado.

### E8.1. Cadastro: sexo, CPF, Instagram e endereço
- [ ] **Novo paciente** está em blocos (Dados, Plano e acompanhamento, Pagamento, Acesso ao app) e tem **Sexo**, **CPF**, **Instagram** e o bloco **Endereço completo** (fechado).
- [ ] **CPF:** use um CPF **de teste** (ex.: `529.982.247-25`, que é só para teste). Salve e abra a ficha: aparece formatado. Digite um CPF errado (ex.: `123.456.789-00`) → o sistema recusa. Tente o mesmo CPF em outro paciente → recusa e diz de quem é.
- [ ] **Instagram:** cole `https://www.instagram.com/usuario/` → guarda `@usuario`; na ficha aparece uma etiqueta clicável.
- [ ] **Endereço:** preencha CEP `01310-100` e estado `sp` → guarda certo (estado em maiúsculas). Estado `ZZ` ou CEP com poucos números → recusa. Para quem mora fora, escreva o país (ex.: Estados Unidos): CEP e estado ficam livres.
- [ ] **Tudo opcional:** cadastre um paciente sem nenhum desses campos → funciona. Apagar o conteúdo e salvar limpa o campo.
- [ ] **Painel → Visão executiva:** em *Distribuição da carteira* aparece **Por sexo** (Feminino/Masculino em %, com a quantidade) e a nota de quantos estão sem sexo informado.
- [ ] **Lista de pacientes:** botão **Cadastro incompleto** lista quem não tem CPF ou endereço.
- [ ] **Planos / Responsáveis / Modalidades:** sem coluna Status; desative um item pelo **Editar** (caixinha "Ativo") e veja-o em cinza com "(desativado)". Colunas agora dizem **Pacientes em carteira** e **Atletas**.
- [ ] **Importação:** baixe o modelo de novo (diz "Versão 4" e tem as colunas novas). Teste com 2 pacientes fictícios, um deles com CPF repetido → aparece como Duplicado.
- [ ] **Privacidade (importante):** entrar como paciente → nenhuma tela mostra CPF, endereço, Instagram ou sexo (nem o seu, nem o dos outros).

### E8.2. Ficha do paciente em abas e menu em grupos
- [ ] **Abas:** abra a ficha de um paciente → aparecem **Resumo**, **Plano e financeiro**, **Acompanhamento** e **App do paciente**; a ficha abre no **Resumo** e a aba atual fica sublinhada.
- [ ] **Resumo:** *Dados do paciente* (com CPF, endereço, plano, vencimento, responsável), *Perfil esportivo* e, no fim, *Privacidade e exclusão (LGPD)*. Não aparecem pontos, pagamentos nem dieta aqui.
- [ ] **Plano e financeiro:** *Pagamentos* e *Renovar acompanhamento*. Registre um pagamento e clique em **Recebi** numa parcela → você **continua nesta aba**. Um paciente com parcela atrasada mostra a **bolinha vermelha** no nome da aba.
- [ ] **Acompanhamento:** registre uma consulta, salve um link de dieta e veja as medidas → cada botão **volta para a aba Acompanhamento**.
- [ ] **App do paciente:** os 6 números (dias seguidos, saldo, pontos no mês, ranking, treinos, último check-in), o nível, **Lançar no extrato** (continua nesta aba e o lançamento aparece no extrato), **Treinos deste paciente** e **Acesso ao app**. **Gerar nova senha provisória** → fica nesta aba e a mensagem pronta aparece no alto.
- [ ] **Topo:** sem o nível; com situação, dias para vencer, "Visto…", modalidades, consultas, responsável, Instagram e plano. É igual em todas as abas.
- [ ] **Cadastro novo:** *Novo paciente* → Cadastrar → abre a ficha no Resumo com a **mensagem de boas-vindas** (só aparece uma vez).
- [ ] **Paciente importado:** a etiqueta **Sem acesso ao app** leva à aba App do paciente, onde está o botão **Liberar acesso**.
- [ ] **Menu lateral:** 4 grupos (Dia a dia, Cadastros, App do paciente, Sistema) com todos os itens de antes, e o item da tela atual destacado. No celular (☰) cabe na tela.
- [ ] **Celular:** as 4 abas rolam para o lado; nenhuma tela da ficha tem rolagem horizontal; as tabelas de parcelas rolam dentro do próprio quadro.
- [ ] **Privacidade (importante):** entrar como paciente e tentar abrir `/admin/pacientes/1?aba=app` → bloqueado. O app do paciente não tem abas nem o menu do admin.

### E8.3. Pacientes: tela nova e cores da logo
- [ ] **Cores e frase:** o menu lateral do `/admin` é vinho com a logo clara; o fundo é creme; no alto (computador) aparece *"Constância vence a perfeição porque todo dia conta."* e a mesma frase, pequena, no rodapé do menu. O app do **paciente** continua com as cores de antes (entre como a Felipe e confira).
- [ ] **Menu do usuário:** clique no seu nome (canto de cima) → **Sair**. Clicar fora ou apertar **Esc** fecha o menu.
- [ ] **Indicadores:** 5 cartões (Pacientes em carteira, Novos no mês, Faturamento contratado, Renovações próximas, Taxa de renovação). Confira com o **Painel**: os números e as setas devem ser iguais. *Renovações próximas* leva para o atalho de mesmo nome.
- [ ] **Atalhos:** clique em **Ativos**, **Inativos**, **Em atraso** e **Renovação próxima**. O número entre parênteses é a quantidade de pacientes que a lista mostra. **Em atraso** inclui quem tem **plano vencido** e quem tem **parcela vencida e não recebida** (aparece o selo vermelho *Parcela atrasada*).
- [ ] **Busca:** procure por parte do nome, do e-mail, do **CPF** (com ou sem pontos) e do **WhatsApp** (com ou sem máscara). "Limpar filtros" volta ao começo.
- [ ] **Mais filtros:** abre e fecha; mostra Responsável, Consultas, Ordenar por e os atalhos Sumidos, Sem acesso ao app, Cadastro incompleto, Atletas, Competem e Sem assessoria. Com um deles ligado, o quadro já abre aberto.
- [ ] **Tabela:** só avatar, nome/e-mail, plano, modalidade, vencimento, status, responsável e ações. **Nada de pontos, "visto por último" ou valores em dinheiro.**
- [ ] **Ações:** 👁 abre a ficha; ✏️ abre a ficha já no bloco *Dados do paciente*; ⋯ mostra Plano e financeiro, Acompanhamento, App do paciente e (com telefone) Abrir WhatsApp. Clicar fora fecha o menu.
- [ ] **Lote:** marque 2 pacientes → escolha a estagiária → **Aplicar** → você volta **para a mesma página**. **Selecionar todos** marca só os da página.
- [ ] **Paginação:** padrão 25 por página; trocar para 10/50/100 funciona; a página 2 mostra outros pacientes; "Mostrando 26–50 de N"; busca e filtros não se perdem ao trocar de página.
- [ ] **Celular:** cada paciente vira um cartão, sem rolagem horizontal; os indicadores ficam em 2 colunas; os atalhos rolam para o lado; o menu ☰ abre o menu vinho completo.
- [ ] **Privacidade (importante):** entrar como paciente e abrir `/admin/pacientes` → bloqueado.

### E8.4. Painel mais limpo
- [ ] **Abrir o Painel:** abre na **Operação**. As abas aparecem na ordem **Operação, Visão executiva**. O texto de cima diz "O que precisa de você hoje." (e "Como está o negócio." na Visão executiva).
- [ ] **4 cartões de tarefa:** *parcelas para cobrar ou conferir* (igual ao Financeiro), *vencem em até 15 dias* (com "N vencidos sem renovar"), *sem abrir o app há 7 dias ou mais* (igual a Pacientes > Sumidos) e *pendências no app* (resgates + denúncias). Clique em cada um: vai para a lista certa. Clique também em "N atrasadas", "N vencidos", "N resgates" e "N denúncias".
- [ ] **Resumo e previsão:** a linha com pacientes em carteira / abriram o app em 7 dias / check-ins hoje / atletas; e "R$ … a renovar em 30 dias" acima da lista de risco (com o exterior à parte, quando houver US$ ou €).
- [ ] **Risco de renovação:** cada paciente com plano e valor, "visto …" e "check-in em …", selo de renovação, botão **WhatsApp** (só com telefone) e o ícone 👁 que abre a ficha. Quem está sumido aparece primeiro, com a etiqueta *sumido*.
- [ ] **Aniversariantes e Próximas provas** aparecem ao lado (computador) ou embaixo (celular), sem rolagem horizontal.
- [ ] **Visão executiva:** faixa **Pontos de atenção** no topo (só com números maiores que zero; compare com Pacientes e Financeiro), depois 4 números grandes. Confira que "Pacientes em carteira", "Vendido", "Recebido" e "Taxa de renovação" estão iguais aos de antes.
- [ ] **Evolução financeira:** troque 6 meses / 12 meses / Ano atual; veja "Vendido no ano" e "A receber" no alto, a linha de **inadimplência** em vermelho e os valores do último mês à direita do gráfico, sem um por cima do outro. "Ver em tabela" mostra os mesmos valores.
- [ ] **Carteira:** clique em **Plano**, **Sexo** e **Modalidade** (um gráfico por vez; as setas ← → do teclado também trocam). **Crescimento:** "+X% em 6 meses" no título e tabela em *Ver em tabela*.
- [ ] **Cores:** a rosca é de tons de vinho/greige; verde só nas setas para cima e no crescimento; vermelho só em atraso e alertas.
- [ ] **Endereços antigos:** `/admin?aba=operacao` abre a Operação e `/admin?p=mes` abre a Visão executiva.
- [ ] **Celular:** os dois painéis em uma coluna, cartões em 2 colunas, sem rolagem horizontal, botão ☰ abre o menu.
- [ ] **Privacidade (importante):** entrar como paciente e abrir `/admin` → bloqueado.
- [ ] **Venda cancelada (ajuste 08.4.1):** num paciente de teste, registre uma venda de US$ 900 em 3 parcelas (Plano e financeiro > Pagamentos). Veja o **Vendido** no Painel (Visão executiva) e em Financeiro: aparece "+ US$ 900,00 (exterior)". **Cancele** 1 parcela: cai US$ 300. Cancele as 3: o US$ some. **Reative** uma: volta US$ 300. A venda continua na ficha como cancelada.

### F. Celular e aparência
- [ ] Nenhuma tela tem rolagem horizontal; botões grandes o bastante para o polegar; barra inferior (Início, Feed, Treino, Ranking, Loja) sempre visível.
- [ ] O painel admin no celular abre o menu pelo botão ☰ (gaveta lateral).
- [ ] Visual claro: fundo off-white, cartões brancos arredondados e **um bloco preto de destaque por tela** (na Início é o das sequências). Menu inferior em pílula preta. O corte diagonal da logo aparece no botão principal, no cantinho do bloco preto e nos dias da semana. Cores só aparecem para status (verde/amarelo/vermelho).
- [ ] No Chrome do celular (HTTPS/localhost): menu ⋮ → **Adicionar à tela inicial** e abrir como app.
- [ ] Desligar o Wi-Fi e abrir o app → aparece a tela "Sem conexão" (não quebra).

### G. Verificações técnicas rápidas (para o desenvolvedor)
- [ ] `http://localhost/nodaysoffclub/config/config.php`, `/database/schema.sql`, `/app/routes.php` e `/storage/` devem dar **403/404**.
- [ ] No navegador, abrir uma imagem de perfil em janela anônima (sem login) → bloqueado.
- [ ] Trocar para `'debug' => false` em `config/config.php` → erros não mostram detalhes na tela.

> **Como foi testado aqui:** o código passou por uma bateria automática de **1296 verificações** (login,
> primeiro acesso, pontos, limites diários, ranking, loja, moderação, admin, planos e valores,
> perfil esportivo, lista de pacientes (status, ordenação, filtros, consultas, responsáveis), importação por planilha (modelo, prévia, duplicados, erros, "Paciente desde", arquivos quebrados, acesso dos importados, lote de 500), painel gerencial (períodos, cartões, gráficos, carteira, crescimento, equipe e alertas conferidos contra um cálculo independente), aniversários (janelas, 29/02, autorização, mensagem editável, privacidade), cadastro com sexo, CPF (dígitos, duplicado), Instagram e endereço (Brasil e exterior), importação v4 e gráfico por sexo (conferido contra cálculo independente), ficha em abas (cada coisa na aba certa, cada botão volta para a aba certa, privacidade) e menu em grupos, tela Pacientes nova (indicadores conferidos contra o Painel e o banco, atalhos e contagens, "Em atraso", busca por CPF e WhatsApp, paginação, lote, tabela sem valores, parâmetros estranhos na URL, cores e frase do painel), Painel mais limpo (Operação primeiro, cartões de tarefa conferidos contra o banco, pontos de atenção iguais aos alertas, quatro números iguais ao Insights, rótulos do gráfico sem sobreposição, abas da carteira, endereços antigos, venda cancelada saindo do Vendido), financeiro (cadastro com pagamento, cartão automático em dia útil, taxas e líquido, Pix para cobrar, comprovantes, cancelamento, moedas, recebíveis), privacidade dos valores e das provas, segurança e CSRF),
> rodando com o PHP 8 e uma cópia do banco em SQLite. O **import real no MySQL do XAMPP não pôde
> ser executado nesse ambiente** — por isso o passo 4/5 da instalação (e, para quem já tinha o banco, os
> arquivos `atualizacao_01_planos.sql`, `atualizacao_02_atletas.sql`, `atualizacao_03_financeiro.sql`, `atualizacao_04_financeiro_v2.sql`, `atualizacao_05_pacientes.sql`, `atualizacao_06_importacao.sql`, `atualizacao_07_painel.sql`, `atualizacao_08_aniversarios.sql` e `atualizacao_08b_cadastro.sql`) é o primeiro teste a fazer.
> Se o phpMyAdmin acusar algum erro, copie a mensagem e me envie que eu corrijo.

---

## 5. Regras de pontos iniciais (editáveis no painel)

| Ação | Pontos | Limite por dia |
|---|---|---|
| Check-in diário | 5 | 1 |
| Treino concluído | 20 | 1 |
| Publicar no feed | 10 | 3 |
| Comentar | 3 | 5 |
| Curtir | 1 | 10 |
| Sequência de 7 dias | 30 | — |
| Sequência de 30 dias | 150 | — |
| Sequência de 100 dias | 500 | — |
| Renovação | 100 | — |
| Indicação que virou paciente | 200 | lançada pelo admin |

**Níveis** (pontos acumulados na vida, nunca rebaixam): Bronze · Prata 500 · Ouro 1.500 · Black 4.000
(configurável). **Ranking** = pontos ganhos dentro do mês; **saldo da loja** = tudo que ganhou − o que gastou.

---

## 6. Mapa para as próximas fases

| Do documento do projeto | Situação |
|---|---|
| Fase 1 — login, perfil, renovação, dieta, treino, feed + moderação, pontos/extrato, ranking mensal, loja manual, admin básico, PWA | ✅ **Neste MVP** |
| Extras da Fase 1 — painel de risco de renovação, streak, check-in de 10 s, níveis, bônus de renovação, mural fixado, evolução de peso | ✅ **Neste MVP** |
| Planos (Trimestral/Semestral/Anual), valores só para o admin, previsão de renovações em R$, faixa de alerta de renovação para o paciente | ✅ **Neste MVP** (atualização 01) |
| Perfil esportivo (atletas): modalidades, assessoria, próximas provas, filtros no painel, edição pelo paciente | ✅ **Neste MVP** (atualização 02) |
| Financeiro: moedas (R$, US$, €), pagamentos parcelados, agenda de recebíveis, "Recebi", atrasadas, reajuste de plano só na renovação | ✅ **Neste MVP** (atualização 03) |
| Financeiro v2: pagamento no cadastro do paciente, vendido × já caiu × falta cair, cartão automático em dia útil, taxa por bandeira com valor líquido, Pix para cobrar (WhatsApp + comprovante), cancelar parcela | ✅ **Neste MVP** (atualização 04) |
| Pacientes v2: status Ativo/Inativo, vencimento, consultas presenciais com cota por plano, responsável (modo babá), ordenação por coluna, filtros e atribuição em lote | ✅ **Neste MVP** (atualização 05) |
| Importação de pacientes por planilha: modelo oficial em Excel, prévia antes de salvar, duplicados, erros, paciente importado sem acesso até liberar | ✅ **Neste MVP** (atualização 06) |
| **Painel gerencial**: visão executiva (financeiro, carteira, crescimento, equipe, alertas), períodos com comparação, "Paciente desde" | ✅ **Neste MVP** (atualização 07) |
| **Leads** (Instagram, WhatsApp, indicação) com funil de conversão no painel gerencial; entrada manual primeiro | ⏭️ Próxima rodada (atualização 09) |
| **Aniversários**: data de nascimento, aniversariantes do dia/semana/mês, botão de WhatsApp com mensagem pronta e editável, caixinha de autorização | ✅ **Neste MVP** (atualização 08) |
| **Cadastro completo**: sexo (gráfico por sexo na carteira), CPF para recibo/nota fiscal, Instagram, endereço completo, filtro "Cadastro incompleto"; telas de Planos/Responsáveis/Modalidades mais limpas | ✅ **Neste MVP** (atualização 08.1) |
| **Ficha do paciente em abas** (Resumo, Plano e financeiro, Acompanhamento, App do paciente) e **menu lateral em grupos** (Dia a dia, Cadastros, App do paciente, Sistema) | ✅ **Neste MVP** (atualização 08.2) |
| **Pacientes: tela nova** (indicadores, busca e filtros compactos, atalhos com contagem, tabela enxuta, ações com ícone, paginação) e **cores da logo** no painel admin | ✅ **Neste MVP** (atualização 08.3) |
| **Painel mais limpo**: Operação abre primeiro (4 cartões de tarefa) e Visão executiva enxuta (pontos de atenção no topo, 4 números, um gráfico principal, carteira por abas, paleta única) | ✅ **Neste MVP** (atualização 08.4) |
| **Aniversário automático** por e-mail e WhatsApp (precisa de hospedagem, serviço de e-mail, API oficial do WhatsApp, autorização do paciente e revisão jurídica) | ⏭️ Depois da hospedagem |
| Estagiárias com **login próprio** (cada uma vê só os pacientes dela, sem valores), mensagem de ação por faixa de vencimento (15/30 dias), acompanhamento da renovação | ⏭️ Próxima rodada (pede revisão de segurança) |
| Financeiro, próximos passos: **notificação push** da cobrança do dia, taxa de renovação, previsão por mês, acompanhamento da renovação (conversei / vai renovar / não vai), marcador "cobrado em", mensagem de cobrança editável, importação de **renovações/histórico** por planilha | ⏭️ Próxima rodada |
| Contagem regressiva da prova no Início do paciente, temas do dia por modalidade, ranking por modalidade | ⏭️ Próxima rodada (dados já estão no banco) |
| Fase 2 — notificações push, medalhas/conquistas, desafios mensais, cupons de parceiros, fotos de evolução (privadas), indicação com link | ⏭️ Tabelas e serviços já preparados (`Points::award`, `point_rules`, `audit_log`); falta construir as telas |
| Fase 3 — Laravel + Filament, relatórios, integrações (WhatsApp automático, pagamento) | ⏭️ Estrutura pronta para migrar (seção 2) |

**Limitações conhecidas do MVP:** sem push; sem medalhas/cupons/desafios; fotos de evolução corporal
ficaram de fora de propósito (dado de saúde sensível — só entra após revisão de segurança/LGPD);
envio de e-mail/recuperação de senha automática não existe (o Gabriel gera senha provisória no painel).

---

## 7. Git — lembretes de commit

Ainda não é repositório? Na pasta do projeto: `git init`. O `.gitignore` já protege
`config/config.php`, uploads, logs e bancos locais.

```
git add .
git commit -m "MVP No Days Off Club: base PHP + MySQL"
```

Sugestão: **um commit a cada etapa que funcionar** (ex.: "instalação ok", "ajuste de regras de pontos",
"nova tela X"). **Nunca** envie para o GitHub: `config/config.php`, `.env`, dados reais de pacientes,
fotos, PDFs de dieta ou backups do banco.

---

## 8. Quando for para a internet (hospedagem)

- Use **HTTPS** (obrigatório para PWA e para proteger senhas).
- Em `config/config.php`: `'debug' => false`, usuário de banco **próprio** (não `root`) com senha forte.
- Troque a senha do admin e **apague** `demo_data.sql` / os pacientes `*.teste@exemplo.com`.
- Aponte o domínio para a pasta do projeto; os arquivos em `app/`, `config/`, `database/` e `storage/`
  já estão bloqueados pelo `.htaccess`, mas o ideal é mantê-los **fora** da pasta pública quando a
  hospedagem permitir.
- Faça **backup diário do banco** e da pasta `storage/`.

## 9. LGPD e cuidados com dados de saúde

- Peso, medidas, dieta e fotos são **dados pessoais sensíveis**: ficam privados por padrão e o app
  só exibe ao próprio paciente e à equipe.
- O **Termo de Consentimento** (`/termos`) é um **modelo**: precisa de revisão de profissional jurídico
  antes do uso com pacientes reais. Mudou o texto? Suba a versão em *Admin → Configurações* para
  todos aceitarem de novo.
- O paciente pode pedir exclusão: *Admin → Paciente → Excluir paciente e dados* apaga tudo dele (inclusive pagamentos e parcelas).
- Durante o desenvolvimento e o piloto interno use **somente pacientes fictícios**.
- **Antes de abrir para pacientes reais**, peça uma revisão de segurança ao desenvolvedor da equipe.

---

## 10. Créditos de fontes

Archivo Black e DM Sans (licença SIL OFL — textos em `assets/fonts/`), embutidas localmente: o app não
faz nenhuma requisição externa.
