import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';

// Comprueba el contrato HTML del campo compartido sin abrir cuentas ni modificar la base de datos.
const server = await createServer({ configFile:false, root:fileURLToPath(new URL('../../', import.meta.url)), plugins:[vue()], ssr:{noExternal:['vuetify']}, server:{middlewareMode:true}, appType:'custom' });
after(() => server.close());
const { default: Field } = await server.ssrLoadModule('/resources/js/Components/SynauxField.vue');
const { createVuetify } = await server.ssrLoadModule('vuetify');
const render = props => renderToString(createSSRApp({render:() => h(Field, props)}).use(createVuetify()));

test('la contraseña comienza oculta y conserva los atributos de validación y autocompletado', async () => {
    const html = await render({label:'Contraseña', type:'password', modelValue:'ejemplo', required:true, minlength:8, autocomplete:'new-password'});
    const input = html.match(/<input[^>]+type="password"[^>]*>/)?.[0];
    assert.ok(input);
    assert.match(input, /required/);
    assert.match(input, /minlength="8"/);
    assert.match(input, /autocomplete="new-password"/);
    assert.match(html, /Mostrar contraseña/);
    assert.match(html, /v-input--density-compact/);
});

test('un selector requerido conserva el testigo de validación sin confundir cero con vacío', async () => {
    const empty = await render({label:'Categoría', type:'select', modelValue:'', options:[{value:0,label:'Sin costo'}], required:true});
    const selected = await render({label:'Categoría', type:'select', modelValue:0, options:[{value:0,label:'Sin costo'}], required:true});
    const proxy = html => html.match(/<input[^>]+class="select-validity-proxy"[^>]*>/)?.[0];
    assert.match(proxy(empty), /required/);
    assert.match(proxy(empty), /value=""/);
    assert.match(proxy(selected), /value="selected"/);
    assert.match(selected, /Sin costo/);
});

test('los límites numéricos y los errores del servidor siguen asociados al control', async () => {
    const html = await render({label:'Cantidad', type:'number', modelValue:0, min:0, max:10, step:0.5, error:'Revisá la cantidad'});
    const input = html.match(/<input[^>]+type="number"[^>]*>/)?.[0];
    assert.match(input, /min="0"/);
    assert.match(input, /max="10"/);
    assert.match(input, /step="0.5"/);
    assert.match(input, /value="0"/);
    assert.match(input, /aria-describedby="[^"]+-help"/);
    assert.match(html, /role="alert"/);
    assert.match(html, /Revisá la cantidad/);
});

test('el área de texto conserva su contenido y validación obligatoria', async () => {
    const html = await render({label:'Descripción', type:'textarea', modelValue:'Contenido de prueba', required:true, maxlength:140});
    assert.match(html, /<textarea[^>]+required/);
    assert.match(html, /maxlength="140"/);
    assert.match(html, /Contenido de prueba/);
});
