import { render, useState, useEffect, useRef } from "@wordpress/element";
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
  TabPanel,
} from "@wordpress/components";

// --- SEgédFÜGGVÉNYEK ---
const parseOptions = (optionsString) => {
  if (!optionsString) return [{ label: "Válassz...", value: "" }];
  const opts = optionsString.split(",").map((opt) => ({
    label: opt.trim(),
    value: opt.trim(),
  }));
  return [{ label: "Válassz...", value: "" }, ...opts];
};

// --- 1. WYSIWYG Komponens (KÍVÜLRE MOZGATVA) ---
const WysiwygControl = ({ label, fieldKey, value, isRequired, onChange }) => {
  const editorId = useRef(
    `wysiwyg_${fieldKey}_${Math.random().toString(36).substr(2, 9)}`,
  ).current;

  useEffect(() => {
    if (window.wp && window.wp.editor) {
      window.wp.editor.initialize(editorId, {
        tinymce: {
          setup: function (editor) {
            editor.on("Change KeyUp", function () {
              onChange(fieldKey, editor.getContent());
            });
          },
        },
        quicktags: true,
        mediaButtons: true,
      });
    }
    return () => {
      if (window.wp && window.wp.editor) {
        window.wp.editor.remove(editorId);
      }
    };
  }, []);

  return (
    <div style={{ marginBottom: "24px" }}>
      <p style={{ fontWeight: 600, marginBottom: "8px" }}>
        {label} {isRequired && <span style={{ color: "#d63638" }}>*</span>}
      </p>
      <textarea
        id={editorId}
        defaultValue={value || ""}
        style={{ width: "100%", minHeight: "200px" }}></textarea>
    </div>
  );
};

// --- 2. Links Komponens (KÍVÜLRE MOZGATVA) ---
const LinksControl = ({ label, fieldKey, value, isRequired, onChange }) => {
  const links = Array.isArray(value) ? value : [];

  const addLink = () => onChange(fieldKey, [...links, { title: "", url: "" }]);
  const removeLink = (index) =>
    onChange(
      fieldKey,
      links.filter((_, i) => i !== index),
    );
  const updateLink = (index, key, val) => {
    const newLinks = [...links];
    newLinks[index][key] = val;
    onChange(fieldKey, newLinks);
  };

  return (
    <div
      style={{
        marginBottom: "24px",
        background: "#f9f9f9",
        padding: "15px",
        border: "1px solid #ddd",
        borderRadius: "4px",
      }}>
      <p style={{ fontWeight: 600, marginBottom: "12px" }}>
        {label} {isRequired && <span style={{ color: "#d63638" }}>*</span>}
      </p>
      {links.map((link, index) => (
        <div
          key={index}
          style={{
            display: "flex",
            gap: "10px",
            marginBottom: "10px",
            alignItems: "center",
          }}>
          <div style={{ flex: 1 }}>
            <TextControl
              placeholder="Gomb szövege"
              value={link.title}
              onChange={(v) => updateLink(index, "title", v)}
              style={{ marginBottom: 0 }}
            />
          </div>
          <div style={{ flex: 2 }}>
            <TextControl
              placeholder="URL (https://...)"
              type="url"
              value={link.url}
              onChange={(v) => updateLink(index, "url", v)}
              style={{ marginBottom: 0 }}
            />
          </div>
          <Button isDestructive isSmall onClick={() => removeLink(index)}>
            X
          </Button>
        </div>
      ))}
      <Button isSecondary onClick={addLink} style={{ marginTop: "10px" }}>
        + Link hozzáadása
      </Button>
    </div>
  );
};

