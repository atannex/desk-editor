/**
 * Normalises HTML pasted from Microsoft Word, Google Docs, LibreOffice and
 * web pages: drops office markup and inline styling, maps styled spans to
 * semantic marks and rebuilds Word's pseudo-lists as real <ul>/<ol>.
 */
const KEEP_ALIGN = /^(p|h[1-6]|li|td|th)$/i;

export function isOfficeHtml(html) {
  return /urn:schemas-microsoft-com:office|class="?Mso|<o:p>|docs-internal-guid|LibreOffice|xmlns:w=/i.test(
    html,
  );
}

export function cleanPastedHtml(html) {
  if (!html || typeof DOMParser === "undefined") return html;
  const office = isOfficeHtml(html);
  const source = html
    .replace(/<!--\[if[\s\S]*?<!\[endif\]-->/gi, "")
    .replace(/<!--[\s\S]*?-->/g, "");
  const doc = new DOMParser().parseFromString(source, "text/html");
  const body = doc.body;

  body
    .querySelectorAll("style, meta, link, xml, script, title, o\\:p, w\\:sdt")
    .forEach((el) => el.remove());
  // Google Docs wraps everything in <b style="font-weight:normal" id="docs-internal-guid-…">.
  body.querySelectorAll('b[id^="docs-internal-guid"]').forEach(unwrap);

  if (office) convertWordLists(doc);

  // Styled spans → semantic marks.
  body.querySelectorAll("span[style], font").forEach((span) => {
    const style = (span.getAttribute("style") || "").toLowerCase();
    let node = span;
    const wrap = (tag) => {
      const el = doc.createElement(tag);
      node.replaceWith(el);
      el.append(node);
      node = el;
    };
    if (/font-weight\s*:\s*(bold|[6-9]00)/.test(style)) wrap("strong");
    if (/font-style\s*:\s*italic/.test(style)) wrap("em");
    if (/text-decoration[^;]*underline/.test(style)) wrap("u");
    if (/text-decoration[^;]*line-through/.test(style)) wrap("s");
    if (/vertical-align\s*:\s*super/.test(style)) wrap("sup");
    if (/vertical-align\s*:\s*sub/.test(style)) wrap("sub");
    unwrap(span);
  });

  // Bold-tagged wrappers that Docs marks as normal weight.
  body.querySelectorAll("b, strong").forEach((el) => {
    if (/font-weight\s*:\s*(normal|400)/i.test(el.getAttribute("style") || ""))
      unwrap(el);
  });

  // Word titles become headings.
  body.querySelectorAll("p.MsoTitle").forEach((p) => rename(doc, p, "h1"));
  body.querySelectorAll("p.MsoSubtitle").forEach((p) => rename(doc, p, "h2"));

  // Strip presentational attributes, keep alignment on blocks.
  body.querySelectorAll("*").forEach((el) => {
    const align = KEEP_ALIGN.test(el.tagName)
      ? el.style?.textAlign || el.getAttribute("align") || ""
      : "";
    for (const attr of [...el.attributes]) {
      const name = attr.name.toLowerCase();
      const keep =
        [
          "href",
          "src",
          "alt",
          "title",
          "colspan",
          "rowspan",
          "start",
          "data-type",
          "data-checked",
        ].includes(name) ||
        (el.tagName === "IMG" && ["width", "height"].includes(name));
      if (!keep) el.removeAttribute(attr.name);
    }
    if (["center", "right", "justify"].includes(align))
      el.setAttribute("style", `text-align: ${align}`);
  });

  // Remove Word's empty spacer paragraphs and nbsp-only runs.
  body.querySelectorAll("p").forEach((p) => {
    if (!p.textContent.replace(/ /g, " ").trim() && !p.querySelector("img, br"))
      p.remove();
  });
  body.querySelectorAll("span").forEach(unwrap);

  return body.innerHTML;
}

function unwrap(el) {
  const parent = el.parentNode;
  if (!parent) return;
  while (el.firstChild) parent.insertBefore(el.firstChild, el);
  el.remove();
}

function rename(doc, el, tag) {
  const next = doc.createElement(tag);
  while (el.firstChild) next.append(el.firstChild);
  el.replaceWith(next);
  return next;
}

/** Word exports lists as paragraphs with mso-list styles and a fake bullet span. */
function convertWordLists(doc) {
  const paragraphs = [...doc.body.querySelectorAll("p")];
  let stack = [];

  const flush = () => (stack = []);

  for (const p of paragraphs) {
    const style = p.getAttribute("style") || "";
    const listMatch = style.match(/mso-list:\s*l(\d+)\s+level(\d+)/i);
    const isListClass = /MsoListParagraph/i.test(p.className);
    if (!listMatch && !isListClass) {
      flush();
      continue;
    }
    const level = listMatch ? Number(listMatch[2]) : 1;
    const marker = p.querySelector(
      'span[style*="mso-list:Ignore" i], span[style*="mso-list: Ignore" i]',
    );
    const markerText = (marker?.textContent || "").replace(/ /g, " ").trim();
    const ordered =
      /^[\da-zA-Z]{1,4}[.)]$/.test(markerText) ||
      /^[ivxlcdm]+[.)]$/i.test(markerText);
    marker?.remove();

    const li = doc.createElement("li");
    while (p.firstChild) li.append(p.firstChild);

    while (stack.length > level) stack.pop();
    while (stack.length < level) {
      const list = doc.createElement(ordered ? "ol" : "ul");
      if (stack.length === 0) p.before(list);
      else
        (
          stack[stack.length - 1].lastElementChild || stack[stack.length - 1]
        ).append(list);
      stack.push(list);
    }
    stack[level - 1].append(li);
    p.remove();
  }
}
