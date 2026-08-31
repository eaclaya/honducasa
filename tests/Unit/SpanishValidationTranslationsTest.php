<?php

use Tests\TestCase;

uses(TestCase::class);

test('content moderation validation errors are translated into Spanish', function () {
    app()->setLocale('es');

    expect(__('This field contains content that is not allowed.'))
        ->toBe('Este campo contiene contenido no permitido.')
        ->and(__('This image contains content that is not allowed.'))
        ->toBe('Esta imagen contiene contenido no permitido.')
        ->and(__('Content moderation is temporarily unavailable. Please try again.'))
        ->toBe('La moderación de contenido no está disponible temporalmente. Inténtalo de nuevo.')
        ->and(__('One of the selected photos is invalid.'))
        ->toBe('Una de las fotos seleccionadas no es válida.');
});
