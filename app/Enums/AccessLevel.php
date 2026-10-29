<?php

namespace App\Enums;

enum AccessLevel: string
{
    case Publico = 'publico';
    case Autenticado = 'autenticado';
    case Restringido = 'restringido';
}
