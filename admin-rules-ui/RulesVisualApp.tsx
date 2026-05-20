import {
  editableToRule,
  newEditableRule,
  parseDocumentFromTextarea,
  ruleToEditable,
  type GroRulesEditorConfig,
  type UiRule,
} from './ruleModel';
import { AdvancedRuleCard } from './components/AdvancedRuleCard';
import { CfgContext, gridColsClass, normalizeConfig, t } from './components/ConfigContext';
import { GroButton } from './components/GroButton';
import { SimpleRuleCard } from './components/SimpleRuleCard';
import { documentJson, useRulesPersistence } from './hooks/useRulesPersistence';
import React, { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';

declare global {
  interface Window {
    groRulesEditor?: GroRulesEditorConfig;
  }
}

function bootstrapFromTextarea(textareaId: string, cfg: GroRulesEditorConfig): {
  version: string;
  rules: UiRule[];
  startsOnRawTab: boolean;
} {
  const ta = typeof document !== 'undefined' ? (document.getElementById(textareaId) as HTMLTextAreaElement | null) : null;
  try {
    const parsed = parseDocumentFromTextarea(ta?.value ?? '', cfg);
    return { version: parsed.version, rules: parsed.rules, startsOnRawTab: false };
  } catch {
    return { version: cfg.defaultVersion ?? '1.0.0', rules: [], startsOnRawTab: true };
  }
}

export interface RulesVisualAppProps {
  textareaId?: string;
}

export function RulesVisualApp({ textareaId = 'gro_rules_json' }: RulesVisualAppProps): React.ReactElement {
  const cfg = useMemo(() => normalizeConfig(window.groRulesEditor), []);
  const init = useMemo(() => bootstrapFromTextarea(textareaId, cfg), [textareaId, cfg]);

  const [version, setVersion] = useState(init.version);
  const [uiRules, setUiRules] = useState<UiRule[]>(init.rules);
  const [parseError, setParseError] = useState<string | null>(null);

  const { setSavedSnapshot, isSaving, saveNotice, hasUnsavedChanges, persistDocument } = useRulesPersistence(cfg);

  const versionRef = useRef(version);
  const uiRulesRef = useRef(uiRules);
  useEffect(() => {
    versionRef.current = version;
    uiRulesRef.current = uiRules;
  }, [version, uiRules]);

  const activeTabRef = useRef<'visual' | 'raw' | 'config' | null>(null);
  const displayColumns = cfg.panelConfig?.displayColumns ?? 1;
  const didNormalizeTextareaRef = useRef(false);
  const didInitBaselineRef = useRef(false);

  const getTextarea = useCallback(() => document.getElementById(textareaId) as HTMLTextAreaElement | null, [textareaId]);

  const flushToTextarea = useCallback(() => {
    const ta = getTextarea();
    if (!ta) return;
    ta.value = documentJson(versionRef.current, uiRulesRef.current, cfg);
  }, [cfg, getTextarea]);

  const importFromTextarea = useCallback(() => {
    const ta = getTextarea();
    if (!ta) throw new Error('Rules textarea missing');
    const parsed = parseDocumentFromTextarea(ta.value, cfg);
    setVersion(parsed.version);
    setUiRules(parsed.rules);
    setParseError(null);
  }, [cfg, getTextarea]);

  const syncPanelsForTab = useCallback((tab: 'visual' | 'raw' | 'config'): void => {
    const tabV = document.querySelector<HTMLElement>('[data-gro-tab="visual"]');
    const tabR = document.querySelector<HTMLElement>('[data-gro-tab="raw"]');
    const tabC = document.querySelector<HTMLElement>('[data-gro-tab="config"]');
    const tabActiveClass = 'gro-tabs__tab--active';
    const panelVisual = document.getElementById('gro-rules-panel-visual');
    const panelRaw = document.getElementById('gro-rules-panel-raw');
    const panelConfig = document.getElementById('gro-rules-panel-config');
    const editorForm = document.getElementById('gro-rules-editor-form');
    const isVisual = tab === 'visual';
    const isRaw = tab === 'raw';
    const isConfig = tab === 'config';

    tabV?.classList.toggle(tabActiveClass, isVisual);
    tabV?.setAttribute('aria-selected', isVisual ? 'true' : 'false');
    tabR?.classList.toggle(tabActiveClass, isRaw);
    tabR?.setAttribute('aria-selected', isRaw ? 'true' : 'false');
    tabC?.classList.toggle(tabActiveClass, isConfig);
    tabC?.setAttribute('aria-selected', isConfig ? 'true' : 'false');

    if (panelVisual) {
      panelVisual.hidden = !isVisual;
      panelVisual.setAttribute('aria-hidden', isVisual ? 'false' : 'true');
    }
    if (panelRaw) {
      panelRaw.hidden = !isRaw;
      panelRaw.toggleAttribute('hidden', !isRaw);
      panelRaw.setAttribute('aria-hidden', isRaw ? 'false' : 'true');
    }
    if (panelConfig) {
      panelConfig.hidden = !isConfig;
      panelConfig.toggleAttribute('hidden', !isConfig);
      panelConfig.setAttribute('aria-hidden', isConfig ? 'false' : 'true');
    }
    if (editorForm) {
      editorForm.hidden = isConfig;
      editorForm.toggleAttribute('hidden', isConfig);
    }
  }, []);

  const revealTab = useCallback(
    (next: 'visual' | 'raw' | 'config'): void => {
      const prev = activeTabRef.current;
      activeTabRef.current = next;

      if (next === 'config') {
        syncPanelsForTab('config');
        return;
      }

      if (next === 'visual') {
        syncPanelsForTab('visual');
        try {
          importFromTextarea();
        } catch (e: unknown) {
          setParseError(String((e as Error).message ?? e));
          setUiRules([]);
          setVersion(cfg.defaultVersion ?? '1.0.0');
        }
        return;
      }

      syncPanelsForTab('raw');
      if (prev === 'visual') {
        try {
          flushToTextarea();
        } catch {
          /* ignore */
        }
      }
    },
    [cfg.defaultVersion, flushToTextarea, importFromTextarea, syncPanelsForTab],
  );

  const revealTabRef = useRef(revealTab);
  revealTabRef.current = revealTab;

  const dirty = hasUnsavedChanges(version, uiRules);

  const handleSaveAll = useCallback(async (): Promise<void> => {
    flushToTextarea();
    const result = await persistDocument(versionRef.current, uiRulesRef.current);
    if (!result.ok) {
      window.alert(result.message);
    }
  }, [flushToTextarea, persistDocument]);

  const handleSaveRule = useCallback(async (): Promise<boolean> => {
    flushToTextarea();
    const result = await persistDocument(versionRef.current, uiRulesRef.current);
    if (!result.ok) {
      window.alert(result.message);
      return false;
    }
    return true;
  }, [flushToTextarea, persistDocument]);

  useEffect(() => {
    const btn = document.getElementById('gro-rules-save-all') as HTMLButtonElement | null;
    if (!btn) return;
    const tab = activeTabRef.current;
    const onConfig = tab === 'config';
    const onRaw = tab === 'raw';
    btn.hidden = onConfig;
    btn.toggleAttribute('hidden', onConfig);
    btn.disabled = onRaw ? false : !dirty || isSaving;
    btn.textContent = isSaving && !onRaw ? t(cfg, 'saving', 'Saving…') : t(cfg, 'saveAll', 'Save all');
  }, [cfg, dirty, isSaving]);

  useLayoutEffect(() => {
    const ta = getTextarea();
    const form = ta?.form ?? null;
    const saveAllBtn = document.getElementById('gro-rules-save-all');

    const onVisualClick = (): void => {
      revealTabRef.current('visual');
    };
    const onRawClick = (): void => {
      revealTabRef.current('raw');
    };
    const onConfigClick = (): void => {
      revealTabRef.current('config');
    };

    document.querySelector<HTMLButtonElement>('[data-gro-tab="visual"]')?.addEventListener('click', onVisualClick);
    document.querySelector<HTMLButtonElement>('[data-gro-tab="raw"]')?.addEventListener('click', onRawClick);
    document.querySelector<HTMLButtonElement>('[data-gro-tab="config"]')?.addEventListener('click', onConfigClick);

    let rawTimer: ReturnType<typeof setTimeout>;
    const onRawInput = (): void => {
      if (activeTabRef.current !== 'raw') return;
      window.clearTimeout(rawTimer);
      rawTimer = window.setTimeout(() => {
        try {
          importFromTextarea();
        } catch (e: unknown) {
          setParseError(String((e as Error).message ?? e));
        }
      }, 400);
    };
    ta?.addEventListener('input', onRawInput);

    const onSubmit = (ev: SubmitEvent): void => {
      if (activeTabRef.current !== 'visual') return;
      ev.preventDefault();
      void handleSaveAll();
    };

    const onSaveAllClick = (ev: MouseEvent): void => {
      if (activeTabRef.current !== 'visual') return;
      ev.preventDefault();
      void handleSaveAll();
    };

    form?.addEventListener('submit', onSubmit);
    saveAllBtn?.addEventListener('click', onSaveAllClick);

    activeTabRef.current = null;

    const openTab = document.documentElement.dataset.groOpenRulesTab;
    if (openTab === 'config') {
      delete document.documentElement.dataset.groOpenRulesTab;
      revealTabRef.current('config');
    } else if (init.startsOnRawTab) {
      setParseError(null);
      revealTabRef.current('raw');
    } else {
      revealTabRef.current('visual');
    }

    if (!init.startsOnRawTab && !didNormalizeTextareaRef.current && ta) {
      didNormalizeTextareaRef.current = true;
      try {
        ta.value = documentJson(init.version, init.rules, cfg);
      } catch (e: unknown) {
        setParseError(String((e as Error).message ?? e));
      }
    }

    if (!didInitBaselineRef.current && ta) {
      didInitBaselineRef.current = true;
      setSavedSnapshot(ta.value);
    }

    return () => {
      document.querySelector<HTMLButtonElement>('[data-gro-tab="visual"]')?.removeEventListener('click', onVisualClick);
      document.querySelector<HTMLButtonElement>('[data-gro-tab="raw"]')?.removeEventListener('click', onRawClick);
      document.querySelector<HTMLButtonElement>('[data-gro-tab="config"]')?.removeEventListener('click', onConfigClick);
      ta?.removeEventListener('input', onRawInput);
      form?.removeEventListener('submit', onSubmit);
      saveAllBtn?.removeEventListener('click', onSaveAllClick);
      window.clearTimeout(rawTimer);
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps -- one-time bootstrap wiring to PHP tabs / form
  }, []);

  useEffect(() => {
    if (activeTabRef.current === 'visual') {
      flushToTextarea();
    }
  }, [version, uiRules, flushToTextarea]);

  const addRule = (): void => {
    setUiRules((prev) => [
      ...prev,
      { type: 'simple', editable: newEditableRule(cfg.actionTypes ?? ['log'], cfg.recurrences ?? ['daily']) },
    ]);
  };

  const duplicateAt = useCallback(
    (ri: number): void => {
      setUiRules((prev) => {
        const cur = prev[ri];
        if (!cur || cur.type !== 'simple') return prev;
        const cloned = editableToRule(cur.editable);
        cloned.id = `${String(cloned.id)}_copy`;
        const nextEditable = ruleToEditable(cloned, cfg.recurrences ?? [], cfg.actionTypes ?? ['log']);
        const out = prev.slice();
        out.splice(ri + 1, 0, { type: 'simple', editable: nextEditable });
        return out;
      });
    },
    [cfg.actionTypes, cfg.recurrences],
  );

  const moveRule = useCallback((ri: number, delta: number): void => {
    setUiRules((prev) => {
      const j = ri + delta;
      if (j < 0 || j >= prev.length) return prev;
      const n = prev.slice();
      const rowI = n[ri];
      const rowJ = n[j];
      if (!rowI || !rowJ) return prev;
      n[ri] = rowJ;
      n[j] = rowI;
      return n;
    });
  }, []);

  return (
    <CfgContext.Provider value={cfg}>
      <div className="gro-rules-visual-inner">
        <div className="gro-rules-visual__toolbar">
          <div className="gro-rules-visual__toolbar-primary">
            <GroButton variant="primary" size="lg" icon="add" className="gro-btn-add-rule" onClick={addRule}>
              {t(cfg, 'addRule', 'Add rule')}
            </GroButton>
          </div>
          <div className="gro-rules-visual__version">
            <label htmlFor="gro_rules_doc_version">{t(cfg, 'versionLabel', 'Format version')}</label>
            <input id="gro_rules_doc_version" type="text" value={version} onChange={(ev) => setVersion(ev.target.value)} />
          </div>
        </div>
        {parseError !== null ? <div className="gro-banner gro-banner--error">{parseError}</div> : null}
        {saveNotice !== null && parseError === null ? (
          <div className="gro-banner gro-banner--success" role="status">
            <p>{saveNotice}</p>
          </div>
        ) : null}
        {parseError === null && uiRules.length === 0 ? (
          <p className="description gro-rules-visual__empty-hint">{t(cfg, 'noRules', 'No rules. Add one or paste JSON in the Raw JSON tab.')}</p>
        ) : null}
        {parseError === null && uiRules.length > 0 ? (
          <ul
            className={gridColsClass('gro-rules-rule-list', displayColumns)}
            aria-label={t(cfg, 'ruleListHeading', 'Configured rules')}
          >
            {uiRules.map((u, ri) => (
              <li key={u.type === 'advanced' ? `adv-${String(ri)}` : `simple-${String(ri)}`} className="gro-rules-rule-list__item">
                {u.type === 'advanced' ? (
                  <AdvancedRuleCard
                    ruleIndex={ri}
                    snapshot={u.snapshot}
                    onRemove={() => setUiRules((p) => p.filter((_, i) => i !== ri))}
                  />
                ) : (
                  <SimpleRuleCard
                    ruleIndex={ri}
                    editable={u.editable}
                    onChange={(next) =>
                      setUiRules((prev) =>
                        prev.map((row, idx) => (idx === ri && row.type === 'simple' ? { type: 'simple', editable: next } : row)),
                      )
                    }
                    onDuplicate={() => duplicateAt(ri)}
                    onMove={(d) => moveRule(ri, d)}
                    onRemove={() => setUiRules((prev) => prev.filter((_, i) => i !== ri))}
                    onSave={handleSaveRule}
                    isSaving={isSaving}
                    hasDocumentChanges={dirty}
                  />
                )}
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </CfgContext.Provider>
  );
}
