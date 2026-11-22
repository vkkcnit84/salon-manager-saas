<?php

namespace App\Messaging\Contracts;

final class SendResult
{
    public function __construct(public readonly bool $ok, public readonly ?string $id = null, public readonly ?string $error = null) {}
}
