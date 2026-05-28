import { render, useState } from "@wordpress/element";
import {
  Panel,
  PanelBody,
  TextControl,
  TextareaControl,
  SelectControl,
  CheckboxControl,
  ToggleControl,
  Button,
  Spinner,
  Notice,
} from "@wordpress/components";

const SZEducateEditor = () => {
  // 1. Kinyerjük a betöltött adatokat a window objektumból
  const { postId, nonce, restUrl, schema, existingTitle, existingData } =
    window.szEducateData || {};

  // 2. Beállítjuk a kezdőértékeket (Initial State)
  const [title, setTitle] = useState(existingTitle || "");
  const [formData, setFormData] = useState(existingData || {});

  const [isSaving, setIsSaving] = useState(false);
  const [message, setMessage] = useState(null);

  const handleChange = (key, value) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  const parseOptions = (optionsString) => {
    if (!optionsString) return [{ label: "Válassz...", value: "" }];
    const opts = optionsString.split(",").map((opt) => ({
      label: opt.trim(),
      value: opt.trim(),
    }));
    return [{ label: "Válassz...", value: "" }, ...opts];
  };

  const renderField = (field) => {
    const value = formData[field.key] || "";

    switch (field.type) {
      case "text":
      case "number":
      case "date":
        return (
          <TextControl
            key={field.key}
            label={field.label}
            type={field.type === "date" ? "date" : field.type}
            value={value}
            onChange={(val) => handleChange(field.key, val)}
            help={field.is_filterable ? "Ez egy szűrhető mező." : ""}
          />
        );
      case "textarea":
        return (
          <TextareaControl
            key={field.key}
            label={field.label}
            value={value}
            onChange={(val) => handleChange(field.key, val)}
          />
        );
      case "select":
      case "radio":
        return (
          <SelectControl
            key={field.key}
            label={field.label}
            value={value}
            options={parseOptions(field.options)}
            onChange={(val) => handleChange(field.key, val)}
          />
        );
      case "boolean":
        return (
          <ToggleControl
            key={field.key}
            label={field.label}
            checked={!!value}
            onChange={(val) => handleChange(field.key, val)}
          />
        );
      case "checkbox":
        const chkOptions = field.options
          ? field.options.split(",").map((o) => o.trim())
          : [];
        // Betöltéskor a DB-ben stringként ("Opció1, Opció2") szerepelhet, ezt vissza kell alakítani tömbbé
        const selectedValues = Array.isArray(value)
          ? value
          : typeof value === "string" && value !== ""
          ? value.split(",").map((v) => v.trim())
          : [];

        return (
          <div key={field.key} style={{ marginBottom: "24px" }}>
            <p style={{ fontWeight: 600, marginBottom: "8px" }}>
              {field.label}
            </p>
            {chkOptions.map((opt) => (
              <CheckboxControl
                key={opt}
                label={opt}
                checked={selectedValues.includes(opt)}
                onChange={(isChecked) => {
                  const newVal = isChecked
                    ? [...selectedValues, opt]
                    : selectedValues.filter((v) => v !== opt);
                  handleChange(field.key, newVal);
                }}
              />
            ))}
          </div>
        );
      default:
        return null;
    }
  };

  const handleSave = () => {
    setIsSaving(true);
    setMessage(null);

    const processedData = { ...formData };
    for (const [key, val] of Object.entries(processedData)) {
      if (Array.isArray(val)) {
        processedData[key] = val.join(", ");
      }
    }

    fetch(restUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": nonce,
      },
      body: JSON.stringify({
        local_post_id: postId,
        title: title,
        course_data: processedData,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          setMessage({ type: "success", text: data.message });
        } else {
          setMessage({ type: "error", text: data.message || data.code });
        }
        setIsSaving(false);
      })
      .catch((err) => {
        setMessage({ type: "error", text: "Kritikus hálózati hiba történt!" });
        setIsSaving(false);
      });
  };

  return (
    <div className="szeducate-react-wrapper">
      <Panel header="SZEducate Képzés Szerkesztő">
        {message && (
          <Notice status={message.type} isDismissible={false}>
            {message.text}
          </Notice>
        )}

        <PanelBody title="1. Alap Adatok (Kötelező)" initialOpen={true}>
          <TextControl
            label="Képzés Címe (Szak megnevezése)"
            value={title}
            onChange={(value) => setTitle(value)}
            placeholder="pl. Mérnökinformatikus BSc"
            required
          />
        </PanelBody>

        {schema && schema.length > 0 ? (
          schema.map((group, index) => (
            <PanelBody
              key={group.group_id}
              title={`2.${index + 1} ${group.group_label}`}
              initialOpen={false}>
              {group.fields && group.fields.map((field) => renderField(field))}
            </PanelBody>
          ))
        ) : (
          <PanelBody title="Hiányzó Séma!">
            <p>
              A Kliens még nem töltötte le az adatbázis sémát. Kérlek, menj a
              beállításokba és szinkronizálj a Hub-bal!
            </p>
          </PanelBody>
        )}

        <PanelBody title="Mentés és Szinkronizáció" initialOpen={true}>
          <Button
            isPrimary
            isBusy={isSaving}
            disabled={isSaving || title.length === 0}
            onClick={handleSave}
            style={{ padding: "5px 30px" }}>
            {isSaving ? (
              <>
                <Spinner /> Szinkronizálás a központtal...
              </>
            ) : (
              "Véglegesítés és Mentés"
            )}
          </Button>
        </PanelBody>
      </Panel>
    </div>
  );
};

document.addEventListener("DOMContentLoaded", () => {
  const rootElement = document.getElementById("szeducate-react-root");
  if (rootElement) {
    render(<SZEducateEditor />, rootElement);
  }
});
