<script setup>
import {ref, watch} from 'vue';
import AppIcon from '../UI/AppIcon.vue';

// Cada navegación o respuesta nueva vuelve a mostrar el aviso; cerrarlo no borra errores del formulario.
const props = defineProps({message: String, errors: Object, pageKey: String});
const emit = defineEmits(['close-message']);
const messageClosed = ref(false);
const errorsClosed = ref(false);
watch([() => props.message, () => props.errors, () => props.pageKey], () => {
    messageClosed.value = false;
    errorsClosed.value = false;
}, {deep: true});

function closeMessage() {
    messageClosed.value = true;
    emit('close-message');
}
</script>

<template>
    <!-- Los avisos permanecen visibles al desplazarse y hasta que la persona decide cerrarlos. -->
    <div class="synaux-notifications">
        <section v-if="message && !messageClosed" class="synaux-notification" role="status" aria-live="polite"
                 aria-atomic="true">
            <span class="notification-symbol"><AppIcon name="check"/></span>
            <div class="notification-copy"><strong>Operación realizada</strong>
                <p>{{ message }}</p></div>
            <button type="button" class="notification-close" aria-label="Cerrar notificación" @click="closeMessage">
                <AppIcon name="close"/>
            </button>
        </section>
        <section v-if="Object.keys(errors ?? {}).length && !errorsClosed" class="synaux-notification notification-error"
                 role="alert" aria-atomic="true">
            <span class="notification-symbol"><AppIcon name="info"/></span>
            <div class="notification-copy"><strong>Revisá los datos antes de continuar</strong>
                <ul>
                    <li v-for="(error, key) in errors" :key="key">{{ error }}</li>
                </ul>
            </div>
            <button type="button" class="notification-close" aria-label="Cerrar aviso de errores"
                    @click="errorsClosed = true">
                <AppIcon name="close"/>
            </button>
        </section>
    </div>
</template>

<style scoped>
/* La tarjeta tiene contraste propio y un ancho acotado para leerse sobre cualquier pantalla. */
.synaux-notifications {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 120;
    width: min(460px, calc(100vw - 48px));
    display: grid;
    gap: 12px;
    pointer-events: none;
}

.synaux-notification {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 18px 12px 18px 18px;
    border: 1px solid #2db3a3;
    border-left: 5px solid #2db3a3;
    border-radius: 14px;
    background: #153b64;
    color: #fff;
    box-shadow: 0 10px 32px #153b6433;
    pointer-events: auto;
}

.notification-symbol {
    display: grid;
    place-items: center;
    flex: 0 0 30px;
    height: 30px;
    border: 1px solid #ffffff66;
    border-radius: 50%;
}

.notification-symbol .icon {
    width: 20px;
    height: 20px;
    color: inherit;
}

.notification-copy {
    flex: 1;
    min-width: 0;
    max-height: min(50vh, 360px);
    overflow-y: auto;
    overflow-wrap: anywhere;
}

.notification-copy strong {
    display: block;
    font-size: 1rem;
    line-height: 1.4;
    color: inherit;
}

.notification-copy p, .notification-copy ul {
    margin: 6px 0 0;
    color: inherit;
    font-size: 1rem;
    line-height: 1.5;
}

.notification-copy ul {
    padding-left: 18px;
}

.notification-copy li + li {
    margin-top: 6px;
}

.notification-close {
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    margin-top: -7px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: inherit;
    cursor: pointer;
}

.notification-close .icon {
    width: 22px;
    height: 22px;
}

.notification-close:hover {
    background: #ffffff22;
}

.notification-close:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 2px;
}

.notification-error {
    background: #fff;
    color: #153b64;
    border-color: #a13b42;
}

.notification-error .notification-symbol {
    color: #a13b42;
    border-color: #a13b42;
}

.notification-error .notification-close:hover {
    background: #153b6411;
}

@media (max-width: 600px) {
    .synaux-notifications {
        right: 12px;
        bottom: max(12px, env(safe-area-inset-bottom));
        width: calc(100vw - 24px);
    }
}
</style>
