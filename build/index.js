/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "react"
/*!************************!*\
  !*** external "React" ***!
  \************************/
(module) {

module.exports = window["React"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);



const parseOptions = optionsString => {
  if (!optionsString) return [{
    label: "Válassz...",
    value: ""
  }];
  const opts = optionsString.split(",").map(opt => ({
    label: opt.trim(),
    value: opt.trim()
  }));
  return [{
    label: "Válassz...",
    value: ""
  }, ...opts];
};
const HelpTextUi = ({
  text
}) => {
  if (!text) return null;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      fontSize: "12px",
      color: "#646970",
      marginTop: "4px",
      marginBottom: "10px",
      fontStyle: "italic",
      lineHeight: "1.4"
    }
  }, text);
};
const WysiwygControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange
}) => {
  const editorId = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(`wysiwyg_${fieldKey}_${Math.random().toString(36).substr(2, 9)}`).current;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (window.wp && window.wp.editor) {
      window.wp.editor.initialize(editorId, {
        tinymce: {
          readonly: isReadonly ? 1 : 0,
          setup: function (editor) {
            editor.on("Change KeyUp", function () {
              if (!isReadonly) onChange(fieldKey, editor.getContent());
            });
          }
        },
        quicktags: !isReadonly,
        mediaButtons: !isReadonly
      });
    }
    return () => {
      if (window.wp && window.wp.editor) window.wp.editor.remove(editorId);
    };
  }, []);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      opacity: isReadonly ? 0.7 : 1,
      pointerEvents: isReadonly ? "none" : "auto"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "4px"
    }
  }, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(HelpTextUi, {
    text: helpText
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("textarea", {
    id: editorId,
    defaultValue: value || "",
    style: {
      width: "100%",
      minHeight: "200px"
    },
    disabled: isReadonly
  }));
};
const LinksControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange
}) => {
  const links = Array.isArray(value) ? value : [];
  const addLink = () => onChange(fieldKey, [...links, {
    title: "",
    url: ""
  }]);
  const removeLink = index => onChange(fieldKey, links.filter((_, i) => i !== index));
  const updateLink = (index, key, val) => {
    const newLinks = [...links];
    newLinks[index][key] = val;
    onChange(fieldKey, newLinks);
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      background: isReadonly ? "#f0f0f0" : "#f9f9f9",
      padding: "15px",
      border: "1px solid #ddd",
      borderRadius: "4px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "4px"
    }
  }, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(HelpTextUi, {
    text: helpText
  }), links.map((link, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: index,
    style: {
      display: "flex",
      gap: "10px",
      marginBottom: "10px",
      alignItems: "center"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      flex: 1
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    placeholder: "Gomb sz\xF6vege",
    value: link.title,
    onChange: v => updateLink(index, "title", v),
    disabled: isReadonly,
    style: {
      marginBottom: 0
    }
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      flex: 2
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    placeholder: "URL (https://...)",
    type: "url",
    value: link.url,
    onChange: v => updateLink(index, "url", v),
    disabled: isReadonly,
    style: {
      marginBottom: 0
    }
  })), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isDestructive: true,
    isSmall: true,
    onClick: () => removeLink(index)
  }, "X"))), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isSecondary: true,
    onClick: addLink,
    style: {
      marginTop: "10px"
    }
  }, "+ Link hozz\xE1ad\xE1sa"));
};
const RepeaterControl = ({
  label,
  field,
  value,
  isReadonly,
  helpText,
  onChange
}) => {
  const rows = Array.isArray(value) ? value : [];
  const subFields = field.sub_fields || [];
  const addRow = () => {
    const newRow = {};
    subFields.forEach(sf => newRow[sf.key] = "");
    onChange(field.key, [...rows, newRow]);
  };
  const removeRow = index => onChange(field.key, rows.filter((_, i) => i !== index));
  const updateRow = (index, sfKey, val) => {
    const newRows = [...rows];
    newRows[index][sfKey] = val;
    onChange(field.key, newRows);
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      background: "#fff",
      border: "1px solid #ccd0d4",
      borderRadius: "4px",
      opacity: isReadonly ? 0.7 : 1
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      padding: "10px 15px",
      background: "#f0f6fc",
      borderBottom: "1px solid #ccd0d4"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(HelpTextUi, {
    text: helpText
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      padding: "15px",
      overflowX: "auto"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("table", {
    style: {
      width: "100%",
      borderCollapse: "collapse"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("thead", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tr", null, subFields.map(sf => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("th", {
    key: sf.key,
    style: {
      textAlign: "left",
      padding: "8px",
      borderBottom: "2px solid #ddd",
      fontSize: "13px"
    }
  }, sf.label)), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("th", {
    style: {
      width: "40px"
    }
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tbody", null, rows.map((row, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tr", {
    key: index
  }, subFields.map(sf => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", {
    key: sf.key,
    style: {
      padding: "8px",
      borderBottom: "1px solid #eee"
    }
  }, sf.type === "boolean" ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
    checked: !!row[sf.key],
    onChange: v => updateRow(index, sf.key, v),
    disabled: isReadonly
  }) : sf.type === "select" ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    value: row[sf.key] || "",
    options: parseOptions(sf.options),
    onChange: v => updateRow(index, sf.key, v),
    disabled: isReadonly,
    style: {
      marginBottom: 0
    }
  }) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    type: sf.type === "number" ? "number" : sf.type === "url" ? "url" : "text",
    value: row[sf.key] || "",
    onChange: v => updateRow(index, sf.key, v),
    disabled: isReadonly,
    style: {
      marginBottom: 0
    }
  }))), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", {
    style: {
      padding: "8px",
      borderBottom: "1px solid #eee",
      textAlign: "center"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isDestructive: true,
    isSmall: true,
    onClick: () => removeRow(index)
  }, "X")))))), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isSecondary: true,
    onClick: addRow,
    style: {
      marginTop: "10px"
    }
  }, "+ Sor hozz\xE1ad\xE1sa")));
};
const ImageUploadControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange
}) => {
  const openMediaUploader = () => {
    if (isReadonly) return;
    const wpMedia = window.wp.media({
      title: "Kép kiválasztása vagy feltöltése",
      button: {
        text: "Kép használata"
      },
      multiple: false
    });
    wpMedia.on("select", () => {
      const attachment = wpMedia.state().get("selection").first().toJSON();
      onChange(fieldKey, attachment.url);
    });
    wpMedia.open();
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      opacity: isReadonly ? 0.7 : 1
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "4px"
    }
  }, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(HelpTextUi, {
    text: helpText
  }), value && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "10px",
      border: "1px solid #ddd",
      padding: "5px",
      display: "inline-block"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    src: value,
    alt: "Preview",
    style: {
      maxWidth: "200px",
      maxHeight: "150px",
      display: "block"
    }
  })), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isSecondary: true,
    onClick: openMediaUploader
  }, value ? "Kép cseréje" : "Kép feltöltése"), value && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isDestructive: true,
    isLink: true,
    onClick: () => onChange(fieldKey, ""),
    style: {
      marginLeft: "10px"
    }
  }, "T\xF6rl\xE9s")));
};
const SZEducateEditor = () => {
  const {
    postId,
    nonce,
    restUrl,
    schema,
    permissions,
    existingTitle,
    existingData
  } = window.szEducateData || {};
  const [title, setTitle] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(existingTitle || "");
  const [formData, setFormData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(existingData || {});
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [message, setMessage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const actions = permissions?.actions || {
    create: true,
    edit: true,
    delete: false
  };
  const isNewPost = !existingTitle;
  const globalReadonly = !isNewPost && !actions.edit;
  const canSave = isNewPost ? actions.create : actions.edit;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (!schema || !existingData) return;
    let needsMigration = false;
    const migratedData = {
      ...existingData
    };
    schema.forEach(group => {
      if (group.fields) {
        group.fields.forEach(field => {
          const val = migratedData[field.key];
          if (val !== undefined && val !== null && val !== "") {
            if (field.type === "repeater" && typeof val === "string") {
              const firstCol = field.sub_fields && field.sub_fields.length > 0 ? field.sub_fields[0].key : "col1";
              migratedData[field.key] = [{
                [firstCol]: val
              }];
              needsMigration = true;
            } else if (field.type === "checkbox" && typeof val === "string") {
              migratedData[field.key] = val.split(",").map(v => v.trim());
              needsMigration = true;
            } else if (field.type === "links" && typeof val === "string") {
              migratedData[field.key] = [{
                title: "Kattints ide",
                url: val.startsWith("http") ? val : "https://" + val
              }];
              needsMigration = true;
            }
          }
        });
      }
    });
    if (needsMigration) setFormData(migratedData);
  }, [schema]);
  const handleChange = (key, value) => setFormData(prev => ({
    ...prev,
    [key]: value
  }));
  const renderField = field => {
    const value = formData[field.key] || "";
    // JAVÍTÁS: Csak akkor tesz csillagot, ha a is_required be van pipálva a hubon.
    const requiredMark = field.is_required ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        color: "#d63638",
        marginLeft: "4px"
      }
    }, "*") : "";
    const isReadonly = !!field.is_readonly || globalReadonly;
    const readonlyMark = isReadonly ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        color: "#888",
        fontSize: "12px",
        fontWeight: "normal",
        marginLeft: "6px"
      }
    }, "(Csak olvashat\xF3)") : "";
    const labelWithRequired = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        fontWeight: 600,
        fontSize: "13px",
        color: "#1d2327",
        display: "inline-flex",
        alignItems: "center"
      }
    }, field.label, " ", requiredMark, " ", readonlyMark);
    const helpStr = field.help_text || "";
    const isFilterableStr = field.is_filterable && !isReadonly ? "Indexelt mező." : "";
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
        const showEmailWarning = isEmail && emailVal && !emailVal.toLowerCase().trim().endsWith("@sze.hu");
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: labelWithRequired,
          type: field.type === "date" ? "date" : field.type === "url" ? "url" : field.type === "email" ? "email" : field.type,
          value: value,
          onChange: val => handleChange(field.key, val),
          help: combinedHelp,
          disabled: isReadonly
        }), showEmailWarning && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          style: {
            color: "#856404",
            backgroundColor: "#fff3cd",
            padding: "8px 12px",
            borderRadius: "4px",
            fontSize: "12px",
            marginTop: "10px",
            border: "1px solid #ffeeba"
          }
        }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", null, "\u26A0\uFE0F Figyelem:"), " K\xE9rj\xFCk, lehet\u0151s\xE9g szerint hivatalos egyetemi email c\xEDmet (@sze.hu v\xE9gz\u0151d\xE9ssel) adj meg!"));
        break;
      case "textarea":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
          label: labelWithRequired,
          value: value,
          help: combinedHelp,
          onChange: val => handleChange(field.key, val),
          disabled: isReadonly
        });
        break;
      case "wysiwyg":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(WysiwygControl, {
          label: labelWithRequired,
          fieldKey: field.key,
          value: value,
          isReadonly: isReadonly,
          helpText: combinedHelp,
          onChange: handleChange
        });
        break;
      case "links":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(LinksControl, {
          label: labelWithRequired,
          fieldKey: field.key,
          value: value,
          isReadonly: isReadonly,
          helpText: combinedHelp,
          onChange: handleChange
        });
        break;
      case "repeater":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(RepeaterControl, {
          label: labelWithRequired,
          field: field,
          value: value,
          isReadonly: isReadonly,
          helpText: combinedHelp,
          onChange: handleChange
        });
        break;
      case "select":
      case "radio":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: labelWithRequired,
          value: value,
          options: parseOptions(field.options),
          help: combinedHelp,
          onChange: val => handleChange(field.key, val),
          disabled: isReadonly
        });
        break;
      case "boolean":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: labelWithRequired,
          checked: !!value,
          help: combinedHelp,
          onChange: val => handleChange(field.key, val),
          disabled: isReadonly
        });
        break;
      case "checkbox":
        const chkOptions = field.options ? field.options.split(",").map(o => o.trim()) : [];
        const selectedValues = Array.isArray(value) ? value : typeof value === "string" && value !== "" ? value.split(",").map(v => v.trim()) : [];
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          style: {
            opacity: isReadonly ? 0.7 : 1,
            pointerEvents: isReadonly ? "none" : "auto"
          }
        }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          style: {
            marginBottom: "4px"
          }
        }, labelWithRequired), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(HelpTextUi, {
          text: combinedHelp
        }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          style: {
            marginTop: "12px",
            display: "flex",
            flexDirection: "column",
            gap: "10px"
          }
        }, chkOptions.map(opt => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CheckboxControl, {
          key: opt,
          label: opt,
          checked: selectedValues.includes(opt),
          disabled: isReadonly,
          onChange: isChecked => {
            const newVal = isChecked ? [...selectedValues, opt] : selectedValues.filter(v => v !== opt);
            handleChange(field.key, newVal);
          },
          style: {
            marginBottom: 0
          }
        }))));
        break;
      case "image":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ImageUploadControl, {
          label: labelWithRequired,
          fieldKey: field.key,
          value: value,
          isReadonly: isReadonly,
          helpText: combinedHelp,
          onChange: handleChange
        });
        break;
      default:
        control = null;
    }
    if (!control) return null;
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: field.key,
      style: {
        marginBottom: "30px",
        paddingBottom: "25px",
        borderBottom: "1px solid #f0f0f1"
      }
    }, control);
  };
  const validateForm = () => {
    if (!title || title.trim() === "") return "A Képzés Címe (Szak megnevezése) kötelező!";
    if (!formData["kepzesi_forma"]) return "A Képzési Forma kiválasztása kötelező!";
    const activeFormat = formData["kepzesi_forma"];
    const fixedFormats = ["BSc", "MSc", "Osztatlan", "Felsőoktatási szakképzés", "Szakirányú továbbképzés", "Mikroképzés", "Előkészítő"];
    if (schema && schema.length > 0) {
      for (const group of schema) {
        let isVisible = true;
        if (group.group_id !== "alap_adatok") {
          if (fixedFormats.includes(group.group_label)) {
            isVisible = group.group_label === activeFormat;
          } else if (group.condition && group.condition.operator) {
            const c = group.condition;
            const targetVal = formData[c.field];
            const stringVal = Array.isArray(targetVal) ? targetVal.join(",") : String(targetVal || "");
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
      setMessage({
        type: "error",
        text: errorMsg
      });
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
      return;
    }
    setIsSaving(true);
    setMessage(null);
    const processedData = {
      ...formData
    };
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
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": nonce
      },
      body: JSON.stringify({
        local_post_id: postId,
        title: title,
        course_data: processedData
      })
    }).then(res => res.json()).then(data => {
      if (data.success) setMessage({
        type: "success",
        text: data.message
      });else setMessage({
        type: "error",
        text: data.message || data.code
      });
      setIsSaving(false);
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }).catch(err => {
      setMessage({
        type: "error",
        text: "Kritikus hálózati hiba történt!"
      });
      setIsSaving(false);
    });
  };
  const buildTabs = () => {
    if (!schema || schema.length === 0) return [];
    const activeFormat = formData["kepzesi_forma"] || "";
    const fixedFormats = ["BSc", "MSc", "Osztatlan", "Felsőoktatási szakképzés", "Szakirányú továbbképzés", "Mikroképzés", "Előkészítő"];
    return schema.filter(group => {
      if (group.group_id === "alap_adatok") return true;
      if (fixedFormats.includes(group.group_label)) return group.group_label === activeFormat;
      if (group.condition && group.condition.operator) {
        const c = group.condition;
        const targetVal = formData[c.field];
        const stringVal = Array.isArray(targetVal) ? targetVal.join(",") : String(targetVal || "");
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
    }).map(group => ({
      name: group.group_id,
      title: group.group_label,
      className: "szeducate-tab-" + group.group_id,
      fields: group.fields
    }));
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "szeducate-react-wrapper",
    style: {
      maxWidth: "1000px",
      margin: "0 auto"
    }
  }, message && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: message.type,
    isDismissible: false,
    style: {
      marginBottom: "20px"
    }
  }, message.text), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Panel, {
    header: `SZEducate Képzés Szerkesztő ${globalReadonly ? "(Csak Megtekintés)" : ""}`
  }, schema && schema.length > 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      background: "#fff",
      border: "1px solid #e2e4e7"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TabPanel, {
    className: "szeducate-tabs",
    activeClass: "is-active",
    tabs: buildTabs()
  }, tab => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      padding: "20px"
    }
  }, tab.name === "alap_adatok" && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "30px",
      paddingBottom: "25px",
      borderBottom: "1px solid #f0f0f1"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        fontWeight: 600,
        fontSize: "13px",
        color: "#1d2327",
        display: "inline-flex",
        alignItems: "center"
      }
    }, "K\xE9pz\xE9s C\xEDme (Szak megnevez\xE9se)", " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        color: "#d63638",
        marginLeft: "4px"
      }
    }, "*"), " ", globalReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        color: "#888",
        fontSize: "12px",
        fontWeight: "normal",
        marginLeft: "6px"
      }
    }, "(Csak olvashat\xF3)")),
    value: title,
    onChange: value => setTitle(value),
    help: "Add meg a k\xE9pz\xE9s pontos, hivatalos megnevez\xE9s\xE9t.",
    disabled: globalReadonly
  })), tab.fields && tab.fields.map(field => renderField(field))))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: "warning",
    isDismissible: false,
    style: {
      marginTop: "20px"
    }
  }, "Hi\xE1nyz\xF3 s\xE9ma! K\xE9rlek szinkroniz\xE1lj a Hubbal a Be\xE1ll\xEDt\xE1sokban.")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginTop: "20px",
      padding: "20px",
      background: "#fff",
      border: "1px solid #e2e4e7",
      display: "flex",
      justifyContent: "space-between",
      alignItems: "center"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      fontSize: "12px",
      color: "#666"
    }
  }, globalReadonly ? "Nincs jogosultságod módosítani ezt a képzést." : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, "A ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      color: "#d63638"
    }
  }, "*"), "-gal jel\xF6lt mez\u0151k kit\xF6lt\xE9se k\xF6telez\u0151.")), canSave && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isPrimary: true,
    isBusy: isSaving,
    onClick: handleSave,
    style: {
      padding: "5px 30px",
      backgroundColor: "#007cba"
    }
  }, isSaving ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null), " Ment\xE9s...") : "Véglegesítés és Mentés")));
};
document.addEventListener("DOMContentLoaded", () => {
  const rootElement = document.getElementById("szeducate-react-root");
  if (rootElement) {
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.render)((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SZEducateEditor, null), rootElement);
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map