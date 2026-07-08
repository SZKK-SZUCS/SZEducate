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

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

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
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);




const FIXED_FORMATS = ["BSc", "MSc", "Osztatlan", "Felsőoktatási szakképzés", "Szakirányú továbbképzés", "Mikroképzés", "Előkészítő"];
const parseOptions = optionsString => {
  if (!optionsString) return [{
    label: "Válassz...",
    value: ""
  }];
  const opts = optionsString.split(";").map(opt => opt.trim()).filter(opt => opt !== "").map(opt => ({
    label: opt,
    value: opt
  }));
  return [{
    label: "Válassz...",
    value: ""
  }, ...opts];
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
};

// Ezek a mezőtípusok jellemzően rövidek (egy input/dropdown/kapcsoló szélességűek),
// ezért egy sorba kerülhetnek egymás mellé - a többi (textarea, wysiwyg, lista-jellegű
// mezők) mindig saját, teljes szélességű sort kap. A tényleges elrendezés (hány fér el
// egymás mellett) a flexbox tördelésre van bízva, a konténer aktuális szélessége szerint.
const COMPACT_FIELD_TYPES = ["text", "number", "date", "email", "url", "select", "radio", "boolean", "true_false"];
const isCompactFieldType = type => COMPACT_FIELD_TYPES.includes(type);
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
const getGroupCompletion = group => {
  if (!group.fields) return null;
  const required = group.fields.filter(f => f.is_required && !f.is_readonly);
  if (required.length === 0) return null;
  return required;
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
const EmptyStateRow = ({
  children
}) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
  style: {
    padding: "14px",
    textAlign: "center",
    color: "#8c8f94",
    fontSize: "13px",
    background: "#fbfbfc",
    border: "1px dashed #dcdcde",
    borderRadius: "4px"
  }
}, children);
const KeywordControl = ({
  label,
  fieldKey,
  value,
  isReadonly,
  helpText,
  onChange
}) => {
  const [suggestions, setSuggestions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)([]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: `/szeducate/v1/client/field-options?key=${fieldKey}`
    }).then(res => {
      setSuggestions(res);
    }).catch(() => {});
  }, [fieldKey]);
  const tokens = typeof value === "string" && value !== "" ? value.split(";").map(v => v.trim()).filter(Boolean) : Array.isArray(value) ? value : [];
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
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FormTokenField, {
    value: tokens,
    suggestions: suggestions,
    onChange: newTokens => {
      onChange(fieldKey, newTokens.join("; "));
    },
    disabled: isReadonly
  }));
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
          plugins: "paste,lists,link,textcolor,colorpicker,table",
          toolbar1: "formatselect,bold,italic,underline,bullist,numlist,link,unlink,forecolor,backcolor,table",
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
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      border: "1px solid #ddd",
      borderRadius: "4px",
      overflow: "hidden"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("textarea", {
    id: editorId,
    defaultValue: value || "",
    style: {
      width: "100%",
      minHeight: "220px",
      display: "block"
    },
    disabled: isReadonly
  })));
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
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "4px"
    }
  }, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(HelpTextUi, {
    text: helpText
  }), links.length === 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(EmptyStateRow, null, "M\xE9g nincs hozz\xE1adva egyetlen link sem.") : links.map((link, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    key: index,
    style: {
      display: "flex",
      gap: "10px",
      marginBottom: "10px",
      alignItems: "center"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      width: "24px",
      color: "#a7aaad",
      fontSize: "12px",
      textAlign: "right",
      flexShrink: 0
    }
  }, index + 1, "."), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
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
    onClick: () => removeLink(index),
    label: "Link elt\xE1vol\xEDt\xE1sa"
  }, "T\xF6rl\xE9s"))), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isSecondary: true,
    onClick: addLink,
    style: {
      marginTop: "6px"
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
      opacity: isReadonly ? 0.7 : 1
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "4px"
    }
  }, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(HelpTextUi, {
    text: helpText
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginTop: "10px",
      paddingTop: "12px",
      borderTop: "1px solid #eceef0",
      overflowX: "auto"
    }
  }, rows.length === 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(EmptyStateRow, null, "M\xE9g nincs hozz\xE1adva egyetlen sor sem.") : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("table", {
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
      fontSize: "12px",
      textTransform: "uppercase",
      letterSpacing: "0.02em",
      color: "#50575e"
    }
  }, sf.label)), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("th", {
    style: {
      width: "50px"
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
    onClick: () => removeRow(index),
    label: "Sor elt\xE1vol\xEDt\xE1sa"
  }, "\xD7")))))), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isSecondary: true,
    onClick: addRow,
    style: {
      marginTop: "12px"
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
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: "16px",
      flexWrap: "wrap"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    onClick: openMediaUploader,
    style: {
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
      flexShrink: 0
    }
  }, value ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    src: value,
    alt: "El\u0151n\xE9zet",
    style: {
      maxWidth: "100%",
      maxHeight: "100%",
      objectFit: "contain"
    }
  }) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      color: "#a7aaad",
      fontSize: "12px"
    }
  }, "Nincs k\xE9p")), !isReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "8px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isSecondary: true,
    onClick: openMediaUploader
  }, value ? "Kép cseréje" : "Kép feltöltése"), value && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isDestructive: true,
    isLink: true,
    onClick: () => onChange(fieldKey, "")
  }, "K\xE9p elt\xE1vol\xEDt\xE1sa"))));
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
    existingData
  } = window.szEducateData || {};
  const [title, setTitle] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(existingTitle || "");
  const [formData, setFormData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(existingData || {});
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [message, setMessage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [errorField, setErrorField] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [jumpToTab, setJumpToTab] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [tabForceKey, setTabForceKey] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(0);
  const [lastSavedAt, setLastSavedAt] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  // Néhány mezőtípus (pl. WYSIWYG) nem kontrollált komponens - a saját belső állapotát
  // csak induláskor olvassa be, ezért egy programozott visszaállításnál (mező-reset vagy
  // verzió-visszaállítás) újra kell "kényszeríteni" a mountolását, hogy a látható tartalom
  // is frissüljön, ne csak a React state a háttérben. Ez a számláló szolgál erre.
  const [resetTick, setResetTick] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(0);
  const [versions, setVersions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)([]);
  const [isLoadingVersions, setIsLoadingVersions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [isRestoring, setIsRestoring] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const fieldRefs = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)({});
  // A legutóbb MENTETT állapot (nem a jelenleg szerkesztett!) - ehhez viszonyítva
  // számoljuk ki, mely mezők változtak azóta, és ez a "dirty" jelző alapja is.
  const savedSnapshot = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)({
    title: existingTitle || "",
    formData: existingData || {}
  });
  const actions = permissions?.actions || {
    create: true,
    edit: true,
    delete: false
  };
  const isNewPost = !existingTitle;
  const globalReadonly = !isNewPost && !actions.edit;
  const canSave = isNewPost ? actions.create : actions.edit;
  const isDirty = JSON.stringify({
    title,
    formData
  }) !== JSON.stringify(savedSnapshot.current);
  const isFieldChangedSinceSave = key => {
    var _formData$key, _savedSnapshot$curren;
    return JSON.stringify((_formData$key = formData[key]) !== null && _formData$key !== void 0 ? _formData$key : null) !== JSON.stringify((_savedSnapshot$curren = savedSnapshot.current.formData[key]) !== null && _savedSnapshot$curren !== void 0 ? _savedSnapshot$curren : null);
  };
  const fetchVersions = () => {
    if (!postId || !versionsUrl) return;
    setIsLoadingVersions(true);
    fetch(`${versionsUrl}?post_id=${postId}`, {
      headers: {
        "X-WP-Nonce": nonce
      }
    }).then(res => res.json()).then(data => {
      if (data.success) setVersions(data.versions || []);
    }).catch(() => {}).finally(() => setIsLoadingVersions(false));
  };
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    fetchVersions();
  }, [postId]);
  const handleRestoreVersion = version => {
    if (!window.confirm(`Biztosan betöltöd ezt a verziót (${new Date(version.edited_at.replace(" ", "T")).toLocaleString("hu-HU")}, módosította: ${version.edited_by})?\n\nA jelenlegi űrlap tartalma felülíródik, de MENTÉSIG semmi nem változik ténylegesen - utána még ellenőrizheted, mielőtt elmented.`)) {
      return;
    }
    setIsRestoring(version.id);
    fetch(`${versionsUrl}/${version.id}?post_id=${postId}`, {
      headers: {
        "X-WP-Nonce": nonce
      }
    }).then(res => res.json()).then(data => {
      if (data.success) {
        setTitle(data.title);
        setFormData(data.course_data || {});
        setResetTick(t => t + 1);
        setMessage({
          type: "warning",
          text: `Betöltve egy korábbi verzió (${new Date(data.edited_at.replace(" ", "T")).toLocaleString("hu-HU")}, ${data.edited_by}). Ellenőrizd az adatokat, majd mentsd el, ha megfelel!`
        });
        window.scrollTo({
          top: 0,
          behavior: "smooth"
        });
      } else {
        setMessage({
          type: "error",
          text: "Nem sikerült betölteni a verziót."
        });
      }
    }).catch(() => {
      setMessage({
        type: "error",
        text: "Hálózati hiba a verzió betöltésekor."
      });
    }).finally(() => setIsRestoring(null));
  };
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    const handler = e => {
      if (!isDirty) return;
      e.preventDefault();
      e.returnValue = "";
      return "";
    };
    window.addEventListener("beforeunload", handler);
    return () => window.removeEventListener("beforeunload", handler);
  }, [isDirty]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (!message || message.type !== "success") return;
    const t = setTimeout(() => setMessage(null), 6000);
    return () => clearTimeout(t);
  }, [message]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (!errorField) return;
    const t = setTimeout(() => {
      const el = fieldRefs.current[errorField];
      if (el) el.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });
    }, 60);
    return () => clearTimeout(t);
  }, [errorField, tabForceKey]);
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
              migratedData[field.key] = val.split(";").map(v => v.trim());
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
    if (needsMigration) {
      setFormData(migratedData);
      savedSnapshot.current = {
        title: existingTitle || "",
        formData: migratedData
      };
    }
  }, [schema]);
  const handleChange = (key, value) => {
    if (errorField === key) setErrorField(null);
    setFormData(prev => ({
      ...prev,
      [key]: value
    }));
  };

  // Egy adott mező visszaállítása a legutóbb MENTETT értékére - nem az egész
  // formot, csak azt az egy mezőt érinti.
  const resetFieldToSaved = key => {
    if (errorField === key) setErrorField(null);
    setFormData(prev => {
      const next = {
        ...prev
      };
      const savedVal = savedSnapshot.current.formData[key];
      if (savedVal === undefined) {
        delete next[key];
      } else {
        next[key] = savedVal;
      }
      return next;
    });
    setResetTick(t => t + 1);
  };
  const ResetButton = ({
    onClick,
    label = "Vissza"
  }) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    onClick: e => {
      e.preventDefault();
      e.stopPropagation();
      onClick();
    },
    title: "Vissza\xE1ll\xEDt\xE1s az utolj\xE1ra mentett \xE9rt\xE9kre",
    style: {
      marginLeft: "6px",
      background: "none",
      border: "none",
      cursor: "pointer",
      color: "#8a6100",
      fontSize: "11px",
      fontWeight: 700,
      padding: 0,
      textDecoration: "underline",
      verticalAlign: "middle"
    }
  }, "\u21BA ", label);
  const renderField = field => {
    const value = formData[field.key] !== undefined ? formData[field.key] : "";
    const requiredMark = field.is_required ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        color: "#d63638",
        marginLeft: "4px"
      }
    }, "*") : "";
    const isReadonly = !!field.is_readonly || globalReadonly;
    const isChanged = !isReadonly && isFieldChangedSinceSave(field.key);
    const readonlyMark = isReadonly ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
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
        verticalAlign: "middle"
      }
    }, "Z\xE1rolva") : "";
    const changedMark = isChanged ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
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
        verticalAlign: "middle"
      }
    }, "M\xF3dos\xEDtva"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ResetButton, {
      onClick: () => resetFieldToSaved(field.key)
    })) : "";
    const labelWithRequired = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        fontWeight: 600,
        fontSize: "13px",
        color: "#1d2327",
        display: "inline-flex",
        alignItems: "center"
      }
    }, field.label, " ", requiredMark, " ", readonlyMark, " ", changedMark);
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
        if (field.key === "kulcsszavak") {
          control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(KeywordControl, {
            label: labelWithRequired,
            fieldKey: field.key,
            value: value,
            isReadonly: isReadonly,
            helpText: combinedHelp,
            onChange: handleChange
          });
        } else {
          control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
            label: labelWithRequired,
            value: value,
            help: combinedHelp,
            onChange: val => handleChange(field.key, val),
            disabled: isReadonly
          });
        }
        break;
      case "wysiwyg":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(WysiwygControl, {
          key: `${field.key}-${resetTick}`,
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
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          label: labelWithRequired,
          value: value,
          options: parseOptions(field.options),
          help: combinedHelp,
          onChange: val => handleChange(field.key, val),
          disabled: isReadonly
        });
        break;
      case "radio":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          style: {
            opacity: isReadonly ? 0.7 : 1,
            pointerEvents: isReadonly ? "none" : "auto"
          }
        }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RadioControl, {
          label: labelWithRequired,
          selected: value,
          options: parseOptions(field.options).filter(o => o.value !== ""),
          help: combinedHelp,
          onChange: val => handleChange(field.key, val),
          disabled: isReadonly
        }));
        break;
      case "boolean":
      case "true_false":
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: labelWithRequired,
          checked: !!value,
          help: combinedHelp,
          onChange: val => handleChange(field.key, val),
          disabled: isReadonly
        });
        break;
      case "checkbox":
        const chkOptions = field.options ? field.options.split(";").map(o => o.trim()).filter(o => o !== "") : [];
        const selectedValues = Array.isArray(value) ? value : typeof value === "string" && value !== "" ? value.split(";").map(v => v.trim()) : [];
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
        }, chkOptions.length === 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(EmptyStateRow, null, "Ehhez a mez\u0151h\xF6z nincsenek be\xE1ll\xEDtva v\xE1laszthat\xF3 opci\xF3k.") : chkOptions.map(opt => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CheckboxControl, {
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
        // Ismeretlen/jövőbeli mezőtípus: soha ne tűnjön el csendben egy mező csak
        // mert a séma olyan típust kapott, amit ez a build még nem ismer - inkább
        // egy nyers szöveges szerkesztőt kap, egyértelmű figyelmeztetéssel.
        control = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: labelWithRequired,
          value: typeof value === "string" ? value : JSON.stringify(value !== null && value !== void 0 ? value : ""),
          onChange: val => handleChange(field.key, val),
          help: combinedHelp,
          disabled: isReadonly
        }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          style: {
            color: "#856404",
            backgroundColor: "#fff3cd",
            padding: "6px 10px",
            borderRadius: "4px",
            fontSize: "12px",
            marginTop: "6px",
            border: "1px solid #ffeeba"
          }
        }, "Ismeretlen mez\u0151t\xEDpus (\"", field.type, "\") - nyers sz\xF6vegszerkeszt\u0151 jelenik meg helyette."));
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
    if (hasError) accentBorder = "4px solid #d63638";else if (isChanged) accentBorder = "4px solid #dba617";
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: field.key,
      ref: el => fieldRefs.current[field.key] = el,
      style: {
        gridColumn: compact ? "auto" : "1 / -1",
        boxSizing: "border-box",
        padding: "16px 18px",
        background: cardBg,
        border: `1px solid ${cardBorder}`,
        borderLeft: accentBorder,
        borderRadius: "8px",
        transition: "background 0.15s, border-color 0.15s"
      }
    }, control, hasError && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
      style: {
        color: "#d63638",
        fontSize: "12px",
        fontWeight: 600,
        marginTop: "8px",
        marginBottom: 0
      }
    }, "Ez a mez\u0151 kit\xF6lt\xE9se k\xF6telez\u0151."));
  };
  const visibleGroups = () => schema && schema.length > 0 ? schema.filter(group => isGroupVisible(group, formData)) : [];
  const validateForm = () => {
    if (!title || title.trim() === "") {
      return {
        message: "A Képzés Címe (Szak megnevezése) kötelező!",
        groupId: null,
        fieldKey: "__title__"
      };
    }
    if (!formData["kepzesi_forma"]) {
      return {
        message: "A Képzési Forma kiválasztása kötelező!",
        groupId: "alap_adatok",
        fieldKey: "kepzesi_forma"
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
            fieldKey: field.key
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
      setMessage({
        type: "error",
        text: error.message
      });
      setErrorField(error.fieldKey);
      if (error.fieldKey === "__title__") {
        window.scrollTo({
          top: 0,
          behavior: "smooth"
        });
      } else if (error.groupId) {
        setJumpToTab(error.groupId);
        setTabForceKey(k => k + 1);
      }
      return;
    }
    setErrorField(null);
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
          processedData[key] = val.join("; ");
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
      if (data.success) {
        setMessage({
          type: "success",
          text: data.message
        });
        savedSnapshot.current = {
          title,
          formData
        };
        setLastSavedAt(new Date());
        fetchVersions();
      } else {
        setMessage({
          type: "error",
          text: data.message || data.code
        });
      }
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
    return visibleGroups().map(group => {
      const required = getGroupCompletion(group);
      let badge = null;
      if (required && !globalReadonly) {
        const filled = required.filter(f => !isFieldEmpty(f, formData[f.key])).length;
        const complete = filled === required.length;
        badge = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
          style: {
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
            border: `1px solid ${complete ? "#b4e3bc" : "#f0dca0"}`
          }
        }, complete ? "✓" : `${filled}/${required.length}`);
      }
      return {
        name: group.group_id,
        title: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, group.group_label, badge),
        className: "szeducate-tab-" + group.group_id,
        fields: group.fields
      };
    });
  };
  const tabs = buildTabs();
  const overallRequired = visibleGroups().flatMap(g => getGroupCompletion(g) || []);
  const overallFilled = overallRequired.filter(f => !isFieldEmpty(f, formData[f.key])).length;

  // key -> label az összes séma-mezőhöz (fülektől függetlenül), a verzió-előzmények
  // és a "módosítva az utolsó mentés óta" lista feliratozásához.
  const allSchemaFields = (schema || []).flatMap(g => g.fields || []);
  const fieldLabelByKey = allSchemaFields.reduce((acc, f) => {
    acc[f.key] = f.label;
    return acc;
  }, {});
  const changedSinceSave = [...(title !== savedSnapshot.current.title ? ["Cím"] : []), ...allSchemaFields.filter(f => isFieldChangedSinceSave(f.key)).map(f => f.label)];
  const describeChangedFields = keys => (keys || []).map(key => {
    if (key === "__initial__") return "Kezdeti verzió";
    if (key === "__title__") return "Cím";
    return fieldLabelByKey[key] || key;
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "szeducate-react-wrapper",
    style: {
      maxWidth: "1200px",
      margin: "0 auto"
    }
  }, message && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: message.type,
    isDismissible: true,
    onRemove: () => setMessage(null),
    style: {
      marginBottom: "20px"
    }
  }, message.text), globalReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: "warning",
    isDismissible: false,
    style: {
      marginBottom: "20px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", null, "Figyelem:"), " Nincs jogosults\xE1god a k\xE9pz\xE9s adatainak m\xF3dos\xEDt\xE1s\xE1ra. Az \u0171rlap csak olvashat\xF3 m\xF3dban ny\xEDlt meg."), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      gap: "20px",
      alignItems: "flex-start",
      flexWrap: "wrap",
      position: "relative"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      flex: "3 1 620px",
      minWidth: 0
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      background: "#fff",
      border: "1px solid #c3c4c7",
      borderRadius: "6px",
      boxShadow: "0 1px 2px rgba(0,0,0,.05)"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    style: {
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
      gap: "8px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, "K\xE9pz\xE9s R\xE9szletei ", globalReadonly ? "(Csak Megtekintés)" : ""), !globalReadonly && overallRequired.length > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      fontSize: "12px",
      fontWeight: 500,
      color: overallFilled === overallRequired.length ? "#1a7f37" : "#996800"
    }
  }, "K\xF6telez\u0151 mez\u0151k: ", overallFilled, "/", overallRequired.length, " ", "kit\xF6ltve")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      padding: "20px"
    }
  }, schema && schema.length > 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TabPanel, {
    key: tabForceKey,
    className: "szeducate-tabs",
    activeClass: "is-active",
    initialTabName: jumpToTab || undefined,
    tabs: tabs
  }, tab => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      padding: "20px 0",
      display: "grid",
      // Legfeljebb 2 oszlop - de ha a hely szűkös (pl. keskenyebb ablak),
      // magától 1 oszlopra esik vissza ahelyett, hogy összenyomná a mezőket.
      gridTemplateColumns: "repeat(auto-fit, minmax(max(240px, calc((100% - 16px) / 2)), 1fr))",
      alignItems: "start",
      gap: "16px"
    }
  }, tab.fields && tab.fields.map(field => renderField(field)))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: "warning",
    isDismissible: false,
    style: {
      marginTop: "20px"
    }
  }, "Hi\xE1nyz\xF3 s\xE9ma! K\xE9rlek szinkroniz\xE1lj a Hubbal a Be\xE1ll\xEDt\xE1sokban.")))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      flex: "1 1 280px",
      position: "sticky",
      top: "50px",
      zIndex: 10
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      background: "#fff",
      border: "1px solid #c3c4c7",
      borderRadius: "6px",
      boxShadow: "0 1px 2px rgba(0,0,0,.05)"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    style: {
      padding: "15px 20px",
      margin: 0,
      borderBottom: "1px solid #c3c4c7",
      fontSize: "14px",
      fontWeight: 600,
      background: "#f6f7f7",
      borderRadius: "6px 6px 0 0"
    }
  }, "Ment\xE9s \xE9s Megnevez\xE9s"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      padding: "20px"
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
    }, "*"), " ", title !== savedSnapshot.current.title && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
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
        verticalAlign: "middle"
      }
    }, "M\xF3dos\xEDtva"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ResetButton, {
      onClick: () => {
        setTitle(savedSnapshot.current.title);
        if (errorField === "__title__") setErrorField(null);
      }
    }))),
    value: title,
    onChange: value => {
      if (errorField === "__title__") setErrorField(null);
      setTitle(value);
    },
    help: "Ez jelenik meg a list\xE1kban \xE9s a c\xEDmekben.",
    disabled: globalReadonly,
    style: {
      marginBottom: "20px",
      outline: errorField === "__title__" ? "2px solid #d63638" : "none",
      borderRadius: errorField === "__title__" ? "4px" : undefined
    }
  }), !globalReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isPrimary: true,
    isLarge: true,
    style: {
      width: "100%",
      justifyContent: "center",
      marginTop: "10px"
    },
    onClick: handleSave,
    disabled: isSaving
  }, isSaving ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null) : "Adatlap Mentése"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginTop: "12px",
      textAlign: "center",
      fontSize: "12px"
    }
  }, isDirty ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      color: "#996800",
      fontWeight: 600
    }
  }, "\u25CF Nem mentett m\xF3dos\xEDt\xE1sok") : lastSavedAt ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      color: "#1a7f37"
    }
  }, "\u2713 Mentve", " ", lastSavedAt.toLocaleTimeString("hu-HU", {
    hour: "2-digit",
    minute: "2-digit"
  }), "-kor") : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      color: "#8c8f94"
    }
  }, "Nincs m\xE9g mentett m\xF3dos\xEDt\xE1s ebben a munkamenetben."))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      fontSize: "12px",
      color: "#666",
      marginTop: "15px",
      marginBottom: 0,
      textAlign: "center"
    }
  }, globalReadonly ? "Nincs jogosultságod módosítani ezt a képzést." : "A csillaggal (*) jelölt mezők kitöltése kötelező."))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      background: "#fff",
      border: "1px solid #c3c4c7",
      borderRadius: "6px",
      boxShadow: "0 1px 2px rgba(0,0,0,.05)",
      marginTop: "16px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    style: {
      padding: "15px 20px",
      margin: 0,
      borderBottom: "1px solid #c3c4c7",
      fontSize: "14px",
      fontWeight: 600,
      background: "#f6f7f7",
      borderRadius: "6px 6px 0 0"
    }
  }, "Verzi\xF3 El\u0151zm\xE9nyek"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      padding: "20px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      marginBottom: "20px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      fontSize: "11px",
      fontWeight: 700,
      color: "#50575e",
      textTransform: "uppercase",
      letterSpacing: "0.03em",
      marginBottom: "8px"
    }
  }, "M\xF3dos\xEDtva az utols\xF3 ment\xE9s \xF3ta"), changedSinceSave.length === 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      fontSize: "12px",
      color: "#8c8f94",
      margin: 0
    }
  }, "Nincs mentetlen m\xF3dos\xEDt\xE1s.") : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      flexWrap: "wrap",
      gap: "6px"
    }
  }, changedSinceSave.map((label, i) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    key: i,
    style: {
      fontSize: "11px",
      fontWeight: 600,
      color: "#8a6100",
      background: "#fff2c9",
      border: "1px solid #f0dca0",
      borderRadius: "10px",
      padding: "3px 9px"
    }
  }, label)))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      justifyContent: "space-between",
      alignItems: "center",
      marginBottom: "8px"
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      fontSize: "11px",
      fontWeight: 700,
      color: "#50575e",
      textTransform: "uppercase",
      letterSpacing: "0.03em"
    }
  }, "Kor\xE1bbi verzi\xF3k"), postId ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    onClick: fetchVersions,
    style: {
      background: "none",
      border: "none",
      color: "#2271b1",
      fontSize: "11px",
      cursor: "pointer",
      padding: 0
    }
  }, "Friss\xEDt\xE9s") : null), !postId ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      fontSize: "12px",
      color: "#8c8f94",
      margin: 0
    }
  }, "Az el\u0151zm\xE9nyek az els\u0151 ment\xE9s ut\xE1n lesznek el\xE9rhet\u0151k.") : isLoadingVersions ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null) : versions.length === 0 ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    style: {
      fontSize: "12px",
      color: "#8c8f94",
      margin: 0
    }
  }, "M\xE9g nincs r\xF6gz\xEDtett verzi\xF3.") : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "8px",
      maxHeight: "380px",
      overflowY: "auto"
    }
  }, versions.map((v, index) => {
    const isCurrent = index === 0;
    const labels = describeChangedFields(v.changed_fields);
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: v.id,
      style: {
        border: "1px solid #e2e4e7",
        borderRadius: "6px",
        padding: "10px 12px",
        background: isCurrent ? "#f6fbf7" : "#fff"
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        display: "flex",
        justifyContent: "space-between",
        alignItems: "baseline",
        gap: "8px"
      }
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", {
      style: {
        fontSize: "12px"
      }
    }, new Date(v.edited_at.replace(" ", "T")).toLocaleString("hu-HU")), isCurrent && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        fontSize: "10px",
        fontWeight: 700,
        color: "#1a7f37",
        whiteSpace: "nowrap"
      }
    }, "JELENLEGI")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        fontSize: "11px",
        color: "#646970",
        marginTop: "2px"
      }
    }, v.edited_by), labels.length > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      style: {
        marginTop: "8px",
        display: "flex",
        flexWrap: "wrap",
        gap: "4px"
      }
    }, labels.slice(0, 6).map((l, i) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      key: i,
      style: {
        fontSize: "10px",
        background: "#f0f0f1",
        border: "1px solid #dcdcde",
        borderRadius: "8px",
        padding: "1px 6px",
        color: "#50575e"
      }
    }, l)), labels.length > 6 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      style: {
        fontSize: "10px",
        color: "#8c8f94"
      }
    }, "+", labels.length - 6, " tov\xE1bbi")), !isCurrent && !globalReadonly && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      isSecondary: true,
      isSmall: true,
      style: {
        marginTop: "10px"
      },
      onClick: () => handleRestoreVersion(v),
      disabled: isRestoring === v.id
    }, isRestoring === v.id ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null) : "Visszaállítás"));
  }))))))));
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