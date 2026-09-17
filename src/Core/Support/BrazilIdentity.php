<?php

declare(strict_types=1);

namespace App\Core\Support;

use DomainException;

final class BrazilIdentity
{
    public static function cpf(string $value): string
    {
        $cpf = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            throw new DomainException('CPF inválido.');
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += ((int)$cpf[$i]) * (($t + 1) - $i);
            }
            $digit = (10 * $sum) % 11;
            if ($digit === 10) $digit = 0;
            if ((int)$cpf[$t] !== $digit) {
                throw new DomainException('CPF inválido.');
            }
        }

        return $cpf;
    }

    public static function phone(string $value): string
    {
        $phone = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($phone, '55') && strlen($phone) >= 12) {
            $phone = substr($phone, 2);
        }
        if (!preg_match('/^[1-9]{2}9?\d{8}$/', $phone)) {
            throw new DomainException('Telefone inválido. Informe DDD + número.');
        }
        return $phone;
    }

    public static function loginDigits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }
}
