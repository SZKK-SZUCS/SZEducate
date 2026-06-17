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

const parseOptions = (optionsString) => {
  if (!optionsString) return [{ label: "Válassz...", value: "" }];
  const opts = optionsString
    .split(",")
    .map((opt) => ({ label: opt.trim(), value: opt.trim() }));
  return [{ label: "Válassz...", value: "" }, ...opts];
};

const HelpTextUi = ({ text }) => {
  if (!text) return null;
  return (
    <p
      style={{
        fontSize: "12px",
        color: "#646970",
        marginTop: "4px",
        marginBottom: "10px",
        fontStyle: "italic",
        lineHeight: "1.4",
      }}>
      {text}
    </p>
  );
};

const WysiwygControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange,
}) => {
  const editorId = useRef(
    `wysiwyg_${fieldKey}_${Math.random().toString(36).substr(2, 9)}`,
  ).current;
  useEffect(() => {
    if (window.wp && window.wp.editor) {
      window.wp.editor.initialize(editorId, {
        tinymce: {
          readonly: isReadonly ? 1 : 0,
          setup: function (editor) {
            editor.on("Change KeyUp", function () {
              if (!isReadonly) onChange(fieldKey, editor.getContent());
            });
          },
        },
        quicktags: !isReadonly,
        mediaButtons: !isReadonly,
      });
    }
    return () => {
      if (window.wp && window.wp.editor) window.wp.editor.remove(editorId);
    };
  }, []);
  return (
    <div
      style={{
        opacity: isReadonly ? 0.7 : 1,
        pointerEvents: isReadonly ? "none" : "auto",
      }}>
      <div style={{ marginBottom: "4px" }}>{label}</div>
      <HelpTextUi text={helpText} />
      <textarea
        id={editorId}
        defaultValue={value || ""}
        style={{ width: "100%", minHeight: "200px" }}
        disabled={isReadonly}></textarea>
    </div>
  );
};

const LinksControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange,
}) => {
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
        background: isReadonly ? "#f0f0f0" : "#f9f9f9",
        padding: "15px",
        border: "1px solid #ddd",
        borderRadius: "4px",
      }}>
      <div style={{ marginBottom: "4px" }}>{label}</div>
      <HelpTextUi text={helpText} />
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
              disabled={isReadonly}
              style={{ marginBottom: 0 }}
            />
          </div>
          <div style={{ flex: 2 }}>
            <TextControl
              placeholder="URL (https://...)"
              type="url"
              value={link.url}
              onChange={(v) => updateLink(index, "url", v)}
              disabled={isReadonly}
              style={{ marginBottom: 0 }}
            />
          </div>
          {!isReadonly && (
            <Button isDestructive isSmall onClick={() => removeLink(index)}>
              X
            </Button>
          )}
        </div>
      ))}
      {!isReadonly && (
        <Button isSecondary onClick={addLink} style={{ marginTop: "10px" }}>
          + Link hozzáadása
        </Button>
      )}
    </div>
  );
};

const RepeaterControl = ({
  label,
  field,
  value,
  isReadonly,
  helpText,
  onChange,
}) => {
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
        background: "#fff",
        border: "1px solid #ccd0d4",
        borderRadius: "4px",
        opacity: isReadonly ? 0.7 : 1,
      }}>
      <div
        style={{
          padding: "10px 15px",
          background: "#f0f6fc",
          borderBottom: "1px solid #ccd0d4",
        }}>
        <div>{label}</div>
        <HelpTextUi text={helpText} />
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
              {!isReadonly && <th style={{ width: "40px" }}></th>}
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
                        disabled={isReadonly}
                      />
                    ) : sf.type === "select" ? (
                      <SelectControl
                        value={row[sf.key] || ""}
                        options={parseOptions(sf.options)}
                        onChange={(v) => updateRow(index, sf.key, v)}
                        disabled={isReadonly}
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
                        disabled={isReadonly}
                        style={{ marginBottom: 0 }}
                      />
                    )}
                  </td>
                ))}
                {!isReadonly && (
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
                )}
              </tr>
            ))}
          </tbody>
        </table>
        {!isReadonly && (
          <Button isSecondary onClick={addRow} style={{ marginTop: "10px" }}>
            + Sor hozzáadása
          </Button>
        )}
      </div>
    </div>
  );
};

const ImageUploadControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange,
}) => {
  const openMediaUploader = () => {
    if (isReadonly) return;
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
    <div style={{ opacity: isReadonly ? 0.7 : 1 }}>
      <div style={{ marginBottom: "4px" }}>{label}</div>
      <HelpTextUi text={helpText} />
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
      {!isReadonly && (
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
      )}
    </div>
  );
};

const SZEducateEditor = () => {
  const {
    postId,
    nonce,
    restUrl,
    schema,
    permissions,
    existingTitle,
    existingData,
  } = window.szEducateData || {};

  const [title, setTitle] = useState(existingTitle || "");
  const [formData, setFormData] = useState(existingData || {});
  const [isSaving, setIsSaving] = useState(false);
  const [message, setMessage] = useState(null);

  const actions = permissions?.actions || {
    create: true,
    edit: true,
    delete: false,
  };
  const isNewPost = !existingTitle;
  const globalReadonly = !isNewPost && !actions.edit;
  const canSave = isNewPost ? actions.create : actions.edit;

  useEffect(() => {
    if (!schema || !existingData) return;
    let needsMigration = false;
    const migratedData = { ...existingData };

    schema.forEach((group) => {
      if (group.fields) {
        group.fields.forEach((field) => {
          const val = migratedData[field.key];
          if (val !== undefined && val !== null && val !== "") {
            if (field.type === "repeater" && typeof val === "string") {
              const firstCol =
                field.sub_fields && field.sub_fields.length > 0
                  ? field.sub_fields[0].key
                  : "col1";
              migratedData[field.key] = [{ [firstCol]: val }];
              needsMigration = true;
            } else if (field.type === "checkbox" && typeof val === "string") {
              migratedData[field.key] = val.split(",").map((v) => v.trim());
              needsMigration = true;
            } else if (field.type === "links" && typeof val === "string") {
              migratedData[field.key] = [
                {
                  title: "Kattints ide",
                  url: val.startsWith("http") ? val : "https://" + val,
                },
              ];
              needsMigration = true;
            }
          }
        });
      }
    });

    if (needsMigration) setFormData(migratedData);
  }, [schema]);

  const handleChange = (key, value) =>
    setFormData((prev) => ({ ...prev, [key]: value }));

  const renderField = (field) => {
    const value = formData[field.key] || "";
    // JAVÍTÁS: Csak akkor tesz csillagot, ha a is_required be van pipálva a hubon.
    const requiredMark = field.is_required ? (
      <span style={{ color: "#d63638", marginLeft: "4px" }}>*</span>
    ) : (
      ""
    );
    const isReadonly = !!field.is_readonly || globalReadonly;
    const readonlyMark = isReadonly ? (
      <span
        style={{
          color: "#888",
          fontSize: "12px",
          fontWeight: "normal",
          marginLeft: "6px",
        }}>
        (Csak olvasható)
      </span>
    ) : (
      ""
    );

    const labelWithRequired = (
      <span
        style={{
          fontWeight: 600,
          fontSize: "13px",
          color: "#1d2327",
          display: "inline-flex",
          alignItems: "center",
        }}>
        {field.label} {requiredMark} {readonlyMark}
      </span>
    );

    const helpStr = field.help_text || "";
    const isFilterableStr =
      field.is_filterable && !isReadonly ? "Indexelt mező." : "";
    const combinedHelp = [helpStr, isFilterableStr].filter(Boolean).join(" ");

    let control = null;

    switch (field.type) {
      case "text":
      case "number":
      case "date":
      case "url":
      case "email":
        const isEmail = field.type === "email";
        const emailVal = value || "";
        const showEmailWarning =
          isEmail &&
          emailVal &&
          !emailVal.toLowerCase().trim().endsWith("@sze.hu");

        control = (
          <>
            <TextControl
              label={labelWithRequired}
              type={
                field.type === "date"
                  ? "date"
                  : field.type === "url"
                  ? "url"
                  : field.type === "email"
                  ? "email"
                  : field.type
              }
              value={value}
              onChange={(val) => handleChange(field.key, val)}
              help={combinedHelp}
              disabled={isReadonly}
            />
            {showEmailWarning && (
              <div
                style={{
                  color: "#856404",
                  backgroundColor: "#fff3cd",
                  padding: "8px 12px",
                  borderRadius: "4px",
                  fontSize: "12px",
                  marginTop: "10px",
                  border: "1px solid #ffeeba",
                }}>
                <strong>⚠️ Figyelem:</strong> Kérjük, lehetőség szerint
                hivatalos egyetemi email címet (@sze.hu végződéssel) adj meg!
              </div>
            )}
          </>
        );
        break;
      case "textarea":
        control = (
          <TextareaControl
            label={labelWithRequired}
            value={value}
            help={combinedHelp}
            onChange={(val) => handleChange(field.key, val)}
            disabled={isReadonly}
          />
        );
        break;
      case "wysiwyg":
        control = (
          <WysiwygControl
            label={labelWithRequired}
            fieldKey={field.key}
            value={value}
            isReadonly={isReadonly}
            helpText={combinedHelp}
            onChange={handleChange}
          />
        );
        break;
      case "links":
        control = (
          <LinksControl
            label={labelWithRequired}
            fieldKey={field.key}
            value={value}
            isReadonly={isReadonly}
            helpText={combinedHelp}
            onChange={handleChange}
          />
        );
        break;
      case "repeater":
        control = (
          <RepeaterControl
            label={labelWithRequired}
            field={field}
            value={value}
            isReadonly={isReadonly}
            helpText={combinedHelp}
            onChange={handleChange}
          />
        );
        break;
      case "select":
      case "radio":
        control = (
          <SelectControl
            label={labelWithRequired}
            value={value}
            options={parseOptions(field.options)}
            help={combinedHelp}
            onChange={(val) => handleChange(field.key, val)}
            disabled={isReadonly}
          />
        );
        break;
      case "boolean":
        control = (
          <ToggleControl
            label={labelWithRequired}
            checked={!!value}
            help={combinedHelp}
            onChange={(val) => handleChange(field.key, val)}
            disabled={isReadonly}
          />
        );
        break;
      case "checkbox":
        const chkOptions = field.options
          ? field.options.split(";").map((o) => o.trim())
          : [];
        const selectedValues = Array.isArray(value)
          ? value
          : typeof value === "string" && value !== ""
          ? value.split(";").map((v) => v.trim())
          : [];
        control = (
          <div
            style={{
              opacity: isReadonly ? 0.7 : 1,
              pointerEvents: isReadonly ? "none" : "auto",
            }}>
            <div style={{ marginBottom: "4px" }}>{labelWithRequired}</div>
            <HelpTextUi text={combinedHelp} />
            <div
              style={{
                marginTop: "12px",
                display: "flex",
                flexDirection: "column",
                gap: "10px",
              }}>
              {chkOptions.map((opt) => (
                <CheckboxControl
                  key={opt}
                  label={opt}
                  checked={selectedValues.includes(opt)}
                  disabled={isReadonly}
                  onChange={(isChecked) => {
                    const newVal = isChecked
                      ? [...selectedValues, opt]
                      : selectedValues.filter((v) => v !== opt);
                    handleChange(field.key, newVal);
                  }}
                  style={{ marginBottom: 0 }}
                />
              ))}
            </div>
          </div>
        );
        break;
      case "image":
        control = (
          <ImageUploadControl
            label={labelWithRequired}
            fieldKey={field.key}
            value={value}
            isReadonly={isReadonly}
            helpText={combinedHelp}
            onChange={handleChange}
          />
        );
        break;
      default:
        control = null;
    }

    if (!control) return null;

    return (
      <div
        key={field.key}
        style={{
          marginBottom: "30px",
          paddingBottom: "25px",
          borderBottom: "1px solid #f0f0f1",
        }}>
        {control}
      </div>
    );
  };

  const validateForm = () => {
    if (!title || title.trim() === "")
      return "A Képzés Címe (Szak megnevezése) kötelező!";
    if (!formData["kepzesi_forma"])
      return "A Képzési Forma kiválasztása kötelező!";

    const activeFormat = formData["kepzesi_forma"];
    const fixedFormats = [
      "BSc",
      "MSc",
      "Osztatlan",
      "Felsőoktatási szakképzés",
      "Szakirányú továbbképzés",
      "Mikroképzés",
      "Előkészítő",
    ];

    if (schema && schema.length > 0) {
      for (const group of schema) {
        let isVisible = true;
        if (group.group_id !== "alap_adatok") {
          if (fixedFormats.includes(group.group_label)) {
            isVisible = group.group_label === activeFormat;
          } else if (group.condition && group.condition.operator) {
            const c = group.condition;
            const targetVal = formData[c.field];
            const stringVal = Array.isArray(targetVal)
              ? targetVal.join(",")
              : String(targetVal || "");
            switch (c.operator) {
              case "==":
                isVisible = stringVal === c.value;
                break;
              case "!=":
                isVisible = stringVal !== c.value;
                break;
              case "not_empty":
                isVisible = stringVal.trim() !== "";
                break;
              case "empty":
                isVisible = stringVal.trim() === "";
                break;
              case "contains":
                isVisible = stringVal.includes(c.value);
                break;
              default:
                isVisible = true;
            }
          }
        }

        if (!isVisible) continue;

        if (!group.fields) continue;
        for (const field of group.fields) {
          const val = formData[field.key];

          // JAVÍTÁS: A kötelező mezők ellenőrzéséből kivettük a field.is_locked feltételt
          if (field.is_required && !field.is_readonly && !globalReadonly) {
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
    if (!canSave) return;
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
        if (data.success) setMessage({ type: "success", text: data.message });
        else setMessage({ type: "error", text: data.message || data.code });
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

      <Panel
        header={`SZEducate Képzés Szerkesztő ${
          globalReadonly ? "(Csak Megtekintés)" : ""
        }`}>
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
                        marginBottom: "30px",
                        paddingBottom: "25px",
                        borderBottom: "1px solid #f0f0f1",
                      }}>
                      <TextControl
                        label={
                          <span
                            style={{
                              fontWeight: 600,
                              fontSize: "13px",
                              color: "#1d2327",
                              display: "inline-flex",
                              alignItems: "center",
                            }}>
                            Képzés Címe (Szak megnevezése){" "}
                            <span
                              style={{ color: "#d63638", marginLeft: "4px" }}>
                              *
                            </span>{" "}
                            {globalReadonly && (
                              <span
                                style={{
                                  color: "#888",
                                  fontSize: "12px",
                                  fontWeight: "normal",
                                  marginLeft: "6px",
                                }}>
                                (Csak olvasható)
                              </span>
                            )}
                          </span>
                        }
                        value={title}
                        onChange={(value) => setTitle(value)}
                        help="Add meg a képzés pontos, hivatalos megnevezését."
                        disabled={globalReadonly}
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
          {globalReadonly ? (
            "Nincs jogosultságod módosítani ezt a képzést."
          ) : (
            <>
              A <span style={{ color: "#d63638" }}>*</span>-gal jelölt mezők
              kitöltése kötelező.
            </>
          )}
        </span>
        {canSave && (
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
        )}
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
