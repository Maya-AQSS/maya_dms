export function TocBlockEditor() {
  return (
    <div className="flex-1 min-h-0 p-6 flex flex-col bg-ui-body/30 dark:bg-ui-dark-bg overflow-auto">
      <div className="mx-auto max-w-[21cm] w-full">
        <div className="w-[21cm] min-h-[29.7cm] mx-auto bg-white text-black border border-ui-border dark:border-ui-dark-border shadow-lg flex items-center justify-center p-8">
          <div className="text-center space-y-3">
            <div className="text-4xl opacity-20">≡</div>
            <p className="text-sm font-semibold text-text-secondary">
              Índice generado automáticamente
            </p>
            <p className="text-xs text-text-muted max-w-xs">
              Este bloque es no editable. Se generará automáticamente en el PDF a partir de los
              encabezados de los bloques de contenido.
            </p>
            <p className="text-xs text-text-muted max-w-xs mt-4 pt-4 border-t border-ui-border">
              Para modificar las entradas del índice, edita los headings de los bloques de contenido.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
