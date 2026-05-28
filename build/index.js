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



const SZEducateEditor = () => {
  // 1. Kinyerjük a betöltött adatokat a window objektumból
  const {
    postId,
    nonce,
    restUrl,
    schema,
    existingTitle,
    existingData
  } = window.szEducateData || {};

  // 2. Beállítjuk a kezdőértékeket (Initial State)
  const [title, setTitle] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(existingTitle || "");
  const [formData, setFormData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(existingData || {});
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [message, setMessage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const handleChange = (key, value) => {
    setFormData(prev => ({
      ...prev,
      [key]: value
    }));
  };
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
  const renderField = field => {
    const value = formData[field.key] || "";
    switch (field.type) {
      case "text":
      case "number":
      case "date":
        return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          key: field.key,
          label: field.label,
          type: field.type === "date" ? "date" : field.type,
          value: value,
          onChange: val => handleChange(field.key, val),
          help: field.is_filterable ? "Ez egy szűrhető mező." : ""
        });
      case "textarea":
        return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextareaControl, {
          key: field.key,
          label: field.label,
          value: value,
          onChange: val => handleChange(field.key, val)
        });
      case "select":
      case "radio":
        return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          key: field.key,
          label: field.label,
          value: value,
          options: parseOptions(field.options),
          onChange: val => handleChange(field.key, val)
        });
      case "boolean":
        return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          key: field.key,
          label: field.label,
          checked: !!value,
          onChange: val => handleChange(field.key, val)
        });
      case "checkbox":
        const chkOptions = field.options ? field.options.split(",").map(o => o.trim()) : [];
        // Betöltéskor a DB-ben stringként ("Opció1, Opció2") szerepelhet, ezt vissza kell alakítani tömbbé
        const selectedValues = Array.isArray(value) ? value : typeof value === "string" && value !== "" ? value.split(",").map(v => v.trim()) : [];
        return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          key: field.key,
          style: {
            marginBottom: "24px"
          }
        }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
          style: {
            fontWeight: 600,
            marginBottom: "8px"
          }
        }, field.label), chkOptions.map(opt => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CheckboxControl, {
          key: opt,
          label: opt,
          checked: selectedValues.includes(opt),
          onChange: isChecked => {
            const newVal = isChecked ? [...selectedValues, opt] : selectedValues.filter(v => v !== opt);
            handleChange(field.key, newVal);
          }
        })));
      default:
        return null;
    }
  };
  const handleSave = () => {
    setIsSaving(true);
    setMessage(null);
    const processedData = {
      ...formData
    };
    for (const [key, val] of Object.entries(processedData)) {
      if (Array.isArray(val)) {
        processedData[key] = val.join(", ");
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
      } else {
        setMessage({
          type: "error",
          text: data.message || data.code
        });
      }
      setIsSaving(false);
    }).catch(err => {
      setMessage({
        type: "error",
        text: "Kritikus hálózati hiba történt!"
      });
      setIsSaving(false);
    });
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "szeducate-react-wrapper"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Panel, {
    header: "SZEducate K\xE9pz\xE9s Szerkeszt\u0151"
  }, message && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
    status: message.type,
    isDismissible: false
  }, message.text), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: "1. Alap Adatok (K\xF6telez\u0151)",
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: "K\xE9pz\xE9s C\xEDme (Szak megnevez\xE9se)",
    value: title,
    onChange: value => setTitle(value),
    placeholder: "pl. M\xE9rn\xF6kinformatikus BSc",
    required: true
  })), schema && schema.length > 0 ? schema.map((group, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    key: group.group_id,
    title: `2.${index + 1} ${group.group_label}`,
    initialOpen: false
  }, group.fields && group.fields.map(field => renderField(field)))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: "Hi\xE1nyz\xF3 S\xE9ma!"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, "A Kliens m\xE9g nem t\xF6lt\xF6tte le az adatb\xE1zis s\xE9m\xE1t. K\xE9rlek, menj a be\xE1ll\xEDt\xE1sokba \xE9s szinkroniz\xE1lj a Hub-bal!")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: "Ment\xE9s \xE9s Szinkroniz\xE1ci\xF3",
    initialOpen: true
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    isPrimary: true,
    isBusy: isSaving,
    disabled: isSaving || title.length === 0,
    onClick: handleSave,
    style: {
      padding: "5px 30px"
    }
  }, isSaving ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null), " Szinkroniz\xE1l\xE1s a k\xF6zponttal...") : "Véglegesítés és Mentés"))));
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