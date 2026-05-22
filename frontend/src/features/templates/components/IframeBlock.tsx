import { useState } from "react";
import { createReactBlockSpec } from "@blocknote/react";
import { MdLink, MdEdit } from "react-icons/md";
import "./blocknote-panel.css";

export const createIframeBlock = createReactBlockSpec(
  {
    type: "iframe",
    propSchema: {
      url: { default: "https://example.com" },
      width: { default: "100%" },
      height: { default: "400px" },
    },
    content: "inline",
  },
  {
    render: (props) => {
      const { url, width, height } = props.block.props;
      const [editing, setEditing] = useState(false);
      const [tempUrl, setTempUrl] = useState(url);
      const [tempWidth, setTempWidth] = useState(width);
      const [tempHeight, setTempHeight] = useState(height);

      const saveChanges = () => {
        props.editor.updateBlock(props.block, {
          type: "iframe",
          props: { url: tempUrl, width: tempWidth, height: tempHeight },
        });
        setEditing(false);
        props.editor.focus();
      };

      return (
        <div className="bn-block-outer" contentEditable={false}>
          <div className="bn-block">
            {/* Botón de edición */}
            <div
              className="bn-iframe-edit-btn"
              onClick={() => setEditing(!editing)}
            >
              <MdLink size={24} />
            </div>

            {/* Panel de edición */}
            {editing && (
              <div className="bn-iframe-edit-panel">
                <input
                  className="bn-iframe-input"
                  type="text"
                  placeholder="URL del iframe"
                  value={tempUrl}
                  onChange={(e) => setTempUrl(e.currentTarget.value)}
                />
                <input
                  className="bn-iframe-input"
                  type="text"
                  placeholder="Ancho (ej: 100%)"
                  value={tempWidth}
                  onChange={(e) => setTempWidth(e.currentTarget.value)}
                />
                <input
                  className="bn-iframe-input"
                  type="text"
                  placeholder="Alto (ej: 400px)"
                  value={tempHeight}
                  onChange={(e) => setTempHeight(e.currentTarget.value)}
                />
                <button className="bn-iframe-save-btn" onClick={saveChanges}>
                  <MdEdit style={{ verticalAlign: "middle", marginRight: 4 }} />
                  Guardar
                </button>
              </div>
            )}

            {/* Render del iframe */}
            <iframe
              src={url}
              width={width}
              height={height}
              className="bn-iframe"
              allowFullScreen
            />
          </div>
        </div>
      );
    },
  }
);