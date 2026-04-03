<?php

declare(strict_types=1);

abstract class BaseRepository
{
    public function __construct(protected PDO $pdo)
    {
    }
}
