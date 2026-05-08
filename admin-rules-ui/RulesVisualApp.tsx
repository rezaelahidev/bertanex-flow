import {
  editableToRule,
  newEditableRule,
  opsNeedValue,
  operatorOptions,
  parseDocumentFromTextarea,
  ruleToEditable,
  serializeDocument,
  stringT,
  type ActionRowUi,
  type ConditionRow,
  type EditableRule,
  type EditableTrigger,
  type GroRulesEditorConfig,
  type UiRule,
} from './ruleModel';
import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from 'react';

declare global {
  interface Window {
    groRulesEditor?: GroRulesEditorConfig;
  }
}

const CfgContext = createContext<GroRulesEditorConfig>({});

function normalizeConfig(raw: GroRulesEditorConfig | undefined): GroRulesEditorConfig {
  return {
    strings: {},
    ...raw,
    defaultVersion: raw?.defaultVersion ?? '1.0.0',
    recurrences: raw?.recurrences ?? ['hourly', 'twicedaily', 'daily', 'weekly'],
    operators: raw?.operators ?? ['eq'],
    actionTypes: raw?.actionTypes ?? ['send_sms'],
    suggestedFields: raw?.suggestedFields ?? [],
    events: raw?.events ?? [],
  };
}

function expandCollapseAria(cfg: GroRulesEditorConfig, isExpanded: boolean, ruleNum: number, title: string): string {
  const key = isExpanded ? 'accordionCollapse' : 'accordionExpand';
  const fallback = isExpanded ? 'Collapse settings for rule %s' : 'Expand settings for rule %s';
  return stringT(cfg, key, fallback).replace('%s', `#${String(ruleNum)}: ${title}`);
}

function removeCollapsedAria(cfg: GroRulesEditorConfig, ruleNum: number, ruleTitle: string): string {
  return stringT(cfg, 'removeRuleCollapsedAria', 'Remove %s permanently from this list').replace(
    '%s',
    `rule #${String(ruleNum)}: ${ruleTitle}`,
  );
}

