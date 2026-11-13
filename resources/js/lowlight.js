import { common, createLowlight } from "lowlight";
import dart from "highlight.js/lib/languages/dart";
import dockerfile from "highlight.js/lib/languages/dockerfile";

export const lowlight = createLowlight(common);
lowlight.register({ dart, dockerfile });
