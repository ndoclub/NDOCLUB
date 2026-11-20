<?php
/**
 * Mapa de URLs do sistema.
 * Formato: $router->get('/url', 'Controller@metodo', ['regras de acesso']);
 * Para criar uma página nova: adicione uma linha aqui + um método no Controller.
 */

// ---------- Público ----------
$router->get('/', 'AuthController@root');
$router->get('/login', 'AuthController@loginForm', ['guest']);
$router->post('/login', 'AuthController@login', ['guest']);
$router->post('/logout', 'AuthController@logout', ['auth_any']);
$router->get('/termos', 'AuthController@terms');
$router->get('/primeiro-acesso', 'AuthController@firstAccessForm', ['auth_any']);
$router->post('/primeiro-acesso', 'AuthController@firstAccess', ['auth_any']);

// ---------- Paciente ----------
$P = ['auth', 'patient'];
$router->get('/home', 'HomeController@index', $P);
$router->get('/checkin', 'CheckinController@form', $P);
$router->post('/checkin', 'CheckinController@save', $P);

$router->get('/treino', 'WorkoutController@index', $P);
$router->post('/treino/{id}/feito', 'WorkoutController@done', $P);
$router->get('/dieta', 'DietController@index', $P);
$router->get('/ranking', 'RankingController@index', $P);
$router->get('/loja', 'StoreController@index', $P);
$router->post('/loja/{id}/resgatar', 'StoreController@redeem', $P);
$router->get('/extrato', 'LedgerController@index', $P);
$router->get('/evolucao', 'ProgressController@index', $P);
$router->post('/evolucao', 'ProgressController@save', $P);
$router->post('/evolucao/{id}/excluir', 'ProgressController@delete', $P);

$router->get('/perfil', 'ProfileController@me', $P);
$router->get('/perfil/editar', 'ProfileController@editForm', $P);
$router->post('/perfil/editar', 'ProfileController@edit', $P);
$router->get('/perfil/esporte', 'ProfileController@sportForm', $P);
$router->post('/perfil/esporte', 'ProfileController@sportSave', $P);
$router->post('/perfil/senha', 'ProfileController@password', $P);
$router->post('/perfil/excluir-foto', 'ProfileController@removePhoto', $P);

// ---------- Comunidade (paciente e admin) ----------
$router->get('/feed', 'FeedController@index', ['auth']);
$router->post('/feed/postar', 'FeedController@store', ['auth']);
$router->get('/post/{id}', 'FeedController@show', ['auth']);
$router->post('/post/{id}/curtir', 'FeedController@like', ['auth']);
$router->post('/post/{id}/comentar', 'FeedController@comment', ['auth']);
$router->post('/post/{id}/excluir', 'FeedController@deletePost', ['auth']);
$router->post('/comentario/{id}/excluir', 'FeedController@deleteComment', ['auth']);
$router->post('/denunciar', 'FeedController@report', ['auth']);
$router->get('/u/{id}', 'ProfileController@show', ['auth']);

// ---------- Arquivos privados (só logado) ----------
$router->get('/media/img/{file}', 'MediaController@image', ['auth_any']);
$router->get('/media/dieta/{file}', 'MediaController@diet', ['auth_any']);

// ---------- Painel administrativo ----------
$A = ['auth', 'admin'];
$router->get('/admin', 'Admin\DashboardController@index', $A);

