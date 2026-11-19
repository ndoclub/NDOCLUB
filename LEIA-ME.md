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
| **Renovação** | Selo verde (mais de 15 dias), amarelo (8 a 15) e vermelho (7 ou menos / vencido) + botão **Renovar pelo WhatsApp**. |
| **Treino** | Treinos gerais (A/B/C) e treinos individuais criados pelo Gabriel; botão **Treino feito** + calendário do mês. |
| **Dieta** | Link (WebDiet ou outro) e/ou PDF privado + recados do Gabriel. |
| **Feed da comunidade** | Posts com foto, curtidas, comentários, tema do dia, denúncia, regras de convivência, mural fixo do Gabriel. |
| **Pontos, extrato e níveis** | Extrato completo; níveis Bronze / Prata / Ouro / Black (nunca rebaixam). |
| **Ranking mensal** | Soma dos pontos do mês; zera todo mês. **Não** mexe no saldo da loja. |
| **Loja** | Troca pontos por brindes; o Gabriel aprova/entrega manualmente. |
| **Evolução** | Peso e cintura com gráfico (só o paciente e a equipe veem). |
| **Perfil** | Foto, dados, trocar senha. |

### Para o Gabriel (painel `/admin`, funciona no celular e no computador)
Painel com indicadores · **risco de renovação** (quem vence logo e quem sumiu há 7+ dias, com botão de
WhatsApp) · pacientes (cadastro, renovar +30/60/90/180/365 dias com bônus, pontos manuais, dieta,
senha provisória, exclusão LGPD) · treinos · moderação do feed e denúncias · mural fixado · loja e
resgates · regras de pontos · ranking · configurações (limites dos selos, WhatsApp, níveis, versão
do termo) · auditoria.

### Segurança já incluída
Senhas com `password_hash` · proteção contra CSRF em todo formulário · limite de tentativas de login ·
sessão com cookie protegido · consultas preparadas (PDO) contra SQL injection · escape de toda saída
(XSS) · CSP sem JavaScript inline · fotos e PDFs **privados** (só abrem logado; dieta só para o dono
e o admin) · fotos reprocessadas (remove dados de GPS/EXIF) · pastas internas bloqueadas pelo `.htaccess`.

---

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
│   ├─ Core/               ← motor: DB, Router, Auth, View, Upload, Middleware
│   ├─ Services/           ← regras de negócio: Points, Ranking, Levels, Streak, Renewal, Settings, Audit
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
6. Confira `config/config.php`: no XAMPP padrão (usuário `root`, senha vazia) **não precisa mudar nada**.
   Se o seu MySQL tiver senha, coloque em `'pass'`.
7. Abra **http://localhost/nodaysoffclub/** no navegador. Deve aparecer a tela de login preta com a logo.

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
- [ ] **Ana** (45 dias): selo **Em dia** verde, sem botão de alerta.
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

### F. Celular e aparência
- [ ] Nenhuma tela tem rolagem horizontal; botões grandes o bastante para o polegar; barra inferior (Início, Feed, Treino, Ranking, Loja) sempre visível.
- [ ] O painel admin no celular abre o menu pelo botão ☰ (gaveta lateral).
- [ ] A identidade segue a logo: fundo preto, texto branco, cortes diagonais. Cores só aparecem para status (verde/amarelo/vermelho).
- [ ] No Chrome do celular (HTTPS/localhost): menu ⋮ → **Adicionar à tela inicial** e abrir como app.
- [ ] Desligar o Wi-Fi e abrir o app → aparece a tela "Sem conexão" (não quebra).

### G. Verificações técnicas rápidas (para o desenvolvedor)
- [ ] `http://localhost/nodaysoffclub/config/config.php`, `/database/schema.sql`, `/app/routes.php` e `/storage/` devem dar **403/404**.
- [ ] No navegador, abrir uma imagem de perfil em janela anônima (sem login) → bloqueado.
- [ ] Trocar para `'debug' => false` em `config/config.php` → erros não mostram detalhes na tela.

> **Como foi testado aqui:** o código passou por uma bateria automática de **204 verificações** (login,
> primeiro acesso, pontos, limites diários, ranking, loja, moderação, admin, segurança e CSRF),
> rodando com o PHP 8 e uma cópia do banco em SQLite. O **import real no MySQL do XAMPP não pôde
> ser executado nesse ambiente** — por isso o passo 4/5 da instalação é o primeiro teste a fazer.
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
- O paciente pode pedir exclusão: *Admin → Paciente → Excluir paciente e dados* apaga tudo dele.
- Durante o desenvolvimento e o piloto interno use **somente pacientes fictícios**.
- **Antes de abrir para pacientes reais**, peça uma revisão de segurança ao desenvolvedor da equipe.

---

## 10. Créditos de fontes

Archivo Black e DM Sans (licença SIL OFL — textos em `assets/fonts/`), embutidas localmente: o app não
faz nenhuma requisição externa.
