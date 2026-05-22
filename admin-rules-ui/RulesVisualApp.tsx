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
  bootstrapError: string | null;
} {
  const ta = typeof document !== 'undefined' ? (document.getElementById(textareaId) as HTMLTextAreaElement | null) : null;
  try {
    const parsed = parseDocumentFromTextarea(ta?.value ?? '', cfg);
    return { version: parsed.version, rules: parsed.rules, bootstrapError: null };
  } catch (e: unknown) {
    return {
      version: cfg.defaultVersion ?? '1.0.0',
      rules: [],
      bootstrapError: String((e as Error).message ?? e),
    };
  }
}

export interface RulesVisualAppProps {
  textareaId?: string;
}

type RulesWorkspaceTab = 'visual' | 'config' | 'patterns';

export function RulesVisualApp({ textareaId = 'gro_rules_json' }: RulesVisualAppProps): React.ReactElement {
  const cfg = useMemo(() => normalizeConfig(window.groRulesEditor), []);
  const init = useMemo(() => bootstrapFromTextarea(textareaId, cfg), [textareaId, cfg]);

  const [version, setVersion] = useState(init.version);
  const [uiRules, setUiRules] = useState<UiRule[]>(init.rules);
  const [parseError, setParseError] = useState<string | null>(init.bootstrapError);

  const { setSavedSnapshot, isSaving, saveNotice, hasUnsavedChanges, persistDocument } = useRulesPersistence(cfg);

  const versionRef = useRef(version);
  const uiRulesRef = useRef(uiRules);
  useEffect(() => {
    versionRef.current = version;
    uiRulesRef.current = uiRules;
  }, [version, uiRules]);

  const activeTabRef = useRef<RulesWorkspaceTab | null>(null);
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

  const syncPanelsForTab = useCallback((tab: RulesWorkspaceTab): void => {
    const tabV = document.querySelector<HTMLElement>('[data-gro-tab="visual"]');
    const tabP = document.querySelector<HTMLElement>('[data-gro-tab="patterns"]');
    const tabC = document.querySelector<HTMLElement>('[data-gro-tab="config"]');
    const tabActiveClass = 'gro-tabs__tab--active';
    const panelVisual = document.getElementById('gro-rules-panel-visual');
    const panelPatterns = document.getElementById('gro-rules-panel-patterns');
    const panelConfig = document.getElementById('gro-rules-panel-config');
    const editorForm = document.getElementById('gro-rules-editor-form');
    const isVisual = tab === 'visual';
    const isPatterns = tab === 'patterns';
    const isConfig = tab === 'config';
    const hideEditor = isConfig || isPatterns;

    tabV?.classList.toggle(tabActiveClass, isVisual);
    tabV?.setAttribute('aria-selected', isVisual ? 'true' : 'false');
    tabP?.classList.toggle(tabActiveClass, isPatterns);
    tabP?.setAttribute('aria-selected', isPatterns ? 'true' : 'false');
    tabC?.classList.toggle(tabActiveClass, isConfig);
    tabC?.setAttribute('aria-selected', isConfig ? 'true' : 'false');

    if (panelVisual) {
      panelVisual.hidden = !isVisual;
      panelVisual.setAttribute('aria-hidden', isVisual ? 'false' : 'true');
    }
    if (panelPatterns) {
      panelPatterns.hidden = !isPatterns;
      panelPatterns.toggleAttribute('hidden', !isPatterns);
      panelPatterns.setAttribute('aria-hidden', isPatterns ? 'false' : 'true');
    }
    if (panelConfig) {
      panelConfig.hidden = !isConfig;
      panelConfig.toggleAttribute('hidden', !isConfig);
      panelConfig.setAttribute('aria-hidden', isConfig ? 'false' : 'true');
    }
    if (editorForm) {
      editorForm.hidden = hideEditor;
      editorForm.toggleAttribute('hidden', hideEditor);
    }
  }, []);

  const revealTab = useCallback(
    (next: RulesWorkspaceTab): void => {
      activeTabRef.current = next;

      if (next === 'config' || next === 'patterns') {
        syncPanelsForTab(next);
        return;
      }

      syncPanelsForTab('visual');
      if (parseError === null) {
        try {
          importFromTextarea();
        } catch (e: unknown) {
          setParseError(String((e as Error).message ?? e));
          setUiRules([]);
          setVersion(cfg.defaultVersion ?? '1.0.0');
        }
      }
    },
    [cfg.defaultVersion, importFromTextarea, parseError, syncPanelsForTab],
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
    const hideSaveAll = tab === 'config' || tab === 'patterns';
    btn.hidden = hideSaveAll;
    btn.toggleAttribute('hidden', hideSaveAll);
    btn.disabled = parseError !== null || !dirty || isSaving;
    btn.textContent = isSaving ? t(cfg, 'saving', 'Saving…') : t(cfg, 'saveAll', 'Save all');
  }, [cfg, dirty, isSaving, parseError]);

  useLayoutEffect(() => {
    const ta = getTextarea();
    const form = ta?.form ?? null;
    const saveAllBtn = document.getElementById('gro-rules-save-all');

    const onVisualClick = (): void => {
      revealTabRef.current('visual');
    };
    const onPatternsClick = (): void => {
      revealTabRef.current('patterns');
    };
    const onConfigClick = (): void => {
      revealTabRef.current('config');
    };

    document.querySelector<HTMLButtonElement>('[data-gro-tab="visual"]')?.addEventListener('click', onVisualClick);
    document.querySelector<HTMLButtonElement>('[data-gro-tab="patterns"]')?.addEventListener('click', onPatternsClick);
    document.querySelector<HTMLButtonElement>('[data-gro-tab="config"]')?.addEventListener('click', onConfigClick);

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
    if (openTab === 'config' || openTab === 'patterns') {
      delete document.documentElement.dataset.groOpenRulesTab;
      revealTabRef.current(openTab);
    } else {
      revealTabRef.current('visual');
    }

    if (init.bootstrapError === null && !didNormalizeTextareaRef.current && ta) {
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
      document.querySelector<HTMLButtonElement>('[data-gro-tab="patterns"]')?.removeEventListener('click', onPatternsClick);
      document.querySelector<HTMLButtonElement>('[data-gro-tab="config"]')?.removeEventListener('click', onConfigClick);
      form?.removeEventListener('submit', onSubmit);
      saveAllBtn?.removeEventListener('click', onSaveAllClick);
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps -- one-time bootstrap wiring to PHP tabs / form
  }, []);

  useEffect(() => {
    if (activeTabRef.current === 'visual' && parseError === null) {
      flushToTextarea();
    }
  }, [version, uiRules, flushToTextarea, parseError]);

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
            <GroButton variant="primary" size="lg" icon="add" className="gro-btn-add-rule" onClick={addRule} disabled={parseError !== null}>
              {t(cfg, 'addRule', 'Add rule')}
            </GroButton>
          </div>
          <div className="gro-rules-visual__version">
            <label htmlFor="gro_rules_doc_version">{t(cfg, 'versionLabel', 'Format version')}</label>
            <input
              id="gro_rules_doc_version"
              type="text"
              value={version}
              onChange={(ev) => setVersion(ev.target.value)}
              disabled={parseError !== null}
            />
          </div>
        </div>
        {parseError !== null ? <div className="gro-banner gro-banner--error">{parseError}</div> : null}
        {saveNotice !== null && parseError === null ? (
          <div className="gro-banner gro-banner--success" role="status">
            <p>{saveNotice}</p>
          </div>
        ) : null}
        {parseError === null && uiRules.length === 0 ? (
          <p className="description gro-rules-visual__empty-hint">{t(cfg, 'noRules', 'No rules yet. Add your first automation.')}</p>
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
