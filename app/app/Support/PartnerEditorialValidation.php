<?php

namespace App\Support;

/** Messages only: existing controllers retain every validation rule. */
final class PartnerEditorialValidation
{
    public static function messages(): array
    {
        return [
            'required' => 'Completa este campo.',
            'present' => 'Completa la selección de este campo.',
            'string' => 'Introduce un texto válido.',
            'integer' => 'Introduce un número entero.',
            'array' => 'Selecciona opciones válidas.',
            'in' => 'Selecciona una de las opciones disponibles.',
            'exists' => 'Selecciona un registro disponible.',
            'date' => 'Introduce una fecha y hora válidas.',
            'after' => 'La fecha final debe ser posterior a la fecha de inicio.',
            'min' => 'El mínimo permitido es :min.',
            'max' => 'El máximo permitido es :max.',
            'gte' => 'El valor debe ser igual o mayor que :value.',
            'alpha_dash' => 'Usa únicamente letras, números, guiones o guiones bajos.',
            'unique' => 'Este valor ya está en uso. Elige otro.',
            'regex' => 'Revisa el formato. Este texto no admite cifras.',
            'prohibited' => 'Este campo no corresponde a la opción seleccionada.',
        ];
    }
}
