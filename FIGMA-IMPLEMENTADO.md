# Synaux · Pantallas y recorridos implementados

Referencia: [Prototipo de Figma Design](https://www.figma.com/design/nJAZnBNmIWnDQaTZA4eg3b/Prototipo).
La identidad usa los SVG verticales aprobados, documentados en `resources/images/brand/ORIGEN.md`.
Stack: Laravel, Inertia, Vue y Vuetify. Sin Tailwind. Código propio comentado en español por bloque lógico.

## Abrir el proyecto

- Presentación de marca: `http://127.0.0.1:8010/`
- Catálogo y aplicación: `http://127.0.0.1:8010/plataforma`
- Galería de las 65 pantallas/estados de Figma: `http://127.0.0.1:8010/prototipo/inicio`

La galería usa datos de ejemplo, lo indica en el encabezado y no guarda formularios ni inicia sesiones.
Solo está disponible en entornos `local` y `testing`. Los nombres y precios ilustrativos del prototipo no
se cargan como clientes, profesionales, trabajos ni tarifas reales. La aplicación operativa comienza con
sus registros existentes y el catálogo inicial de tres servicios.

## Recorridos disponibles

- Catálogo con búsqueda, categorías, detalles, portfolio, trabajo, nosotros y contacto.
- Registro, ingreso, salida, errores y recuperación/restablecimiento de contraseña.
- Estimador en cuatro pasos: servicio, preguntas, revisión y resultado. Conserva las respuestas al registrarse.
- Cuenta cliente: resumen, estimaciones guardadas, solicitudes privadas/públicas y borradores, publicaciones,
  propuestas, comparación, invitaciones, presupuestos con versiones y proyectos.
- Cuenta profesional: perfil, habilidades, disponibilidad, directorio, oportunidades, propuestas e invitaciones,
  presupuestos, seguimiento de proyectos y portfolio con carga de imágenes.
- Gestión: revisión de solicitudes, presupuestos, versiones, seguimiento, portfolio y mensajes de contacto.
- Administración: edición de servicios, preguntas/opciones, componentes, reglas y nuevas versiones de tarifas.

## Configuración inicial necesaria

1. Registrá tu cuenta desde la web. El registro público siempre crea una cuenta sin administración.
2. Para habilitar Gestión, ejecutá deliberadamente en esta carpeta:
   `C:\xampp8.2\php\php.exe artisan synaux:admin tu-correo@ejemplo.com`.
   El comando solo modifica una cuenta existente; no crea contraseñas ni administradores automáticos.
3. Completá el perfil profesional para identificar al emisor de presupuestos y publicar trabajos propios.
4. En Administración → Tarifario, cargá los valores reales y activá una versión completa. Mientras no exista,
   el estimador informa que el alcance necesita revisión profesional; nunca muestra un precio cero inventado.
5. Para enviar recuperaciones por correo configurá un servidor SMTP en `.env`. El transporte `log` de Laravel
   registra el mensaje localmente; no entrega un correo a la casilla del usuario.

Las consultas de contacto quedan guardadas en Gestión → Consultas. La confirmación indica que se registraron;
no afirma que se haya enviado un correo. Las invitaciones se consultan dentro de las cuentas.
Los pagos se coordinan entre las partes: esta implementación no procesa cobros.

## Persistencia y permisos

Las migraciones incorporan el DER V4 sin ejecutar `CREATE DATABASE` ni reemplazar usuarios. Se agregaron
instantáneas de respuestas, moneda e importes para conservar el historial. Las versiones enviadas no se
sobrescriben. Solo el cliente puede aceptar/rechazar la versión actual y vigente; la aceptación crea un único
proyecto y cierra la solicitud. Un profesional necesita una propuesta elegida para emitir un presupuesto.

Las imágenes JPG, PNG y WebP se guardan con nombres generados por Laravel y se sirven mediante `/media/portfolio/`
con permisos de lectura. No dependen de enlaces simbólicos, que pueden fallar en una unidad sincronizada.

## Organización del código

- `resources/js/Pages`: entradas de Inertia; `Screens`: familias de pantallas; `Components`: controles reutilizables.
- `resources/css/workspace*.css`: estilos del diseño y adaptación a celular.
- `app/Http/Controllers/Synaux*`: rutas de pantalla y formularios autorizados.
- `app/Services/Synaux*`: catálogo/cálculo, lecturas y versiones formales.
- `resources/reference/screens.json`: correspondencia de las 65 pantallas con sus nodos originales de Figma.
- `tests/Feature/SynauxFlowTest.php`: recorridos y permisos con SQLite en memoria.

Validación: `php artisan test`, `npm run build` y Laravel Pint para PHP. Las comprobaciones de esta entrega
usan SQLite; no se verificó una instalación MySQL/MariaDB. El respaldo previo a la aplicación está registrado
en la carpeta de trabajo de Codex, dentro de `output/synaux/backup-figma-20261020-170251`.
