import { describe, expect, it } from 'vitest';
import { cleanPastedHtml, isOfficeHtml } from '../../resources/js/paste.js';

describe('paste cleanup', () => {
    it('detects office markup', () => {
        expect(isOfficeHtml('<p class=MsoNormal>x</p>')).toBe(true);
        expect(isOfficeHtml('<b id="docs-internal-guid-1">x</b>')).toBe(true);
        expect(isOfficeHtml('<p>plain</p>')).toBe(false);
    });

    it('turns Word lists, titles and styled spans into semantic HTML', () => {
        const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office"><body>
            <p class=MsoTitle>Report</p>
            <p class=MsoListParagraph style='mso-list:l0 level1 lfo1'><span style='mso-list:Ignore'>·<span>&nbsp;</span></span>One</p>
            <p class=MsoListParagraph style='mso-list:l0 level2 lfo1'><span style='mso-list:Ignore'>o</span>Nested</p>
            <p class=MsoListParagraph style='mso-list:l0 level1 lfo1'><span style='mso-list:Ignore'>·</span>Two <span style='font-weight:bold'>bold</span></p>
            <p class=MsoNormal><o:p>&nbsp;</o:p></p>
            <p class=MsoListParagraph style='mso-list:l1 level1 lfo2'><span style='mso-list:Ignore'>1.</span>First</p>
            <p class=MsoNormal style='text-align:center'>Centre <span style='font-style:italic'>it</span></p>
        </body></html>`;
        const out = cleanPastedHtml(html);
        expect(out).toContain('<h1>Report</h1>');
        expect(out).toMatch(/<ul><li>One<ul><li>Nested<\/li><\/ul><\/li><li>Two <strong>bold<\/strong><\/li><\/ul>/);
        expect(out).toContain('<ol><li>First</li></ol>');
        expect(out).toContain('<p style="text-align: center">Centre <em>it</em></p>');
        expect(out).not.toMatch(/Mso|mso-|o:p|class=/);
    });

    it('cleans Google Docs wrappers', () => {
        const html = '<meta charset="utf-8"><b style="font-weight:normal;" id="docs-internal-guid-abc"><p dir="ltr"><span style="font-weight:700;">Bold</span><span> and </span><span style="font-style:italic;text-decoration:line-through;">gone</span></p></b>';
        expect(cleanPastedHtml(html)).toBe('<p><strong>Bold</strong> and <s><em>gone</em></s></p>');
    });

    it('strips scripts, styles and event handlers', () => {
        const out = cleanPastedHtml('<style>p{}</style><p onclick="x()" class="a">Hi<script>alert(1)</script></p><img src="/a.png" onerror="x()">');
        expect(out).toBe('<p>Hi</p><img src="/a.png">');
    });
});
