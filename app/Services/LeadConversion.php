<?php
namespace App\Services;

use App\Core\DB;

/**
 * Conversão de lead em paciente (Atualização 09.2).
 *
 * Usa os MESMOS módulos do resto do sistema, sem copiar regra:
 *  - Pacientes: cria a linha em users (role patient), com os dados pessoais, ou vincula a um paciente que já existe;
 *  - Planos: plano contratado, vencimento e valor combinado (patient_billing) quando diferente do valor do plano;
 *  - Financeiro: a venda e as parcelas entram por Payments::create (paciente novo) ou Renewal::applyRenewal (paciente existente).
 * Tudo acontece numa transação só: ou o lead vira paciente COM a venda lançada, ou nada muda.
 * O fechamento fica registrado em lead_conversions (valor contratado, plano, condição, venda ligada) e na linha do tempo.
 */
final class LeadConversion
{
    /** "Pix à vista", "Cartão de crédito em 3x"... gravado em lead_conversions.payment_conditions. */
    public static function conditionText(array $pay): string
    {
        $n = (int) ($pay['installments'] ?? 1);
        return Payments::methodLabel((string) ($pay['method'] ?? 'outro')) . ($n > 1 ? ' em ' . $n . 'x' : ' à vista');
    }

    /**
     * Executa a conversão. $c (já validado pelo controller):
     *  mode 'new'|'link' · plan (linha de plans) · amount, currency, store_custom · pay (Payments::parseForm) · responsible_id (?int)
     *  new:  name, email, phone, goal, birth, pd (PersonalData::clean), joined_on, renewal_date, password
     *  link: patient_id
     * Retorna ['patient_id' => , 'payment_id' => , 'mode' => ].
     */
    public static function convert(array $lead, array $c, int $by): array
    {
        return DB::tx(function () use ($lead, $c, $by) {
            $id = (int) $lead['id'];
            $cur = DB::one('SELECT status, stage_id, recovery_status FROM leads WHERE id = ?', [$id]);
            if (!$cur || !in_array($cur['status'], ['open', 'lost'], true)) {
                throw new \RuntimeException('Este lead já foi convertido ou não existe mais.');
            }
            $plan = $c['plan'];
            $planId = (int) $plan['id'];
            $pay = $c['pay'];
            $amount = (float) $c['amount'];
            $currency = currency_or_brl($c['currency']);
            $resp = $c['responsible_id'] ?: null;

            if ($c['mode'] === 'new') {
                $uid = DB::insert(
                    "INSERT INTO users (role, name, email, password_hash, phone, goal, status, renewal_date, joined_on, birth_date, msg_optin, msg_optin_at, plan_id, responsible_id, must_change_password, lead_id, created_at)
                     VALUES ('patient', ?, ?, ?, ?, ?, 'active', ?, ?, ?, 0, NULL, ?, ?, 1, ?, ?)",
                    [
                        $c['name'], $c['email'], password_hash($c['password'], PASSWORD_DEFAULT), $c['phone'] ?: null, $c['goal'] ?: null,
                        $c['renewal_date'], $c['joined_on'], $c['birth'] ?: null, $planId, $resp, $id, now(),
                    ]
                );
                DB::run('UPDATE users SET ' . implode(' = ?, ', PersonalData::COLUMNS) . ' = ? WHERE id = ?', array_merge(PersonalData::dbValues($c['pd']), [$uid]));
                if ($c['store_custom']) {
                    Plans::setCustomPrice($uid, $amount, $currency, 'manual');
                }
                $paymentId = Payments::create(
                    $uid, $planId, null, $amount, $currency, $pay['method'], $pay['installments'], today(), $pay['first_due'],
                    $pay['paid_count'], $pay['received_brl'], $by, $pay['brand'], $pay['fee_pct']
                );
                $startOn = $c['joined_on'];
                $note = 'Novo paciente cadastrado';
            } else {
                $uid = (int) $c['patient_id'];
                DB::run('UPDATE users SET plan_id = ?, responsible_id = COALESCE(?, responsible_id) WHERE id = ?', [$planId, $resp, $uid]);
                DB::run('UPDATE users SET lead_id = ? WHERE id = ? AND lead_id IS NULL', [$id, $uid]);
                if ($c['store_custom']) {
                    Plans::setCustomPrice($uid, $amount, $currency, 'manual');
                }
                $ren = Renewal::applyRenewal($uid, (int) $plan['duration_days'], $by, $amount, $planId, $pay, $currency);
                $paymentId = $ren['payment_id'];
                $startOn = today();
                $note = 'Vinculado ao paciente que já existia: ' . (string) DB::val('SELECT name FROM users WHERE id = ?', [$uid]);
            }

            $cond = self::conditionText($pay);
            DB::insert(
                'INSERT INTO lead_conversions (lead_id, patient_id, plan_id, contracted_value, currency, contracted_on, start_on, payment_conditions, responsible_id, source_id, by_user_id, payment_id, mode, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $uid, $planId, $amount, $currency, today(), $startOn, $cond, $resp, $lead['source_id'] ?: null, $by, $paymentId, $c['mode'], now()]
            );

            $wonStage = (int) DB::val("SELECT id FROM lead_stages WHERE kind = 'won' ORDER BY sort_order ASC, id ASC LIMIT 1");
            $recovery = $cur['status'] === 'lost' ? 'recovered' : $cur['recovery_status'];
            DB::run(
                "UPDATE leads SET status = 'won', stage_id = ?, patient_id = ?, converted_at = ?, next_on = NULL, next_type = NULL, next_responsible_id = NULL,
                        recovery_status = ?, updated_by = ?, updated_at = ? WHERE id = ?",
                [$wonStage, $uid, now(), $recovery, $by, now(), $id]
            );
            Leads::addEvent($id, 'converted', [
                'summary' => 'Convertido em paciente · ' . $plan['name'] . ' · ' . money($amount, 2, $currency) . ' · ' . $cond,
                'notes' => $note,
                'from' => Leads::stageName((int) $cur['stage_id']),
                'to' => Leads::stageName($wonStage),
            ], $by);

            return ['patient_id' => $uid, 'payment_id' => $paymentId, 'mode' => $c['mode']];
        });
    }

    /** Dados do fechamento de um lead convertido (a conversão mais recente), com o nome do plano. */
    public static function forLead(int $leadId): ?array
    {
        return DB::one(
            'SELECT c.*, p.name AS plan_name FROM lead_conversions c LEFT JOIN plans p ON p.id = c.plan_id WHERE c.lead_id = ? ORDER BY c.id DESC LIMIT 1',
            [$leadId]
        );
    }
}
