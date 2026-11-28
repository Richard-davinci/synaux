// Separa la presentación de cada fila de los campos que necesita guardar el servidor.
export function prepararImporte(id, nombre, importe) {
    const min = importe?.price_min ?? '';
    const max = importe?.price_max ?? '';
    return {id, nombre, min, max, fijo: min === '' || Number(min) === Number(max)};
}

export function datosDeImportes(precios) {
    return Object.fromEntries(Object.entries(precios).map(([grupo, filas]) => [
        grupo, filas.map(({id, min, max}) => ({id, min, max})),
    ]));
}
