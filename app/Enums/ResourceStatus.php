<?php

namespace App\Enums;

enum ResourceStatus: string
{
    case Borrador = 'borrador';
    case Revision = 'revision';
    case Observado = 'observado';
    case Aprobado = 'aprobado';
    case Publicado = 'publicado';
    case Retirado = 'retirado';
}
