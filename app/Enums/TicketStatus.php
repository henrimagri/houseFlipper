<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Novo = 'novo';
    case EmAndamento = 'em_andamento';
    case AguardandoCliente = 'aguardando_cliente';
    case AguardandoCorretor = 'aguardando_corretor';
    case Encerrado = 'encerrado';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
