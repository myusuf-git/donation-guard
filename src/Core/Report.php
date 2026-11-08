<?php
namespace DonationGuard\Core;

/** Result of one reconciliation run. */
final class Report
{
    /** @var Outcome[] */ public $outcomes = [];
    /** @var bool */ public $dryRun;
    /** @var string */ public $startedAt;

    public function __construct(bool $dryRun, string $startedAt)
    {
        $this->dryRun = $dryRun;
        $this->startedAt = $startedAt;
    }

    public function add(Outcome $o): void
    {
        $this->outcomes[] = $o;
    }

    /** @return array<string,int> count per action */
    public function counts(): array
    {
        $c = [];
        foreach ($this->outcomes as $o) {
            $c[$o->action] = ($c[$o->action] ?? 0) + 1;
        }
        ksort($c);
        return $c;
    }

    public function checked(): int
    {
        return count($this->outcomes);
    }

    /** Donations that need a human: mismatches + unresolved. */
    public function needsAttention(): array
    {
        return array_values(array_filter($this->outcomes, function (Outcome $o) {
            return in_array($o->action, [Outcome::MISMATCH, Outcome::UNRESOLVED], true);
        }));
    }

    public function toArray(): array
    {
        return [
            'started_at' => $this->startedAt,
            'dry_run'    => $this->dryRun,
            'checked'    => $this->checked(),
            'counts'     => $this->counts(),
            'items'      => array_map(function (Outcome $o) {
                return [
                    'donation_id' => $o->donation->id,
                    'gateway'     => $o->donation->gateway,
                    'reference'   => $o->donation->reference,
                    'amount'      => $o->donation->amount,
                    'action'      => $o->action,
                    'gateway_status' => $o->status ? $o->status->value : null,
                    'gateway_raw'    => $o->status ? $o->status->raw : null,
                    'note'        => $o->note,
                ];
            }, $this->outcomes),
        ];
    }
}
