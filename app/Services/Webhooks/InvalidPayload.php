<?php

namespace App\Services\Webhooks;

use RuntimeException;

/** Payload donasi dari provider tidak bisa dibaca (field wajib hilang / nominal tidak valid). */
class InvalidPayload extends RuntimeException
{
}
