<?php

namespace App\Enums;

enum DealStage: string
{
    case Novo = 'novo';
    case Contato = 'contato';
    case Qualificado = 'qualificado';
    case Sugeridos = 'sugeridos';
    case Visita = 'visita';
    case Proposta = 'proposta';
    case Negociacao = 'negociacao';
    case Fechamento = 'fechamento';
    case Perdido = 'perdido';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
