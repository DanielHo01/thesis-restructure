---
name: easyplot
description: Use when the user asks for scientific data preparation, exploratory analysis, statistical inference, model evaluation, or research figures in R or Python, including analysis-only requests, multi-panel composition, and publication export. EasyPlot is the user's single entry point for research data analysis and visualization; exclude unrelated business dashboards and general application development.
---

# EasyPlot

EasyPlot is the user's single entry point for scientific data analysis and visualization. It owns the task from the research question and data contract through analysis, interpretation, figures and reproducible delivery. R is the default; an explicit Python request stays in Python. Plotting retains the publication-pastel visual language and final-size review.

## One entry point, selective local modules

- The user should not need to select or install a second scientific-analysis/visualization skill. Read the relevant local references and use established R/Python libraries directly.
- Accept analysis-only requests without requiring a figure. For plotting-only requests, preserve supplied analysis results.
- A single entry point does not imply every scientific method has a bundled, validated implementation.
- Preserve existing project conventions and user-approved methods.

| Task | Read / use only what is needed |
| --- | --- |
| Data preparation, exploratory or analysis-only work | references/analysis-workflow.md |
| Two independent groups or paired observations | easyplot_analysis.R or easyplot_analysis.py |
| Technical replicates, repeated/nested units, or family of comparisons | references/replicates-and-multiplicity.md |
| China maps in R, especially ggmapcn | references/china-maps-ggmapcn.md + easyplot_china_map.R |
| Global/world maps, graticules, or area-preserving projection | references/global-maps.md |
| Mechanism, workflow, architecture, node-and-arrow schematic | references/schematics.md |
| Export, palette or publication audit | references/qa-tools.md |
| Named colour schemes, ggsci/CVD palettes, Chinese colours | references/palette-library.md |
| Learning from another project, article or skill | references/source-adoption.md |

## Scope

- Use the existing project analysis/plotting stack when established. Otherwise prefer R, with ggplot2 for figures.
- Python is selected only when the user explicitly requests it.
- Keep all consequential choices visible in the delivered script.
- Preserve the meaning of the data. Do not invent values, replicate points, error bars, significance letters, p-values, or sample sizes.
- Keep figure number, title, caption, statistical methods, and provenance outside the plot by default.
- Read references/templates.md when choosing a template or implementing a new one.
- Read references/style-guide.md for the shared visual contract and palette.
- Read references/schematics.md for node/edge data contracts.
- Read references/aesthetic-distillation.md for "顶刊风格" or restrained journal aesthetic.
- Read references/journal-requirements.md when the user names a journal.
- Read references/publication-qa.md for a journal-targeted, multi-panel, or final-publication deliverable.
- Read references/qa-tools.md when auditing an existing export.
- Read references/typography-export.md for CJK/mixed scripts, font failures, editable vectors, or Windows R encoding.
- Read references/python-backend.md whenever the user specifies Python.
- Read references/palette-library.md for built-in named palettes and CVD-aware choices.
- For R scientific plate archetypes, source scripts/easyplot_templates.R.
- For China-wide site/prediction map, source scripts/easyplot_china_map.R.
- For Python figures, import scripts/easyplot_py.py.

## Backend routing

- No language specified: use R/ggplot2 unless the project already establishes another plotting stack.
- User explicitly says Python, .py, Matplotlib, Seaborn, or Plotly: use the Python backend.
- User explicitly says R or ggplot2: use the R backend.
- If a Python project already has Seaborn or Plotly conventions, preserve them.

## Default visual language

The default `publication_pastel` style:
- white canvas and plotting area; no gradients, shadows, or 3D effects;
- left and bottom axes by default, with restrained ticks and no heavy background grid;
- narrow pastel bars with a fine dark outline, explicit error bars, and optional raw replicate points;
- statistical letters or stars placed above the uncertainty extent when supplied;
- stable group order and stable color semantics across panels;
- scientific typography with italic species names and correctly formatted units, superscripts, subscripts, and Greek symbols;
- linear scales by default; use log10 only when justified and label it clearly;
- multi-panel figures share one palette, one typographic system, and deliberate alignment.

### Palette preference for a new figure

For a new common plot with no explicit or established project palette, ask once:

> 这张图希望用哪类配色？① 自动匹配（推荐） ② EasyPlot 柔和期刊风（publication_pastel） ③ 经典科研（ggsci / ColorBrewer） ④ 色盲友好 ⑤ 中国/东方色（china.* / dongfang.*） ⑥ 指定色带 ID 或 HEX

## Scientific visualization safeguards

- Record the audience, medium, intended final width, variable semantics, units, replicate structure, missing/censored values, transformations, source-data path, and output provenance.
- Keep universal figure principles separate from venue rules.
- Prefer position on a common scale. Use zero-baseline bars for ordinary amounts.
- Distinguish missing, zero, censored, excluded, and out-of-range observations.
- Match color type to data semantics and audit rendered contrast.
- Export with explicit dimensions, device, format, background, DPI, and overwrite behavior.
- Use the local metadata and palette audit tools for final-publication screening.
- Apply the local publication preflight for journal-targeted work.

## Journal profiles

When a journal is named, load its profile before composing the figure. Profiles marked provisional must produce a visible warning and be rechecked before submission.

## Workflow

1. Identify the requested outcome: explanation/plan, analysis only, figures from existing results, or end-to-end analysis and figure.
2. For analysis, establish experimental/observational units, pairing/clustering, variables/units, outcomes, missingness and exclusions.
3. Run only the required analysis. Preserve raw inputs.
4. When a figure is requested, select the smallest suitable template.
5. Deliver a reproducible .R or .py script, result tables/notes, and figures/manifests.
6. Verify the affected calculation or workflow, then inspect rendered output at final size.

## Integrity rules for summary figures

- A summary bar requires an explicit estimator and uncertainty definition.
- A missing uncertainty column is a missing decision, not a reason to fabricate one.
- Significance letters, brackets, and stars are inputs or results of a named analysis.
- Bars representing amounts normally start at zero on a linear scale.

## Output contract

For an analysis request, deliver the selected method and rationale, analysis-unit counts and exclusions, an effect/uncertainty table, diagnostics/limitations, and reproducible code.

For a data-backed plotting request, deliver:
- one self-contained .R or .py script
- the rendered figure in the requested format
- a provenance manifest for multi-format or final-publication exports
- a short caption or notes sidecar when uncertainty, transformations, exclusions, missingness need disclosure
- a manuscript-facing figure title/caption outside the image by default

## Minimal final check

Run the affected R/Python calculation or plotting check. For analysis verify unit counts, effect direction, ID alignment, missing-value handling. For figures confirm group order, axes/units, clipping, typography, explicit missingness and external captions.
