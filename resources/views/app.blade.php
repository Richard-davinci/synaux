<!DOCTYPE html>
<html lang="es">
<head>
    {{-- Codificación y escala inicial para mostrar correctamente el español y las pantallas móviles. --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Carga la entrada de Vue y los estilos mediante Vite, en desarrollo o en la versión compilada. --}}
    @vite('resources/js/app.js')
    {{-- Metadatos de respaldo; cada página puede definir su propio título y descripción con Head. --}}
    <x-inertia::head>
        <title>synaux</title>
        <meta name="description"
              content="Servicios digitales de synaux: definí tu necesidad y conocé cómo avanzar con tu proyecto.">
    </x-inertia::head>
</head>
<body>
{{-- Punto de montaje donde Inertia entrega a Vue la página y sus datos iniciales. --}}
<x-inertia::app/>
{{-- Mensaje alternativo cuando el navegador tiene JavaScript desactivado. --}}
<noscript>Activá JavaScript para acceder a las pantallas de synaux.</noscript>
</body>
</html>
