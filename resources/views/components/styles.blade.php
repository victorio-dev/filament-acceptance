{{--
    The styles of the decision components, inside the public-links layout (its CSS variables give
    the brand colour and a text colour picked for contrast). Include once per page.
--}}
<style>
    .fa-content { margin: 0 0 1.5rem; overflow-wrap: anywhere; }
    .fa-approval dt { font-weight: 600; margin: 0; }
    .fa-approval dd { margin: 0 0 1rem; }
    .fa-approval dd p { margin: 0 0 .5rem; }
    .fa-fingerprint { color: var(--fpl-muted); font-size: .875rem; margin: 0 0 1.5rem; }
    .fa-fingerprint code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .fa-notice { padding: .75rem 1rem; border-inline-start: 4px solid var(--fpl-primary); background: var(--fpl-surface); margin: 0 0 1.5rem; }
    .fa-notice > :first-child { margin-block-start: 0; }
    .fa-notice > :last-child { margin-block-end: 0; }
    .fa-panel { border: 1px solid var(--fpl-border); border-radius: .5rem; padding: 1.25rem; margin: 0 0 1.5rem; background: var(--fpl-surface); }
    .fa-panel h2 { font-size: 1.15rem; margin: 0 0 1rem; }
    .fa-field { margin: 0 0 1rem; }
    .fa-field label { display: block; font-weight: 600; margin: 0 0 .35rem; }
    .fa-field input[type="text"], .fa-field input[type="email"], .fa-field textarea {
        display: block; width: 100%; min-height: 44px; padding: .6rem .75rem;
        font: inherit; font-size: 1rem; color: var(--fpl-text); background: var(--fpl-bg);
        border: 1px solid var(--fpl-muted); border-radius: .375rem;
    }
    .fa-field [aria-invalid="true"] { border: 2px solid #b91c1c; }
    .fa-check { display: flex; gap: .75rem; align-items: flex-start; margin: 0 0 1rem; }
    .fa-check input { width: 1.5rem; height: 1.5rem; margin: .1rem 0 0; flex: none; }
    .fa-check label { font-weight: 600; }
    .fa-error { color: #b91c1c; margin: .35rem 0 0; font-weight: 600; }
    @media (prefers-color-scheme: dark) {
        .fa-error { color: #fca5a5; }
        .fa-field [aria-invalid="true"] { border-color: #fca5a5; }
    }
    .fa-error-summary { padding: .75rem 1rem; border: 2px solid currentColor; border-radius: .375rem; margin: 0 0 1rem; font-weight: 600; }
    .fa-button {
        display: inline-flex; align-items: center; justify-content: center;
        min-height: 44px; min-width: 44px; padding: .6rem 1.25rem;
        font: inherit; font-weight: 600; border-radius: .375rem; cursor: pointer;
    }
    .fa-button:focus-visible { outline: 3px solid var(--fpl-text); outline-offset: 2px; }
    .fa-button-primary { background: var(--fpl-primary); color: var(--fpl-on-primary); border: 2px solid var(--fpl-primary); }
    .fa-button-secondary { background: var(--fpl-bg); color: var(--fpl-text); border: 2px solid var(--fpl-text); }
    .fa-legal { color: var(--fpl-muted); font-size: .875rem; margin: 1rem 0 0; }
    .fa-help { color: var(--fpl-muted); font-size: .875rem; margin: .35rem 0 0; }
    .fa-buttons { display: flex; flex-wrap: wrap; gap: .75rem; margin: 1.25rem 0 0; }
    .fa-button-decline { background: var(--fpl-bg); color: #b91c1c; border: 2px solid #b91c1c; }
    .fa-warning { font-weight: 600; margin: 0 0 1rem; }
    .fa-subject { font-weight: 600; margin: 0 0 .25rem; }
    @media (prefers-color-scheme: dark) {
        .fa-button-decline { color: #fca5a5; border-color: #fca5a5; }
    }
    .fa-summary dt { font-weight: 600; }
    .fa-summary dd { margin: 0 0 .75rem; white-space: pre-line; overflow-wrap: anywhere; }
    @media (max-width: 30rem) { .fa-buttons { flex-direction: column; } .fa-button { width: 100%; } }
</style>
