import { build } from "esbuild";
import { copyFile, mkdir } from "node:fs/promises";

await mkdir("dist", { recursive: true });

await build({
  entryPoints: ["resources/checkout/js/index.js"],
  outfile: "dist/flexpay-checkout.esm.js",
  bundle: true,
  format: "esm",
  target: ["es2020"],
  legalComments: "none",
});

await build({
  entryPoints: ["resources/checkout/js/index.js"],
  outfile: "dist/flexpay-checkout.iife.js",
  bundle: true,
  format: "iife",
  globalName: "FlexPayCheckout",
  target: ["es2020"],
  legalComments: "none",
});

await copyFile(
  "resources/checkout/css/flexpay-checkout.css",
  "dist/flexpay-checkout.css",
);
