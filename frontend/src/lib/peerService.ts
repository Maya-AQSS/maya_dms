/**
 * Convención Maya: cada servicio se sirve en
 *   `<slot-prefix>-<service-name>.<domain-suffix>`
 *
 * Ejemplo: con `window.location.hostname = "desarrollo-ceedcv-dms.192.168.2.1.nip.io"`,
 *   peerOrigin('dashboard-api') → "https://desarrollo-ceedcv-dashboard-api.192.168.2.1.nip.io"
 *
 * Este helper deriva el origen de un servicio hermano (mismo slot/dominio)
 * desde el hostname actual del navegador. Es resiliente a cualquier slot/IP
 * y elimina la necesidad de hardcodear URLs como `*.maya.test`.
 */
export function peerOrigin(targetService: string): string {
  const { protocol, hostname } = window.location;

  // Fallback para entornos sin sub-dominio (ej. 'localhost')
  const firstDot = hostname.indexOf('.');
  if (firstDot === -1) {
    return `${protocol}//${hostname}`;
  }

  const firstSegment = hostname.substring(0, firstDot); // 'desarrollo-ceedcv-dms'
  const domainSuffix = hostname.substring(firstDot); // '.192.168.2.1.nip.io'

  // El nombre de servicio actual es el último token tras el último `-` del primer segmento.
  // Lo que está antes es el slot-prefix (puede estar vacío si no hay prefijo).
  const lastDash = firstSegment.lastIndexOf('-');
  const slotPrefix = lastDash !== -1 ? firstSegment.substring(0, lastDash + 1) : '';

  return `${protocol}//${slotPrefix}${targetService}${domainSuffix}`;
}

/**
 * Resuelve la URL de un servicio hermano: si la env var override (`VITE_<KEY>`)
 * está definida y no vacía, se prefiere; en caso contrario se deriva del hostname.
 */
export function resolveServiceUrl(envValue: string | undefined, targetService: string): string {
  const trimmed = envValue?.trim();
  if (trimmed) return trimmed.replace(/\/$/, '');
  return peerOrigin(targetService);
}
