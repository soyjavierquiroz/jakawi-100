<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/** Shared by writes and resolution; includes registered routes and constrained root programs. */
class PartnerSlug
{
    public static function reserved(): array
    {
        $slugs = ['admin', 'login', 'logout', 'register', 'up', 'explorar', 'beneficios', 'experiencias', 'retos', 'desbloqueos', 'mi-jakawi', 'perfil', 'l', 'p', 'api', 'password', 'email', 'membership', 'membresias', 'afiliados', 'creadores', 'promotores'];
        foreach (Route::getRoutes() as $route) {
            $prefix = explode('/', trim($route->uri(), '/'))[0];
            if ($prefix !== '' && ! str_contains($prefix, '{')) $slugs[] = strtolower($prefix);
            foreach (explode('|', $route->wheres['program'] ?? '') as $program) {
                if (preg_match('/^[a-z0-9-]+$/D', $program)) $slugs[] = $program;
            }
        }
        return array_values(array_unique($slugs));
    }

    public static function valid(mixed $slug): bool
    {
        return is_string($slug) && strlen($slug) <= 255
            && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) === 1
            && ! in_array($slug, self::reserved(), true);
    }

    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! self::valid($value)) $fail('Usa un slug público en minúsculas, con letras, números o guiones, que no esté reservado por JAKAWI.');
        };
    }
}
