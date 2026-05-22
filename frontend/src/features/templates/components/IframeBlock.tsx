import { useState } from "react";
import { createReactBlockSpec } from "@blocknote/react";
import { Button, TextInput } from '@maya/shared-ui-react';

export const createIframeBlock = createReactBlockSpec(
  {
    type: "iframe",
    propSchema: {
      url: { default: "" },
    },
    content: "inline",
  },
  {
    render: (props) => {
      const { url } = props.block.props;
      const [editing, setEditing] = useState(!url);
      const [tempUrl, setTempUrl] = useState(url);

      const saveChanges = () => {
        // Solo guardamos si es un enlace de YouTube válido
        if (tempUrl.startsWith("https://www.youtube.com/watch")) {
          props.editor.updateBlock(props.block, {
            type: "iframe",
            props: { url: tempUrl },
          });
          setEditing(false);
          props.editor.focus();
        } else {
          alert("Por favor ingresa una URL válida de YouTube (https://www.youtube.com/watch).");
        }
      };

      // Función para convertir la URL de YouTube a embed
      const getYouTubeEmbedUrl = (url) => {
        try {
          const urlObj = new URL(url);
          const videoId = urlObj.searchParams.get("v");
          return videoId ? `https://www.youtube.com/embed/${videoId}` : "";
        } catch {
          return "";
        }
      };

      return (
        <div className="bn-block-outer" contentEditable={false}>
          <div className="bn-block">
            {/* Botón de edición */}
            <div
              className="bn-iframe-edit-btn"
              onClick={() => setEditing(!editing)}
            >
              <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="8 9 3 12 8 15"></polyline>
                <polyline points="16 9 21 12 16 15"></polyline>
                <line x1="3" y1="12" x2="21" y2="12"></line>
              </svg>
            </div>

            {/* Panel de edición */}
            {editing && (
              <div className="bn-iframe-edit-panel">
                <TextInput
                  placeholder="URL de YouTube"
                  value={tempUrl}
                  onChange={(e) => setTempUrl(e.currentTarget.value)}
                />
                <Button
                type="button"
                variant="primary"
                size="md"
                className="flex-1"
                        onClick={saveChanges}>
                  Guardar
                </Button>
              </div>
            )}

            {/* Render del iframe solo si hay URL válida */}
            {url && (
              <iframe
                src={getYouTubeEmbedUrl(url)}
                width="560"
                height="315"
                className="bn-iframe"
                allowFullScreen
              />
            )}
          </div>
        </div>
      );
    },
  }
);