$router->get('/admin/pacientes', 'Admin\PatientController@index', $A);
$router->get('/admin/pacientes/novo', 'Admin\PatientController@create', $A);
$router->post('/admin/pacientes', 'Admin\PatientController@store', $A);
$router->get('/admin/pacientes/{id}', 'Admin\PatientController@show', $A);
$router->post('/admin/pacientes/{id}', 'Admin\PatientController@update', $A);
$router->post('/admin/pacientes/{id}/renovar', 'Admin\PatientController@renew', $A);
$router->post('/admin/pacientes/{id}/pontos', 'Admin\PatientController@points', $A);
$router->post('/admin/pacientes/{id}/senha', 'Admin\PatientController@resetPassword', $A);
$router->post('/admin/pacientes/{id}/dieta', 'Admin\PatientController@diet', $A);
$router->post('/admin/pacientes/{id}/dieta/pdf-remover', 'Admin\PatientController@removeDietPdf', $A);
$router->post('/admin/pacientes/{id}/esporte', 'Admin\PatientController@sport', $A);
$router->post('/admin/pacientes/{id}/pagamentos', 'Admin\PatientController@addPayment', $A);
$router->post('/admin/pacientes/{id}/excluir', 'Admin\PatientController@delete', $A);

$router->get('/admin/planos', 'Admin\PlanController@index', $A);
$router->get('/admin/planos/novo', 'Admin\PlanController@form', $A);
$router->post('/admin/planos', 'Admin\PlanController@store', $A);
$router->get('/admin/planos/{id}', 'Admin\PlanController@form', $A);
$router->post('/admin/planos/{id}', 'Admin\PlanController@update', $A);

$router->get('/admin/financeiro', 'Admin\FinanceController@index', $A);
$router->post('/admin/parcelas/{id}/receber', 'Admin\FinanceController@receive', $A);
$router->post('/admin/parcelas/{id}/desfazer', 'Admin\FinanceController@undo', $A);

$router->get('/admin/modalidades', 'Admin\SportController@index', $A);
$router->get('/admin/modalidades/novo', 'Admin\SportController@form', $A);
$router->post('/admin/modalidades', 'Admin\SportController@store', $A);
$router->get('/admin/modalidades/{id}', 'Admin\SportController@form', $A);
$router->post('/admin/modalidades/{id}', 'Admin\SportController@update', $A);

$router->get('/admin/treinos', 'Admin\WorkoutController@index', $A);
$router->get('/admin/treinos/novo', 'Admin\WorkoutController@form', $A);
$router->post('/admin/treinos', 'Admin\WorkoutController@store', $A);
$router->get('/admin/treinos/{id}', 'Admin\WorkoutController@form', $A);
$router->post('/admin/treinos/{id}', 'Admin\WorkoutController@update', $A);
$router->post('/admin/treinos/{id}/excluir', 'Admin\WorkoutController@delete', $A);

$router->get('/admin/moderacao', 'Admin\ModerationController@index', $A);
$router->post('/admin/mural', 'Admin\ModerationController@announce', $A);
$router->post('/admin/posts/{id}/remover', 'Admin\ModerationController@removePost', $A);
$router->post('/admin/posts/{id}/fixar', 'Admin\ModerationController@pin', $A);
$router->post('/admin/comentarios/{id}/remover', 'Admin\ModerationController@removeComment', $A);
$router->post('/admin/denuncias/{id}/resolver', 'Admin\ModerationController@resolve', $A);

$router->get('/admin/loja', 'Admin\StoreController@index', $A);
$router->get('/admin/loja/novo', 'Admin\StoreController@form', $A);
$router->post('/admin/loja', 'Admin\StoreController@store', $A);
$router->get('/admin/loja/{id}', 'Admin\StoreController@form', $A);
$router->post('/admin/loja/{id}', 'Admin\StoreController@update', $A);
$router->get('/admin/resgates', 'Admin\StoreController@redemptions', $A);
$router->post('/admin/resgates/{id}/status', 'Admin\StoreController@setStatus', $A);

$router->get('/admin/pontos', 'Admin\PointsController@index', $A);
$router->post('/admin/pontos', 'Admin\PointsController@save', $A);
$router->get('/admin/ranking', 'Admin\PointsController@ranking', $A);

$router->get('/admin/config', 'Admin\SettingsController@index', $A);
$router->post('/admin/config', 'Admin\SettingsController@save', $A);
$router->get('/admin/auditoria', 'Admin\SettingsController@audit', $A);
