import Swal from 'sweetalert2';
import '../../css/alertas.css';

// Reúne la confirmación de borrado y su presentación; recibe el nombre visible y una acción asíncrona.
// La acción devuelve true si se borró, o el motivo que debe permanecer visible si el servidor lo impide.
export function mostrarConfirmacionEliminacion({entidad, nombre, eliminar}) {
    const articulo = entidad === 'categoría' ? 'esta' : 'este';
    return Swal.fire({
        title: `¿Eliminar ${articulo} ${entidad}?`,
        text: `${nombre}. El borrado es definitivo y solo está disponible si no tiene datos asociados.`,
        icon: 'warning',
        iconColor: '#a13b42',
        width: 440,
        showCancelButton: true,
        confirmButtonText: `Eliminar ${entidad}`,
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        showLoaderOnConfirm: true,
        buttonsStyling: false,
        customClass: {
            popup: 'synaux-alerta',
            title: 'synaux-alerta-titulo',
            htmlContainer: 'synaux-alerta-texto',
            actions: 'synaux-alerta-acciones',
            confirmButton: 'synaux-alerta-confirmar',
            cancelButton: 'synaux-alerta-cancelar',
            validationMessage: 'synaux-alerta-error',
        },
        allowOutsideClick: () => !Swal.isLoading(),
        allowEscapeKey: () => !Swal.isLoading(),
        preConfirm: async () => {
            try {
                const resultado = await eliminar();
                if (resultado === true) return true;
                Swal.showValidationMessage(resultado || `No se pudo eliminar ${entidad}. Intentá de nuevo.`);
            } catch {
                Swal.showValidationMessage(`No se pudo eliminar ${entidad}. Intentá de nuevo.`);
            }
            return false;
        },
    });
}
