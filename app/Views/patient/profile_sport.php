<section class="page">
    <p class="lead" style="margin-top:0">Conte para o Gabriel como você treina. Isso ajuda a ajustar a sua alimentação, principalmente perto de provas.</p>
    <form method="post" action="<?= e(url('/perfil/esporte')) ?>">
        <?= csrf_field() ?>
        <?php partial('athlete-fields', ['athlete' => $athlete, 'choices' => $choices, 'selected' => $selected]); ?>

        <div class="section" style="margin:8px 0 16px">
            <label class="checkbox"><input type="checkbox" name="show_public" value="1"<?= !empty($athlete['show_public']) ? ' checked' : '' ?>><span><strong>Mostrar minhas modalidades no meu perfil da comunidade</strong></span></label>
            <p class="hint" style="margin-bottom:0">Os outros membros veem só o nome das modalidades (ex.: Corrida, Hyrox). Assessoria e provas ficam só entre você e o Gabriel.</p>
        </div>

        <button class="btn btn-block" type="submit">Salvar perfil esportivo</button>
        <a class="btn btn-ghost btn-block mt" href="<?= e(url('/perfil')) ?>">Cancelar</a>
    </form>
</section>
