// Writes resources/lang/en/editor.php from the JS defaults so Laravel can translate the UI.
import fs from "fs";
import { defaultStrings } from "../resources/js/i18n.js";

const esc = (v) => v.replace(/\\/g, "\\\\").replace(/'/g, "\\'");
const lines = Object.entries(defaultStrings).map(
  ([k, v]) => `    '${k}' => '${esc(v)}',`,
);
fs.writeFileSync(
  new URL("../resources/lang/en/editor.php", import.meta.url),
  `<?php\n\n// Interface strings for the editor. Publish with --tag=desk-editor-lang to translate.\n\nreturn [\n${lines.join("\n")}\n];\n`,
);
console.log(`${lines.length} strings`);
