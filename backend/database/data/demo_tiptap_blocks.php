<?php

declare(strict_types=1);

/**
 * Bloques de la plantilla demo «DemoTipTap».
 *
 * Showcase de (casi) todas las funcionalidades del editor TipTap: marcas
 * (negrita, cursiva, subrayado, tachado, código en línea, color, resaltado,
 * enlace), bloques (párrafo, encabezados H1-H3, cita, bloque de código, regla
 * horizontal, listas de viñetas/numeradas/tareas, sangría, alineaciones,
 * tablas, imagen) y bloques de maquetación (portada, índice, hoja en blanco).
 *
 * Derivado y depurado de la plantilla viva «TestPlantilla Personal» capturada en
 * el snapshot del 2026-06-10. Limpieza aplicada para que sea portable entre slots:
 *   - portada: eliminada la región de imagen (apuntaba a media con token de slot);
 *   - imagen: `src` sustituido por un placeholder data-URI autocontenido;
 *   - enlaces: `href` reapuntado de URLs del slot a https://ceedcv.gva.es;
 *   - índice / hoja en blanco / bloques vacíos: sin cuerpo (default_content null);
 *   - contenido envuelto como documento Tiptap `{"type":"doc",...}`.
 *
 * El contenido es JSON puro ({@see demo_tiptap_blocks.json}) para no transcribir a
 * mano 33 documentos Tiptap ricos y perder fidelidad. El pack
 * {@see programaciones_didacticas_pack.php} asigna template_id + UUIDs de bloque.
 *
 * @return list<array{sort_order:int, title:string, block_type:string, block_state:string, page_break_after:bool, default_content:array<string,mixed>|null}>
 */
$json = file_get_contents(__DIR__.'/demo_tiptap_blocks.json');
$rows = $json !== false ? json_decode($json, true) : null;

return is_array($rows) ? $rows : [];
