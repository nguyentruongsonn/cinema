<?php

namespace App\Exceptions;

use RuntimeException;

class SeatConflictException extends RuntimeException
{
    public function __construct(
        string $message = 'Một số ghế vừa được người khác giữ.',
        private readonly array $conflictedSeats = []
    ) {
        parent::__construct($message, 409);
    }

    public function conflictedSeats(): array
    {
        return $this->conflictedSeats;
    }
}
