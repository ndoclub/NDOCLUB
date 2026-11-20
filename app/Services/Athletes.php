<?php
namespace App\Services;

use App\Core\DB;

/**
 * Perfil esportivo (atletas): modalidades + assessoria + próxima prova.
 * Quem vê o quê:
 *  - Gabriel (admin): tudo.
 *  - O próprio paciente: tudo dele (e pode editar).
 *  - Outros pacientes (perfil público): SÓ os nomes das modalidades, e apenas se o paciente
 *    marcou "mostrar no meu perfil" (show_public). Assessoria e provas nunca aparecem para outros.
 */
final class Athletes
{
    /** Modalidades (ativas por padrão), em ordem alfabética. */
    public static function sports(bool $onlyActive = true): array
    {
        return DB::all('SELECT * FROM sports' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY name ASC');
    }

    /** Modalidades com a quantidade de atletas ativos em cada uma (admin). */
    public static function sportsWithCounts(): array
    {
        return DB::all(
            "SELECT s.*, (SELECT COUNT(*) FROM user_sports us
                            JOIN athlete_profiles a ON a.user_id = us.user_id AND a.is_athlete = 1
                            JOIN users u ON u.id = us.user_id AND u.status = 'active'
                           WHERE us.sport_id = s.id) AS athletes
               FROM sports s ORDER BY s.active DESC, s.name ASC"
        );
    }

    public static function profile(int $userId): ?array
    {
        return DB::one('SELECT * FROM athlete_profiles WHERE user_id = ?', [$userId]);
    }

    /** IDs das modalidades do paciente. */
    public static function sportIds(int $userId): array
    {
        return array_map('intval', array_column(DB::all('SELECT sport_id FROM user_sports WHERE user_id = ?', [$userId]), 'sport_id'));
    }

    /** Opções para o formulário: modalidades ativas + as que o paciente já tem (mesmo desativadas). */
    public static function choicesFor(int $userId): array
    {
        return DB::all(
            'SELECT s.* FROM sports s WHERE s.active = 1 OR s.id IN (SELECT sport_id FROM user_sports WHERE user_id = ?) ORDER BY s.name ASC',
            [$userId]
        );
    }

