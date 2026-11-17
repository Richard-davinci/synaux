# Desarrollo local de Synaux

Trabajar en esta carpeta `synaux`; la carpeta hermana `synaux-base` se conserva como referencia.
Stack: Laravel 12, Inertia 3, Vue 3 y Vuetify 4, con CSS propio y sin Tailwind.

## Iniciar

En dos terminales, desde esta carpeta:

```powershell
C:\xampp8.2\php\php.exe artisan serve --host=127.0.0.1 --port=8010
```

```powershell
npm.cmd run dev
```

Abrir `http://127.0.0.1:8010/`. La landing presenta la marca; `/plataforma` abre el catálogo.
Si ya están ejecutándose los servidores, basta con recargar el navegador.
En esta computadora se usa `npm.cmd` por la resolución de `npm` en PowerShell.

Al copiar a otra computadora: `composer install`, `npm.cmd ci`, preparar `.env` sin compartir credenciales,
ejecutar `php artisan migrate` y `php artisan db:seed --class=SynauxCatalogSeeder`.
El seeder incorpora contenido inicial sin crear cuentas ni tarifas de ejemplo.

## Diseño y marca

Referencia vigente: [Figma Design · Prototipo](https://www.figma.com/design/nJAZnBNmIWnDQaTZA4eg3b/Prototipo).
Se implementaron sus familias de pantallas públicas, cliente, profesional, gestión y administración.
La galería `/prototipo/inicio` permite recorrer las 65 vistas/estados con datos aislados de referencia.

`BrandLogo.vue` usa los originales verticales aprobados el 20 de octubre de 2026: sin eslogan a 100 px
en navegación; con «Creciendo juntos» a 360 px en la presentación principal y 200 px en los pies.
Los pies mantienen el fondo blanco y la marca original a color. Los SVG no se reconstruyen, recolorean ni
giran. Su procedencia está en `resources/images/brand/ORIGEN.md`. Los originales horizontales de Recursos
finales se conservan como referencia histórica.

Paleta: `#153B64`, `#1F6F7F`, `#2DB3A3`, `#FFFFFF`, `#F5F9FA` y `#446075`.
Space Grotesk para títulos y Nunito para lectura; ambas fuentes se sirven localmente con sus licencias OFL.

## Código y datos

- `resources/js/Pages`: entradas de Inertia; `Screens`: pantallas agrupadas por recorrido.
- `resources/js/Components`: controles y marca reutilizables.
- `resources/css/workspace.css` y `workspace-overrides.css`: composición de Figma y adaptación móvil.
- `routes/web.php`, controladores `Synaux*` y servicios `Synaux*`: datos, validación y permisos del servidor.
- `database/migrations`: DER V4 e instantáneas necesarias para los recorridos.
- `database/seeders/SynauxCatalogSeeder.php`: catálogo inicial y preguntas; no incluye precios ficticios.
- `resources/reference/screens.json`: inventario de pantallas con sus nodos de Figma.

El catálogo operativo ahora consulta la base de datos. El archivo anterior `config/catalog.php` queda como
referencia y no controla las pantallas nuevas. Las versiones enviadas conservan su historial, y solo la versión
vigente puede aceptarse para crear un proyecto. La galería local no concede roles ni guarda formularios reales.

El código propio lleva comentarios explicativos en español por bloque lógico. Esta convención está registrada
en `AGENTS.md`; no se agregan comentarios dentro de bibliotecas de terceros ni SVG originales.

## Configuración y comprobaciones

Ver [FIGMA-IMPLEMENTADO.md](FIGMA-IMPLEMENTADO.md) para la configuración de tu cuenta de gestión, perfil
profesional, tarifas reales y entrega de correos. La aplicación no crea administradores automáticamente.
Las consultas de contacto se consultan desde `/gestion/contactos` con una cuenta autorizada.

```powershell
npm.cmd run build
C:\xampp8.2\php\php.exe artisan test
```

Las pruebas usan SQLite en memoria y no borran la base local. Antes de aplicar esta implementación se guardó
una copia de los archivos reemplazados y un respaldo consistente de SQLite en la carpeta de trabajo de Codex.
