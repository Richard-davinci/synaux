<?php

// Cada instalación decide cuándo habilitar el área profesional y el marketplace.
return [
    'stage_two_enabled' => (bool)env('SYNAUX_STAGE_TWO_ENABLED', false),
];
