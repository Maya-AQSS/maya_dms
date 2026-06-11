import { apiErrorFromResponse, buildApiUrl, getBearerToken } from './http';

/**
 * Descarga binaria autenticada: fetch + blob + `<a>` sintético.
 *
 * El JWT viaja en el header `Authorization`, por eso no sirve un `<a href>`
 * directo al endpoint. Patrón único para todas las descargas PDF del proyecto
 * (plantilla, versión histórica de plantilla y documento) — las tres copias
 * anteriores eran byte-idénticas en manejo de errores y headers.
 *
 * @param apiPath Ruta relativa al API (los segmentos dinámicos ya codificados).
 * @param filename Nombre del fichero; se fuerza la extensión `.pdf` si falta
 *                 (hoy todas las descargas autenticadas son PDF).
 */
export async function downloadAuthenticatedBlob(apiPath: string, filename: string): Promise<void> {
  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(apiPath), {
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  });
  if (!response.ok) {
    throw await apiErrorFromResponse(response);
  }
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  try {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
  } finally {
    URL.revokeObjectURL(url);
  }
}
