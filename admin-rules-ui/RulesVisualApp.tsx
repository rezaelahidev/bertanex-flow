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
import { ConfirmDialog } from './components/ConfirmDialog';
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

type RulesWorkspaceTab = 'visual' | 'config' | 'reset' | 'patterns' | 'email' | 'runs';

export function RulesVisualApp({ textareaId = 'gro_rules_json' }: RulesVisualAppProps): React.ReactElement {
  const cfg = useMemo(() => normalizeConfig(window.groRulesEditor), []);
  const init = useMemo(() => bootstrapFromTextarea(textareaId, cfg), [textareaId, cfg]);

  const [version, setVersion] = useState(init.version);
  const [uiRules, setUiRules] = useState<UiRule[]>(init.rules);
  const [parseError, setParseError] = useState<string | null>(init.bootstrapError);
  const [saveError, setSaveError] = useState<string | null>(null);

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
    const tabActiveClass = 'gro-tabs__tab--active';
    document.querySelectorAll<HTMLElement>('[data-gro-tab]').forEach((el) => {
      const on = el.getAttribute('data-gro-tab') === tab;
      el.classList.toggle(tabActiveClass, on);
      el.classList.remove('is-primary', 'is-secondary');
      if (on) {
        el.setAttribute('aria-current', 'true');
      } else {
        el.removeAttribute('aria-current');
      }
      el.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll<HTMLElement>('[data-gro-panel]').forEach((panel) => {
      const on = panel.getAttribute('data-gro-panel') === tab;
      panel.hidden = !on;
      panel.toggleAttribute('hidden', !on);
      panel.setAttribute('aria-hidden', on ? 'false' : 'true');
    });
    const panelUrl = cfg.saveAjaxUrl;
    const panelAction = cfg.panelAction;
    const panelNonce = cfg.saveNonce;
    if (panelUrl && panelAction && panelNonce) {
      const body = new URLSearchParams();
      body.set('action', panelAction);
      body.set('nonce', panelNonce);
      body.set('tab', tab);
      void fetch(panelUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      });
    }
    const editorForm = document.getElementById('gro-rules-editor-form');
    if (editorForm) {
      const hideEditor = tab !== 'visual';
      editorForm.hidden = hideEditor;
      editorForm.toggleAttribute('hidden', hideEditor);
    }
  }, []);

  const revealTab = useCallback(
    (next: RulesWorkspaceTab): void => {
      activeTabRef.current = next;

      if (next !== 'visual') {
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
      setSaveError(result.message);
    }
  }, [flushToTextarea, persistDocument]);

  useEffect(() => {
    document.dispatchEvent(
      new CustomEvent('gro-rules-state', {
        detail: { dirty: dirty && parseError === null, saving: isSaving },
      }),
    );
  }, [dirty, isSaving, parseError]);

  useLayoutEffect(() => {
    const ta = getTextarea();
    const form = ta?.form ?? null;

    const tabList = document.querySelector('.gro-tabs');
    const onTabClick = (ev: Event): void => {
      const button = (ev.target as HTMLElement | null)?.closest<HTMLElement>('[data-gro-tab]');
      const next = button?.getAttribute('data-gro-tab');
      if (next === 'visual' || next === 'patterns' || next === 'email' || next === 'runs' || next === 'config' || next === 'reset') {
        revealTabRef.current(next);
      }
    };
    tabList?.addEventListener('click', onTabClick);

    const onSubmit = (ev: SubmitEvent): void => {
      if (activeTabRef.current !== 'visual') return;
      ev.preventDefault();
      void handleSaveAll();
    };

    const onSidebarSave = (): void => {
      if (activeTabRef.current !== 'visual') return;
      void handleSaveAll();
    };

    form?.addEventListener('submit', onSubmit);
    document.addEventListener('gro-sidebar-save', onSidebarSave);

    activeTabRef.current = null;

    const openTab = document.documentElement.dataset.groOpenRulesTab;
    delete document.documentElement.dataset.groOpenRulesTab;
    if (openTab === 'config' || openTab === 'reset' || openTab === 'patterns' || openTab === 'email' || openTab === 'runs' || openTab === 'visual') {
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
      tabList?.removeEventListener('click', onTabClick);
      form?.removeEventListener('submit', onSubmit);
      document.removeEventListener('gro-sidebar-save', onSidebarSave);
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

  const Notice = window.wp.components.Notice;
  const enabledCount = parseError === null
    ? uiRules.filter((rule) => (rule.type === 'simple' ? rule.editable.enabled : rule.snapshot.enabled !== false)).length
    : 0;

  return (
    <CfgContext.Provider value={cfg}>
      <div className="gro-rules-visual-inner">
        <header className="gro-page-header">
          <div className="gro-page-header__titles">
            <h1 className="gro-page-header__title">{t(cfg, 'automationsTitle', 'Automation Rules')}</h1>
            <p className="gro-page-header__lede">{t(cfg, 'automationsLede', 'Build automations, then manage SMS patterns, email templates, and delivery settings separately.')}</p>
          </div>
          {parseError === null ? (
            <dl className="gro-page-stats" aria-label={t(cfg, 'rulesSummary', 'Rules summary')}>
              <div className="gro-page-stats__item">
                <dt>{t(cfg, 'totalRules', 'Total rules')}</dt>
                <dd>{uiRules.length}</dd>
              </div>
              <div className="gro-page-stats__item gro-page-stats__item--accent">
                <dt>{t(cfg, 'enabledRules', 'Enabled')}</dt>
                <dd>{enabledCount}</dd>
              </div>
            </dl>
          ) : null}
        </header>
        <div className="gro-rules-visual__toolbar">
          <div className="gro-rules-visual__toolbar-primary">
            <GroButton variant="primary" size="lg" icon="add" className="gro-btn-add-rule" onClick={addRule} disabled={parseError !== null}>
              {t(cfg, 'addRule', 'Add rule')}
            </GroButton>
          </div>
        </div>
        {parseError !== null ? (
          <Notice status="error" isDismissible={false}>
            {parseError}
          </Notice>
        ) : null}
        {saveNotice !== null && parseError === null ? (
          <Notice status="success" isDismissible={false}>
            {saveNotice}
          </Notice>
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
                  />
                )}
              </li>
            ))}
          </ul>
        ) : null}
        <ConfirmDialog
          isOpen={saveError !== null}
          message={saveError ?? ''}
          confirmLabel={t(cfg, 'modalClose', 'OK')}
          onConfirm={() => setSaveError(null)}
          onCancel={() => setSaveError(null)}
        />
      </div>
    </CfgContext.Provider>
  );
}
