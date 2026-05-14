# Third-party notices

This file lists third-party libraries bundled in OuInPo Suite.

## FPDF

- Path: `src/Modules/Gate/plugin/fpdf/`
- Version: 1.86, verified in `fpdf.php`
- Author: Olivier Plathey
- License: permissive FPDF license, see `src/Modules/Gate/plugin/fpdf/license.txt`

## Parsedown

- Path: `src/Modules/SegFault/plugin/libs/parsedown/`
- Package: `erusev/parsedown`
- License: MIT, verified in `composer.json` and `LICENSE.txt`

## PDFParser

- Path: `src/Modules/SegFault/plugin/libs/pdfparser/`
- Package: `smalot/pdfparser`
- License: LGPL-3.0 / LGPLv3, verified in `composer.json`, `LICENSE.txt` and source headers

## Original OuInPo assets

- Paths: `assets/css/`, `assets/js/`, `packs/`, `docs/`
- License: project license and content terms described in `LICENSE` and `CONTENT-LICENSE.md`
- Notes: public UI assets, educational packs and OuInPo-specific visual/textual elements are original to the project unless a file-level notice says otherwise.

## Replaceable educational assets

- Paths: pedagogical JSON packs in `packs/` and uploaded practical-subject resources handled by the plugin
- Notes: these assets are specific to a teaching context and may be replaced by an educator before redistribution or classroom use.

## Gate assets

- Gate assets are listed for completeness only. They were not modified during the 0.5.0 hardening pass and remain scheduled for a separate review.