function t(cfg: GroRulesEditorConfig, key: string, fallback: string): string {
  return stringT(cfg, key, fallback);
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

interface TriggersBlockProps {
  ruleIndex: number;
  editable: EditableTrigger;
  onChange: (next: EditableTrigger) => void;
}

function TriggersBlock({ ruleIndex, editable: te, onChange }: TriggersBlockProps): React.ReactElement {
  const cfg = useContext(CfgContext);
  const radioGroup = `gro_tr_${ruleIndex}`;
  const eventOpts = cfg.events ?? [];

  const extras = useMemo(() => te.events.filter((x) => !eventOpts.some((eo) => eo.value === x)), [te.events, eventOpts]);

  const syncCustomExtras = (customLine: string): void => {
    const known = eventOpts.map((eo) => eo.value);
    const parts = customLine
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
    const fromCheckboxes = te.events.filter((x) => known.includes(x));
    const merged = [...fromCheckboxes];
    parts.forEach((p) => {
      if (!merged.includes(p)) merged.push(p);
    });
    onChange({ ...te, events: merged });
  };

  return (
    <div className="gro-rule-section">
      <h4>{t(cfg, 'triggerHeading', 'When to run')}</h4>
      <div className="gro-trigger-mode">
        {(
          [
            ['none', t(cfg, 'triggerNone', 'Evaluate without event filter')],
            ['events', t(cfg, 'triggerEvents', 'On automation events')],
            ['schedule', t(cfg, 'triggerSchedule', 'On a schedule (cron)')],
          ] as const
        ).map(([value, label]) => (
          <label key={value}>
            <input
              type="radio"
              name={radioGroup}
              checked={te.mode === value}
              onChange={() => {
                const rec = cfg.recurrences?.[0] ?? 'daily';
                onChange({
                  ...te,
                  mode: value,
                  schedule:
                    value === 'schedule' && (!te.schedule.hook || te.mode !== 'schedule')
                      ? { hook: '', recurrence: te.schedule.recurrence || rec }
                      : te.schedule,
                });
              }}
            />{' '}
            {label}
          </label>
        ))}
      </div>
      {te.mode === 'events' ? (
        <>
          <div className="gro-events-grid">
            {eventOpts.map((ev) => (
              <label key={ev.value}>
                <input
                  type="checkbox"
                  checked={te.events.includes(ev.value)}
                  onChange={(e) => {
                    let next = te.events.slice();
                    if (e.target.checked && !next.includes(ev.value)) next.push(ev.value);
                    if (!e.target.checked) next = next.filter((x) => x !== ev.value);
                    onChange({ ...te, events: next });
                  }}
                />{' '}
                {ev.label || ev.value}
              </label>
            ))}
          </div>
          <p>
            <label>
              <strong>{t(cfg, 'customEventsLabel', 'Additional event names (comma-separated)')}</strong>
            </label>
            <input
              type="text"
              className="large-text"
              defaultValue={extras.join(', ')}
              key={`ce-${extras.join('|')}-${ruleIndex}`}
              onBlur={(e) => syncCustomExtras(e.target.value)}
            />
          </p>
        </>
      ) : null}
      {te.mode === 'schedule' ? (
        <div className="gro-schedule-fields">
          <p>
            <label>
              <strong>{t(cfg, 'hookLabel', 'Cron hook')}</strong>
            </label>
            <input
              type="text"
              className="regular-text"
              value={te.schedule.hook}
              onChange={(e) => onChange({ ...te, schedule: { ...te.schedule, hook: e.target.value } })}
            />
          </p>
          <p>
            <label>
              <strong>{t(cfg, 'recurrenceLabel', 'Recurrence')}</strong>
            </label>
            <select
              value={te.schedule.recurrence}
              onChange={(e) => onChange({ ...te, schedule: { ...te.schedule, recurrence: e.target.value } })}
            >
              {(cfg.recurrences ?? []).map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </select>
          </p>
        </div>
      ) : null}
    </div>
  );
}

interface ConditionsBlockProps {
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
}

function ConditionsBlock({ editable: e, onChange }: ConditionsBlockProps): React.ReactElement {
  const cfg = useContext(CfgContext);
  const suggested = cfg.suggestedFields ?? [];

  const setCondition = (idx: number, row: ConditionRow): void => {
    onChange({ ...e, conditions: e.conditions.map((c, i) => (i === idx ? row : c)) });
  };

  return (
    <div className="gro-rule-section">
      <h4>{t(cfg, 'conditionsHeading', 'Conditions (all must match)')}</h4>
      <div className="gro-conditions-table-wrap">
        <table className="widefat gro-conditions-table">
          <thead>
            <tr>
              <th>{t(cfg, 'fieldCol', 'Field')}</th>
              <th>{t(cfg, 'opCol', 'Operator')}</th>
              <th>{t(cfg, 'patternCol', 'Pattern (regex)')}</th>
              <th>{t(cfg, 'valueCol', 'Value (JSON)')}</th>
              <th aria-label={t(cfg, 'removeRow', 'Remove')} />
            </tr>
          </thead>
          <tbody>
            {e.conditions.map((c, idx) => {
              const match = suggested.includes(c.field);
              const fieldSelValue = match ? c.field : '__custom__';

              return (
                <tr key={idx}>
                  <td>
                    <select
                      value={fieldSelValue}
                      onChange={(ev) => {
                        const v = ev.target.value;
                        if (v === '__custom__') setCondition(idx, { ...c, field: match ? '' : c.field });
                        else setCondition(idx, { ...c, field: v });
                      }}
                    >
                      <option value="__custom__">{t(cfg, 'customField', 'Custom…')}</option>
                      {suggested.map((sf) => (
                        <option key={sf} value={sf}>
                          {sf}
                        </option>
                      ))}
                    </select>
                    <br />
                    <input
                      type="text"
                      className="regular-text"
                      placeholder={t(cfg, 'fieldPlaceholder', 'State field key')}
                      style={{ display: fieldSelValue === '__custom__' ? undefined : 'none' }}
                      value={match ? '' : c.field}
                      onChange={(ev) => setCondition(idx, { ...c, field: ev.target.value })}
                    />
                  </td>
                  <td>
                    <select value={c.op} onChange={(ev) => setCondition(idx, { ...c, op: ev.target.value })}>
                      {operatorOptions(cfg, c.op).map((o) => (
                        <option key={o} value={o}>
                          {o}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td style={{ display: c.op === 'regex' ? undefined : 'none' }}>
                    <input
                      type="text"
                      className="code"
                      placeholder="/^...$/"
                      value={c.pattern}
                      onChange={(ev) => setCondition(idx, { ...c, pattern: ev.target.value })}
                    />
                  </td>
                  <td style={{ display: opsNeedValue(c.op) ? undefined : 'none' }}>
                    <textarea
                      rows={2}
                      value={c.valueJson}
                      placeholder='true, false, 3, "text", []'
                      onChange={(ev) => setCondition(idx, { ...c, valueJson: ev.target.value })}
                    />
                  </td>
                  <td>
                    <button
                      type="button"
                      className="button button-small"
                      onClick={() => {
                        const next = e.conditions.filter((_, i) => i !== idx);
                        onChange({
                          ...e,
                          conditions: next.length ? next : [{ field: '', op: 'eq', valueJson: '', pattern: '' }],
                        });
                      }}
                    >
                      {t(cfg, 'removeRow', 'Remove')}
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <button
        type="button"
        className="button"
        onClick={() => onChange({ ...e, conditions: [...e.conditions, { field: '', op: 'eq', valueJson: '', pattern: '' }] })}
      >
        {t(cfg, 'addCondition', 'Add condition')}
      </button>
    </div>
  );
}

interface ActionsBlockProps {
  editable: EditableRule;
  onChange: (next: EditableRule) => void;
}

function ActionsBlock({ editable: e, onChange }: ActionsBlockProps): React.ReactElement {
  const cfg = useContext(CfgContext);
  const at = cfg.actionTypes ?? ['log'];

  const patchAction = (idx: number, row: ActionRowUi): void => {
    onChange({ ...e, actions: e.actions.map((a, i) => (i === idx ? row : a)) });
  };

  return (
    <div className="gro-rule-section">
      <h4>{t(cfg, 'actionsHeading', 'Actions')}</h4>
      {e.actions.map((a, idx) => (
        <div key={idx} className="gro-action-row">
          <div className="gro-action-row__grid">
            <p>
              <label>
                <strong>{t(cfg, 'actionType', 'Type')}</strong>
              </label>
              <select value={at.includes(a.type) ? a.type : at[0]} onChange={(ev) => patchAction(idx, { ...a, type: ev.target.value })}>
                {at.map((x) => (
                  <option key={x} value={x}>
                    {x}
                  </option>
                ))}
              </select>
            </p>
            <p>
              <label>
                <strong>{t(cfg, 'templateId', 'template_id')}</strong>
              </label>
              <input
                type="text"
                className="regular-text code"
                value={a.template_id}
                onChange={(ev) => patchAction(idx, { ...a, template_id: ev.target.value })}
              />
            </p>
            <p>
              <label>
                <strong>{t(cfg, 'delaySeconds', 'delay_seconds')}</strong>
              </label>
              <input
                type="number"
                min={0}
                step={1}
                value={a.delay_seconds}
                onChange={(ev) => patchAction(idx, { ...a, delay_seconds: parseInt(ev.target.value, 10) || 0 })}
              />
            </p>
          </div>
          <p>
            <label>
              <strong>{t(cfg, 'paramsJson', 'params (JSON object)')}</strong>
            </label>
            <textarea
              rows={4}
              className="large-text code"
              value={a.paramsJson}
              onChange={(ev) => patchAction(idx, { ...a, paramsJson: ev.target.value })}
            />
          </p>
          <span className="gro-rule-card__actions-row">
            <button
              type="button"
              className="button button-small"
              onClick={() => {
                const next = e.actions.filter((_, i) => i !== idx);
                onChange({
                  ...e,
                  actions: next.length ? next : [{ type: at[0], template_id: '', delay_seconds: 0, paramsJson: '{}' }],
                });
              }}
            >
              {t(cfg, 'removeAction', 'Remove action')}
            </button>
          </span>
        </div>
      ))}
      <button
        type="button"
        className="button"
        onClick={() =>
          onChange({
            ...e,
            actions: [...e.actions, { type: at[0], template_id: '', delay_seconds: 0, paramsJson: '{}' }],
          })
        }
      >
        {t(cfg, 'addAction', 'Add action')}
      </button>
    </div>
  );
}

interface SimpleRuleCardProps {
  ruleIndex: number;
  editable: EditableRule;
  isExpanded: boolean;
  onToggle: () => void;
  onChange: (next: EditableRule) => void;
  onDuplicate: () => void;
  onMove: (delta: number) => void;
  onRemove: () => void;
}

function SimpleRuleCard({
  ruleIndex,
  editable: e,
  isExpanded,
  onToggle,
  onChange,
  onDuplicate,
  onMove,
  onRemove,
}: SimpleRuleCardProps): React.ReactElement {
  const cfg = useContext(CfgContext);

  const summaryTitle = e.label.trim() || e.id || t(cfg, 'untitledRuleSummary', 'Untitled rule');

  const panelId = `gro-rule-panel-${ruleIndex}`;
  const titleHeadingId = `gro-rule-summary-title-${ruleIndex}`;
  const ruleNumHuman = ruleIndex + 1;
  const collapseToggleLabel = expandCollapseAria(cfg, isExpanded, ruleNumHuman, summaryTitle);
  const removeFromRowLabel = removeCollapsedAria(cfg, ruleNumHuman, summaryTitle);

  return (
    <article className={`gro-rule-card ${isExpanded ? '' : 'gro-rule-card--collapsed'}`}>
      <div className="gro-rule-card__accordion-row">
        <button
          type="button"
          className="gro-rule-card__accordion-trigger"
          aria-expanded={isExpanded}
          aria-controls={panelId}
          aria-label={collapseToggleLabel}
          onClick={() => onToggle()}
        >
          <span className="gro-rule-card__accordion-trigger-inner">
            <span className="gro-rule-card__chevron" aria-hidden />
            <span className="gro-rule-card__index-badge" aria-hidden>
              #{ruleNumHuman}
            </span>
            <span className="gro-rule-card__summary-stack">
              <span id={titleHeadingId} className="gro-rule-card__summary-main">
                {summaryTitle}
              </span>
              <span className="gro-rule-card__summary-meta">
                <span
                  className={
                    e.enabled ? 'gro-rule-card__status-badge gro-rule-card__status-badge--on' : 'gro-rule-card__status-badge gro-rule-card__status-badge--off'
                  }
                >
                  {e.enabled ? t(cfg, 'enabledLabel', 'Enabled') : t(cfg, 'disabledLabel', 'Disabled')}
                </span>
                <span className="gro-rule-card__summary-sep" aria-hidden>
                  {' · '}
                </span>
                <span className="gro-rule-card__summary-chip">
                  <span className="gro-rule-card__meta-key">{t(cfg, 'collapsedPriorityLabel', 'Priority:')}</span>{' '}
                  <strong className="gro-rule-card__meta-val">{e.priority}</strong>
                </span>
                <span className="gro-rule-card__summary-sep" aria-hidden>
                  {' · '}
                </span>
                <span className="gro-rule-card__summary-chip gro-rule-card__summary-chip--id">
                  <span className="gro-rule-card__meta-key">{t(cfg, 'collapsedIdLabel', 'Rule ID:')}</span>{' '}
                  <code>{e.id}</code>
                </span>
              </span>
            </span>
          </span>
        </button>
        <div className="gro-rule-card__accordion-toolbar">
          <button
            type="button"
            className="button button-small button-link-delete gro-rule-card__remove-collapsed"
            aria-label={removeFromRowLabel}
            onClick={(ev) => {
              ev.preventDefault();
              ev.stopPropagation();
              onRemove();
            }}
          >
            {t(cfg, 'removeRuleShort', 'Remove')}
          </button>
        </div>
      </div>
      <div
        id={panelId}
        role="region"
        className="gro-rule-card__panel"
        aria-labelledby={titleHeadingId}
        hidden={!isExpanded}
      >
        <header className="gro-rule-card__head gro-rule-card__head--expanded">
          <h4 className="gro-rule-card__expanded-title">{t(cfg, 'ruleDetailsHeading', 'Rule details')}</h4>
          <input
            type="text"
            className="regular-text"
            style={{ flex: '1 1 220px' }}
            placeholder={t(cfg, 'placeholderLabel', 'Label (optional)')}
            value={e.label}
            onChange={(ev) => onChange({ ...e, label: ev.target.value })}
          />
          <span className="gro-rule-card__meta">
            <label>
              <input type="checkbox" checked={e.enabled} onChange={(ev) => onChange({ ...e, enabled: ev.target.checked })} />{' '}
              {t(cfg, 'enabledLabel', 'Enabled')}
            </label>
            <label>
              {t(cfg, 'priorityLabel', 'Priority')}{' '}
              <input
                type="number"
                step={1}
                style={{ width: '5em' }}
                value={e.priority}
                onChange={(ev) => onChange({ ...e, priority: parseInt(ev.target.value, 10) || 10 })}
              />
            </label>
            <label>
              {t(cfg, 'ruleIdShort', 'id')}{' '}
              <input type="text" className="code" style={{ width: '16em' }} value={e.id} onChange={(ev) => onChange({ ...e, id: ev.target.value })} />
            </label>
          </span>
          <div className="gro-rule-card__actions-row">
            <button type="button" className="button button-small" onClick={onDuplicate}>
              {t(cfg, 'dupRule', 'Duplicate')}
            </button>
            <button type="button" className="button button-small" onClick={() => onMove(-1)}>
              {t(cfg, 'up', 'Move up')}
            </button>
            <button type="button" className="button button-small" onClick={() => onMove(1)}>
              {t(cfg, 'down', 'Move down')}
            </button>
            <button type="button" className="button button-small" onClick={onRemove}>
              {t(cfg, 'removeRule', 'Remove')}
            </button>
          </div>
        </header>
        <div className="gro-rule-card__body">
          <TriggersBlock ruleIndex={ruleIndex} editable={e.trigger} onChange={(trigger) => onChange({ ...e, trigger })} />
          <ConditionsBlock editable={e} onChange={onChange} />
          <ActionsBlock editable={e} onChange={onChange} />
        </div>
      </div>
    </article>
  );
}

interface AdvancedRuleCardProps {
  ruleIndex: number;
  snapshot: Record<string, unknown>;
  isExpanded: boolean;
  onToggle: () => void;
  onRemove: () => void;
}

function AdvancedRuleCard({ ruleIndex, snapshot, isExpanded, onToggle, onRemove }: AdvancedRuleCardProps): React.ReactElement {
  const cfg = useContext(CfgContext);
  const title = typeof snapshot.id === 'string' ? snapshot.id : `#${ruleIndex + 1}`;
  const panelId = `gro-rule-panel-adv-${ruleIndex}`;
  const titleHeadingId = `gro-rule-adv-summary-${ruleIndex}`;
  const ruleNumHuman = ruleIndex + 1;
  const summaryForAria = typeof snapshot.id === 'string' ? snapshot.id : `Rule ${ruleNumHuman}`;
  const collapseToggleLabel = expandCollapseAria(cfg, isExpanded, ruleNumHuman, summaryForAria);
  const removeFromRowLabel = removeCollapsedAria(cfg, ruleNumHuman, summaryForAria);

  return (
    <article className={`gro-rule-card gro-rule-card--advanced ${isExpanded ? '' : 'gro-rule-card--collapsed'}`}>
      <div className="gro-rule-card__accordion-row">
        <button
          type="button"
          className="gro-rule-card__accordion-trigger"
          aria-expanded={isExpanded}
          aria-controls={panelId}
          aria-label={collapseToggleLabel}
          onClick={() => onToggle()}
        >
          <span className="gro-rule-card__accordion-trigger-inner">
            <span className="gro-rule-card__chevron" aria-hidden />
            <span className="gro-rule-card__index-badge" aria-hidden>
              #{ruleNumHuman}
            </span>
            <span className="gro-rule-card__summary-stack">
              <span className="gro-rule-card__summary-badges">
                <span className="gro-badge gro-badge--warn">{t(cfg, 'advancedBadge', 'Advanced')}</span>
              </span>
              <span id={titleHeadingId} className="gro-rule-card__summary-main gro-rule-card__summary-main--mono">
                <code>{title}</code>
              </span>
            </span>
          </span>
        </button>
        <div className="gro-rule-card__accordion-toolbar">
          <button
            type="button"
            className="button button-small button-link-delete gro-rule-card__remove-collapsed"
            aria-label={removeFromRowLabel}
            onClick={(ev) => {
              ev.preventDefault();
              ev.stopPropagation();
              onRemove();
            }}
          >
            {t(cfg, 'removeRuleShort', 'Remove')}
          </button>
        </div>
      </div>
      <div
        id={panelId}
        role="region"
        className="gro-rule-card__panel"
        aria-labelledby={titleHeadingId}
        hidden={!isExpanded}
      >
        <div className="gro-rule-card__head gro-rule-card__head--expanded gro-rule-card__head--advanced-only">
          <div className="gro-rule-card__actions-row">
            <button type="button" className="button button-small" onClick={onRemove}>
              {t(cfg, 'removeRule', 'Remove')}
            </button>
          </div>
        </div>
        <div className="gro-banner gro-banner--advanced">
          <p>
            {t(
              cfg,
              'advancedDesc',
              'This rule uses triggers or nested conditions that the visual editor does not edit. Change it under Raw JSON; the card will reload after a successful parse.',
            )}
          </p>
        </div>
      </div>
    </article>
  );
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
  /** Accordion: at most one rule expanded; default all collapsed (`null`). */
  const [expandedRuleIndex, setExpandedRuleIndex] = useState<number | null>(null);

  const toggleRuleAccordion = useCallback((ri: number): void => {
    setExpandedRuleIndex((prev) => (prev === ri ? null : ri));
  }, []);

  const versionRef = useRef(version);
  const uiRulesRef = useRef(uiRules);
  useEffect(() => {
    versionRef.current = version;
    uiRulesRef.current = uiRules;
  }, [version, uiRules]);

  const activeTabRef = useRef<'visual' | 'raw' | null>(null);
  /* After first paint, textarea may mirror normalized JSON */
  const didNormalizeTextareaRef = useRef(false);

  const getTextarea = useCallback(() => document.getElementById(textareaId) as HTMLTextAreaElement | null, [textareaId]);

  const flushToTextarea = useCallback(() => {
    const ta = getTextarea();
    if (!ta) return;
    const doc = serializeDocument(versionRef.current, uiRulesRef.current, cfg.defaultVersion ?? '1.0.0');
    ta.value = JSON.stringify(doc, null, 2);
  }, [cfg.defaultVersion, getTextarea]);

  const importFromTextarea = useCallback(() => {
    const ta = getTextarea();
    if (!ta) throw new Error('Rules textarea missing');
    const parsed = parseDocumentFromTextarea(ta.value, cfg);
    setVersion(parsed.version);
    setUiRules(parsed.rules);
    setParseError(null);
    setExpandedRuleIndex(null);
  }, [cfg, getTextarea]);

  const syncPanelsForTab = useCallback((tab: 'visual' | 'raw'): void => {
    const tabV = document.querySelector<HTMLElement>('[data-gro-tab="visual"]');
    const tabR = document.querySelector<HTMLElement>('[data-gro-tab="raw"]');
    const panelVisual = document.getElementById('gro-rules-panel-visual');
    const panelRaw = document.getElementById('gro-rules-panel-raw');
    const isVisual = tab === 'visual';

    tabV?.classList.toggle('nav-tab-active', isVisual);
    tabV?.setAttribute('aria-selected', isVisual ? 'true' : 'false');
    tabR?.classList.toggle('nav-tab-active', !isVisual);
    tabR?.setAttribute('aria-selected', isVisual ? 'false' : 'true');

    if (panelVisual) {
      panelVisual.hidden = !isVisual;
      panelVisual.setAttribute('aria-hidden', isVisual ? 'false' : 'true');
    }
    if (panelRaw) {
      panelRaw.hidden = isVisual;
      panelRaw.toggleAttribute('hidden', isVisual);
      panelRaw.setAttribute('aria-hidden', isVisual ? 'true' : 'false');
    }
  }, []);

  const revealTab = useCallback(
    (next: 'visual' | 'raw'): void => {
      const prev = activeTabRef.current;
      activeTabRef.current = next;

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

  /** Wire tabs, textarea input, submit, initial tab once */
  useLayoutEffect(() => {
    const ta = getTextarea();
    const form = ta?.form ?? null;

    const onVisualClick = (): void => {
      revealTabRef.current('visual');
    };
    const onRawClick = (): void => {
      revealTabRef.current('raw');
    };

    document.querySelector<HTMLButtonElement>('[data-gro-tab="visual"]')?.addEventListener('click', onVisualClick);
    document.querySelector<HTMLButtonElement>('[data-gro-tab="raw"]')?.addEventListener('click', onRawClick);

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
      try {
        const doc = serializeDocument(versionRef.current, uiRulesRef.current, cfg.defaultVersion ?? '1.0.0');
        const el = getTextarea();
        if (el) el.value = JSON.stringify(doc, null, 2);
      } catch (e: unknown) {
        ev.preventDefault();
        window.alert(String((e as Error).message ?? e));
      }
    };
    form?.addEventListener('submit', onSubmit);

    activeTabRef.current = null;

    if (init.startsOnRawTab) {
      setParseError(null);
      revealTabRef.current('raw');
    } else {
      revealTabRef.current('visual');
    }

    if (!init.startsOnRawTab && !didNormalizeTextareaRef.current && ta) {
      didNormalizeTextareaRef.current = true;
      try {
        const doc = serializeDocument(init.version, init.rules, cfg.defaultVersion ?? '1.0.0');
        ta.value = JSON.stringify(doc, null, 2);
      } catch (e: unknown) {
        setParseError(String((e as Error).message ?? e));
      }
    }

    return () => {
      document.querySelector<HTMLButtonElement>('[data-gro-tab="visual"]')?.removeEventListener('click', onVisualClick);
      document.querySelector<HTMLButtonElement>('[data-gro-tab="raw"]')?.removeEventListener('click', onRawClick);
      ta?.removeEventListener('input', onRawInput);
      form?.removeEventListener('submit', onSubmit);
      window.clearTimeout(rawTimer);
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps -- one-time bootstrap wiring to PHP tabs / form
  }, []);

  const addRule = (): void => {
    let newIndex = 0;
    setUiRules((prev) => {
      newIndex = prev.length;
      return [
        ...prev,
        { type: 'simple', editable: newEditableRule(cfg.actionTypes ?? ['log'], cfg.recurrences ?? ['daily']) },
      ];
    });
    queueMicrotask(() => setExpandedRuleIndex(newIndex));
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
      queueMicrotask(() => setExpandedRuleIndex(ri + 1));
    },
    [cfg.actionTypes, cfg.recurrences],
  );

  const moveRule = useCallback((ri: number, delta: number): void => {
    let swapped = false;
    let swapJ = ri;
    setUiRules((prev) => {
      const j = ri + delta;
      if (j < 0 || j >= prev.length) return prev;
      const n = prev.slice();
      const rowI = n[ri];
      const rowJ = n[j];
      if (!rowI || !rowJ) return prev;
      n[ri] = rowJ;
      n[j] = rowI;
      swapped = true;
      swapJ = j;
      return n;
    });
    if (!swapped) return;
    setExpandedRuleIndex((exp) => {
      if (exp === null) return null;
      if (exp === ri) return swapJ;
      if (exp === swapJ) return ri;
      return exp;
    });
  }, []);

  return (
    <CfgContext.Provider value={cfg}>
      <div className="gro-rules-visual-inner">
        <div className="gro-rules-visual__toolbar">
          <button type="button" className="button button-primary" onClick={addRule}>
            {t(cfg, 'addRule', 'Add rule')}
          </button>
          <span className="gro-rules-visual__version">
            <label htmlFor="gro_rules_doc_version">{t(cfg, 'versionLabel', 'Format version')}</label>
            <input id="gro_rules_doc_version" type="text" value={version} onChange={(ev) => setVersion(ev.target.value)} />
          </span>
        </div>
        {parseError !== null ? <div className="gro-banner gro-banner--error">{parseError}</div> : null}
        {parseError === null && uiRules.length === 0 ? (
          <p className="description gro-rules-visual__empty-hint">{t(cfg, 'noRules', 'No rules. Add one or paste JSON in the Raw JSON tab.')}</p>
        ) : null}
        {parseError === null && uiRules.length > 0 ? (
          <ul className="gro-rules-rule-list" aria-label={t(cfg, 'ruleListHeading', 'Configured rules')}>
            {uiRules.map((u, ri) => (
              <li key={u.type === 'advanced' ? `adv-${String(ri)}` : `simple-${String(ri)}-${u.editable.id}`} className="gro-rules-rule-list__item">
                {u.type === 'advanced' ? (
                  <AdvancedRuleCard
                    ruleIndex={ri}
                    snapshot={u.snapshot}
                    isExpanded={expandedRuleIndex === ri}
                    onToggle={() => toggleRuleAccordion(ri)}
                    onRemove={() => {
                      if (!window.confirm(t(cfg, 'confirmRemoveAdv', 'Remove this rule from the document?'))) return;
                      setUiRules((p) => p.filter((_, i) => i !== ri));
                      setExpandedRuleIndex((exp) => {
                        if (exp === null) return null;
                        if (exp === ri) return null;
                        if (exp > ri) return exp - 1;
                        return exp;
                      });
                    }}
                  />
                ) : (
                  <SimpleRuleCard
                    ruleIndex={ri}
                    editable={u.editable}
                    isExpanded={expandedRuleIndex === ri}
                    onToggle={() => toggleRuleAccordion(ri)}
                    onChange={(next) =>
                      setUiRules((prev) =>
                        prev.map((row, idx) => (idx === ri && row.type === 'simple' ? { type: 'simple', editable: next } : row)),
                      )
                    }
                    onDuplicate={() => duplicateAt(ri)}
                    onMove={(d) => moveRule(ri, d)}
                    onRemove={() => {
                      if (!window.confirm(t(cfg, 'confirmRemove', 'Remove this rule?'))) return;
                      setUiRules((prev) => prev.filter((_, i) => i !== ri));
                      setExpandedRuleIndex((exp) => {
                        if (exp === null) return null;
                        if (exp === ri) return null;
                        if (exp > ri) return exp - 1;
                        return exp;
                      });
                    }}
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