    /**
     * Nomes das modalidades de vários pacientes de uma vez (só de quem é atleta).
     * Com $publicOnly = true, só de quem escolheu mostrar no perfil.
     * Retorna [userId => ['Corrida', 'Hyrox']].
     */
    public static function namesFor(array $userIds, bool $publicOnly = false): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $rows = DB::all(
            "SELECT us.user_id, s.name FROM user_sports us
               JOIN sports s ON s.id = us.sport_id
               JOIN athlete_profiles a ON a.user_id = us.user_id
              WHERE a.is_athlete = 1" . ($publicOnly ? ' AND a.show_public = 1' : '') . " AND us.user_id IN ($in)
              ORDER BY s.name ASC",
            $userIds
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['user_id']][] = $r['name'];
        }
        return $out;
    }

    /**
     * Lê e valida o formulário do perfil esportivo (serve para o admin e para o paciente).
     * Retorna ['errors' => [...], 'data' => [...], 'sports' => [ids]].
     */
    public static function parse(int $userId): array
    {
        $errors = [];
        $isAthlete = !empty($_POST['is_athlete']) ? 1 : 0;

        // só aceita modalidades que existem e estão ativas (ou que o paciente já tinha)
        $allowed = array_map('intval', array_column(self::choicesFor($userId), 'id'));
        $posted = $_POST['sport_ids'] ?? [];
        $sports = [];
        foreach ((array) $posted as $v) {
            $id = (int) $v;
            if ($id > 0 && in_array($id, $allowed, true)) {
                $sports[$id] = $id;
            }
        }

        $hasCoach = !empty($_POST['has_coach']) ? 1 : 0;
        $coachName = input('coach_name');
        if (mb_strlen($coachName) > 80) {
            $errors[] = 'O nome da assessoria pode ter até 80 letras.';
        }
        $competes = !empty($_POST['competes']) ? 1 : 0;
        $eventName = input('event_name');
        if (mb_strlen($eventName) > 100) {
            $errors[] = 'O nome da prova pode ter até 100 letras.';
        }
        $eventDate = input('event_date');
        if ($eventDate !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $eventDate);
            if (!$d || $d->format('Y-m-d') !== $eventDate) {
                $errors[] = 'Data da prova inválida.';
            }
        }
        if ($isAthlete && !$sports) {
            $errors[] = 'Escolha pelo menos uma modalidade (ou desligue "É atleta").';
        }

        return [
            'errors' => $errors,
            'data' => [
                'is_athlete' => $isAthlete,
                'has_coach' => $hasCoach,
                'coach_name' => ($hasCoach && $coachName !== '') ? $coachName : null,
                'competes' => $competes,
                'event_name' => ($competes && $eventName !== '') ? $eventName : null,
                'event_date' => ($competes && $eventDate !== '') ? $eventDate : null,
            ],
            'sports' => array_values($sports),
        ];
    }

    /**
     * Grava o perfil. Se "É atleta" estiver desligado, só muda esse sinal e preserva o resto
     * (se ligar de novo, os dados voltam). $showPublic = null mantém a escolha atual do paciente.
     */
    public static function save(int $userId, array $parsed, ?int $showPublic = null): void
    {
        $d = $parsed['data'];
        DB::tx(function () use ($userId, $parsed, $d, $showPublic) {
            $exists = self::profile($userId);
            if (!$exists) {
                DB::run(
                    'INSERT INTO athlete_profiles (user_id, is_athlete, has_coach, coach_name, competes, event_name, event_date, show_public, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$userId, $d['is_athlete'], $d['has_coach'], $d['coach_name'], $d['competes'], $d['event_name'], $d['event_date'], $showPublic ?? 0, now()]
                );
            } elseif (!$d['is_athlete']) {
                DB::run('UPDATE athlete_profiles SET is_athlete = 0, updated_at = ? WHERE user_id = ?', [now(), $userId]);
                if ($showPublic !== null) {
                    DB::run('UPDATE athlete_profiles SET show_public = ? WHERE user_id = ?', [$showPublic, $userId]);
                }
                return;
            } else {
                DB::run(
                    'UPDATE athlete_profiles SET is_athlete = ?, has_coach = ?, coach_name = ?, competes = ?, event_name = ?, event_date = ?, show_public = ?, updated_at = ? WHERE user_id = ?',
                    [$d['is_athlete'], $d['has_coach'], $d['coach_name'], $d['competes'], $d['event_name'], $d['event_date'], $showPublic ?? (int) $exists['show_public'], now(), $userId]
                );
            }
            if ($d['is_athlete']) {
                DB::run('DELETE FROM user_sports WHERE user_id = ?', [$userId]);
                foreach ($parsed['sports'] as $sid) {
                    DB::run('INSERT INTO user_sports (user_id, sport_id) VALUES (?, ?)', [$userId, (int) $sid]);
                }
            }
        });
    }

    /** Próximas provas (a partir de hoje) de pacientes ativos, da mais perto para a mais longe. */
    public static function upcoming(int $limit = 8): array
    {
        return DB::all(
            "SELECT a.user_id AS id, u.name, u.avatar, a.event_name, a.event_date, a.has_coach, a.coach_name
               FROM athlete_profiles a JOIN users u ON u.id = a.user_id
              WHERE a.is_athlete = 1 AND a.competes = 1 AND a.event_date IS NOT NULL AND a.event_date >= ?
                AND u.role = 'patient' AND u.status = 'active'
              ORDER BY a.event_date ASC, u.name ASC LIMIT " . (int) $limit,
            [today()]
        );
    }

    public static function countAthletes(): int
    {
        return (int) DB::val("SELECT COUNT(*) FROM athlete_profiles a JOIN users u ON u.id = a.user_id WHERE a.is_athlete = 1 AND u.role = 'patient' AND u.status = 'active'");
    }
}
