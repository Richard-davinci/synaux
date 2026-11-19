<script setup>
import SidePanel from '../UI/SidePanel.vue';
import { computed, ref, watch } from 'vue';
import { router, usePage, usePoll } from '@inertiajs/vue3';
import I from '../UI/AppIcon.vue';
import { date } from '../../support/formatters';

// La bandeja se actualiza sin recargar formularios; Inertia pausa la consulta cuando se desmonta.
const page = usePage();
const open = ref(false);
const busy = ref(false);
const notifications = computed(() => page.props.notifications ?? { unread_count: 0, items: [] });
usePoll(30000, { only: ['notifications'], preserveScroll: true, preserveState: true });
watch(() => page.url, () => { open.value = false; });

// Las escrituras solo marcan avisos propios; el servidor construye y protege los destinos de los detalles.
function read(item, navigate = false) {
    if (busy.value) return;
    if (item?.read_at && navigate) { open.value = false; router.visit(item.href); return; }
    busy.value = true;
    router.patch(item ? `/notificaciones/${item.id}/leer` : '/notificaciones/leer-todas', {}, {
        only: ['notifications'], preserveScroll: true, preserveState: true,
        onSuccess: () => { if (navigate) { open.value = false; router.visit(item.href); } },
        onFinish: () => { busy.value = false; },
    });
}
</script>

<template>
    <!-- El acceso está fuera del menú público para mantenerlo visible también en celulares. -->
    <button class="notification-trigger" type="button" :aria-label="`Notificaciones: ${notifications.unread_count} sin leer`" aria-haspopup="dialog" :aria-expanded="open" @click="open = true"><I name="bell" /><span v-if="notifications.unread_count" class="notification-count">{{ notifications.unread_count > 99 ? '99+' : notifications.unread_count }}</span></button>
    <SidePanel title="Actividad de tu cuenta" v-model="open" max-width="400" aria-labelledby="notification-center-title">
        <section class="notification-center">
            <header><div><h2 id="notification-center-title">Notificaciones</h2><p aria-live="polite">{{ notifications.unread_count ? `${notifications.unread_count} sin leer` : 'Estás al día' }}</p></div></header>
            <div v-if="notifications.unread_count" class="notification-toolbar"><button type="button" :disabled="busy" @click="read(null)">Marcar todas como leídas</button></div>
            <ul v-if="notifications.items.length" class="notification-list"><li v-for="item in notifications.items" :key="item.id" :class="{ unread: !item.read_at }"><a :href="item.href" :aria-disabled="busy" @click.prevent="read(item, true)"><div class="notification-item-title"><strong>{{ item.title }}</strong><span v-if="!item.read_at">Nuevo</span></div><p>{{ item.message }}</p><small>{{ date(item.created_at) }}</small></a><button v-if="!item.read_at" type="button" class="notification-mark" :disabled="busy" :aria-label="`Marcar como leída: ${item.title}, ${item.message}`" @click="read(item)"><I name="check" /></button></li></ul>
            <p v-else class="notification-empty">Todavía no hay notificaciones. Acá vas a recibir las novedades de tus solicitudes, presupuestos y proyectos.</p>
        </section>
    </SidePanel>
</template>

<style scoped>
/* El centro usa superficies claras, foco visible y desplazamiento interno en pantallas bajas. */
.notification-trigger, .notification-icon-button, .notification-mark { position: relative; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 44px; height: 44px; padding: 8px; border: 0; border-radius: 10px; background: transparent; color: #153b64; cursor: pointer; }
.notification-trigger:hover, .notification-icon-button:hover, .notification-mark:hover { background: #f5f9fa; }
.notification-trigger .icon, .notification-icon-button .icon, .notification-mark .icon { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 1.7; }
.notification-count { position: absolute; top: 0; right: 0; display: grid; place-items: center; min-width: 19px; height: 19px; padding: 0 4px; background: #153b64; color: #fff; border-radius: 12px; font-size: .65rem; font-weight: 800; }
.notification-center { max-height: 85dvh; overflow-y: auto; border-radius: 16px; background: #fff; color: #153b64; font-family: 'Nunito', sans-serif; }
.notification-center header { display: flex; justify-content: space-between; gap: 12px; padding: 24px 24px 12px; }
.notification-center h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem; }
.notification-center header p { margin: 4px 0 0; color: #446075; }
.notification-toolbar { padding: 0 24px 16px; border-bottom: 1px solid #1f6f7f22; }
.notification-toolbar button { min-height: 36px; border: 0; background: transparent; color: #1f6f7f; text-decoration: underline; cursor: pointer; font: inherit; }
.notification-list { padding: 0; margin: 0; list-style: none; }
.notification-list li { display: flex; align-items: center; border-bottom: 1px solid #1f6f7f22; padding-right: 12px; }
.notification-list li.unread { background: #2db3a30d; }
.notification-list a { flex: 1; min-width: 0; padding: 18px 24px; color: inherit; text-decoration: none; overflow-wrap: anywhere; }
.notification-item-title { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.notification-item-title span { font-size: .7rem; font-weight: 800; color: #1f6f7f; }
.notification-list p { margin: 5px 0; }
.notification-list small { color: #446075; }
.notification-empty { padding: 12px 24px 28px; color: #446075; }
button:focus-visible, a:focus-visible { outline: 3px solid #1f6f7f; outline-offset: -3px; }
button:disabled { opacity: .5; cursor: wait; }
</style>
