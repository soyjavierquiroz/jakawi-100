<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class PrelaunchOperationBlocked extends HttpException
{
    public const MESSAGE = 'JAKAWI está en prelanzamiento. Las operaciones comerciales están deshabilitadas; puedes consultar información y preparar contenido para revisión.';

    public function __construct()
    {
        parent::__construct(423, self::MESSAGE);
    }
}
