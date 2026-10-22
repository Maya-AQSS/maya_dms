// react-draggable@4.6.0 (transitiva vía react-grid-layout) lee
// process.env.DRAGGABLE_DEBUG sin guarda; en el navegador `process` no
// existe y lanza "process is not defined" al arrastrar/redimensionar los
// widgets del dashboard. Shim mínimo cargado antes que cualquier módulo.
//
// Va en un fichero propio (index.html lo carga con <script src>) y no en línea:
// la CSP de producción (`script-src 'self'`, MAYA_CSP del chart) bloquea los
// <script> en línea.
window.process = window.process || { env: {} };
