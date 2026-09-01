/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

import { build } from "esbuild";
import { mkdir, readFile, writeFile } from "node:fs/promises";

const banner =
  "/*! FlexPay Intégration By Holsonmp - Holduix | Copyright (c) 2026 Holson Mpangala / Holduix | MIT */";

await mkdir("dist", { recursive: true });

await build({
  entryPoints: ["resources/checkout/js/index.js"],
  outfile: "dist/flexpay-checkout.esm.js",
  bundle: true,
  format: "esm",
  target: ["es2020"],
  legalComments: "none",
  banner: { js: banner },
});

await build({
  entryPoints: ["resources/checkout/js/index.js"],
  outfile: "dist/flexpay-checkout.iife.js",
  bundle: true,
  format: "iife",
  globalName: "FlexPayCheckout",
  target: ["es2020"],
  legalComments: "none",
  banner: { js: banner },
});

const cssSource = await readFile(
  "resources/checkout/css/flexpay-checkout.css",
  "utf8",
);
const cssWithoutSourceHeader = cssSource.replace(
  /^\/\*\*[\s\S]*?SPDX-License-Identifier: MIT\s*\*\/\s*/,
  "",
);
await writeFile(
  "dist/flexpay-checkout.css",
  `${banner}\n\n${cssWithoutSourceHeader}`,
  "utf8",
);
