<?php

// Catálogo inicial de contenido: esta configuración todavía no consulta tablas de servicios.
return [
    // Contenido inicial del catálogo. No son presupuestos ni precios comerciales.
    'services' => [
        // Desarrollo web: identificador estable, categoría, textos de tarjeta y alcance del detalle.
        [
            'id' => 'sitio-institucional',
            'category' => 'Desarrollo web',
            'name' => 'Sitio institucional',
            'summary' => 'Un lugar propio para presentar tu organización, tus servicios y lo que te hace diferente.',
            'description' => 'Una presencia digital que organiza tu información y facilita que las personas conozcan tu propuesta. El alcance se define según las secciones, el contenido y las funciones que necesitás.',
            'includes' => ['Organización de secciones y contenido', 'Diseño adaptable a escritorio y celular', 'Presentación de servicios y vías de contacto'],
        ],
        // Diseño: contenido de la tarjeta de identidad visual y sus posibles entregables.
        [
            'id' => 'identidad-visual',
            'category' => 'Diseño',
            'name' => 'Identidad visual',
            'summary' => 'Un sistema visual para expresar quién sos y mantener una marca reconocible en cada aplicación.',
            'description' => 'Definimos una dirección visual coherente con la personalidad y los objetivos de tu marca. Las piezas y aplicaciones se acuerdan según las necesidades del proyecto.',
            'includes' => ['Dirección y concepto visual', 'Sistema de color y tipografía', 'Aplicaciones acordadas según el alcance'],
        ],
        // Marketing: contenido de campaña y aclaración sobre la inversión publicitaria.
        [
            'id' => 'campana-digital',
            'category' => 'Marketing',
            'name' => 'Campaña digital',
            'summary' => 'Una propuesta de comunicación para conectar con tu público alrededor de un objetivo concreto.',
            'description' => 'Organizamos el mensaje, los canales y las piezas de una campaña. La propuesta contempla objetivos, público y entregables; la inversión publicitaria se acuerda por separado cuando corresponda.',
            'includes' => ['Definición del objetivo y público', 'Propuesta de mensajes y canales', 'Plan de piezas y acciones según el alcance'],
        ],
    ],
];
