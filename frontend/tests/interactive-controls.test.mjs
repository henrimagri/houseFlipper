import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import ts from 'typescript';

async function sourceFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const nested = await Promise.all(entries.map(async (entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? sourceFiles(target) : entry.name.endsWith('.tsx') ? [target] : [];
  }));
  return nested.flat();
}

test('native buttons have an action or submit a containing form', async () => {
  const failures = [];

  for (const file of await sourceFiles(new URL('../src', import.meta.url).pathname.replace(/^\/(?:[A-Za-z]:)/, match => match.slice(1)))) {
    const source = ts.createSourceFile(file, await readFile(file, 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);

    function visit(node) {
      if (ts.isJsxElement(node) && node.openingElement.tagName.getText(source) === 'button') {
        const attributes = node.openingElement.attributes.properties.filter(ts.isJsxAttribute);
        const names = new Set(attributes.map(attribute => attribute.name.getText(source)));
        const type = attributes.find(attribute => attribute.name.getText(source) === 'type')?.initializer?.getText(source).replace(/["']/g, '');
        let parent = node.parent;
        let inForm = false;
        while (parent) {
          if (ts.isJsxElement(parent) && parent.openingElement.tagName.getText(source) === 'form') {
            inForm = true;
            break;
          }
          parent = parent.parent;
        }

        if (!names.has('onClick') && !(inForm && type !== 'button')) {
          const position = source.getLineAndCharacterOfPosition(node.getStart(source));
          failures.push(`${file}:${position.line + 1}`);
        }
      }
      ts.forEachChild(node, visit);
    }

    visit(source);
  }

  assert.deepEqual(failures, []);
});