// --- 3. Repeater Komponens (KÍVÜLRE MOZGATVA) ---
const RepeaterControl = ({ label, field, value, isRequired, onChange }) => {
  const rows = Array.isArray(value) ? value : [];
  const subFields = field.sub_fields || [];

  const addRow = () => {
    const newRow = {};
    subFields.forEach((sf) => (newRow[sf.key] = ""));
    onChange(field.key, [...rows, newRow]);
  };
  const removeRow = (index) =>
    onChange(
      field.key,
      rows.filter((_, i) => i !== index),
    );
  const updateRow = (index, sfKey, val) => {
    const newRows = [...rows];
    newRows[index][sfKey] = val;
    onChange(field.key, newRows);
  };

  return (
    <div
      style={{
        marginBottom: "24px",
        background: "#fff",
        border: "1px solid #ccd0d4",
        borderRadius: "4px",
      }}>
      <div
        style={{
          padding: "10px 15px",
          background: "#f0f6fc",
          borderBottom: "1px solid #ccd0d4",
          fontWeight: 600,
        }}>
        {label} {isRequired && <span style={{ color: "#d63638" }}>*</span>}
      </div>
      <div style={{ padding: "15px", overflowX: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse" }}>
          <thead>
            <tr>
              {subFields.map((sf) => (
                <th
                  key={sf.key}
                  style={{
                    textAlign: "left",
                    padding: "8px",
                    borderBottom: "2px solid #ddd",
                    fontSize: "13px",
                  }}>
                  {sf.label}
                </th>
              ))}
              <th style={{ width: "40px" }}></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => (
              <tr key={index}>
                {subFields.map((sf) => (
                  <td
                    key={sf.key}
                    style={{ padding: "8px", borderBottom: "1px solid #eee" }}>
                    {sf.type === "boolean" ? (
                      <ToggleControl
                        checked={!!row[sf.key]}
                        onChange={(v) => updateRow(index, sf.key, v)}
                      />
                    ) : sf.type === "select" ? (
                      <SelectControl
                        value={row[sf.key] || ""}
                        options={parseOptions(sf.options)}
                        onChange={(v) => updateRow(index, sf.key, v)}
                        style={{ marginBottom: 0 }}
                      />
                    ) : (
                      <TextControl
                        type={
                          sf.type === "number"
                            ? "number"
                            : sf.type === "url"
                            ? "url"
                            : "text"
                        }
                        value={row[sf.key] || ""}
                        onChange={(v) => updateRow(index, sf.key, v)}
                        style={{ marginBottom: 0 }}
                      />
                    )}
                  </td>
                ))}
                <td
                  style={{
                    padding: "8px",
                    borderBottom: "1px solid #eee",
                    textAlign: "center",
                  }}>
                  <Button
                    isDestructive
                    isSmall
                    onClick={() => removeRow(index)}>
                    X
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <Button isSecondary onClick={addRow} style={{ marginTop: "10px" }}>
          + Sor hozzáadása
        </Button>
      </div>
    </div>
  );
};

// --- 4. Képfeltöltő Komponens (KÍVÜLRE MOZGATVA) ---
const ImageUploadControl = ({
  label,
  fieldKey,
  value,
  isRequired,
  onChange,
}) => {
  const openMediaUploader = () => {
    const wpMedia = window.wp.media({
      title: "Kép kiválasztása vagy feltöltése",
      button: { text: "Kép használata" },
      multiple: false,
    });
    wpMedia.on("select", () => {
      const attachment = wpMedia.state().get("selection").first().toJSON();
      onChange(fieldKey, attachment.url);
    });
    wpMedia.open();
  };

  return (
    <div style={{ marginBottom: "24px" }}>
      <p style={{ fontWeight: 600, marginBottom: "8px" }}>
        {label} {isRequired && <span style={{ color: "#d63638" }}>*</span>}
      </p>
      {value && (
        <div
          style={{
            marginBottom: "10px",
            border: "1px solid #ddd",
            padding: "5px",
            display: "inline-block",
          }}>
          <img
            src={value}
            alt="Preview"
            style={{ maxWidth: "200px", maxHeight: "150px", display: "block" }}
          />
        </div>
      )}
      <div>
        <Button isSecondary onClick={openMediaUploader}>
          {value ? "Kép cseréje" : "Kép feltöltése"}
        </Button>
        {value && (
          <Button
            isDestructive
            isLink
            onClick={() => onChange(fieldKey, "")}
            style={{ marginLeft: "10px" }}>
            Törlés
          </Button>
        )}
      </div>
    </div>
  );
};

// --- FŐ KOMPONENS ---
const SZEducateEditor = () => {
  const { postId, nonce, restUrl, schema, existingTitle, existingData } =
    window.szEducateData || {};

  const [title, setTitle] = useState(existingTitle || "");
  const [formData, setFormData] = useState(existingData || {});
  const [isSaving, setIsSaving] = useState(false);
  const [message, setMessage] = useState(null);

  const handleChange = (key, value) => {
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  const renderField = (field) => {
    const value = formData[field.key] || "";
    const requiredMark =
      field.is_required || field.is_locked ? (
        <span style={{ color: "#d63638" }}>*</span>
      ) : (
        ""
      );
    const labelWithRequired = (
      <>
        {field.label} {requiredMark}
      </>
    );

    switch (field.type) {
      case "text":
      case "number":
      case "date":
      case "url":
        return (
          <TextControl
            key={field.key}
            label={labelWithRequired}
            type={
              field.type === "date"
                ? "date"
                : field.type === "url"
                ? "url"
                : field.type
            }
            value={value}
            onChange={(val) => handleChange(field.key, val)}
            help={field.is_filterable ? "Indexelt mező." : ""}
          />
        );
      case "textarea":
        return (
          <TextareaControl
            key={field.key}
            label={labelWithRequired}
            value={value}
            onChange={(val) => handleChange(field.key, val)}
          />
        );
      case "wysiwyg":
        return (
          <WysiwygControl
            key={field.key}
            label={field.label}
            fieldKey={field.key}
            value={value}
            isRequired={field.is_required}
            onChange={handleChange}
          />
        );
      case "links":
        return (
          <LinksControl
            key={field.key}
            label={field.label}
            fieldKey={field.key}
            value={value}
            isRequired={field.is_required}
            onChange={handleChange}
          />
        );
      case "repeater":
        return (
          <RepeaterControl
            key={field.key}
            label={field.label}
            field={field}
            value={value}
            isRequired={field.is_required}
            onChange={handleChange}
          />
        );
      case "select":
      case "radio":
        return (
          <SelectControl
            key={field.key}
            label={labelWithRequired}
            value={value}
            options={parseOptions(field.options)}
            onChange={(val) => handleChange(field.key, val)}
          />
        );
      case "boolean":
        return (
          <ToggleControl
            key={field.key}
            label={labelWithRequired}
            checked={!!value}
            onChange={(val) => handleChange(field.key, val)}
          />
        );
      case "checkbox":
        const chkOptions = field.options
          ? field.options.split(",").map((o) => o.trim())
          : [];
        const selectedValues = Array.isArray(value)
          ? value
          : typeof value === "string" && value !== ""
          ? value.split(",").map((v) => v.trim())
          : [];
        return (
          <div key={field.key} style={{ marginBottom: "24px" }}>
            <p style={{ fontWeight: 600, marginBottom: "8px" }}>
              {labelWithRequired}
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
      case "image":
        return (
          <ImageUploadControl
            key={field.key}
            label={field.label}
            fieldKey={field.key}
            value={value}
            isRequired={field.is_required || field.is_locked}
            onChange={handleChange}
          />
        );
      default:
        return null;
    }
  };

  const validateForm = () => {
    if (!title || title.trim() === "")
      return "A Képzés Címe (Szak megnevezése) kötelező!";
    if (!formData["kepzesi_forma"])
      return "A Képzési Forma kiválasztása kötelező!";

    const activeFormat = formData["kepzesi_forma"];

    if (schema && schema.length > 0) {
      for (const group of schema) {
        if (
          group.group_id !== "alap_adatok" &&
          group.group_label !== activeFormat
        ) {
          let groupVisible = true;
          if (group.condition && group.condition.operator) {
            const c = group.condition;
            const targetVal = formData[c.field];
            const stringVal = Array.isArray(targetVal)
              ? targetVal.join(",")
              : String(targetVal || "");
            switch (c.operator) {
              case "==":
                groupVisible = stringVal === c.value;
                break;
              case "!=":
                groupVisible = stringVal !== c.value;
                break;
              case "not_empty":
                groupVisible = stringVal.trim() !== "";
                break;
              case "empty":
                groupVisible = stringVal.trim() === "";
                break;
              case "contains":
                groupVisible = stringVal.includes(c.value);
                break;
              default:
                groupVisible = true;
            }
          }
          if (!groupVisible) continue;
        }

        if (!group.fields) continue;
        for (const field of group.fields) {
          if (field.is_required || field.is_locked) {
            const val = formData[field.key];
            let isEmpty = false;

            if (val === undefined || val === null) {
              isEmpty = true;
            } else if (field.type === "repeater" || field.type === "links") {
              if (!Array.isArray(val) || val.length === 0) isEmpty = true;
            } else if (Array.isArray(val) && val.length === 0) {
              isEmpty = true;
            } else if (typeof val === "string" && val.trim() === "") {
              isEmpty = true;
            }

            if (isEmpty) {
              return `Kérlek töltsd ki a következő kötelező mezőt a(z) "${group.group_label}" fülön: ${field.label}`;
            }
          }
        }
      }
    }
    return null;
  };

  const handleSave = () => {
    const errorMsg = validateForm();
    if (errorMsg) {
      setMessage({ type: "error", text: errorMsg });
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    setIsSaving(true);
    setMessage(null);

    const processedData = { ...formData };
    for (const [key, val] of Object.entries(processedData)) {
      if (Array.isArray(val)) {
        if (val.length > 0 && typeof val[0] === "object") {
          processedData[key] = val;
        } else {
          processedData[key] = val.join(", ");
        }
      }
    }

    fetch(restUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
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
        window.scrollTo({ top: 0, behavior: "smooth" });
      })
      .catch((err) => {
        setMessage({ type: "error", text: "Kritikus hálózati hiba történt!" });
        setIsSaving(false);
      });
  };

  const buildTabs = () => {
    if (!schema || schema.length === 0) return [];

    const activeFormat = formData["kepzesi_forma"] || "";
    const fixedFormats = [
      "BSc",
      "MSc",
      "Osztatlan",
      "Felsőoktatási szakképzés",
      "Szakirányú továbbképzés",
      "Mikroképzés",
      "Előkészítő",
    ];

    return schema
      .filter((group) => {
        if (group.group_id === "alap_adatok") return true;
        if (fixedFormats.includes(group.group_label))
          return group.group_label === activeFormat;
        if (group.condition && group.condition.operator) {
          const c = group.condition;
          const targetVal = formData[c.field];
          const stringVal = Array.isArray(targetVal)
            ? targetVal.join(",")
            : String(targetVal || "");
          switch (c.operator) {
            case "==":
              return stringVal === c.value;
            case "!=":
              return stringVal !== c.value;
            case "not_empty":
              return stringVal.trim() !== "";
            case "empty":
              return stringVal.trim() === "";
            case "contains":
              return stringVal.includes(c.value);
            default:
              return true;
          }
        }
        return true;
      })
      .map((group) => ({
        name: group.group_id,
        title: group.group_label,
        className: "szeducate-tab-" + group.group_id,
        fields: group.fields,
      }));
  };

  return (
    <div
      className="szeducate-react-wrapper"
      style={{ maxWidth: "1000px", margin: "0 auto" }}>
      {message && (
        <Notice
          status={message.type}
          isDismissible={false}
          style={{ marginBottom: "20px" }}>
          {message.text}
        </Notice>
      )}

      <Panel header="SZEducate Képzés Szerkesztő">
        {schema && schema.length > 0 ? (
          <div style={{ background: "#fff", border: "1px solid #e2e4e7" }}>
            <TabPanel
              className="szeducate-tabs"
              activeClass="is-active"
              tabs={buildTabs()}>
              {(tab) => (
                <div style={{ padding: "20px" }}>
                  {tab.name === "alap_adatok" && (
                    <div
                      style={{
                        marginBottom: "24px",
                        paddingBottom: "24px",
                        borderBottom: "1px solid #eee",
                      }}>
                      <TextControl
                        label={
                          <>
                            Képzés Címe (Szak megnevezése){" "}
                            <span style={{ color: "#d63638" }}>*</span>
                          </>
                        }
                        value={title}
                        onChange={(value) => setTitle(value)}
                      />
                    </div>
                  )}
                  {tab.fields && tab.fields.map((field) => renderField(field))}
                </div>
              )}
            </TabPanel>
          </div>
        ) : (
          <Notice
            status="warning"
            isDismissible={false}
            style={{ marginTop: "20px" }}>
            Hiányzó séma! Kérlek szinkronizálj a Hubbal a Beállításokban.
          </Notice>
        )}
      </Panel>

      <div
        style={{
          marginTop: "20px",
          padding: "20px",
          background: "#fff",
          border: "1px solid #e2e4e7",
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
        }}>
        <span style={{ fontSize: "12px", color: "#666" }}>
          A <span style={{ color: "#d63638" }}>*</span>-gal jelölt mezők
          kitöltése kötelező.
        </span>
        <Button
          isPrimary
          isBusy={isSaving}
          onClick={handleSave}
          style={{ padding: "5px 30px", backgroundColor: "#007cba" }}>
          {isSaving ? (
            <>
              <Spinner /> Mentés...
            </>
          ) : (
            "Véglegesítés és Mentés"
          )}
        </Button>
      </div>
    </div>
  );
};

document.addEventListener("DOMContentLoaded", () => {
  const rootElement = document.getElementById("szeducate-react-root");
  if (rootElement) {
    render(<SZEducateEditor />, rootElement);
  }
});
