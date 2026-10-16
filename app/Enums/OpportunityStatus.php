<?php

namespace App\Enums;

enum OpportunityStatus: string
{
    case New = 'new';
    case Analyzing = 'analyzing';
    case Interested = 'interested';
    case VisitScheduled = 'visit_scheduled';
    case Negotiating = 'negotiating';
    case ProposalSent = 'proposal_sent';
    case Purchased = 'purchased';
    case Discarded = 'discarded';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo', self::Analyzing => 'Em análise', self::Interested => 'Interessado',
            self::VisitScheduled => 'Visita agendada', self::Negotiating => 'Negociação',
            self::ProposalSent => 'Proposta enviada', self::Purchased => 'Adquirido',
            self::Discarded => 'Descartado', self::Archived => 'Arquivado',
        };
    }

    public static function options(): array
    {
        return array_column(array_map(fn (self $item) => ['value' => $item->value, 'label' => $item->label()], self::cases()), null, 'value');
    }
}
