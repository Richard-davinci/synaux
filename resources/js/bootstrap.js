// Cliente HTTP disponible para futuras consultas desde la interfaz.
import axios from 'axios';
window.axios = axios;

// Permite que Laravel identifique estas consultas como solicitudes realizadas por JavaScript.
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
