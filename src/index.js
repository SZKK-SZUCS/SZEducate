import { render, useState, useEffect, useRef } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  SelectControl,
  RadioControl,
  CheckboxControl,
  ToggleControl,
  Button,
  Spinner,
  Notice,
  TabPanel,
  FormTokenField,
} from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";

const FIXED_FORMATS = [
  "BSc",
  "MSc",
  "Osztatlan",
  "Felsőoktatási szakképzés",
  "Szakirányú továbbképzés",
  "Mikroképzés",
  "Előkészítő",
];

const parseOptions = (optionsString) => {
  if (!optionsString) return [{ label: "Válassz...", value: "" }];
  const opts = optionsString
    .split(";")
    .map((opt) => opt.trim())
    .filter((opt) => opt !== "")
    .map((opt) => ({ label: opt, value: opt }));
  return [{ label: "Válassz...", value: "" }, ...opts];
};

// Egy csoport (fül) láthatósága a "kepzesi_forma" pivot-mezőtől vagy egy explicit
// feltételtől függhet - ugyanezt a logikát használja a fül-építés ÉS a validáció is,
// hogy a kettő soha ne tudjon egymástól eltérni.
const isGroupVisible = (group, formData) => {
  if (group.group_id === "alap_adatok") return true;
  if (FIXED_FORMATS.includes(group.group_label)) {
    return group.group_label === (formData["kepzesi_forma"] || "");
  }
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
};

// Ezek a mezőtípusok jellemzően rövidek (egy input/dropdown/kapcsoló szélességűek),
// ezért egy sorba kerülhetnek egymás mellé - a többi (textarea, wysiwyg, lista-jellegű
// mezők) mindig saját, teljes szélességű sort kap. A tényleges elrendezés (hány fér el
// egymás mellett) a flexbox tördelésre van bízva, a konténer aktuális szélessége szerint.
const COMPACT_FIELD_TYPES = [
  "text",
  "number",
  "date",
  "email",
  "url",
  "select",
  "radio",
  "boolean",
  "true_false",
];
const isCompactFieldType = (type) => COMPACT_FIELD_TYPES.includes(type);

const isFieldEmpty = (field, val) => {
  if (field.type === "boolean" || field.type === "true_false") return false;
  if (val === undefined || val === null) return true;
  if (field.type === "repeater" || field.type === "links") {
    return !Array.isArray(val) || val.length === 0;
  }
  if (Array.isArray(val)) return val.length === 0;
  if (typeof val === "string") return val.trim() === "";
  return false;
};

