# detect.js (browser / OS detection)

**Shipped (minified) asset:** `assets/js/detect.js`  
**Readable source (this folder):** `detect.js` (npm `detect.js@2.2.2` distributable)  
**Build input:** `detect.build.js` (upstream `build/detect.js`)

**Upstream:** [darcyclarke/Detect.js](https://github.com/darcyclarke/Detect.js) / npm [`detect.js@2.2.2`](https://www.npmjs.com/package/detect.js)  
**Author / copyright:** Darcy Clarke (see `LICENSE` and the source file header)  
**License:** Dual MIT and GPL (as declared by upstream package and source header)  
**Purpose:** Lightweight user-agent parsing on the WordPress login screen for activity / security logging context.

## Build / provenance (Guideline 4)

The minified file in `assets/js/detect.js` is the upstream `detect.min.js` from npm `detect.js@2.2.2`, with a short provenance banner prepended.  
Readable source matching that version is retained here so WordPress.org reviewers can verify origin.

To refresh from upstream:

```bash
# npm pack detect.js@2.2.2 && extract, then:
cp package/detect.js        src/vendor/detect.js/detect.js
cp package/build/detect.js  src/vendor/detect.js/detect.build.js
# prepend the short MIT/provenance banner, then:
cp package/detect.min.js    assets/js/detect.js
```

Do not remove this README, `LICENSE`, or the readable `detect.js` from release ZIPs.
