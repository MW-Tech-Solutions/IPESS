<?php
declare(strict_types=1);

function admin_roles(): array
{
    return ['admissions_officer', 'ict_admin', 'super_admin', 'finance_officer'];
}

function require_admin(array $roles = []): array
{
    return require_login($roles ?: admin_roles());
}
