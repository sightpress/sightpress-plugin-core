// Serves the fixture pages for the browser tests of the helpers.
// /he-IL/… and /ar-SA/… serve the same page in that locale, right to left.
import { readFileSync } from 'node:fs';
import { createServer } from 'node:http';

const good = readFileSync(new URL('./fixtures/good.html', import.meta.url), 'utf8');
const routes = new Set(['/good', '/events/summer-fair', '/admin/good']);

createServer((request, response) => {
  const path = new URL(request.url ?? '/', 'http://localhost').pathname;
  const [, locale, rest] = /^\/(he-IL|ar-SA)(\/.*)$/.exec(path) ?? [null, null, path];
  const found = routes.has(rest);
  response.writeHead(found ? 200 : 404, { 'content-type': 'text/html; charset=utf-8' });
  response.end(!found
    ? '<!doctype html><title>Not found</title>'
    : locale ? good.replace('lang="en-GB" dir="ltr"', `lang="${locale}" dir="rtl"`) : good);
}).listen(Number(process.env.PORT ?? 4177));