// Egy csoport kötelező mezőinek kitöltöttsége - a fül-fejlécben megjelenő jelvényhez.
// Csak a ténylegesen szerkeszthető (nem readonly) kötelező mezőket számoljuk.
const getGroupCompletion = (group) => {
  if (!group.fields) return null;
  const required = group.fields.filter((f) => f.is_required && !f.is_readonly);
  if (required.length === 0) return null;
  return required;
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

const EmptyStateRow = ({ children }) => (
  <div
    style={{
      padding: "14px",
      textAlign: "center",
      color: "#8c8f94",
      fontSize: "13px",
      background: "#fbfbfc",
      border: "1px dashed #dcdcde",
      borderRadius: "4px",
    }}>
    {children}
  </div>
);

const KeywordControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange,
}) => {
  const [suggestions, setSuggestions] = useState([]);

  useEffect(() => {
    apiFetch({ path: `/szeducate/v1/client/field-options?key=${fieldKey}` })
      .then((res) => {
        setSuggestions(res);
      })
      .catch(() => {});
  }, [fieldKey]);

  const tokens =
    typeof value === "string" && value !== ""
      ? value
          .split(";")
          .map((v) => v.trim())
          .filter(Boolean)
      : Array.isArray(value)
      ? value
      : [];

  return (
    <div
      style={{
        opacity: isReadonly ? 0.7 : 1,
        pointerEvents: isReadonly ? "none" : "auto",
      }}>
      <div style={{ marginBottom: "4px" }}>{label}</div>
      <HelpTextUi text={helpText} />
      <FormTokenField
        value={tokens}
        suggestions={suggestions}
        onChange={(newTokens) => {
          onChange(fieldKey, newTokens.join("; "));
        }}
        disabled={isReadonly}
      />
    </div>
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
          plugins: "paste,lists,link,textcolor,colorpicker,table",
          toolbar1:
            "formatselect,bold,italic,underline,bullist,numlist,link,unlink,forecolor,backcolor,table",
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
      <div
        style={{
          border: "1px solid #ddd",
          borderRadius: "4px",
          overflow: "hidden",
        }}>
        <textarea
          id={editorId}
          defaultValue={value || ""}
          style={{ width: "100%", minHeight: "220px", display: "block" }}
          disabled={isReadonly}></textarea>
      </div>
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
    <div>
      <div style={{ marginBottom: "4px" }}>{label}</div>
      <HelpTextUi text={helpText} />

      {links.length === 0 ? (
        <EmptyStateRow>Még nincs hozzáadva egyetlen link sem.</EmptyStateRow>
      ) : (
        links.map((link, index) => (
          <div
            key={index}
            style={{
              display: "flex",
              gap: "10px",
              marginBottom: "10px",
              alignItems: "center",
            }}>
            <div
              style={{
                width: "24px",
                color: "#a7aaad",
                fontSize: "12px",
                textAlign: "right",
                flexShrink: 0,
              }}>
              {index + 1}.
            </div>
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
              <Button
                isDestructive
                isSmall
                onClick={() => removeLink(index)}
                label="Link eltávolítása">
                Törlés
              </Button>
            )}
          </div>
        ))
      )}

      {!isReadonly && (
        <Button isSecondary onClick={addLink} style={{ marginTop: "6px" }}>
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
    <div style={{ opacity: isReadonly ? 0.7 : 1 }}>
      <div style={{ marginBottom: "4px" }}>{label}</div>
      <HelpTextUi text={helpText} />
      <div
        style={{
          marginTop: "10px",
          paddingTop: "12px",
          borderTop: "1px solid #eceef0",
          overflowX: "auto",
        }}>
        {rows.length === 0 ? (
          <EmptyStateRow>Még nincs hozzáadva egyetlen sor sem.</EmptyStateRow>
        ) : (
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
                      fontSize: "12px",
                      textTransform: "uppercase",
                      letterSpacing: "0.02em",
                      color: "#50575e",
                    }}>
                    {sf.label}
                  </th>
                ))}
                {!isReadonly && <th style={{ width: "50px" }}></th>}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr key={index}>
                  {subFields.map((sf) => (
                    <td
                      key={sf.key}
                      style={{
                        padding: "8px",
                        borderBottom: "1px solid #eee",
                      }}>
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
                        onClick={() => removeRow(index)}
                        label="Sor eltávolítása">
                        &times;
                      </Button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {!isReadonly && (
          <Button isSecondary onClick={addRow} style={{ marginTop: "12px" }}>
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

      <div
        style={{
          display: "flex",
          alignItems: "center",
          gap: "16px",
          flexWrap: "wrap",
        }}>
        <div
          onClick={openMediaUploader}
          style={{
            width: "160px",
            height: "110px",
            border: value ? "1px solid #dcdcde" : "1px dashed #c3c4c7",
            borderRadius: "6px",
            background: value ? "#fff" : "#fbfbfc",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            cursor: isReadonly ? "default" : "pointer",
            overflow: "hidden",
            flexShrink: 0,
          }}>
          {value ? (
            <img
              src={value}
              alt="Előnézet"
              style={{
                maxWidth: "100%",
                maxHeight: "100%",
                objectFit: "contain",
              }}
            />
          ) : (
            <span style={{ color: "#a7aaad", fontSize: "12px" }}>
              Nincs kép
            </span>
          )}
        </div>

        {!isReadonly && (
          <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
            <Button isSecondary onClick={openMediaUploader}>
              {value ? "Kép cseréje" : "Kép feltöltése"}
            </Button>
            {value && (
              <Button
                isDestructive
                isLink
                onClick={() => onChange(fieldKey, "")}>
                Kép eltávolítása
              </Button>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

const SZEducateEditor = () => {
  const {
    postId,
    nonce,
    restUrl,
    versionsUrl,
    schema,
    permissions,
    existingTitle,
    existingData,
  } = window.szEducateData || {};

  const [title, setTitle] = useState(existingTitle || "");
  const [formData, setFormData] = useState(existingData || {});
  const [isSaving, setIsSaving] = useState(false);
  const [message, setMessage] = useState(null);
  const [errorField, setErrorField] = useState(null);
  const [jumpToTab, setJumpToTab] = useState(null);
  const [tabForceKey, setTabForceKey] = useState(0);
  const [lastSavedAt, setLastSavedAt] = useState(null);
  // Néhány mezőtípus (pl. WYSIWYG) nem kontrollált komponens - a saját belső állapotát
  // csak induláskor olvassa be, ezért egy programozott visszaállításnál (mező-reset vagy
  // verzió-visszaállítás) újra kell "kényszeríteni" a mountolását, hogy a látható tartalom
  // is frissüljön, ne csak a React state a háttérben. Ez a számláló szolgál erre.
  const [resetTick, setResetTick] = useState(0);

  const [versions, setVersions] = useState([]);
  const [isLoadingVersions, setIsLoadingVersions] = useState(false);
  const [isRestoring, setIsRestoring] = useState(null);

  const fieldRefs = useRef({});
  // A legutóbb MENTETT állapot (nem a jelenleg szerkesztett!) - ehhez viszonyítva
  // számoljuk ki, mely mezők változtak azóta, és ez a "dirty" jelző alapja is.
  const savedSnapshot = useRef({
    title: existingTitle || "",
    formData: existingData || {},
  });

  const actions = permissions?.actions || {
    create: true,
    edit: true,
    delete: false,
  };
  const isNewPost = !existingTitle;
  const globalReadonly = !isNewPost && !actions.edit;
  const canSave = isNewPost ? actions.create : actions.edit;

  const isDirty =
    JSON.stringify({ title, formData }) !==
    JSON.stringify(savedSnapshot.current);

  const isFieldChangedSinceSave = (key) =>
    JSON.stringify(formData[key] ?? null) !==
    JSON.stringify(savedSnapshot.current.formData[key] ?? null);

  const fetchVersions = () => {
    if (!postId || !versionsUrl) return;
    setIsLoadingVersions(true);
    fetch(`${versionsUrl}?post_id=${postId}`, {
      headers: { "X-WP-Nonce": nonce },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) setVersions(data.versions || []);
      })
      .catch(() => {})
      .finally(() => setIsLoadingVersions(false));
  };

  useEffect(() => {
    fetchVersions();
  }, [postId]);

  const handleRestoreVersion = (version) => {
    if (
      !window.confirm(
        `Biztosan betöltöd ezt a verziót (${new Date(
          version.edited_at.replace(" ", "T"),
        ).toLocaleString("hu-HU")}, módosította: ${
          version.edited_by
        })?\n\nA jelenlegi űrlap tartalma felülíródik, de MENTÉSIG semmi nem változik ténylegesen - utána még ellenőrizheted, mielőtt elmented.`,
      )
    ) {
      return;
    }

    setIsRestoring(version.id);
    fetch(`${versionsUrl}/${version.id}?post_id=${postId}`, {
      headers: { "X-WP-Nonce": nonce },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          setTitle(data.title);
          setFormData(data.course_data || {});
          setResetTick((t) => t + 1);
          setMessage({
            type: "warning",
            text: `Betöltve egy korábbi verzió (${new Date(
              data.edited_at.replace(" ", "T"),
            ).toLocaleString("hu-HU")}, ${
              data.edited_by
            }). Ellenőrizd az adatokat, majd mentsd el, ha megfelel!`,
          });
          window.scrollTo({ top: 0, behavior: "smooth" });
        } else {
          setMessage({
            type: "error",
            text: "Nem sikerült betölteni a verziót.",
          });
        }
      })
      .catch(() => {
        setMessage({
          type: "error",
          text: "Hálózati hiba a verzió betöltésekor.",
        });
      })
      .finally(() => setIsRestoring(null));
  };

  useEffect(() => {
    const handler = (e) => {
      if (!isDirty) return;
      e.preventDefault();
      e.returnValue = "";
      return "";
    };
    window.addEventListener("beforeunload", handler);
    return () => window.removeEventListener("beforeunload", handler);
  }, [isDirty]);

  useEffect(() => {
    if (!message || message.type !== "success") return;
    const t = setTimeout(() => setMessage(null), 6000);
    return () => clearTimeout(t);
  }, [message]);

  useEffect(() => {
    if (!errorField) return;
    const t = setTimeout(() => {
      const el = fieldRefs.current[errorField];
      if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
    }, 60);
    return () => clearTimeout(t);
  }, [errorField, tabForceKey]);

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
              migratedData[field.key] = val.split(";").map((v) => v.trim());
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

    if (needsMigration) {
      setFormData(migratedData);
      savedSnapshot.current = {
        title: existingTitle || "",
        formData: migratedData,
      };
    }
  }, [schema]);

  const handleChange = (key, value) => {
    if (errorField === key) setErrorField(null);
    setFormData((prev) => ({ ...prev, [key]: value }));
  };

  // Egy adott mező visszaállítása a legutóbb MENTETT értékére - nem az egész
  // formot, csak azt az egy mezőt érinti.
  const resetFieldToSaved = (key) => {
    if (errorField === key) setErrorField(null);
    setFormData((prev) => {
      const next = { ...prev };
      const savedVal = savedSnapshot.current.formData[key];
      if (savedVal === undefined) {
        delete next[key];
      } else {
        next[key] = savedVal;
      }
      return next;
    });
    setResetTick((t) => t + 1);
  };

  const ResetButton = ({ onClick, label = "Vissza" }) => (
    <button
      type="button"
      onClick={(e) => {
        e.preventDefault();
        e.stopPropagation();
        onClick();
      }}
      title="Visszaállítás az utoljára mentett értékre"
      style={{
        marginLeft: "6px",
        background: "none",
        border: "none",
        cursor: "pointer",
        color: "#8a6100",
        fontSize: "11px",
        fontWeight: 700,
        padding: 0,
        textDecoration: "underline",
        verticalAlign: "middle",
      }}>
      &#8634; {label}
    </button>
  );

  const renderField = (field) => {
    const value = formData[field.key] !== undefined ? formData[field.key] : "";
    const requiredMark = field.is_required ? (
      <span style={{ color: "#d63638", marginLeft: "4px" }}>*</span>
    ) : (
      ""
    );
    const isReadonly = !!field.is_readonly || globalReadonly;
    const isChanged = !isReadonly && isFieldChangedSinceSave(field.key);
    const readonlyMark = isReadonly ? (
      <span
        style={{
          color: "#787c82",
          fontSize: "10px",
          fontWeight: 700,
          textTransform: "uppercase",
          letterSpacing: "0.04em",
          marginLeft: "8px",
          padding: "2px 7px",
          borderRadius: "10px",
          background: "#eef0f1",
          border: "1px solid #dcdcde",
          verticalAlign: "middle",
        }}>
        Zárolva
      </span>
    ) : (
      ""
    );
    const changedMark = isChanged ? (
      <>
        <span
          style={{
            color: "#8a6100",
            fontSize: "10px",
            fontWeight: 700,
            textTransform: "uppercase",
            letterSpacing: "0.04em",
            marginLeft: "8px",
            padding: "2px 7px",
            borderRadius: "10px",
            background: "#fff2c9",
            border: "1px solid #f0dca0",
            verticalAlign: "middle",
          }}>
          Módosítva
        </span>
        <ResetButton onClick={() => resetFieldToSaved(field.key)} />
      </>
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
        {field.label} {requiredMark} {readonlyMark} {changedMark}
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
        if (field.key === "kulcsszavak") {
          control = (
            <KeywordControl
              label={labelWithRequired}
              fieldKey={field.key}
              value={value}
              isReadonly={isReadonly}
              helpText={combinedHelp}
              onChange={handleChange}
            />
          );
        } else {
          control = (
            <TextareaControl
              label={labelWithRequired}
              value={value}
              help={combinedHelp}
              onChange={(val) => handleChange(field.key, val)}
              disabled={isReadonly}
            />
          );
        }
        break;
      case "wysiwyg":
        control = (
          <WysiwygControl
            key={`${field.key}-${resetTick}`}
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
      case "radio":
        control = (
          <div
            style={{
              opacity: isReadonly ? 0.7 : 1,
              pointerEvents: isReadonly ? "none" : "auto",
            }}>
            <RadioControl
              label={labelWithRequired}
              selected={value}
              options={parseOptions(field.options).filter(
                (o) => o.value !== "",
              )}
              help={combinedHelp}
              onChange={(val) => handleChange(field.key, val)}
              disabled={isReadonly}
            />
          </div>
        );
        break;
      case "boolean":
      case "true_false":
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
          ? field.options
              .split(";")
              .map((o) => o.trim())
              .filter((o) => o !== "")
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
              {chkOptions.length === 0 ? (
                <EmptyStateRow>
                  Ehhez a mezőhöz nincsenek beállítva választható opciók.
                </EmptyStateRow>
              ) : (
                chkOptions.map((opt) => (
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
                ))
              )}
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
        // Ismeretlen/jövőbeli mezőtípus: soha ne tűnjön el csendben egy mező csak
        // mert a séma olyan típust kapott, amit ez a build még nem ismer - inkább
        // egy nyers szöveges szerkesztőt kap, egyértelmű figyelmeztetéssel.
        control = (
          <div>
            <TextControl
              label={labelWithRequired}
              value={
                typeof value === "string" ? value : JSON.stringify(value ?? "")
              }
              onChange={(val) => handleChange(field.key, val)}
              help={combinedHelp}
              disabled={isReadonly}
            />
            <div
              style={{
                color: "#856404",
                backgroundColor: "#fff3cd",
                padding: "6px 10px",
                borderRadius: "4px",
                fontSize: "12px",
                marginTop: "6px",
                border: "1px solid #ffeeba",
              }}>
              Ismeretlen mezőtípus (&quot;{field.type}&quot;) - nyers
              szövegszerkesztő jelenik meg helyette.
            </div>
          </div>
        );
    }

    if (!control) return null;

    const hasError = errorField === field.key;
    const compact = isCompactFieldType(field.type);

    // Minden mező saját, jól elkülönített "kártya" - a hátterük árnyalata jelzi a
    // mező jellegét (zárolt / összetett-tartalmú / normál), így első pillantásra
    // felismerhető a mintázat anélkül, hogy külön ikonokra lenne szükség.
    let cardBg = "#fff";
    let cardBorder = "#e2e4e7";
    if (hasError) {
      cardBg = "#fef7f7";
      cardBorder = "#e0a3a5";
    } else if (isChanged) {
      cardBg = "#fffbea";
      cardBorder = "#f0dca0";
    } else if (isReadonly) {
      cardBg = "#f6f7f7";
      cardBorder = "#dcdcde";
    } else if (!compact) {
      cardBg = "#fbfcfe";
    }

    let accentBorder = `1px solid ${cardBorder}`;
    if (hasError) accentBorder = "4px solid #d63638";
    else if (isChanged) accentBorder = "4px solid #dba617";

    return (
      <div
        key={field.key}
        ref={(el) => (fieldRefs.current[field.key] = el)}
        style={{
          gridColumn: compact ? "auto" : "1 / -1",
          boxSizing: "border-box",
          padding: "16px 18px",
          background: cardBg,
          border: `1px solid ${cardBorder}`,
          borderLeft: accentBorder,
          borderRadius: "8px",
          transition: "background 0.15s, border-color 0.15s",
        }}>
        {control}
        {hasError && (
          <p
            style={{
              color: "#d63638",
              fontSize: "12px",
              fontWeight: 600,
              marginTop: "8px",
              marginBottom: 0,
            }}>
            Ez a mező kitöltése kötelező.
          </p>
        )}
      </div>
    );
  };

  const visibleGroups = () =>
    schema && schema.length > 0
      ? schema.filter((group) => isGroupVisible(group, formData))
      : [];

  const validateForm = () => {
    if (!title || title.trim() === "") {
      return {
        message: "A Képzés Címe (Szak megnevezése) kötelező!",
        groupId: null,
        fieldKey: "__title__",
      };
    }
    if (!formData["kepzesi_forma"]) {
      return {
        message: "A Képzési Forma kiválasztása kötelező!",
        groupId: "alap_adatok",
        fieldKey: "kepzesi_forma",
      };
    }

    for (const group of visibleGroups()) {
      if (!group.fields) continue;
      for (const field of group.fields) {
        if (!field.is_required || field.is_readonly || globalReadonly) continue;
        const val = formData[field.key];
        if (isFieldEmpty(field, val)) {
          return {
            message: `Kérlek töltsd ki a következő kötelező mezőt a(z) "${group.group_label}" fülön: ${field.label}`,
            groupId: group.group_id,
            fieldKey: field.key,
          };
        }
      }
    }
    return null;
  };

  const handleSave = () => {
    if (!canSave) return;
    const error = validateForm();
    if (error) {
      setMessage({ type: "error", text: error.message });
      setErrorField(error.fieldKey);
      if (error.fieldKey === "__title__") {
        window.scrollTo({ top: 0, behavior: "smooth" });
      } else if (error.groupId) {
        setJumpToTab(error.groupId);
        setTabForceKey((k) => k + 1);
      }
      return;
    }

    setErrorField(null);
    setIsSaving(true);
    setMessage(null);

    const processedData = { ...formData };
    for (const [key, val] of Object.entries(processedData)) {
      if (Array.isArray(val)) {
        if (val.length > 0 && typeof val[0] === "object") {
          processedData[key] = val;
        } else {
          processedData[key] = val.join("; ");
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
          savedSnapshot.current = { title, formData };
          setLastSavedAt(new Date());
          fetchVersions();
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

    return visibleGroups().map((group) => {
      const required = getGroupCompletion(group);
      let badge = null;
      if (required && !globalReadonly) {
        const filled = required.filter(
          (f) => !isFieldEmpty(f, formData[f.key]),
        ).length;
        const complete = filled === required.length;
        badge = (
          <span
            style={{
              marginLeft: "6px",
              display: "inline-block",
              minWidth: "16px",
              padding: "0 5px",
              borderRadius: "9px",
              fontSize: "10px",
              lineHeight: "16px",
              fontWeight: 700,
              textAlign: "center",
              color: complete ? "#1a7f37" : "#996800",
              background: complete ? "#edfaef" : "#fff8e5",
              border: `1px solid ${complete ? "#b4e3bc" : "#f0dca0"}`,
            }}>
            {complete ? "✓" : `${filled}/${required.length}`}
          </span>
        );
      }

      return {
        name: group.group_id,
        title: (
          <span>
            {group.group_label}
            {badge}
          </span>
        ),
        className: "szeducate-tab-" + group.group_id,
        fields: group.fields,
      };
    });
  };

  const tabs = buildTabs();
  const overallRequired = visibleGroups().flatMap(
    (g) => getGroupCompletion(g) || [],
  );
  const overallFilled = overallRequired.filter(
    (f) => !isFieldEmpty(f, formData[f.key]),
  ).length;

  // key -> label az összes séma-mezőhöz (fülektől függetlenül), a verzió-előzmények
  // és a "módosítva az utolsó mentés óta" lista feliratozásához.
  const allSchemaFields = (schema || []).flatMap((g) => g.fields || []);
  const fieldLabelByKey = allSchemaFields.reduce((acc, f) => {
    acc[f.key] = f.label;
    return acc;
  }, {});

  const changedSinceSave = [
    ...(title !== savedSnapshot.current.title ? ["Cím"] : []),
    ...allSchemaFields
      .filter((f) => isFieldChangedSinceSave(f.key))
      .map((f) => f.label),
  ];

  const describeChangedFields = (keys) =>
    (keys || []).map((key) => {
      if (key === "__initial__") return "Kezdeti verzió";
      if (key === "__title__") return "Cím";
      return fieldLabelByKey[key] || key;
    });

  return (
    <div
      className="szeducate-react-wrapper"
      style={{ maxWidth: "1200px", margin: "0 auto" }}>
      {message && (
        <Notice
          status={message.type}
          isDismissible={true}
          onRemove={() => setMessage(null)}
          style={{ marginBottom: "20px" }}>
          {message.text}
        </Notice>
      )}

      {globalReadonly && (
        <Notice
          status="warning"
          isDismissible={false}
          style={{ marginBottom: "20px" }}>
          <strong>Figyelem:</strong> Nincs jogosultságod a képzés adatainak
          módosítására. Az űrlap csak olvasható módban nyílt meg.
        </Notice>
      )}

      <div
        style={{
          display: "flex",
          gap: "20px",
          alignItems: "flex-start",
          flexWrap: "wrap",
          position: "relative",
        }}>
        {/* Bal oldali statikus kártya */}
        <div style={{ flex: "3 1 620px", minWidth: 0 }}>
          <div
            style={{
              background: "#fff",
              border: "1px solid #c3c4c7",
              borderRadius: "6px",
              boxShadow: "0 1px 2px rgba(0,0,0,.05)",
            }}>
            <h2
              style={{
                padding: "15px 20px",
                margin: 0,
                borderBottom: "1px solid #c3c4c7",
                fontSize: "14px",
                fontWeight: 600,
                background: "#f6f7f7",
                borderRadius: "6px 6px 0 0",
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                flexWrap: "wrap",
                gap: "8px",
              }}>
              <span>
                Képzés Részletei {globalReadonly ? "(Csak Megtekintés)" : ""}
              </span>
              {!globalReadonly && overallRequired.length > 0 && (
                <span
                  style={{
                    fontSize: "12px",
                    fontWeight: 500,
                    color:
                      overallFilled === overallRequired.length
                        ? "#1a7f37"
                        : "#996800",
                  }}>
                  Kötelező mezők: {overallFilled}/{overallRequired.length}{" "}
                  kitöltve
                </span>
              )}
            </h2>
            <div style={{ padding: "20px" }}>
              {schema && schema.length > 0 ? (
                <TabPanel
                  key={tabForceKey}
                  className="szeducate-tabs"
                  activeClass="is-active"
                  initialTabName={jumpToTab || undefined}
                  tabs={tabs}>
                  {(tab) => (
                    <div
                      style={{
                        padding: "20px 0",
                        display: "grid",
                        // Legfeljebb 2 oszlop - de ha a hely szűkös (pl. keskenyebb ablak),
                        // magától 1 oszlopra esik vissza ahelyett, hogy összenyomná a mezőket.
                        gridTemplateColumns:
                          "repeat(auto-fit, minmax(max(240px, calc((100% - 16px) / 2)), 1fr))",
                        alignItems: "start",
                        gap: "16px",
                      }}>
                      {tab.fields &&
                        tab.fields.map((field) => renderField(field))}
                    </div>
                  )}
                </TabPanel>
              ) : (
                <Notice
                  status="warning"
                  isDismissible={false}
                  style={{ marginTop: "20px" }}>
                  Hiányzó séma! Kérlek szinkronizálj a Hubbal a Beállításokban.
                </Notice>
              )}
            </div>
          </div>
        </div>

        {/* Jobb oldali ragadós (sticky) kártya */}
        <div
          style={{
            flex: "1 1 280px",
            position: "sticky",
            top: "50px",
            zIndex: 10,
          }}>
          <div
            style={{
              background: "#fff",
              border: "1px solid #c3c4c7",
              borderRadius: "6px",
              boxShadow: "0 1px 2px rgba(0,0,0,.05)",
            }}>
            <h2
              style={{
                padding: "15px 20px",
                margin: 0,
                borderBottom: "1px solid #c3c4c7",
                fontSize: "14px",
                fontWeight: 600,
                background: "#f6f7f7",
                borderRadius: "6px 6px 0 0",
              }}>
              Mentés és Megnevezés
            </h2>
            <div style={{ padding: "20px" }}>
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
                    <span style={{ color: "#d63638", marginLeft: "4px" }}>
                      *
                    </span>{" "}
                    {title !== savedSnapshot.current.title && (
                      <>
                        <span
                          style={{
                            color: "#8a6100",
                            fontSize: "10px",
                            fontWeight: 700,
                            textTransform: "uppercase",
                            letterSpacing: "0.04em",
                            marginLeft: "4px",
                            padding: "2px 7px",
                            borderRadius: "10px",
                            background: "#fff2c9",
                            border: "1px solid #f0dca0",
                            verticalAlign: "middle",
                          }}>
                          Módosítva
                        </span>
                        <ResetButton
                          onClick={() => {
                            setTitle(savedSnapshot.current.title);
                            if (errorField === "__title__") setErrorField(null);
                          }}
                        />
                      </>
                    )}
                  </span>
                }
                value={title}
                onChange={(value) => {
                  if (errorField === "__title__") setErrorField(null);
                  setTitle(value);
                }}
                help="Ez jelenik meg a listákban és a címekben."
                disabled={globalReadonly}
                style={{
                  marginBottom: "20px",
                  outline:
                    errorField === "__title__" ? "2px solid #d63638" : "none",
                  borderRadius: errorField === "__title__" ? "4px" : undefined,
                }}
              />

              {!globalReadonly && (
                <>
                  <Button
                    isPrimary
                    isLarge
                    style={{
                      width: "100%",
                      justifyContent: "center",
                      marginTop: "10px",
                    }}
                    onClick={handleSave}
                    disabled={isSaving}>
                    {isSaving ? <Spinner /> : "Adatlap Mentése"}
                  </Button>

                  <div
                    style={{
                      marginTop: "12px",
                      textAlign: "center",
                      fontSize: "12px",
                    }}>
                    {isDirty ? (
                      <span style={{ color: "#996800", fontWeight: 600 }}>
                        &#9679; Nem mentett módosítások
                      </span>
                    ) : lastSavedAt ? (
                      <span style={{ color: "#1a7f37" }}>
                        &#10003; Mentve{" "}
                        {lastSavedAt.toLocaleTimeString("hu-HU", {
                          hour: "2-digit",
                          minute: "2-digit",
                        })}
                        -kor
                      </span>
                    ) : (
                      <span style={{ color: "#8c8f94" }}>
                        Nincs még mentett módosítás ebben a munkamenetben.
                      </span>
                    )}
                  </div>
                </>
              )}

              <p
                style={{
                  fontSize: "12px",
                  color: "#666",
                  marginTop: "15px",
                  marginBottom: 0,
                  textAlign: "center",
                }}>
                {globalReadonly
                  ? "Nincs jogosultságod módosítani ezt a képzést."
                  : "A csillaggal (*) jelölt mezők kitöltése kötelező."}
              </p>
            </div>
          </div>

          <div
            style={{
              background: "#fff",
              border: "1px solid #c3c4c7",
              borderRadius: "6px",
              boxShadow: "0 1px 2px rgba(0,0,0,.05)",
              marginTop: "16px",
            }}>
            <h2
              style={{
                padding: "15px 20px",
                margin: 0,
                borderBottom: "1px solid #c3c4c7",
                fontSize: "14px",
                fontWeight: 600,
                background: "#f6f7f7",
                borderRadius: "6px 6px 0 0",
              }}>
              Verzió Előzmények
            </h2>
            <div style={{ padding: "20px" }}>
              <div style={{ marginBottom: "20px" }}>
                <div
                  style={{
                    fontSize: "11px",
                    fontWeight: 700,
                    color: "#50575e",
                    textTransform: "uppercase",
                    letterSpacing: "0.03em",
                    marginBottom: "8px",
                  }}>
                  Módosítva az utolsó mentés óta
                </div>
                {changedSinceSave.length === 0 ? (
                  <p style={{ fontSize: "12px", color: "#8c8f94", margin: 0 }}>
                    Nincs mentetlen módosítás.
                  </p>
                ) : (
                  <div
                    style={{ display: "flex", flexWrap: "wrap", gap: "6px" }}>
                    {changedSinceSave.map((label, i) => (
                      <span
                        key={i}
                        style={{
                          fontSize: "11px",
                          fontWeight: 600,
                          color: "#8a6100",
                          background: "#fff2c9",
                          border: "1px solid #f0dca0",
                          borderRadius: "10px",
                          padding: "3px 9px",
                        }}>
                        {label}
                      </span>
                    ))}
                  </div>
                )}
              </div>

              <div>
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    marginBottom: "8px",
                  }}>
                  <span
                    style={{
                      fontSize: "11px",
                      fontWeight: 700,
                      color: "#50575e",
                      textTransform: "uppercase",
                      letterSpacing: "0.03em",
                    }}>
                    Korábbi verziók
                  </span>
                  {postId ? (
                    <button
                      type="button"
                      onClick={fetchVersions}
                      style={{
                        background: "none",
                        border: "none",
                        color: "#2271b1",
                        fontSize: "11px",
                        cursor: "pointer",
                        padding: 0,
                      }}>
                      Frissítés
                    </button>
                  ) : null}
                </div>

                {!postId ? (
                  <p style={{ fontSize: "12px", color: "#8c8f94", margin: 0 }}>
                    Az előzmények az első mentés után lesznek elérhetők.
                  </p>
                ) : isLoadingVersions ? (
                  <Spinner />
                ) : versions.length === 0 ? (
                  <p style={{ fontSize: "12px", color: "#8c8f94", margin: 0 }}>
                    Még nincs rögzített verzió.
                  </p>
                ) : (
                  <div
                    style={{
                      display: "flex",
                      flexDirection: "column",
                      gap: "8px",
                      maxHeight: "380px",
                      overflowY: "auto",
                    }}>
                    {versions.map((v, index) => {
                      const isCurrent = index === 0;
                      const labels = describeChangedFields(v.changed_fields);
                      return (
                        <div
                          key={v.id}
                          style={{
                            border: "1px solid #e2e4e7",
                            borderRadius: "6px",
                            padding: "10px 12px",
                            background: isCurrent ? "#f6fbf7" : "#fff",
                          }}>
                          <div
                            style={{
                              display: "flex",
                              justifyContent: "space-between",
                              alignItems: "baseline",
                              gap: "8px",
                            }}>
                            <strong style={{ fontSize: "12px" }}>
                              {new Date(
                                v.edited_at.replace(" ", "T"),
                              ).toLocaleString("hu-HU")}
                            </strong>
                            {isCurrent && (
                              <span
                                style={{
                                  fontSize: "10px",
                                  fontWeight: 700,
                                  color: "#1a7f37",
                                  whiteSpace: "nowrap",
                                }}>
                                JELENLEGI
                              </span>
                            )}
                          </div>
                          <div
                            style={{
                              fontSize: "11px",
                              color: "#646970",
                              marginTop: "2px",
                            }}>
                            {v.edited_by}
                          </div>
                          {labels.length > 0 && (
                            <div
                              style={{
                                marginTop: "8px",
                                display: "flex",
                                flexWrap: "wrap",
                                gap: "4px",
                              }}>
                              {labels.slice(0, 6).map((l, i) => (
                                <span
                                  key={i}
                                  style={{
                                    fontSize: "10px",
                                    background: "#f0f0f1",
                                    border: "1px solid #dcdcde",
                                    borderRadius: "8px",
                                    padding: "1px 6px",
                                    color: "#50575e",
                                  }}>
                                  {l}
                                </span>
                              ))}
                              {labels.length > 6 && (
                                <span
                                  style={{
                                    fontSize: "10px",
                                    color: "#8c8f94",
                                  }}>
                                  +{labels.length - 6} további
                                </span>
                              )}
                            </div>
                          )}
                          {!isCurrent && !globalReadonly && (
                            <Button
                              isSecondary
                              isSmall
                              style={{ marginTop: "10px" }}
                              onClick={() => handleRestoreVersion(v)}
                              disabled={isRestoring === v.id}>
                              {isRestoring === v.id ? (
                                <Spinner />
                              ) : (
                                "Visszaállítás"
                              )}
                            </Button>
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
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
